<?php

namespace App\Services;

use App\Models\Formula;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FormulaStepAiService
{
    /**
     * @return array<int, array{latex: string, note: string}>
     */
    public function generateForFormula(Formula $formula, ?string $locale = null): array
    {
        $apiKey = trim((string) config('services.gemini.key', ''));
        if ($apiKey === '') {
            throw new \RuntimeException('Gemini API key is not configured.');
        }

        $formula->loadMissing('translations');

        $preferredLocale = $this->normalizeLocale($locale ?? app()->getLocale());
        $translation = $formula->translations->firstWhere('locale', $preferredLocale)
            ?? $formula->translations->firstWhere('locale', config('app.fallback_locale', 'en'))
            ?? $formula->translations->firstWhere('locale', 'en')
            ?? $formula->translations->first();

        $model = $this->resolveModel();
        $prompt = $this->buildPrompt(
            formulaLatex: (string) $formula->formula,
            formulaName: trim((string) ($translation?->name ?? 'Formula')),
            formulaDescription: trim((string) ($translation?->description ?? '')),
            locale: $preferredLocale
        );

        $response = $this->requestGeminiWithBusyRetry(
            apiKey: $apiKey,
            model: $model,
            prompt: $prompt,
            maxTokens: 2300
        );

        if (!$response->successful()) {
            $status = $response->status();
            $error = trim((string) $response->json('error.message', ''));
            if ($status === 503 || $this->isHighDemandError($response)) {
                throw new \RuntimeException("Model {$model} is currently busy. Please try again in a moment.");
            }

            throw new \RuntimeException(
                trim("Model {$model} failed with HTTP {$status}. {$error}")
            );
        }

        $raw = (string) $response->json('candidates.0.content.parts.0.text', '');
        $decoded = $this->parseModelJson($raw);
        if ($decoded === null) {
            throw new \RuntimeException("Model {$model} returned invalid JSON payload.");
        }

        $steps = $this->normalizeSteps($decoded, $preferredLocale);
        if (count($steps) < 3) {
            throw new \RuntimeException("Model {$model} returned incomplete steps.");
        }

        return $steps;
    }

    private function buildPrompt(
        string $formulaLatex,
        string $formulaName,
        string $formulaDescription,
        string $locale
    ): string {
        $localeLine = match ($locale) {
            'hy' => 'Write all notes in Armenian.',
            'ru' => 'Write all notes in Russian.',
            'fr' => 'Write all notes in French.',
            default => 'Write all notes in English.',
        };

        $descriptionLine = $formulaDescription !== ''
            ? "Description/context: {$formulaDescription}"
            : 'Description/context: not provided.';

        return implode("\n", [
            'You are a strict math tutor.',
            'Generate a solved example with step-by-step derivation for the given formula.',
            'Output MUST be valid JSON only, no markdown, no code fences, no extra text.',
            'Return this exact schema:',
            '{"steps":[{"latex":"","note":""}]}',
            'Important:',
            '- Generate between 3 and 10 steps.',
            '- Each step.latex must be a valid LaTeX expression without $ delimiters.',
            '- Keep notes short and educational.',
            '- Do not reference images, diagrams, or external resources.',
            '- Escape JSON backslashes (for example: \\\\frac, \\\\sqrt, \\\\cdot).',
            $localeLine,
            "Formula name: {$formulaName}",
            "Formula: {$formulaLatex}",
            $descriptionLine,
        ]);
    }

    private function requestGemini(string $apiKey, string $model, string $prompt, int $maxTokens): Response
    {
        $modelName = str_starts_with($model, 'models/') ? substr($model, 7) : $model;
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $modelName
        );

        return Http::timeout(18)
            ->connectTimeout(5)
            ->retry(1, 300, function ($exception) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                $response = null;
                if (is_object($exception) && property_exists($exception, 'response')) {
                    $response = $exception->response;
                }

                if (!$response) {
                    return false;
                }

                return in_array($response->status(), [408, 429, 500, 502, 503, 504], true);
            })
            ->withOptions([
                \CURLOPT_IPRESOLVE => \CURL_IPRESOLVE_V4,
            ])
            ->withoutVerifying()
            ->post($endpoint . '?key=' . $apiKey, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => $maxTokens,
                    'responseMimeType' => 'application/json',
                ],
            ]);
    }

    private function requestGeminiWithBusyRetry(string $apiKey, string $model, string $prompt, int $maxTokens): Response
    {
        $attempt = 0;
        $maxAttempts = 4;
        $lastResponse = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $response = $this->requestGemini($apiKey, $model, $prompt, $maxTokens);
            $lastResponse = $response;

            if (!$response->successful() && ($response->status() === 503 || $this->isHighDemandError($response))) {
                if ($attempt < $maxAttempts) {
                    usleep($this->busyBackoffMs($attempt) * 1000);
                    continue;
                }
            }

            return $response;
        }

        return $lastResponse instanceof Response
            ? $lastResponse
            : throw new \RuntimeException('Gemini request failed.');
    }

    private function busyBackoffMs(int $attempt): int
    {
        $delays = [700, 1500, 2800, 4500];
        $index = max(0, min(count($delays) - 1, $attempt - 1));

        return $delays[$index];
    }

    private function isHighDemandError(Response $response): bool
    {
        $status = (string) $response->json('error.status', '');
        $message = mb_strtolower((string) $response->json('error.message', ''));

        if ($status === 'UNAVAILABLE') {
            return true;
        }

        return str_contains($message, 'high demand')
            || str_contains($message, 'please try again later')
            || str_contains($message, 'temporarily unavailable');
    }

    private function resolveModel(): string
    {
        $model = trim((string) config('services.gemini.model', 'gemini-2.5-flash'));
        if ($model === '') {
            throw new \RuntimeException('Gemini model is not configured.');
        }

        $model = str_starts_with($model, 'models/') ? substr($model, 7) : $model;
        if ($model === 'gemini-flash-latest') {
            return 'gemini-2.5-flash';
        }

        return $model;
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));
        if (!in_array($locale, ['en', 'hy', 'fr', 'ru'], true)) {
            return 'en';
        }

        return $locale;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseModelJson(string $raw): ?array
    {
        $text = trim($raw);
        if ($text === '') {
            return null;
        }

        $candidate = trim((string) preg_replace('/^```(?:json)?|```$/i', '', $text));
        $candidates = [$candidate];

        $objStart = strpos($candidate, '{');
        $objEnd = strrpos($candidate, '}');
        if ($objStart !== false && $objEnd !== false && $objEnd > $objStart) {
            $candidates[] = substr($candidate, $objStart, $objEnd - $objStart + 1);
        }

        foreach ($candidates as $rawCandidate) {
            $normalized = $this->normalizeJsonCandidate((string) $rawCandidate);
            $attempts = [
                $normalized,
                $this->repairJsonBackslashes($normalized),
                preg_replace('/,\s*([}\]])/', '$1', $this->repairJsonBackslashes($normalized)) ?? $normalized,
                $this->closeTruncatedJson($this->repairJsonBackslashes($normalized)),
                $this->closeTruncatedJson($normalized),
            ];

            foreach ($attempts as $attempt) {
                $decoded = json_decode((string) $attempt, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    private function normalizeJsonCandidate(string $candidate): string
    {
        $candidate = trim($candidate);
        $candidate = str_replace(["\r\n", "\r"], "\n", $candidate);
        $candidate = preg_replace('/^\xEF\xBB\xBF/u', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/[^\P{C}\n\t]/u', '', $candidate) ?? $candidate;

        return $candidate;
    }

    private function repairJsonBackslashes(string $json): string
    {
        $validEscapes = ['"', '\\', '/', 'b', 'f', 'n', 'r', 't'];
        $length = strlen($json);
        $result = '';
        $inString = false;
        $escape = false;

        for ($i = 0; $i < $length; $i++) {
            $ch = $json[$i];

            if (!$inString) {
                $result .= $ch;
                if ($ch === '"') {
                    $inString = true;
                    $escape = false;
                }
                continue;
            }

            if ($escape) {
                $result .= $ch;
                $escape = false;
                continue;
            }

            if ($ch === '\\') {
                $next = $i + 1 < $length ? $json[$i + 1] : '';
                if ($next === '') {
                    $result .= '\\\\';
                    continue;
                }

                if ($next === 'u') {
                    $hex = substr($json, $i + 2, 4);
                    if (strlen($hex) === 4 && ctype_xdigit($hex)) {
                        $result .= '\\';
                        $escape = true;
                        continue;
                    }
                    $result .= '\\\\';
                    continue;
                }

                if (in_array($next, $validEscapes, true)) {
                    $result .= '\\';
                    $escape = true;
                    continue;
                }

                $result .= '\\\\';
                continue;
            }

            $result .= $ch;
            if ($ch === '"') {
                $inString = false;
            }
        }

        return $result;
    }

    private function closeTruncatedJson(string $json): string
    {
        $json = trim($json);
        if ($json === '') {
            return $json;
        }

        $output = '';
        $stack = [];
        $inString = false;
        $escape = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $ch = $json[$i];
            $output .= $ch;

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }

            if ($ch === '{') {
                $stack[] = '}';
                continue;
            }

            if ($ch === '[') {
                $stack[] = ']';
                continue;
            }

            if (($ch === '}' || $ch === ']') && !empty($stack) && end($stack) === $ch) {
                array_pop($stack);
            }
        }

        if ($inString) {
            $output .= '"';
        }

        $output = preg_replace('/,\s*$/', '', $output) ?? $output;
        while (!empty($stack)) {
            $output = preg_replace('/,\s*$/', '', $output) ?? $output;
            $output .= array_pop($stack);
        }

        return preg_replace('/,\s*([}\]])/', '$1', $output) ?? $output;
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<int, array{latex: string, note: string}>
     */
    private function normalizeSteps(array $decoded, string $locale): array
    {
        $items = [];
        if (isset($decoded['steps']) && is_array($decoded['steps'])) {
            $items = $decoded['steps'];
        } elseif (array_key_exists(0, $decoded) && is_array($decoded[0])) {
            $items = $decoded;
        }

        $normalized = [];
        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }

            $latexRaw = (string) (
                $item['latex']
                ?? $item['math']
                ?? $item['equation']
                ?? $item['expression']
                ?? ''
            );
            $noteRaw = (string) (
                $item['note']
                ?? $item['desc']
                ?? $item['explanation']
                ?? ''
            );

            $latex = $this->normalizeStepLatex($latexRaw);
            if ($latex === '') {
                continue;
            }

            $note = trim($noteRaw);
            if ($note === '') {
                $note = $this->defaultStepNote($locale, $i + 1);
            }

            $normalized[] = [
                'latex' => $latex,
                'note' => $note,
            ];
        }

        if (count($normalized) > 12) {
            $normalized = array_slice($normalized, 0, 12);
        }

        return $normalized;
    }

    private function normalizeStepLatex(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '$$') && str_ends_with($value, '$$') && strlen($value) > 4) {
            $value = trim(substr($value, 2, -2));
        } elseif (str_starts_with($value, '$') && str_ends_with($value, '$') && strlen($value) > 2) {
            $value = trim(substr($value, 1, -1));
        }

        return trim($value);
    }

    private function defaultStepNote(string $locale, int $step): string
    {
        return match ($locale) {
            'hy' => "Քայլ {$step}",
            'ru' => "Шаг {$step}",
            'fr' => "Étape {$step}",
            default => "Step {$step}",
        };
    }
}

