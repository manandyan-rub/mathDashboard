<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExerciseController extends Controller
{

    public function generate(Request $request): JsonResponse
    {
        @set_time_limit(120);

        $data = $request->validate([
            'prompt' => 'required|string|max:1000',
            'count' => 'nullable|integer|min:1|max:10',
        ]);
        $count = (int) ($data['count'] ?? 1);
        $count = max(1, min(10, $count));

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return response()->json([
                'message' => __('Gemini API key is missing.'),
            ], 500);
        }

        $prompt = $this->buildPrompt($data['prompt'], $count);
        $model = (string) config('services.gemini.model', 'gemini-flash-latest');
        $fallbackModel = (string) config('services.gemini.fallback_model', 'gemini-2.5-flash-lite');

        $maxTokens = min(2048, 300 + ($count * 200));
        $response = $this->requestGemini($apiKey, $model, $prompt, $maxTokens);

        if (!$response->successful() && $this->isHighDemand($response)) {
            $response = $this->requestGemini($apiKey, $fallbackModel, $prompt, $maxTokens);
        }

        if (!$response->successful() && $this->isHighDemand($response)) {
            return response()->json([
                'busy' => true,
                'message' => __('The model is busy. Please try again in a few minutes.'),
            ], 200);
        }

        if (!$response->successful()) {
            $details = (string) ($response->json('error.message') ?? $response->body());
            logger()->error('Gemini request failed', [
                'status' => $response->status(),
                'details' => $details,
            ]);
            return response()->json([
                'message' => __('Gemini request failed.'),
                'details' => trim($details) !== '' ? trim($details) : null,
            ], 502);
        }

        $text = (string) $response->json('candidates.0.content.parts.0.text', '');

        try {
            $decoded = $this->parseGeminiJson($text);
        } catch (\Throwable $e) {
            if ($this->isLikelyTruncated($text)) {
                $retryResponse = $this->requestGemini($apiKey, $fallbackModel, $prompt, $maxTokens);
                if ($retryResponse->successful()) {
                    $retryText = (string) $retryResponse->json('candidates.0.content.parts.0.text', '');
                    try {
                        $decoded = $this->parseGeminiJson($retryText);
                        $text = $retryText;
                    } catch (\Throwable $retryError) {
                        logger()->warning('Gemini returned invalid JSON after retry', [
                            'text' => Str::limit($retryText, 2000),
                        ]);
                        return response()->json([
                            'invalid' => true,
                            'reason' => $this->isLikelyTruncated($retryText) ? 'truncated' : 'parse_error',
                            'raw' => $retryText,
                        ], 200);
                    }
                } else {
                    if ($this->isHighDemand($retryResponse)) {
                        return response()->json([
                            'busy' => true,
                            'message' => __('The model is busy. Please try again in a few minutes.'),
                        ], 200);
                    }
                }
            }
            logger()->warning('Gemini returned invalid JSON', [
                'text' => Str::limit($text, 2000),
            ]);
            return response()->json([
                'invalid' => true,
                'reason' => $this->isLikelyTruncated($text) ? 'truncated' : 'parse_error',
                'raw' => $text,
            ], 200);
        }

        $exercises = $this->normalizeExercises($decoded);

        if (empty($exercises)) {
            return response()->json([
                'invalid' => true,
                'reason' => 'empty',
                'raw' => $text,
            ], 200);
        }

        $responsePayload = [
            'exercises' => $exercises,
        ];

        if (count($exercises) === 1) {
            $responsePayload['question'] = $exercises[0]['question'];
            $responsePayload['answer'] = $exercises[0]['answer'];
            $responsePayload['difficulty'] = $exercises[0]['difficulty'];
        }

        return response()->json($responsePayload);
    }

    private function requestGemini(string $apiKey, string $model, string $prompt, int $maxTokens = 512)
    {
    $model = str_starts_with($model, 'models/') ? substr($model, 7) : $model;
    $endpoint = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
        $model
    );

    return Http::timeout(30)
        ->connectTimeout(10)
        ->withOptions([
            \CURLOPT_IPRESOLVE => \CURL_IPRESOLVE_V4, 
        ])
        ->withoutVerifying() // 3. Bypass local SSL certificate issues (Remove this when you deploy to a live server!)
        ->retry(2, 250, function ($exception) {
            if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                return true;
            }

            $response = null;
            if (property_exists($exception, 'response')) {
                $response = $exception->response;
            }

            if (!$response) {
                return true;
            }

            $status = $response->status();
            return in_array($status, [429, 500, 502, 503], true);
        }, false)
        ->withHeaders([
            'Content-Type' => 'application/json',
            // Note: You do not need the X-Goog-Api-Key header if you are passing it in the URL
        ])
        ->post($endpoint . '?key=' . $apiKey, [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => $maxTokens,
            ],
        ]);
    }

    private function isHighDemand($response): bool
    {
        if (!$response) {
            return true;
        }
        $status = $response->status();
        if (in_array($status, [429, 503], true)) {
            return true;
        }
        $message = (string) ($response->json('error.message') ?? '');
        return str_contains(strtolower($message), 'high demand');
    }

    public function pdf(Request $request): Response
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:120',
            'question' => 'nullable|string|max:5000',
            'answer' => 'nullable|string|max:5000',
            'difficulty' => 'nullable|string|max:120',
            'exercises' => 'nullable|array|max:10',
            'exercises.*.question' => 'required_with:exercises|string|max:5000',
            'exercises.*.answer' => 'required_with:exercises|string|max:5000',
            'exercises.*.difficulty' => 'nullable|string|max:120',
        ]);

        $filename = $this->safeFilename($data['title'] ?? null);

        $payload = $this->preparePdfData($data);

        if (!class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
            return response()->json([
                'message' => __('PDF renderer is not installed.'),
                'details' => __('Run: composer require barryvdh/laravel-dompdf'),
            ], 500);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.exercises', $payload)
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($filename);
    }

    private function buildPrompt(string $userPrompt, int $count): string
    {
        $countLine = $count > 1
            ? "Generate exactly {$count} exercises and return them in a JSON array named \"exercises\"."
            : 'Generate a single exercise.';

        return implode("\n", [
            'You are a math teacher.',
            $countLine,
            'Return a JSON object with EXACTLY these English keys: "question", "answer", "difficulty".',
            'If generating multiple, return: {"exercises":[{"question":"","answer":"","difficulty":""}, ...]}',
            'CRITICAL: Do not translate the JSON keys into Armenian. The values should be in Armenian, but the keys MUST remain English.',
            'Use LaTeX for all math expressions. Wrap inline math with $...$.',
            'Return minified JSON on a single line, use double quotes only.',
            'Do not include markdown, backticks, or any extra text.',
            'User request: ' . trim($userPrompt),
        ]);
    }

    private function parseGeminiJson(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?/i', '', $clean);
        $clean = preg_replace('/```$/', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded)) {
                return $decoded;
            }
            if (is_string($decoded)) {
                $inner = trim($decoded);
                if ($inner !== '' && (str_starts_with($inner, '{') || str_starts_with($inner, '['))) {
                    $decodedInner = json_decode($inner, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedInner)) {
                        return $decodedInner;
                    }
                }
            }
        }

        $candidate = $this->extractJsonCandidate($clean);
        if ($candidate !== null) {
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        $fallback = $this->parseKeyValueFallback($clean);
        if (!empty($fallback)) {
            return $fallback;
        }

        throw new \RuntimeException('Invalid JSON payload.');
    }

    private function extractJsonCandidate(string $text): ?string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = substr($text, $start, $end - $start + 1);
        $candidate = str_replace(['“', '”', '„', '«', '»', 'вЂњ', 'вЂќ', 'вЂћ', 'В«', 'В»'], '"', $candidate);
        $candidate = str_replace(["\r\n", "\r"], "\n", $candidate);

        $candidate = preg_replace('/([{,]\\s*)([A-Za-z_][A-Za-z0-9_]*)(\\s*:)/', '$1"$2"$3', $candidate);
        $candidate = preg_replace("/:\\s*'([^']*)'/", ': "$1"', $candidate);
        $candidate = preg_replace('/,\\s*([}\\]])/', '$1', $candidate);

        return $candidate;
    }

    private function isLikelyTruncated(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }
        if (str_starts_with($trimmed, '{') && !str_contains($trimmed, '}')) {
            return true;
        }
        if (str_contains($trimmed, '{') && !str_contains($trimmed, '}')) {
            return true;
        }
        return false;
    }

    private function parseKeyValueFallback(string $text): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = str_replace(['“', '”', '„', '«', '»'], '"', $normalized);
        $normalized = str_replace(["\t"], ' ', $normalized);

        $keyMap = [
            'question' => ['question', 'հարց', 'հարցը', 'вопрос'],
            'answer' => ['answer', 'պատասխան', 'լուծում', 'լուծումը', 'ответ', 'réponse', 'reponse'],
            'difficulty' => ['difficulty', 'բարդություն', 'сложность', 'difficulté', 'difficulte'],
        ];

        $result = [];
        foreach ($keyMap as $target => $aliases) {
            foreach ($aliases as $alias) {
                $pattern = sprintf('/%s\\s*[:=]\\s*("([^"]*)"|([^\\n]+))/iu', preg_quote($alias, '/'));
                if (preg_match($pattern, $normalized, $match)) {
                    $value = $match[2] !== '' ? $match[2] : $match[3];
                    $value = trim($value, " \t\n\"'");
                    if ($value !== '') {
                        $result[$target] = $value;
                        break;
                    }
                }
            }
        }

        if (empty($result)) {
            return [];
        }

        return [
            'question' => $result['question'] ?? '',
            'answer' => $result['answer'] ?? '',
            'difficulty' => $result['difficulty'] ?? '',
        ];
    }

    private function normalizeExercises(array $decoded): array
    {
        $items = [];

        if (isset($decoded['exercises']) && is_array($decoded['exercises'])) {
            $items = $decoded['exercises'];
        } elseif (array_key_exists(0, $decoded) && is_array($decoded[0])) {
            $items = $decoded;
        } elseif (isset($decoded['question']) || isset($decoded['answer'])) {
            $items = [$decoded];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            $difficulty = trim((string) ($item['difficulty'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $normalized[] = [
                'question' => $question,
                'answer' => $answer,
                'difficulty' => $difficulty,
            ];
        }

        return $normalized;
    }

    private function preparePdfData(array $exercise): array
    {
        $title = trim((string) ($exercise['title'] ?? '')) ?: __('AI Exercise');
        $items = [];
        if (!empty($exercise['exercises']) && is_array($exercise['exercises'])) {
            $items = $exercise['exercises'];
        } elseif (!empty($exercise['question']) || !empty($exercise['answer'])) {
            $items[] = [
                'question' => (string) ($exercise['question'] ?? ''),
                'answer' => (string) ($exercise['answer'] ?? ''),
                'difficulty' => (string) ($exercise['difficulty'] ?? ''),
            ];
        }

        if (empty($items)) {
            $items[] = [
                'question' => '',
                'answer' => '',
                'difficulty' => '',
            ];
        }

        $prepared = [];
        foreach ($items as $item) {
            $prepared[] = [
                'question' => (string) ($item['question'] ?? ''),
                'answer' => (string) ($item['answer'] ?? ''),
                'difficulty' => (string) ($item['difficulty'] ?? ''),
            ];
        }

        return [
            'title' => $title,
            'exercises' => $prepared,
        ];
    }

    private function buildPdf(array $exercise): string
    {
        $title = trim((string) ($exercise['title'] ?? '')) ?: __('AI Exercise');
        $items = $exercise['exercises'] ?? [];

        $lines = [
            ['size' => 18, 'text' => $title],
            ['size' => 12, 'text' => ''],
        ];

        foreach ($items as $index => $item) {
            $question = (string) ($item['question'] ?? '');
            $answer = (string) ($item['answer'] ?? '');
            $difficulty = trim((string) ($item['difficulty'] ?? ''));
            $difficultyLine = $difficulty !== ''
                ? __('Difficulty: :difficulty', ['difficulty' => $difficulty])
                : __('Difficulty: Not specified');

            $lines[] = ['size' => 12, 'text' => __('Exercise :number:', ['number' => (string) ($index + 1)])];
            $lines[] = ['size' => 12, 'text' => $difficultyLine];
            $lines[] = ['size' => 12, 'text' => __('Question:')];
            foreach ($this->wrapText($question, 90) as $line) {
                $lines[] = ['size' => 12, 'text' => $line];
            }
            $lines[] = ['size' => 12, 'text' => ''];
            $lines[] = ['size' => 12, 'text' => __('Answer:')];
            foreach ($this->wrapText($answer, 90) as $line) {
                $lines[] = ['size' => 12, 'text' => $line];
            }
            if ($index < count($items) - 1) {
                $lines[] = ['size' => 12, 'text' => ''];
            }
        }

        $content = "BT\n";
        $y = 760;

        foreach ($lines as $line) {
            if ($y < 60) {
                break;
            }

            $size = (int) $line['size'];
            $text = $this->pdfEscape((string) $line['text']);
            $content .= sprintf("/F1 %d Tf 1 0 0 1 50 %d Tm (%s) Tj\n", $size, $y, $text);
            $y -= ($size + 6);
        }

        $content .= "ET\n";

        return $this->composePdf($content);
    }

    private function wrapText(string $text, int $width): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return [''];
        }

        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }

            $wrapped = wordwrap($paragraph, $width, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function pdfEscape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function composePdf(string $content): string
    {
        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function safeFilename(?string $title): string
    {
        $base = trim((string) $title);
        $slug = $base !== '' ? Str::slug($base, '_') : '';
        $slug = $slug !== '' ? $slug : 'exercise';

        return $slug . '.pdf';
    }
}
