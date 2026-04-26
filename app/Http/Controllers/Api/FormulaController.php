<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\FormulaStep;
use App\Services\FormulaStepAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormulaController extends Controller
{
    public function index(): JsonResponse
    {
        $locales = $this->preferredLocales();

        $formulas = Formula::query()
            ->with(['translations' => function ($query) use ($locales) {
                $query->whereIn('locale', $locales);
            }])
            ->orderBy('id')
            ->get();

        return response()->json(
            $formulas->map(fn (Formula $formula) => $this->transformFormula($formula, $locales))->values()
        );
    }

    public function show(Formula $formula): JsonResponse
    {
        $locales = $this->preferredLocales();
        $formula->load(['translations' => function ($query) use ($locales) {
            $query->whereIn('locale', $locales);
        }]);

        return response()->json(
            $this->transformFormula($formula, $locales)
        );
    }

    public function steps(Formula $formula, Request $request, FormulaStepAiService $aiService): JsonResponse
    {
        $formula->load('step');

        $steps = $formula->step?->steps_json;
        if (is_array($steps) && !empty($steps)) {
            return response()->json([
                'formula_id' => $formula->id,
                'has_steps' => true,
                'steps' => $steps,
                'source' => 'db',
            ]);
        }

        try {
            $generated = $aiService->generateForFormula(
                $formula,
                (string) $request->input('locale', app()->getLocale())
            );
        } catch (\Throwable $e) {
            return response()->json([
                'formula_id' => $formula->id,
                'has_steps' => false,
                'steps' => [],
                'message' => __('Failed to generate formula steps. Please try again.'),
                'details' => $e->getMessage(),
            ], 502);
        }

        FormulaStep::updateOrCreate(
            ['formula_id' => $formula->id],
            ['steps_json' => $generated]
        );

        return response()->json([
            'formula_id' => $formula->id,
            'has_steps' => !empty($generated),
            'steps' => $generated,
            'source' => 'ai',
        ]);
    }

    public function regenerateSteps(Formula $formula, Request $request, FormulaStepAiService $aiService): JsonResponse
    {
        $locale = (string) $request->input('locale', app()->getLocale());

        try {
            $generated = $aiService->generateForFormula($formula, $locale);
        } catch (\Throwable $e) {
            return response()->json([
                'formula_id' => $formula->id,
                'has_steps' => false,
                'steps' => [],
                'message' => __('Failed to regenerate formula steps. Please try again.'),
                'details' => $e->getMessage(),
            ], 502);
        }

        FormulaStep::updateOrCreate(
            ['formula_id' => $formula->id],
            ['steps_json' => $generated]
        );

        return response()->json([
            'formula_id' => $formula->id,
            'has_steps' => !empty($generated),
            'steps' => $generated,
            'source' => 'ai',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function preferredLocales(): array
    {
        $locales = [
            app()->getLocale(),
            config('app.fallback_locale', 'en'),
            'en',
        ];

        return array_values(array_unique(array_filter($locales)));
    }

    /**
     * @param array<int, string> $locales
     * @return array<string, mixed>
     */
    private function transformFormula(Formula $formula, array $locales): array
    {
        $translation = null;
        foreach ($locales as $locale) {
            $translation = $formula->translations->firstWhere('locale', $locale);
            if ($translation) {
                break;
            }
        }

        $translation ??= $formula->translations->first();

        return [
            'id' => $formula->id,
            'name' => $translation?->name ?? '',
            'category' => $formula->category,
            'formula' => $formula->formula,
            'description' => $translation?->description,
            'example' => $formula->example,
            'locale' => $translation?->locale,
        ];
    }
}
