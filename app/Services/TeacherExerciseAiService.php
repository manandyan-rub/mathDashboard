<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TeacherExerciseAiService
{
    /**
     * @param array<string, mixed> $payload
     * @return array{items: array<int, array<string, mixed>>, prompt: string, raw: string}
     */
    public function generate(array $payload): array
    {
        $count = max(1, min(20, (int) ($payload['count'] ?? 10)));
        $language = (string) ($payload['language'] ?? 'en');
        $topic = trim((string) ($payload['topic'] ?? 'General mathematics'));
        $grade = trim((string) ($payload['grade'] ?? ''));
        $difficulty = strtolower(trim((string) ($payload['difficulty'] ?? 'medium')));
        $instructions = trim((string) ($payload['instructions'] ?? ''));

        $prompt = $this->buildPrompt(
            topic: $topic,
            grade: $grade,
            difficulty: $difficulty,
            count: $count,
            language: $language,
            instructions: $instructions
        );

        $apiKey = (string) config('services.gemini.key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Gemini API key is not configured.');
        }

        $models = array_values(array_unique(array_filter([
            (string) config('services.gemini.model', 'gemini-flash-latest'),
            (string) config('services.gemini.fallback_model', 'gemini-2.5-flash-lite'),
            'gemini-2.5-flash-lite',
        ])));

        $lastRaw = '';
        $lastError = '';
        foreach ($models as $model) {
            $response = $this->requestGemini(
                apiKey: $apiKey,
                model: $model,
                prompt: $prompt,
                maxTokens: min(8192, 1200 + ($count * 500))
            );

            if (!$response->successful()) {
                $status = $response->status();
                $errorMessage = (string) ($response->json('error.message') ?? '');
                $lastError = trim("Model {$model} failed with HTTP {$status}. {$errorMessage}");
                continue;
            }

            $raw = (string) $response->json('candidates.0.content.parts.0.text', '');
            $lastRaw = $raw;
            $decoded = $this->parseModelJson($raw);
            if ($decoded === null) {
                $lastError = "Model {$model} returned invalid JSON payload.";
                continue;
            }

            $items = $this->normalizeItems($decoded, $count, $difficulty);
            if (!empty($items)) {
                return [
                    'items' => $items,
                    'prompt' => $prompt,
                    'raw' => $raw,
                ];
            }

            $lastError = "Model {$model} returned empty or invalid exercise items.";
        }

        throw new \RuntimeException($lastError !== '' ? $lastError : 'Gemini generation failed.');
    }

    private function buildPrompt(
        string $topic,
        string $grade,
        string $difficulty,
        int $count,
        string $language,
        string $instructions
    ): string {
        $languageLine = match ($language) {
            'hy' => 'Write all question/option/answer/explanation values in Armenian.',
            'ru' => 'Write all question/option/answer/explanation values in Russian.',
            'fr' => 'Write all question/option/answer/explanation values in French.',
            default => 'Write all question/option/answer/explanation values in English.',
        };

        $difficultyLine = match ($difficulty) {
            'easy' => 'Set difficulty to easy and keep each question short.',
            'hard' => 'Set difficulty to hard and include stronger distractors.',
            default => 'Set difficulty to medium.',
        };

        $gradeLine = $grade !== '' ? "Target grade: {$grade}." : 'Target grade: not specified.';
        $extra = $instructions !== '' ? "Extra teacher instructions: {$instructions}" : 'No extra teacher instructions.';

        return implode("\n", [
            'You are a strict math content generator for teachers.',
            "Generate exactly {$count} multiple-choice exercises.",
            'Every exercise must include 4 options and exactly 1 correct answer.',
            'Use LaTeX for mathematical expressions and wrap each math expression with single dollar delimiters like $...$.',
            'Example: What is $2 + 3$?',
            'LaTeX must be valid: balanced braces, valid commands, and no unmatched $ delimiters.',
            'Keep output concise to avoid truncation: short questions, short options, concise explanation.',
            'You may optionally include inline HTML and inline SVG for 2D/3D shapes.',
            'Allowed shape tags: <svg>, <g>, <path>, <rect>, <circle>, <ellipse>, <line>, <polygon>, <polyline>, <text>, <tspan>.',
            'Never use external image URLs, scripts, iframes, or CSS links.',
            'Do NOT output markdown links, markdown images, or any URLs.',
            'Return JSON only, on one line, with this exact schema:',
            '{"items":[{"type":"mcq","question":"","question_html":"","shape_svg":"","options":["","","",""],"options_html":["","","",""],"answer":"","answer_html":"","explanation":"","difficulty":"easy|medium|hard","points":1}]}',
            'Do not include markdown fences or any additional commentary.',
            $languageLine,
            $difficultyLine,
            $gradeLine,
            "Topic: {$topic}",
            $extra,
        ]);
    }

    private function requestGemini(string $apiKey, string $model, string $prompt, int $maxTokens): Response
    {
        $modelName = str_starts_with($model, 'models/') ? substr($model, 7) : $model;
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $modelName
        );

        return Http::timeout(30)
            ->connectTimeout(8)
            ->retry(1, 250)
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
                    'temperature' => 0.35,
                    'maxOutputTokens' => $maxTokens,
                ],
            ]);
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

        $candidates = [];
        $candidates[] = $text;
        $candidates[] = trim((string) preg_replace('/^```(?:json)?|```$/i', '', $text));

        $objStart = strpos($text, '{');
        $objEnd = strrpos($text, '}');
        if ($objStart !== false && $objEnd !== false && $objEnd > $objStart) {
            $candidates[] = substr($text, $objStart, $objEnd - $objStart + 1);
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeJsonCandidate($candidate);
            $decoded = json_decode($normalized, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeJsonCandidate(string $candidate): string
    {
        $candidate = trim($candidate);
        $candidate = str_replace(["\r\n", "\r"], "\n", $candidate);
        $candidate = str_replace(['вЂњ', 'вЂќ', 'вЂћ', 'В«', 'В»'], '"', $candidate);
        $candidate = preg_replace('/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)(\s*:)/', '$1"$2"$3', $candidate);
        $candidate = preg_replace("/:\s*'([^']*)'/", ': "$1"', $candidate);
        $candidate = preg_replace('/,\s*([}\]])/', '$1', $candidate);

        return $candidate;
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $decoded, int $count, string $difficulty): array
    {
        $items = [];

        if (isset($decoded['items']) && is_array($decoded['items'])) {
            $items = $decoded['items'];
        } elseif (array_key_exists(0, $decoded) && is_array($decoded[0])) {
            $items = $decoded;
        }

        $normalized = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $questionText = $this->sanitizeText((string) ($item['question'] ?? ''));
            $questionHtml = $this->sanitizeRichHtml((string) ($item['question_html'] ?? ''), false);
            $shapeSvg = $this->sanitizeRichHtml((string) ($item['shape_svg'] ?? ''), true);
            $question = $questionHtml !== '' ? $questionHtml : $questionText;
            if ($shapeSvg !== '') {
                $question = trim($question . '<div>' . $shapeSvg . '</div>');
            }

            $rawOptions = [];
            if (isset($item['options_html']) && is_array($item['options_html'])) {
                $rawOptions = $item['options_html'];
            } elseif (isset($item['options']) && is_array($item['options'])) {
                $rawOptions = $item['options'];
            }

            $options = array_values(array_filter(array_map(function ($value) {
                $string = (string) $value;
                if ($this->looksLikeHtml($string)) {
                    return $this->sanitizeRichHtml($string, false);
                }
                return $this->sanitizeOptionText($string);
            }, $rawOptions), fn ($value) => $value !== ''));

            $answerRaw = (string) ($item['answer_html'] ?? $item['answer'] ?? '');
            $answer = $this->looksLikeHtml($answerRaw)
                ? $this->sanitizeRichHtml($answerRaw, false)
                : $this->sanitizeOptionText($answerRaw);

            $explanationRaw = (string) ($item['explanation_html'] ?? $item['explanation'] ?? '');
            $explanation = $this->looksLikeHtml($explanationRaw)
                ? $this->sanitizeRichHtml($explanationRaw, false)
                : $this->sanitizeText($explanationRaw);

            $itemDifficulty = strtolower(trim((string) ($item['difficulty'] ?? $difficulty)));
            $points = max(1, min(20, (int) ($item['points'] ?? 1)));

            if ($question === '' || !is_array($options)) {
                continue;
            }

            if (
                $shapeSvg === '' &&
                !$this->looksLikeHtml($question) &&
                $this->containsBlockedVisualPrompt(strip_tags($question))
            ) {
                continue;
            }

            if ($answer === '' && !empty($options)) {
                $answer = $options[0];
            }

            if ($answer !== '' && $this->findOptionIndexByValue($options, $answer) === null) {
                $options[] = $answer;
            }

            while (count($options) < 4) {
                $fallbackOption = (string) random_int(1, 99);
                if ($this->findOptionIndexByValue($options, $fallbackOption) === null) {
                    $options[] = $fallbackOption;
                }
            }

            $options = array_values($this->uniqueByCanonical($options));
            if (count($options) > 4) {
                $answerIndex = $this->findOptionIndexByValue($options, $answer);
                $options = array_slice($options, 0, 4);
                if ($answerIndex !== null && $this->findOptionIndexByValue($options, $answer) === null) {
                    $options[3] = $answer;
                }
            }

            if ($answer === '' || $this->findOptionIndexByValue($options, $answer) === null) {
                $answer = $options[0];
            }

            $normalized[] = [
                'item_order' => $index + 1,
                'type' => trim((string) ($item['type'] ?? 'mcq')) ?: 'mcq',
                'question' => $question,
                'options' => array_values($options),
                'answer' => $answer,
                'explanation' => $explanation !== '' ? $explanation : null,
                'difficulty' => in_array($itemDifficulty, ['easy', 'medium', 'hard'], true)
                    ? $itemDifficulty
                    : $difficulty,
                'points' => $points,
                'source' => 'ai',
            ];
        }

        if (count($normalized) > $count) {
            $normalized = array_slice($normalized, 0, $count);
        }

        return $normalized;
    }

    private function sanitizeText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Remove markdown images and links first.
        $value = preg_replace('/!\[[^\]]*]\(([^)]+)\)/u', '', $value) ?? $value;
        $value = preg_replace('/\[([^\]]+)]\(([^)]+)\)/u', '$1', $value) ?? $value;

        // Remove raw URLs.
        $value = preg_replace('/https?:\/\/\S+/iu', '', $value) ?? $value;
        $value = preg_replace('/www\.\S+/iu', '', $value) ?? $value;

        // Normalize whitespace.
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value;
        $value = str_replace('\$', '$', $value);
        $value = $this->normalizeLatexDelimiters($value);

        return trim($value);
    }

    private function normalizeLatexDelimiters(string $value): string
    {
        // Convert alternative delimiters to single-dollar inline style.
        $value = preg_replace_callback('/\\\\\((.+?)\\\\\)/u', function (array $m) {
            return '$' . trim($m[1]) . '$';
        }, $value) ?? $value;

        $value = preg_replace_callback('/\\\\\[(.+?)\\\\\]/u', function (array $m) {
            return '$' . trim($m[1]) . '$';
        }, $value) ?? $value;

        $value = preg_replace_callback('/\$\$([\s\S]*?)\$\$/u', function (array $m) {
            return '$' . trim($m[1]) . '$';
        }, $value) ?? $value;

        // Trim spaces inside delimiters: $ 2 + 3 $ -> $2 + 3$
        $value = preg_replace_callback('/\$(.+?)\$/u', function (array $m) {
            $inner = preg_replace('/\s+/u', ' ', trim($m[1])) ?? trim($m[1]);
            if ($inner === '') {
                return '';
            }
            return '$' . $inner . '$';
        }, $value) ?? $value;

        // Remove unmatched trailing delimiters, if any.
        while ((substr_count($value, '$') % 2) !== 0) {
            $last = strrpos($value, '$');
            if ($last === false) {
                break;
            }
            $value = substr($value, 0, $last) . substr($value, $last + 1);
        }

        return $value;
    }

    private function sanitizeOptionText(string $value): string
    {
        $value = $this->sanitizeText($value);

        // Strip ordered/unordered list prefixes coming from LLM output.
        $value = preg_replace('/^\s*(?:[-*вЂў]+|\d{1,2}[.)])\s*/u', '', $value) ?? $value;

        return trim($value);
    }

    private function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<[^>]+>/u', $value);
    }

    /**
     * @param array<int, string> $options
     * @return array<int, string>
     */
    private function uniqueByCanonical(array $options): array
    {
        $seen = [];
        $result = [];

        foreach ($options as $option) {
            $canonical = $this->canonicalizeForCompare((string) $option);
            if ($canonical === '' || isset($seen[$canonical])) {
                continue;
            }

            $seen[$canonical] = true;
            $result[] = (string) $option;
        }

        return $result;
    }

    /**
     * @param array<int, string> $options
     */
    private function findOptionIndexByValue(array $options, string $answer): ?int
    {
        $needle = $this->canonicalizeForCompare($answer);
        if ($needle === '') {
            return null;
        }

        foreach ($options as $index => $option) {
            if ($this->canonicalizeForCompare((string) $option) === $needle) {
                return $index;
            }
        }

        return null;
    }

    private function canonicalizeForCompare(string $value): string
    {
        $plain = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = $this->sanitizeOptionText($plain);
        $plain = str_replace('$', '', $plain);
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return mb_strtolower(trim($plain));
    }

    private function sanitizeRichHtml(string $value, bool $allowSvg): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Remove markdown wrappers and links first.
        $value = preg_replace('/!\[[^\]]*]\(([^)]+)\)/u', '', $value) ?? $value;
        $value = preg_replace('/\[([^\]]+)]\(([^)]+)\)/u', '$1', $value) ?? $value;
        $value = preg_replace('/https?:\/\/\S+/iu', '', $value) ?? $value;
        $value = preg_replace('/www\.\S+/iu', '', $value) ?? $value;

        $allowed = [
            'p', 'br', 'strong', 'em', 'b', 'i', 'u', 'ul', 'ol', 'li', 'span', 'div', 'sup', 'sub', 'code', 'pre',
        ];

        if ($allowSvg) {
            $allowed = array_merge($allowed, [
                'svg', 'g', 'path', 'circle', 'rect', 'line', 'polygon', 'polyline', 'ellipse', 'text', 'tspan',
            ]);
        }

        $allowedTags = '<' . implode('><', $allowed) . '>';
        $value = strip_tags($value, $allowedTags);

        // Remove event handlers and external-resource attrs.
        $value = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $value) ?? $value;
        $value = preg_replace('/\s+(href|src)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $value) ?? $value;

        return trim($value);
    }

    private function containsBlockedVisualPrompt(string $value): bool
    {
        $normalized = mb_strtolower($value);
        $keywords = [
            'image',
            'picture',
            'figure',
            'diagram',
            'photo',
            'shown',
            'see the image',
            'look at the image',
            'Х¶ХЇХЎЦЂ',
            'ХєХЎХїХЇХҐЦЂ',
            'Х¶ХЇХЎЦЂХёЦ‚Хґ',
            'РёР·РѕР±СЂР°Р¶',
            'СЂРёСЃСѓРЅРє',
            'С„РѕС‚Рѕ',
            'РЅР° РєР°СЂС‚РёРЅРєРµ',
            'image ci-dessus',
            'dans lвЂ™image',
            'photo ci-dessus',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
