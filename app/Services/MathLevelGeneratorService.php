<?php

namespace App\Services;

use App\Models\MathLevelPrompt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MathLevelGeneratorService
{
    public function generate(MathLevelPrompt $prompt): array
    {
        $count = max(1, min(20, (int) ($prompt->question_count ?? 10)));
        $optionsCount = max(2, min(6, (int) ($prompt->options_count ?? 4)));
        $apiKey = (string) config('services.gemini.key', '');

        $difficulty = (string) ($prompt->level?->difficulty ?? '');

        if ($apiKey !== '') {
            $items = $this->generateWithGemini($apiKey, $prompt->prompt, $count, $optionsCount, $prompt->locale, $difficulty);
            if (!empty($items)) {
                return $items;
            }
        }

        return $this->generateLocalQuestions($prompt->prompt, $count, $optionsCount, $prompt->level?->slug, $difficulty);
    }

    private function generateWithGemini(string $apiKey, string $levelPrompt, int $count, int $optionsCount, ?string $locale, ?string $difficulty): array
    {
        $promptText = $this->buildPrompt($levelPrompt, $count, $optionsCount, $locale, $difficulty);
        $maxTokens = min(2048, 350 + ($count * 120));

        $models = array_values(array_unique(array_filter([
            (string) config('services.gemini.fallback_model', 'gemini-2.5-flash-lite'),
            (string) config('services.gemini.model', 'gemini-flash-latest'),
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash',
        ])));

        foreach ($models as $model) {
            try {
                $response = $this->requestGemini($apiKey, $model, $promptText, $maxTokens);
            } catch (\Throwable $e) {
                Log::warning('Gemini request exception', ['model' => $model, 'error' => $e->getMessage()]);
                continue;
            }

            if (!$response->successful()) {
                continue;
            }

            $text = (string) $response->json('candidates.0.content.parts.0.text', '');
            $decoded = $this->parseGeminiJson($text);
            if ($decoded === null) {
                continue;
            }

            $items = $this->normalizeQuestions($decoded, $count, $optionsCount);
            if (!empty($items)) {
                return $items;
            }
        }

        return [];
    }

    private function buildPrompt(string $prompt, int $count, int $optionsCount, ?string $locale, ?string $difficulty): string
    {
        $languageLine = match ($locale) {
            'hy' => 'Write all values in Armenian.',
            'fr' => 'Write all values in French.',
            'ru' => 'Write all values in Russian.',
            default => 'Write all values in English.',
        };

        $difficultyLine = match (strtolower((string) $difficulty)) {
            'hard' => 'Difficulty must be HARD. Use multi-step reasoning and stronger distractors.',
            'medium' => 'Difficulty must be MEDIUM. Avoid very trivial arithmetic.',
            'beginner', 'easy' => 'Difficulty must be BEGINNER. Keep steps simple.',
            default => 'Difficulty must follow the level requirements.',
        };

        return implode("\n", [
            'You are a strict math quiz generator.',
            "Generate exactly {$count} multiple-choice questions.",
            "Each question must have exactly {$optionsCount} options and exactly one correct answer.",
            'Return JSON only in one line with this schema:',
            '{"questions":[{"question":"","options":["","","",""],"correct_index":0,"correct_answer":"","explanation":""}]}',
            'Use only these keys: questions, question, options, correct_index, correct_answer, explanation.',
            'Use LaTeX in values when needed with $...$.',
            $languageLine,
            $difficultyLine,
            'No markdown, no code fences, no extra text.',
            'Level requirements:',
            trim($prompt),
        ]);
    }

    private function requestGemini(string $apiKey, string $model, string $prompt, int $maxTokens)
    {
        $model = str_starts_with($model, 'models/') ? substr($model, 7) : $model;
        $endpoint = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent', $model);

        return Http::timeout(16)
            ->connectTimeout(5)
            ->retry(1, 200)
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
                ],
            ]);
    }

    private function parseGeminiJson(string $text): ?array
    {
        $candidates = [];
        $raw = trim($text);
        if ($raw !== '') {
            $candidates[] = $raw;
            $candidates[] = trim(preg_replace('/^```(?:json)?|```$/i', '', $raw));
        }

        $objStart = strpos($raw, '{');
        $objEnd = strrpos($raw, '}');
        if ($objStart !== false && $objEnd !== false && $objEnd > $objStart) {
            $candidates[] = substr($raw, $objStart, $objEnd - $objStart + 1);
        }

        $arrStart = strpos($raw, '[');
        $arrEnd = strrpos($raw, ']');
        if ($arrStart !== false && $arrEnd !== false && $arrEnd > $arrStart) {
            $candidates[] = substr($raw, $arrStart, $arrEnd - $arrStart + 1);
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->cleanupJson($candidate);
            $decoded = json_decode($normalized, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function cleanupJson(string $candidate): string
    {
        $candidate = trim($candidate);
        $candidate = str_replace(["\r\n", "\r"], "\n", $candidate);
        $candidate = str_replace(['Р В Р’В Р В РІР‚В Р В Р’В Р Р†Р вЂљРЎв„ўР В Р Р‹Р РЋРІвЂћСћ', 'Р В Р’В Р В РІР‚В Р В Р’В Р Р†Р вЂљРЎв„ўР В Р Р‹Р РЋРЎв„ў', 'Р В Р’В Р В РІР‚В Р В Р’В Р Р†Р вЂљРЎв„ўР В Р Р‹Р Р†Р вЂљРЎвЂќ', 'Р В Р’В Р Р†Р вЂљРІвЂћСћР В РІР‚в„ўР вЂ™Р’В«', 'Р В Р’В Р Р†Р вЂљРІвЂћСћР В РІР‚в„ўР вЂ™Р’В»'], '"', $candidate);
        $candidate = preg_replace('/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)(\s*:)/', '$1"$2"$3', $candidate);
        $candidate = preg_replace("/:\s*'([^']*)'/", ': "$1"', $candidate);
        $candidate = preg_replace('/,\s*([}\]])/', '$1', $candidate);
        return $candidate;
    }

    private function normalizeQuestions(array $decoded, int $count, int $optionsCount): array
    {
        $items = [];
        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
            $items = $decoded['questions'];
        } elseif (array_key_exists(0, $decoded) && is_array($decoded[0])) {
            $items = $decoded;
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $options = $item['options'] ?? [];
            if ($question === '' || !is_array($options) || count($options) < 2) {
                continue;
            }

            $options = array_values(array_filter(array_map(function ($opt) {
                $clean = trim((string) $opt);
                $clean = preg_replace('/^[A-Da-d1-9][\)\.]\s*/', '', $clean);
                return $clean;
            }, $options), fn ($v) => $v !== ''));

            if (count($options) < 2) {
                continue;
            }
            if (count($options) > $optionsCount) {
                $options = array_slice($options, 0, $optionsCount);
            }

            $correctIndex = (int) ($item['correct_index'] ?? 0);
            if ($correctIndex >= 1 && $correctIndex <= count($options)) {
                $correctIndex -= 1;
            }
            if ($correctIndex < 0 || $correctIndex >= count($options)) {
                $correctIndex = 0;
            }

            $correctAnswer = trim((string) ($item['correct_answer'] ?? ''));
            if ($correctAnswer !== '') {
                $match = $this->findMatchingOptionIndex($options, $correctAnswer);
                if ($match !== null) {
                    $correctIndex = $match;
                }
            }

            $computed = $this->computeAnswerFromQuestion($question);
            if ($computed !== null) {
                $match = $this->findMatchingOptionIndex($options, '$' . $computed . '$');
                if ($match !== null) {
                    $correctIndex = $match;
                }
            }

            $normalized[] = [
                'question' => $question,
                'options' => $options,
                'correct_index' => $correctIndex,
                'explanation' => trim((string) ($item['explanation'] ?? '')) ?: null,
            ];
        }

        if (count($normalized) > $count) {
            $normalized = array_slice($normalized, 0, $count);
        }

        return $normalized;
    }

    private function findMatchingOptionIndex(array $options, string $answer): ?int
    {
        $answerNorm = $this->normalizeMathString($answer);
        foreach ($options as $i => $option) {
            if ($this->normalizeMathString($option) === $answerNorm) {
                return $i;
            }
        }
        return null;
    }

    private function normalizeMathString(string $value): string
    {
        $v = str_replace(['$', '\\', ' ', 'Р В Р’В Р В РІР‚В Р В Р вЂ Р Р†Р вЂљРЎв„ўР вЂ™Р’В¬Р В Р вЂ Р В РІР‚С™Р Р†РІР‚С›РЎС›', 'Р В Р’В Р В РІР‚В Р В Р’В Р Р†Р вЂљРЎв„ўР В Р вЂ Р В РІР‚С™Р РЋРЎв„ў'], ['', '', '', '-', '-'], trim($value));
        return $v;
    }

    private function computeAnswerFromQuestion(string $question): ?string
    {
        if (!preg_match('/\\$([^$]+)\\$/u', $question, $m)) {
            return null;
        }

        $expr = trim($m[1]);
        $expr = str_replace(['\\times', '\\cdot', '\\div', 'Р В Р’В Р В РІР‚В Р В Р вЂ Р Р†Р вЂљРЎв„ўР вЂ™Р’В¬Р В Р вЂ Р В РІР‚С™Р Р†РІР‚С›РЎС›', 'Р В Р’В Р В РІР‚В Р В Р’В Р Р†Р вЂљРЎв„ўР В Р вЂ Р В РІР‚С™Р РЋРЎв„ў'], ['*', '*', '/', '-', '-'], $expr);
        $expr = preg_replace('/\\\\frac\s*\{([^}]+)\}\s*\{([^}]+)\}/', '($1/$2)', $expr);
        $expr = preg_replace('/\s+/u', '', $expr);

        if ($expr === '' || preg_match('/[^0-9\+\-\*\/\(\)\.]/', $expr)) {
            return null;
        }

        $value = $this->safeEvaluateSimpleExpression($expr);
        if ($value === null) {
            return null;
        }

        if (abs($value - round($value)) < 0.000001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    private function safeEvaluateSimpleExpression(string $expr): ?float
    {
        $tokens = preg_split('/([+\-*\/\(\)])/u', $expr, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (!$tokens) {
            return null;
        }

        $output = [];
        $ops = [];
        $prec = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

        foreach ($tokens as $t) {
            if (is_numeric($t)) {
                $output[] = (float) $t;
                continue;
            }
            if ($t === '(') {
                $ops[] = $t;
                continue;
            }
            if ($t === ')') {
                while (!empty($ops) && end($ops) !== '(') {
                    $output[] = array_pop($ops);
                }
                if (!empty($ops) && end($ops) === '(') {
                    array_pop($ops);
                }
                continue;
            }
            if (!isset($prec[$t])) {
                return null;
            }
            while (!empty($ops) && end($ops) !== '(' && $prec[end($ops)] >= $prec[$t]) {
                $output[] = array_pop($ops);
            }
            $ops[] = $t;
        }

        while (!empty($ops)) {
            $op = array_pop($ops);
            if ($op === '(') {
                return null;
            }
            $output[] = $op;
        }

        $stack = [];
        foreach ($output as $token) {
            if (is_float($token)) {
                $stack[] = $token;
                continue;
            }
            if (count($stack) < 2) {
                return null;
            }
            $b = array_pop($stack);
            $a = array_pop($stack);
            if ($token === '+') $stack[] = $a + $b;
            if ($token === '-') $stack[] = $a - $b;
            if ($token === '*') $stack[] = $a * $b;
            if ($token === '/') {
                if (abs($b) < 0.0000001) return null;
                $stack[] = $a / $b;
            }
        }

        return count($stack) === 1 ? $stack[0] : null;
    }

    private function generateLocalQuestions(string $levelPrompt, int $count, int $optionsCount, ?string $levelSlug = null, ?string $difficulty = null): array
    {
        $mode = $this->detectMode($levelPrompt, $levelSlug);
        $difficulty = $this->normalizeDifficulty($difficulty, $levelPrompt, $levelSlug);
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = match ($mode) {
                'sub' => $this->makeSubQuestion($optionsCount, $difficulty),
                'mul' => $this->makeMulQuestion($optionsCount, $difficulty),
                'div' => $this->makeDivQuestion($optionsCount, $difficulty),
                'pct' => $this->makePercentQuestion($optionsCount, $difficulty),
                'eq' => $this->makeEqQuestion($optionsCount, $difficulty),
                'frac' => $this->makeFractionQuestion($optionsCount, $difficulty),
                'geom' => $this->makeGeometryQuestion($optionsCount, $difficulty),
                'func' => $this->makeFunctionQuestion($optionsCount, $difficulty),
                'stat' => $this->makeStatisticsQuestion($optionsCount, $difficulty),
                'logic' => $this->makeLogicQuestion($optionsCount, $difficulty),
                'trig' => $this->makeTrigQuestion($optionsCount, $difficulty),
                'calc' => $this->makeCalculusQuestion($optionsCount, $difficulty),
                'speed' => $this->makeMixedQuestion($optionsCount, 'hard'),
                'exam' => $this->makeMixedQuestion($optionsCount, 'hard'),
                default => $this->makeMixedQuestion($optionsCount, $difficulty),
            };
        }

        return $items;
    }

    private function normalizeDifficulty(?string $difficulty, string $prompt, ?string $levelSlug): string
    {
        $d = strtolower(trim((string) $difficulty));
        if (in_array($d, ['beginner', 'easy'], true)) {
            return 'beginner';
        }
        if ($d === 'medium') {
            return 'medium';
        }
        if ($d === 'hard') {
            return 'hard';
        }

        $slug = strtolower((string) ($levelSlug ?? ''));
        $p = mb_strtolower($prompt);
        if (
            str_contains($slug, 'hard') ||
            str_contains($slug, 'boss') ||
            str_contains($slug, 'einstein') ||
            str_contains($p, 'բարդ') ||
            str_contains($p, 'hard')
        ) {
            return 'hard';
        }
        if (
            str_contains($slug, 'medium') ||
            str_contains($p, 'միջին') ||
            str_contains($p, 'medium')
        ) {
            return 'medium';
        }
        return 'beginner';
    }

    private function detectMode(string $prompt, ?string $levelSlug = null): string
    {
        $p = mb_strtolower($prompt);
        $slug = strtolower((string) ($levelSlug ?? ''));

        if ($slug !== '') {
            if (str_contains($slug, 'speed')) return 'speed';
            if (str_contains($slug, 'exam')) return 'exam';
            if (str_contains($slug, 'trig')) return 'trig';
            if (str_contains($slug, 'derivative') || str_contains($slug, 'integral') || str_contains($slug, 'calculus')) return 'calc';
            if (str_contains($slug, 'logic') || str_contains($slug, 'sequence') || str_contains($slug, 'missing-number')) return 'logic';
            if (str_contains($slug, 'stat') || str_contains($slug, 'probability') || str_contains($slug, 'combinatorics')) return 'stat';
            if (str_contains($slug, 'function') || str_contains($slug, 'domain') || str_contains($slug, 'graph') || str_contains($slug, 'transform')) return 'func';
            if (str_contains($slug, 'geometry') || str_contains($slug, 'angles') || str_contains($slug, 'triangle') || str_contains($slug, 'pythagorean') || str_contains($slug, 'circle') || str_contains($slug, 'area') || str_contains($slug, 'volume')) return 'geom';
            if (str_contains($slug, 'fraction') || str_contains($slug, 'decimal')) return 'frac';
            if (str_contains($slug, 'percent')) return 'pct';
            if (str_contains($slug, 'equation') || str_contains($slug, 'algebra') || str_contains($slug, 'expression') || str_contains($slug, 'inequal') || str_contains($slug, 'quadratic') || str_contains($slug, 'system')) return 'eq';
            if (str_contains($slug, 'division')) return 'div';
            if (str_contains($slug, 'multiplication')) return 'mul';
            if (str_contains($slug, 'subtraction')) return 'sub';
            if (str_contains($slug, 'addition')) return 'add';
            if (str_contains($slug, 'mixed') || str_contains($slug, 'daily')) return 'mix';
        }

        if (str_contains($p, 'integral') || str_contains($p, 'derivative') || str_contains($p, 'ինտեգրալ') || str_contains($p, 'ածանց')) return 'calc';
        if (str_contains($p, 'trigon') || str_contains($p, 'sin') || str_contains($p, 'cos') || str_contains($p, 'tan')) return 'trig';
        if (str_contains($p, 'probability') || str_contains($p, 'statistics') || str_contains($p, 'հավանական')) return 'stat';
        if (str_contains($p, 'function') || str_contains($p, 'գրաֆ') || str_contains($p, 'ֆունկց')) return 'func';
        if (str_contains($p, 'geometry') || str_contains($p, 'triangle') || str_contains($p, 'circle') || str_contains($p, 'երկրաչափ')) return 'geom';
        if (str_contains($p, 'fraction') || str_contains($p, 'decimal') || str_contains($p, 'կոտոր')) return 'frac';
        if (str_contains($p, 'percent') || str_contains($p, 'percentage') || str_contains($p, 'տոկոս')) return 'pct';
        if (str_contains($p, 'equation') || str_contains($p, 'algebra') || str_contains($p, 'հավասար')) return 'eq';
        if (str_contains($p, 'division') || str_contains($p, 'divide') || str_contains($p, 'բաժան')) return 'div';
        if (str_contains($p, 'multiplication') || str_contains($p, 'multiply') || str_contains($p, 'բազմապատ')) return 'mul';
        if (str_contains($p, 'subtraction') || str_contains($p, 'subtract') || str_contains($p, 'հան')) return 'sub';

        return 'mix';
    }

    private function makeAddQuestion(int $optionsCount, string $difficulty): array
    {
        [$min, $max] = match ($difficulty) {
            'hard' => [80, 600],
            'medium' => [20, 250],
            default => [2, 90],
        };
        $a = random_int($min, $max);
        $b = random_int($min, $max);
        $spread = $difficulty === 'hard' ? 60 : ($difficulty === 'medium' ? 25 : 12);
        return $this->buildNumericQuestion(__('Calculate: :expr', ['expr' => '$' . $a . ' + ' . $b . '$']), $a + $b, $optionsCount, $spread);
    }

    private function makeSubQuestion(int $optionsCount, string $difficulty): array
    {
        [$min, $max] = match ($difficulty) {
            'hard' => [90, 700],
            'medium' => [30, 300],
            default => [10, 120],
        };
        $a = random_int($min, $max);
        $b = random_int(1, $a - 1);
        $spread = $difficulty === 'hard' ? 60 : ($difficulty === 'medium' ? 25 : 12);
        return $this->buildNumericQuestion(__('Calculate: :expr', ['expr' => '$' . $a . ' - ' . $b . '$']), $a - $b, $optionsCount, $spread);
    }

    private function makeMulQuestion(int $optionsCount, string $difficulty): array
    {
        [$min, $max] = match ($difficulty) {
            'hard' => [8, 35],
            'medium' => [3, 20],
            default => [2, 12],
        };
        $a = random_int($min, $max);
        $b = random_int($min, $max);
        $spread = $difficulty === 'hard' ? 80 : ($difficulty === 'medium' ? 30 : 14);
        return $this->buildNumericQuestion(__('Calculate: :expr', ['expr' => '$' . $a . ' \\times ' . $b . '$']), $a * $b, $optionsCount, $spread);
    }

    private function makeDivQuestion(int $optionsCount, string $difficulty): array
    {
        $b = match ($difficulty) {
            'hard' => random_int(7, 25),
            'medium' => random_int(3, 16),
            default => random_int(2, 12),
        };
        $correct = match ($difficulty) {
            'hard' => random_int(8, 45),
            'medium' => random_int(3, 25),
            default => random_int(2, 16),
        };
        $a = $b * $correct;
        $spread = $difficulty === 'hard' ? 25 : ($difficulty === 'medium' ? 14 : 8);
        return $this->buildNumericQuestion(__('Calculate: :expr', ['expr' => '$' . $a . ' \\div ' . $b . '$']), $correct, $optionsCount, $spread);
    }

    private function makePercentQuestion(int $optionsCount, string $difficulty): array
    {
        $base = match ($difficulty) {
            'hard' => random_int(300, 4000),
            'medium' => random_int(80, 1000),
            default => random_int(40, 400),
        };
        $pctList = match ($difficulty) {
            'hard' => [6, 12, 18, 22, 35, 40],
            'medium' => [5, 10, 15, 20, 25, 30],
            default => [5, 10, 20, 25],
        };
        $pct = $pctList[random_int(0, count($pctList) - 1)];
        $correct = (int) round(($base * $pct) / 100);
        $spread = $difficulty === 'hard' ? 120 : ($difficulty === 'medium' ? 40 : 18);

        return $this->buildNumericQuestion(
            __('What is :pct% of :base?', ['pct' => (string) $pct, 'base' => '$' . $base . '$']),
            $correct,
            $optionsCount,
            $spread
        );
    }

    private function makeEqQuestion(int $optionsCount, string $difficulty): array
    {
        if ($difficulty === 'hard') {
            $r1 = random_int(-8, 8);
            $r2 = random_int(-8, 8);
            while ($r2 === $r1) {
                $r2 = random_int(-8, 8);
            }
            $sum = $r1 + $r2;
            $prod = $r1 * $r2;
            $correct = max($r1, $r2);
            return $this->buildNumericQuestion(
                __('Solve: :expr', ['expr' => '$x^2 - (' . $sum . ')x + (' . $prod . ') = 0$ (larger root)']),
                $correct,
                $optionsCount,
                10
            );
        }

        $x = random_int(-12, 12);
        $a = $difficulty === 'medium' ? random_int(2, 12) : random_int(2, 9);
        $b = $difficulty === 'medium' ? random_int(-30, 30) : random_int(-15, 15);
        $c = ($a * $x) + $b;
        $spread = $difficulty === 'medium' ? 12 : 8;

        return $this->buildNumericQuestion(
            __('Solve: :expr', ['expr' => '$' . $a . 'x + (' . $b . ') = ' . $c . '$']),
            $x,
            $optionsCount,
            $spread
        );
    }

    private function makeFractionQuestion(int $optionsCount, string $difficulty): array
    {
        if ($difficulty === 'hard') {
            $a = random_int(1, 7);
            $b = random_int(2, 11);
            $c = random_int(1, 7);
            $d = random_int(2, 11);
            $num = $a * $d;
            $den = $b * $c;
            $correct = $this->toFractionLatex($num, $den);
            $question = '$\\frac{' . $a . '}{' . $b . '} \\div \\frac{' . $c . '}{' . $d . '}$';
        } else {
            $den = $difficulty === 'medium' ? random_int(5, 12) : random_int(3, 9);
            $a = random_int(1, $den - 1);
            $b = random_int(1, $den - 1);
            $num = $a + $b;
            $correct = $this->toFractionLatex($num, $den);
            $question = '$\\frac{' . $a . '}{' . $den . '} + \\frac{' . $b . '}{' . $den . '}$';
        }

        $options = [$correct];
        while (count($options) < max(4, $optionsCount)) {
            $n = random_int(1, 24);
            $d = random_int(2, 24);
            $candidate = $this->toFractionLatex($n, $d);
            if (!in_array($candidate, $options, true)) {
                $options[] = $candidate;
            }
        }

        return $this->buildChoiceQuestion(__('Calculate: :expr', ['expr' => $question]), $options, 0, $optionsCount);
    }

    private function makeGeometryQuestion(int $optionsCount, string $difficulty): array
    {
        $kind = random_int(0, $difficulty === 'hard' ? 2 : 1);

        if ($kind === 0) {
            $l = random_int(3, $difficulty === 'hard' ? 40 : 18);
            $w = random_int(2, $difficulty === 'hard' ? 30 : 14);
            return $this->buildNumericQuestion(
                __('Calculate: :expr', ['expr' => '$S = ' . $l . ' \\times ' . $w . '$']),
                $l * $w,
                $optionsCount,
                $difficulty === 'hard' ? 60 : 20
            );
        }

        if ($kind === 1) {
            $a = random_int(3, $difficulty === 'hard' ? 20 : 10);
            $b = random_int(4, $difficulty === 'hard' ? 22 : 12);
            $c = random_int(5, $difficulty === 'hard' ? 24 : 14);
            return $this->buildNumericQuestion(
                __('Calculate: :expr', ['expr' => '$V = ' . $a . ' \\times ' . $b . ' \\times ' . $c . '$']),
                $a * $b * $c,
                $optionsCount,
                $difficulty === 'hard' ? 120 : 45
            );
        }

        $a = random_int(3, 11);
        $b = random_int(3, 11);
        $correct = (int) round(sqrt(($a ** 2) + ($b ** 2)));
        return $this->buildNumericQuestion(
            __('Calculate: :expr', ['expr' => '$d = \\sqrt{' . $a . '^2 + ' . $b . '^2}$']),
            $correct,
            $optionsCount,
            10
        );
    }

    private function makeFunctionQuestion(int $optionsCount, string $difficulty): array
    {
        $a = random_int($difficulty === 'hard' ? -9 : -5, $difficulty === 'hard' ? 9 : 6);
        if ($a === 0) $a = 2;
        $b = random_int(-12, 12);
        $x = random_int(-6, 8);
        $correct = ($a * $x) + $b;

        return $this->buildNumericQuestion(
            __('Calculate: :expr', ['expr' => '$f(x)=' . $a . 'x+' . $b . ',\\ f(' . $x . ')=?$']),
            $correct,
            $optionsCount,
            $difficulty === 'hard' ? 20 : 10
        );
    }

    private function makeStatisticsQuestion(int $optionsCount, string $difficulty): array
    {
        if ($difficulty === 'hard') {
            $values = [];
            for ($i = 0; $i < 5; $i++) {
                $values[] = random_int(10, 95);
            }
            sort($values);
            $correct = $values[2];
            return $this->buildNumericQuestion(
                __('Calculate: :expr', ['expr' => 'median(' . implode(',', $values) . ')']),
                $correct,
                $optionsCount,
                15
            );
        }

        $values = [];
        for ($i = 0; $i < 4; $i++) {
            $values[] = random_int(10, 70);
        }
        $sum = array_sum($values);
        $correct = (int) round($sum / 4);
        return $this->buildNumericQuestion(
            __('Calculate: :expr', ['expr' => 'mean(' . implode(',', $values) . ')']),
            $correct,
            $optionsCount,
            12
        );
    }

    private function makeLogicQuestion(int $optionsCount, string $difficulty): array
    {
        $start = random_int(1, 20);
        if (random_int(0, 1) === 0 || $difficulty === 'beginner') {
            $step = random_int(2, $difficulty === 'hard' ? 11 : 6);
            $seq = [$start, $start + $step, $start + (2 * $step), $start + (3 * $step)];
            $correct = $start + (4 * $step);
        } else {
            $ratio = random_int(2, 4);
            $seq = [$start, $start * $ratio, $start * ($ratio ** 2), $start * ($ratio ** 3)];
            $correct = $start * ($ratio ** 4);
        }

        return $this->buildNumericQuestion(
            __('Calculate: :expr', ['expr' => implode(', ', $seq) . ', ?']),
            $correct,
            $optionsCount,
            $difficulty === 'hard' ? 40 : 16
        );
    }

    private function makeTrigQuestion(int $optionsCount, string $difficulty): array
    {
        $pool = [
            ['q' => '$\\sin 30^\\circ$', 'a' => '$\\frac{1}{2}$', 'w' => ['$\\frac{\\sqrt{3}}{2}$', '$1$', '$0$']],
            ['q' => '$\\cos 60^\\circ$', 'a' => '$\\frac{1}{2}$', 'w' => ['$\\frac{\\sqrt{3}}{2}$', '$1$', '$0$']],
            ['q' => '$\\tan 45^\\circ$', 'a' => '$1$', 'w' => ['$0$', '$\\frac{\\sqrt{3}}{3}$', '$\\sqrt{3}$']],
            ['q' => '$\\sin 45^\\circ$', 'a' => '$\\frac{\\sqrt{2}}{2}$', 'w' => ['$\\frac{1}{2}$', '$\\frac{\\sqrt{3}}{2}$', '$1$']],
        ];

        $pick = $pool[random_int(0, count($pool) - 1)];
        $options = array_merge([$pick['a']], $pick['w']);
        if ($difficulty === 'hard') {
            $options[] = '$-\\frac{1}{2}$';
        }

        return $this->buildChoiceQuestion(
            __('Solve: :expr', ['expr' => $pick['q']]),
            $options,
            0,
            $optionsCount
        );
    }

    private function makeCalculusQuestion(int $optionsCount, string $difficulty): array
    {
        if ($difficulty === 'hard' && random_int(0, 1) === 1) {
            $n = random_int(2, 6);
            $correct = '$\\frac{x^' . ($n + 1) . '}{' . ($n + 1) . '} + C$';
            $options = [
                $correct,
                '$' . $n . 'x^' . ($n - 1) . ' + C$',
                '$x^' . $n . ' + C$',
                '$\\frac{x^' . $n . '}{' . $n . '} + C$',
            ];
            return $this->buildChoiceQuestion(
                __('Solve: :expr', ['expr' => '$\\int x^' . $n . '\\,dx$']),
                $options,
                0,
                $optionsCount
            );
        }

        $n = random_int(2, 7);
        $correct = '$' . $n . 'x^' . ($n - 1) . '$';
        $options = [
            $correct,
            '$x^' . $n . '$',
            '$' . ($n + 1) . 'x^' . $n . '$',
            '$' . ($n - 1) . 'x^' . ($n - 2) . '$',
        ];
        return $this->buildChoiceQuestion(
            __('Solve: :expr', ['expr' => '$\\frac{d}{dx}(x^' . $n . ')$']),
            $options,
            0,
            $optionsCount
        );
    }

    private function makeMixedQuestion(int $optionsCount, string $difficulty): array
    {
        $pool = match ($difficulty) {
            'hard' => ['mul', 'div', 'pct', 'eq', 'geom', 'func', 'logic', 'trig', 'calc'],
            'medium' => ['add', 'sub', 'mul', 'div', 'pct', 'eq', 'frac', 'geom', 'func', 'logic', 'stat'],
            default => ['add', 'sub', 'mul', 'div', 'pct'],
        };

        $picked = $pool[random_int(0, count($pool) - 1)];
        return match ($picked) {
            'sub' => $this->makeSubQuestion($optionsCount, $difficulty),
            'mul' => $this->makeMulQuestion($optionsCount, $difficulty),
            'div' => $this->makeDivQuestion($optionsCount, $difficulty),
            'pct' => $this->makePercentQuestion($optionsCount, $difficulty),
            'eq' => $this->makeEqQuestion($optionsCount, $difficulty),
            'frac' => $this->makeFractionQuestion($optionsCount, $difficulty),
            'geom' => $this->makeGeometryQuestion($optionsCount, $difficulty),
            'func' => $this->makeFunctionQuestion($optionsCount, $difficulty),
            'logic' => $this->makeLogicQuestion($optionsCount, $difficulty),
            'stat' => $this->makeStatisticsQuestion($optionsCount, $difficulty),
            'trig' => $this->makeTrigQuestion($optionsCount, $difficulty),
            'calc' => $this->makeCalculusQuestion($optionsCount, $difficulty),
            default => $this->makeAddQuestion($optionsCount, $difficulty),
        };
    }

    private function buildChoiceQuestion(string $question, array $options, int $correctIndex, int $optionsCount, ?string $explanation = null): array
    {
        $options = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $options), fn ($v) => $v !== ''));
        $options = array_values(array_unique($options));
        $correct = $options[$correctIndex] ?? ($options[0] ?? '$0$');

        while (count($options) < $optionsCount) {
            $candidate = '$' . random_int(-40, 120) . '$';
            if (!in_array($candidate, $options, true)) {
                $options[] = $candidate;
            }
        }

        if (count($options) > $optionsCount) {
            $options = array_slice($options, 0, $optionsCount);
            if (!in_array($correct, $options, true)) {
                $options[$optionsCount - 1] = $correct;
            }
        }

        shuffle($options);
        $idx = array_search($correct, $options, true);

        return [
            'question' => $question,
            'options' => $options,
            'correct_index' => is_int($idx) ? $idx : 0,
            'explanation' => $explanation,
        ];
    }

    private function buildNumericQuestion(string $question, int $correct, int $optionsCount, int $spread = 12): array
    {
        $options = [$correct];
        while (count($options) < $optionsCount) {
            $candidate = $correct + random_int(-$spread, $spread);
            if (!in_array($candidate, $options, true)) {
                $options[] = $candidate;
            }
        }
        shuffle($options);
        $correctIndex = array_search($correct, $options, true);

        return [
            'question' => $question,
            'options' => array_map(fn ($v) => '$' . $v . '$', $options),
            'correct_index' => is_int($correctIndex) ? $correctIndex : 0,
            'explanation' => null,
        ];
    }

    private function toFractionLatex(int $num, int $den): string
    {
        if ($den === 0) {
            return '$0$';
        }
        $sign = ($num < 0 xor $den < 0) ? '-' : '';
        $num = abs($num);
        $den = abs($den);
        $g = $this->gcd($num, $den);
        $num = (int) ($num / $g);
        $den = (int) ($den / $g);
        if ($den === 1) {
            return '$' . $sign . $num . '$';
        }
        return '$' . $sign . '\\frac{' . $num . '}{' . $den . '}$';
    }

    private function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);
        if ($a === 0) return max(1, $b);
        if ($b === 0) return max(1, $a);
        while ($b !== 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }
        return max(1, $a);
    }
}
