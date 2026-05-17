<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegralAiService
{
    /**
     * @return array{final_answer_latex: string, final_answer_plain: string, steps: array<int, array{latex: string, explanation: string|null}>}|null
     */
    public function solve(string $latex, string $wolframQuery, ?string $locale = null): ?array
    {
        $apiKey = trim((string) config('services.gemini.key', ''));
        if ($apiKey === '') {
            return null;
        }

        $prompt = $this->buildPrompt($latex, $wolframQuery, $this->normalizeLocale($locale ?? app()->getLocale()));

        foreach ($this->resolveModels() as $model) {
            try {
                $response = $this->requestGeminiWithBusyRetry(
                    apiKey: $apiKey,
                    model: $model,
                    prompt: $prompt,
                    maxTokens: 2600
                );
            } catch (\Throwable $e) {
                Log::warning('Gemini integral step request exception', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (!$response->successful()) {
                Log::warning('Gemini integral step request failed', [
                    'model' => $model,
                    'status' => $response->status(),
                    'error' => trim((string) $response->json('error.message', '')),
                ]);
                continue;
            }

            $raw = (string) $response->json('candidates.0.content.parts.0.text', '');
            $decoded = $this->parseModelJson($raw);
            if ($decoded === null) {
                Log::warning('Gemini integral response was invalid JSON', ['model' => $model]);
                continue;
            }

            $normalized = $this->normalizeSolution($decoded);
            if ($normalized !== null) {
                return $normalized;
            }

            Log::warning('Gemini integral response was incomplete', ['model' => $model]);
        }

        return null;
    }

    private function buildPrompt(string $latex, string $wolframQuery, string $locale): string
    {
        $localeLine = match ($locale) {
            'hy' => 'Write step explanations in Armenian.',
            'ru' => 'Write step explanations in Russian.',
            'fr' => 'Write step explanations in French.',
            default => 'Write step explanations in English.',
        };

        return implode("\n", [
            'You are a strict calculus tutor.',
            'Solve the given integral and show the derivation.',
            'Return valid JSON only. Do not use markdown or code fences.',
            'Use this exact schema:',
            '{"final_answer_latex":"","final_answer_plain":"","steps":[{"latex":"","explanation":""}]}',
            'Rules:',
            '- final_answer_latex must be the final answer as LaTeX without $ delimiters.',
            '- final_answer_plain must be WolframAlpha-compatible plaintext, not LaTeX.',
            '- If the integral is indefinite, include + C in the final answer.',
            '- steps must contain 2 to 8 useful steps.',
            '- Each step.latex must be valid LaTeX without $ delimiters.',
            '- Keep explanations short and educational.',
            '- Escape JSON backslashes, for example \\\\int, \\\\frac, \\\\sin.',
            $localeLine,
            "Integral LaTeX: {$latex}",
            "Wolfram-style query: {$wolframQuery}",
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

                return $response && in_array($response->status(), [408, 500, 502, 503, 504], true);
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
                    'temperature' => 0.15,
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

        return $status === 'UNAVAILABLE'
            || str_contains($message, 'high demand')
            || str_contains($message, 'please try again later')
            || str_contains($message, 'temporarily unavailable');
    }

    /**
     * @return array<int, string>
     */
    private function resolveModels(): array
    {
        $models = [
            (string) config('services.gemini.model', 'gemini-2.5-flash'),
            (string) config('services.gemini.fallback_model', 'gemini-2.5-flash-lite'),
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash',
        ];

        $resolved = [];
        foreach ($models as $model) {
            $model = $this->normalizeModelName($model);
            if ($model !== '' && !in_array($model, $resolved, true)) {
                $resolved[] = $model;
            }
        }

        return $resolved;
    }

    private function normalizeModelName(string $model): string
    {
        $model = trim($model);
        if ($model === '') {
            return '';
        }

        $model = str_starts_with($model, 'models/') ? substr($model, 7) : $model;

        return $model === 'gemini-flash-latest'
            ? 'gemini-2.5-flash'
            : $model;
    }

    private function parseModelJson(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $candidates = [
            $raw,
            trim(preg_replace('/^```(?:json)?|```$/i', '', $raw) ?? $raw),
        ];

        $objStart = strpos($raw, '{');
        $objEnd = strrpos($raw, '}');
        if ($objStart !== false && $objEnd !== false && $objEnd > $objStart) {
            $candidates[] = substr($raw, $objStart, $objEnd - $objStart + 1);
        }

        foreach ($candidates as $candidate) {
            $candidate = $this->repairJsonBackslashes($candidate);
            $candidate = preg_replace('/,\s*([}\]])/', '$1', $candidate) ?? $candidate;
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function repairJsonBackslashes(string $json): string
    {
        return preg_replace('/\\\\(?!["\\\\\/bfnrtu])/', '\\\\\\\\', $json) ?? $json;
    }

    /**
     * @return array{final_answer_latex: string, final_answer_plain: string, steps: array<int, array{latex: string, explanation: string|null}>}|null
     */
    private function normalizeSolution(array $decoded): ?array
    {
        $finalLatex = trim((string) ($decoded['final_answer_latex'] ?? $decoded['final_latex'] ?? ''));
        $finalPlain = trim((string) ($decoded['final_answer_plain'] ?? $decoded['final_plain'] ?? ''));
        $rawSteps = $decoded['steps'] ?? [];

        if ($finalLatex === '' && $finalPlain === '') {
            return null;
        }

        if (!is_array($rawSteps)) {
            return null;
        }

        $steps = [];
        foreach ($rawSteps as $step) {
            if (is_string($step)) {
                $latex = trim($step);
                $explanation = null;
            } elseif (is_array($step)) {
                $latex = trim((string) ($step['latex'] ?? ''));
                $explanation = trim((string) ($step['explanation'] ?? $step['note'] ?? ''));
                $explanation = $explanation !== '' ? $explanation : null;
            } else {
                continue;
            }

            if ($latex !== '') {
                $steps[] = [
                    'latex' => $latex,
                    'explanation' => $explanation,
                ];
            }
        }

        if (empty($steps)) {
            return null;
        }

        return [
            'final_answer_latex' => $finalLatex !== '' ? $finalLatex : $finalPlain,
            'final_answer_plain' => $finalPlain !== '' ? $finalPlain : $finalLatex,
            'steps' => array_slice($steps, 0, 8),
        ];
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return in_array($locale, ['en', 'hy', 'fr', 'ru'], true) ? $locale : 'en';
    }
}
