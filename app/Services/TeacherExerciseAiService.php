<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeacherExerciseAiService
{
    /**
     * @param array<string, mixed> $payload
     * @return array{items: array<int, array<string, mixed>>, prompt: string, raw: string}
     */
    public function generate(array $payload): array
    {
        $count = max(1, min(20, (int) ($payload['count'] ?? 10)));
        $batchSize = max(1, min(6, (int) ($payload['batch_size'] ?? 5)));
        $language = (string) ($payload['language'] ?? 'en');
        $topic = trim((string) ($payload['topic'] ?? 'General mathematics'));
        $grade = trim((string) ($payload['grade'] ?? ''));
        $difficulty = strtolower(trim((string) ($payload['difficulty'] ?? 'medium')));
        $instructions = trim((string) ($payload['instructions'] ?? ''));

        $apiKey = (string) config('services.gemini.key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Gemini API key is not configured.');
        }

        $model = $this->resolveModel();

        $allItems = [];
        $partialWarnings = [];
        $remaining = $count;
        $offset = 0;
        $attempt = 0;
        $maxAttempts = max(8, $count * 3);
        $consecutiveFailures = 0;

        $lastRaw = '';
        $lastError = '';
        while ($remaining > 0 && $attempt < $maxAttempts) {
            $attempt++;
            $effectiveBatchSize = $consecutiveFailures >= 2 ? 1 : $batchSize;
            $currentBatchCount = min($effectiveBatchSize, $remaining);
            $prompt = $this->buildPrompt(
                topic: $topic,
                grade: $grade,
                difficulty: $difficulty,
                count: $currentBatchCount,
                language: $language,
                instructions: $instructions
            );

            try {
                $response = $this->requestGeminiWithBusyRetry(
                    apiKey: $apiKey,
                    model: $model,
                    prompt: $prompt,
                    maxTokens: min(3600, 500 + ($currentBatchCount * 260))
                );
            } catch (\Throwable $e) {
                $lastError = "Model {$model} request exception: {$e->getMessage()}";
                Log::warning('TeacherExerciseAiService Gemini exception', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                    'batch_count' => $currentBatchCount,
                    'attempt' => $attempt,
                ]);
                $consecutiveFailures++;
                continue;
            }

            if (!$response->successful()) {
                $status = $response->status();
                $errorMessage = (string) ($response->json('error.message') ?? '');
                if ($status === 503 || $this->isHighDemandError($response)) {
                    $lastError = "Model {$model} is currently busy. Please try again in a moment.";
                } else {
                    $lastError = trim("Model {$model} failed with HTTP {$status}. {$errorMessage}");
                }
                $consecutiveFailures++;
                continue;
            }

            $raw = (string) $response->json('candidates.0.content.parts.0.text', '');
            $lastRaw = $raw;
            $decoded = $this->parseModelJson($raw);
            if ($decoded === null) {
                $strictPrompt = $prompt . "\n" .
                    'CRITICAL: Return ONLY valid minified JSON object with key "items". ' .
                    'Escape LaTeX backslashes as double backslashes in JSON strings.';

                $retryResponse = $this->requestGeminiWithBusyRetry(
                    apiKey: $apiKey,
                    model: $model,
                    prompt: $strictPrompt,
                    maxTokens: min(3600, 500 + ($currentBatchCount * 260))
                );

                if ($retryResponse->successful()) {
                    $retryRaw = (string) $retryResponse->json('candidates.0.content.parts.0.text', '');
                    $lastRaw = $retryRaw;
                    $decoded = $this->parseModelJson($retryRaw);
                }

                if ($decoded === null) {
                    $lastError = "Model {$model} returned invalid JSON payload.";
                    $consecutiveFailures++;
                    continue;
                }
            }

            $batchItems = $this->normalizeItems(
                $decoded,
                $currentBatchCount,
                $difficulty,
                $language
            );
            if (empty($batchItems)) {
                $lastError = "Model {$model} returned empty or invalid exercise items.";
                $consecutiveFailures++;
                continue;
            }

            if (count($batchItems) < $currentBatchCount) {
                $partialWarnings[] = "Model {$model} returned incomplete items (" . count($batchItems) . "/{$currentBatchCount}).";
            }

            foreach ($batchItems as $item) {
                $offset++;
                $item['item_order'] = $offset;
                $allItems[] = $item;
            }

            $remaining = $count - count($allItems);
            $consecutiveFailures = 0;
        }

        if (empty($allItems)) {
            throw new \RuntimeException($lastError !== '' ? $lastError : 'Gemini generation failed.');
        }

        if ($remaining > 0) {
            Log::warning('TeacherExerciseAiService: partial generation returned', [
                'requested' => $count,
                'generated' => count($allItems),
                'model' => $model,
                'last_error' => $lastError,
            ]);
        }

        if (!empty($partialWarnings)) {
            Log::warning('TeacherExerciseAiService: incomplete batches recovered', [
                'requested' => $count,
                'generated' => count($allItems),
                'model' => $model,
                'warnings' => $partialWarnings,
            ]);
        }

        return [
            'items' => array_slice($allItems, 0, $count),
            'prompt' => $this->buildPrompt(
                topic: $topic,
                grade: $grade,
                difficulty: $difficulty,
                count: $count,
                language: $language,
                instructions: $instructions
            ),
            'raw' => $lastRaw,
        ];
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
            'The output difficulty MUST match the requested difficulty and grade.',
            'Avoid trivial arithmetic unless difficulty is easy and grade <= 3.',
            'Use LaTeX for mathematical expressions and wrap each math expression with single dollar delimiters like $...$.',
            'Example: What is $2 + 3$?',
            'LaTeX must be valid: balanced braces, valid commands, and no unmatched $ delimiters.',
            'IMPORTANT: output must be valid JSON. Escape backslashes in JSON strings (for example: \\\\frac, \\\\int, \\\\sqrt).',
            'Keep output concise to avoid truncation: short questions, short options, concise explanation.',
            'Do not reference external images, uploaded images, or "look at picture/diagram" style prompts.',
            'You may include inline SVG only when absolutely needed for the question itself.',
            'Allowed shape tags: <svg>, <g>, <path>, <rect>, <circle>, <ellipse>, <line>, <polygon>, <polyline>, <text>, <tspan>.',
            'Never use external image URLs, scripts, iframes, or CSS links.',
            'Do NOT output markdown links, markdown images, or any URLs.',
            'Return JSON only, on one line, with this exact schema:',
            '{"items":[{"type":"mcq","question_html":"<p>...</p>","options_html":["<p>...</p>","<p>...</p>","<p>...</p>","<p>...</p>"],"answer_html":"<p>...</p>","explanation_html":"<p>...</p>","difficulty":"easy|medium|hard","points":1}]}',
            'Also include plain text mirrors when possible: question, options, answer, explanation.',
            'Only if needed for geometry, add optional field shape_svg with safe inline <svg> markup.',
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

        return Http::timeout(18)
            ->connectTimeout(5)
            ->retry(1, 350, function ($exception) {
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

    /**
     * @return array<int, string>
     */
    private function resolveModel(): string
    {
        $model = trim((string) config('services.gemini.model', 'gemini-2.5-flash'));
        if ($model === '') {
            throw new \RuntimeException('Gemini model is not configured.');
        }

        $model = str_starts_with($model, 'models/') ? substr($model, 7) : $model;

        // "gemini-flash-latest" often returns non-JSON in strict mode.
        // Force a stable JSON-friendly model while still honoring single-model strictness.
        if ($model === 'gemini-flash-latest') {
            return 'gemini-2.5-flash';
        }

        return $model;
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

        Log::warning('TeacherExerciseAiService: invalid JSON payload from Gemini', [
            'raw' => mb_substr($text, 0, 2500),
            'json_error' => json_last_error_msg(),
        ]);
        return null;
    }

    private function normalizeJsonCandidate(string $candidate): string
    {
        $candidate = trim($candidate);
        $candidate = str_replace(["\r\n", "\r"], "\n", $candidate);
        $candidate = str_replace(['вЂњ', 'вЂќ', 'вЂћ', 'В«', 'В»'], '"', $candidate);
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
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $decoded, int $count, string $difficulty, string $language): array
    {
        $items = [];

        if (isset($decoded['items']) && is_array($decoded['items'])) {
            $items = $decoded['items'];
        } elseif (array_key_exists(0, $decoded) && is_array($decoded[0])) {
            $items = $decoded;
        }

        $normalized = [];
        $seenQuestions = [];
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

            $plainQuestion = trim((string) preg_replace('/\s+/u', ' ', strip_tags($question)));
            if (mb_strlen($plainQuestion) < 12) {
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

            if (!$this->matchesRequestedLanguage($language, $question, $options, $answer, $explanation)) {
                continue;
            }

            $questionFingerprint = $this->fingerprintQuestion($question);
            if ($questionFingerprint !== '' && isset($seenQuestions[$questionFingerprint])) {
                continue;
            }
            if ($questionFingerprint !== '') {
                $seenQuestions[$questionFingerprint] = true;
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
        $value = preg_replace('/^\s*(?:[-*•·∙]+|\d{1,2}[.)])\s*/u', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param array<int, string> $options
     */
    private function matchesRequestedLanguage(
        string $language,
        string $question,
        array $options,
        string $answer,
        ?string $explanation
    ): bool {
        $blob = trim(implode(' ', array_filter([
            strip_tags($question),
            implode(' ', $options),
            $answer,
            $explanation ?? '',
        ])));

        if ($blob === '') {
            return false;
        }

        if ($language === 'hy') {
            return preg_match('/\p{Armenian}/u', $blob) === 1;
        }

        if ($language === 'ru') {
            return preg_match('/\p{Cyrillic}/u', $blob) === 1;
        }

        return true;
    }

    private function fingerprintQuestion(string $question): string
    {
        $plain = mb_strtolower(strip_tags($question));
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        $plain = trim($plain);

        return $plain;
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
            'in the image',
            'as shown in the image',
            'as seen in the figure',
            'image ci-dessus',
            'dans l image',
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




