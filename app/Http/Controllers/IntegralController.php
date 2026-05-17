<?php

namespace App\Http\Controllers;

use App\Services\IntegralAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegralController extends Controller
{
    public function calculate(Request $request, IntegralAiService $aiService)
    {
        $request->validate([
            'latex' => ['required', 'string', 'max:5000'],
        ]);

        $latex = $request->input('latex');
        $query = $this->latexToWolframQuery($latex);
        $firstPayload = $this->queryWolfram($query);

        if (! $firstPayload['ok']) {
            return response()->json([
                'message' => 'Could not compute the integral.',
                'details' => $firstPayload['details'],
                'query' => $query,
            ], 422);
        }

        $queryResult = $firstPayload['queryresult'];
        $pods = $this->podsFromQueryResult($queryResult);
        $wolframResult = $this->extractResultText($pods);
        $steps = [];
        $result = $wolframResult;
        $aiVerified = false;
        $stepsSource = 'wolfram';
        $stepsNote = 'AI step solution is not available. Showing Wolfram result only.';

        try {
            $aiSolution = $aiService->solve($latex, $query, app()->getLocale());
            if ($aiSolution !== null) {
                $aiVerified = $this->verifyAiResultWithWolfram($latex, $wolframResult, $aiSolution);
                if ($aiVerified) {
                    $result = $aiSolution['final_answer_latex'];
                    $steps = $aiSolution['steps'];
                    $stepsSource = 'ai_verified_by_wolfram';
                    $stepsNote = null;
                } else {
                    $stepsNote = 'AI steps were hidden because Wolfram did not verify the AI final answer. Showing Wolfram result only.';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Integral AI step generation failed', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);
        }

        return response()->json([
            'latex' => $latex,
            'query' => $query,
            'result' => $result,
            'wolfram_result' => $wolframResult,
            'steps' => $steps,
            'steps_source' => $stepsSource,
            'ai_verified' => $aiVerified,
            'steps_available' => count($steps) > 0,
            'steps_note' => count($steps) > 0 ? null : $stepsNote,
        ]);
    }

    private function latexToWolframQuery(string $latex): string
    {
        $q = trim($latex);

        $q = str_replace(['\\left', '\\right', '\\,', '\\!'], '', $q);
        $q = str_replace(['\\cdot', '\\times'], '*', $q);
        $q = str_replace(['\\pi'], 'pi', $q);
        $q = str_replace(['\\sin', '\\cos', '\\tan', '\\ln', '\\log', '\\sqrt'], ['sin', 'cos', 'tan', 'ln', 'log', 'sqrt'], $q);
        $q = preg_replace('/\\\\frac\s*\{([^{}]+)\}\s*\{([^{}]+)\}/', '(($1)/($2))', $q);

        if (str_contains($q, '\\int')) {
            $q = str_replace('\\int', 'integrate ', $q);
        }

        $q = str_replace(['{', '}'], '', $q);
        $q = preg_replace('/\s+/', ' ', $q);

        return trim($q);
    }

    private function queryWolfram(string $query, ?string $podstate = null): array
    {
        $params = [
            'appid' => config('services.wolfram.app_id'),
            'input' => $query,
            'output' => 'json',
            'format' => 'plaintext',
        ];

        if ($podstate !== null) {
            $params['podstate'] = $podstate;
        }

        $response = Http::timeout(20)->get('https://api.wolframalpha.com/v2/query', $params);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'details' => $response->body(),
            ];
        }

        $payload = $response->json();
        $queryResult = $payload['queryresult'] ?? null;

        if (! is_array($queryResult) || ! ($queryResult['success'] ?? false)) {
            return [
                'ok' => false,
                'details' => $payload,
            ];
        }

        return [
            'ok' => true,
            'queryresult' => $queryResult,
        ];
    }

    private function podsFromQueryResult(array $queryResult): array
    {
        $pods = $queryResult['pods'] ?? [];
        return is_array($pods) ? $pods : [];
    }

    private function toSubpodList(array $pod): array
    {
        $subpods = $pod['subpods'] ?? [];

        if (is_array($subpods) && array_is_list($subpods)) {
            return $subpods;
        }

        if (is_array($subpods)) {
            return [$subpods];
        }

        return [];
    }

    private function extractPlaintextsFromPod(array $pod): array
    {
        $texts = [];

        foreach ($this->toSubpodList($pod) as $subpod) {
            $text = trim((string) ($subpod['plaintext'] ?? ''));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    private function extractResultText(array $pods): string
    {
        $preferredTitles = [
            'result',
            'indefinite integral',
            'definite integral',
            'integral',
        ];

        foreach ($preferredTitles as $needle) {
            foreach ($pods as $pod) {
                $title = strtolower((string) ($pod['title'] ?? ''));
                if (! str_contains($title, $needle)) {
                    continue;
                }

                $texts = $this->extractPlaintextsFromPod($pod);
                if (count($texts) > 0) {
                    return $texts[0];
                }
            }
        }

        foreach ($pods as $pod) {
            $texts = $this->extractPlaintextsFromPod($pod);
            if (count($texts) > 0) {
                return $texts[0];
            }
        }

        return '';
    }

    private function findStepPodstate(array $pods): ?string
    {
        foreach ($pods as $pod) {
            $states = $pod['states'] ?? [];
            if (! is_array($states)) {
                continue;
            }

            foreach ($states as $state) {
                $name = strtolower((string) ($state['name'] ?? ''));
                $input = (string) ($state['input'] ?? '');

                if ($input !== '' && str_contains($name, 'step-by-step')) {
                    return $input;
                }
            }
        }

        return null;
    }

    private function extractStepLines(array $pods): array
    {
        $preferredLines = [];
        $fallbackLines = [];

        foreach ($pods as $pod) {
            $title = strtolower((string) ($pod['title'] ?? ''));
            $isPreferred = str_contains($title, 'step-by-step')
                || str_contains($title, 'result')
                || str_contains($title, 'integral');

            foreach ($this->extractPlaintextsFromPod($pod) as $text) {
                foreach (preg_split('/\R+/', $text) ?: [] as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        if ($isPreferred) {
                            $preferredLines[] = $line;
                        } else {
                            $fallbackLines[] = $line;
                        }
                    }
                }
            }
        }

        $lines = count($preferredLines) > 0 ? $preferredLines : $fallbackLines;
        $lines = array_values(array_unique($lines));

        return array_map(static fn (string $line) => [
            'latex' => $line,
            'explanation' => null,
        ], $lines);
    }

    /**
     * @param array{final_answer_latex: string, final_answer_plain: string, steps: array<int, array{latex: string, explanation: string|null}>} $aiSolution
     */
    private function verifyAiResultWithWolfram(string $latex, string $wolframResult, array $aiSolution): bool
    {
        $aiExpression = $this->expressionForWolfram(
            $aiSolution['final_answer_plain'] !== ''
                ? $aiSolution['final_answer_plain']
                : $aiSolution['final_answer_latex']
        );
        $wolframExpression = $this->expressionForWolfram($wolframResult);

        if ($aiExpression === '' || $wolframExpression === '') {
            return false;
        }

        if ($this->normalizedAnswerText($aiExpression) === $this->normalizedAnswerText($wolframExpression)) {
            return true;
        }

        $isDefinite = $this->isDefiniteIntegral($latex);
        $variable = $this->integrationVariable($latex);

        $verificationQuery = $isDefinite
            ? "FullSimplify[({$aiExpression}) - ({$wolframExpression})]"
            : "FullSimplify[D[({$aiExpression}), {$variable}] - D[({$wolframExpression}), {$variable}]]";

        $payload = $this->queryWolfram($verificationQuery);
        if ($payload['ok'] && $this->wolframResultIsZero($payload['queryresult'])) {
            return true;
        }

        $booleanQuery = $isDefinite
            ? "FullSimplify[({$aiExpression}) == ({$wolframExpression})]"
            : "FullSimplify[D[({$aiExpression}), {$variable}] == D[({$wolframExpression}), {$variable}]]";

        $booleanPayload = $this->queryWolfram($booleanQuery);

        return $booleanPayload['ok'] && $this->wolframResultIsTrue($booleanPayload['queryresult']);
    }

    private function expressionForWolfram(string $value): string
    {
        $expression = trim($value);
        if ($expression === '') {
            return '';
        }

        $expression = str_replace(["\r\n", "\r"], "\n", $expression);
        $expression = trim(explode("\n", $expression)[0] ?? $expression);
        $expression = trim($expression, " \t\n\r\0\x0B$");

        if (str_contains($expression, '=')) {
            $parts = explode('=', $expression);
            $expression = trim((string) end($parts));
        }

        if (str_contains($expression, '\\')) {
            $expression = $this->latexToWolframQuery($expression);
        }

        $expression = str_replace(['π', '−', '×', '·'], ['pi', '-', '*', '*'], $expression);
        $expression = preg_replace('/\barbitrary\s+constant\b/i', '', $expression) ?? $expression;
        $expression = preg_replace('/\bconstant\b/i', '', $expression) ?? $expression;
        $expression = $this->stripIntegrationConstant($expression);
        $expression = preg_replace('/\s+/', ' ', $expression) ?? $expression;

        return trim($expression, " \t\n\r\0\x0B+");
    }

    private function stripIntegrationConstant(string $expression): string
    {
        $expression = preg_replace('/([+\-])\s*C\b/', '', $expression) ?? $expression;
        $expression = preg_replace('/\bC\s*([+\-])/', '$1', $expression) ?? $expression;

        return trim($expression);
    }

    private function normalizedAnswerText(string $value): string
    {
        $value = mb_strtolower($this->stripIntegrationConstant($value));
        $value = str_replace([' ', "\t", "\n", "\r", '*', '(', ')'], '', $value);
        $value = str_replace(['π', '−'], ['pi', '-'], $value);

        return trim($value);
    }

    private function isDefiniteIntegral(string $latex): bool
    {
        return (bool) preg_match('/\\\\int\s*_\s*\{?[^{}\s]+\}?\s*\^\s*\{?[^{}\s]+\}?/', $latex);
    }

    private function integrationVariable(string $latex): string
    {
        if (preg_match('/\\\\mathrm\s*\{\s*d\s*\}\s*([a-zA-Z])/', $latex, $match)) {
            return $match[1];
        }

        if (preg_match('/(?:\\\\,|\\\\!|\s)*d\s*([a-zA-Z])\s*$/', trim($latex), $match)) {
            return $match[1];
        }

        if (preg_match('/d\s*([a-zA-Z])\b/', $latex, $match)) {
            return $match[1];
        }

        return 'x';
    }

    private function wolframResultIsZero(array $queryResult): bool
    {
        $text = $this->extractResultText($this->podsFromQueryResult($queryResult));
        $normalized = trim(str_replace([' ', '−'], ['', '-'], mb_strtolower($text)));

        return (bool) preg_match('/^0(?:\.0+)?(?:\b|$)/', $normalized);
    }

    private function wolframResultIsTrue(array $queryResult): bool
    {
        $text = mb_strtolower($this->extractResultText($this->podsFromQueryResult($queryResult)));

        return str_contains($text, 'true') || str_contains($text, 'yes');
    }
}
