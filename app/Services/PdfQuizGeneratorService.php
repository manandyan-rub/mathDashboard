<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PdfQuizGeneratorService
{
    public function generate(string $text, array $options = []): array
    {
        $count = (int) ($options['count'] ?? 10);
        $count = max(1, min($count, 30));

        $difficulty = (string) ($options['difficulty'] ?? 'medium');
        $language = (string) ($options['language'] ?? 'armenian');

        $source = $this->truncateText($text, 6000);

        $prompt = $this->buildPrompt($source, $count, $difficulty, $language);

        $model = (string) config('services.gemini.model', 'gemini-flash-latest');
        $fallback = (string) config('services.gemini.fallback_model', 'gemini-2.5-flash-lite');
        $apiKey = (string) config('services.gemini.key');

        if ($apiKey === '') {
            throw new RuntimeException('Gemini API key is missing.');
        }

        $response = $this->requestGemini($prompt, $apiKey, $model);
        if ($this->isHighDemand($response)) {
            $response = $this->requestGemini($prompt, $apiKey, $fallback);
        }

        if (!$response->ok()) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            throw new RuntimeException($message ?: 'Gemini request failed.');
        }

        $rawText = (string) ($response->json('candidates.0.content.parts.0.text') ?? $response->body());
        $decoded = $this->parseGeminiJson($rawText);

        return $this->normalizeQuestions($decoded, $count);
    }

    private function requestGemini(string $prompt, string $apiKey, string $model)
    {
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $model
        );

        return Http::timeout(30)
            ->retry(2, 250, function ($exception) {
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }
                $response = $exception?->response;
                if (!$response) {
                    return true;
                }
                return in_array($response->status(), [429, 500, 502, 503], true);
            })
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
                    'temperature' => 0.4,
                    'maxOutputTokens' => 1200,
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
        return str_contains(Str::lower($message), 'high demand');
    }

    private function buildPrompt(string $text, int $count, string $difficulty, string $language): string
    {
        return implode("\n", [
            'You are a math teacher.',
            "Generate exactly {$count} multiple-choice questions.",
            'Each question must have exactly 4 answer options.',
            'Return JSON only. Do not add any extra text or markdown.',
            'Return this exact JSON shape:',
            '{"questions":[{"question":"","options":["","","",""],"correct_index":0,"explanation":""}]}',
            'Use the requested language for values only. Keys must stay in English.',
            "Difficulty: {$difficulty}. Language: {$language}.",
            'Source text:',
            $text,
        ]);
    }

    private function truncateText(string $text, int $maxChars): string
    {
        $clean = preg_replace("/\\s+/", " ", trim($text));
        if ($clean === null) {
            $clean = trim($text);
        }
        return mb_substr($clean, 0, $maxChars);
    }

    private function parseGeminiJson(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?/i', '', $clean);
        $clean = preg_replace('/```$/', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $candidate = $this->extractJsonCandidate($clean);
        if ($candidate !== null) {
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('Gemini returned invalid JSON.');
    }

    private function extractJsonCandidate(string $text): ?string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = substr($text, $start, $end - $start + 1);
        $candidate = str_replace(["\r\n", "\r"], "\n", $candidate);
        $candidate = preg_replace('/([{,]\\s*)([A-Za-z_][A-Za-z0-9_]*)(\\s*:)/', '$1"$2"$3', $candidate);
        $candidate = preg_replace("/:\\s*'([^']*)'/", ': "$1"', $candidate);
        $candidate = preg_replace('/,\\s*([}\\]])/', '$1', $candidate);

        return $candidate;
    }

    private function normalizeQuestions(array $decoded, int $count): array
    {
        $items = $decoded['questions'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = trim((string) ($item['question'] ?? ''));
            $options = $item['options'] ?? [];
            $correctIndex = (int) ($item['correct_index'] ?? -1);
            $explanation = trim((string) ($item['explanation'] ?? ''));

            if ($question === '' || !is_array($options) || count($options) !== 4) {
                continue;
            }
            if ($correctIndex < 0 || $correctIndex > 3) {
                continue;
            }

            $normalized[] = [
                'question' => $question,
                'options' => array_values(array_map('strval', $options)),
                'correct_index' => $correctIndex,
                'explanation' => $explanation,
            ];

            if (count($normalized) >= $count) {
                break;
            }
        }

        return $normalized;
    }
}
