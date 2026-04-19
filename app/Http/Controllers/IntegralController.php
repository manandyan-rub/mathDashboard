<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class IntegralController extends Controller
{
    public function calculate(Request $request)
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
        $result = $this->extractResultText($pods);
        $steps = [];

        $stepPodstate = $this->findStepPodstate($pods);
        if ($stepPodstate !== null) {
            $secondPayload = $this->queryWolfram($query, $stepPodstate);
            if ($secondPayload['ok']) {
                $stepPods = $this->podsFromQueryResult($secondPayload['queryresult']);
                $steps = $this->extractStepLines($stepPods);
            }
        }

        return response()->json([
            'latex' => $latex,
            'query' => $query,
            'result' => $result,
            'steps' => $steps,
            'steps_available' => count($steps) > 0,
            'steps_note' => count($steps) > 0
                ? null
                : 'Step-by-step solution is not available for this query (or for current API plan).',
        ]);
    }

    private function latexToWolframQuery(string $latex): string
    {
        $q = trim($latex);

        $q = str_replace(['\\left', '\\right', '\\,', '\\!'], '', $q);
        $q = str_replace(['\\sin', '\\cos', '\\tan', '\\ln', '\\log', '\\sqrt'], ['sin', 'cos', 'tan', 'ln', 'log', 'sqrt'], $q);
        $q = preg_replace('/\\\\frac\s*\{([^{}]+)\}\s*\{([^{}]+)\}/', '(($1)/($2))', $q);

        if (str_contains($q, '\\int')) {
            $q = str_replace('\\int', 'integrate ', $q);
        }

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
}
