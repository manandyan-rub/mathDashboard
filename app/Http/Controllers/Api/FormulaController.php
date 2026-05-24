<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\FormulaGroup;
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
            ->with('translations')
            ->orderBy('id')
            ->get();

        return response()->json(
            $formulas->map(fn (Formula $formula) => $this->transformFormula($formula, $locales))->values()
        );
    }

    public function groups(): JsonResponse
    {
        return response()->json(
            FormulaGroup::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->map(fn (FormulaGroup $group) => $this->transformGroup($group))
                ->values()
        );
    }

    public function show(Formula $formula): JsonResponse
    {
        $locales = $this->preferredLocales();
        $formula->load('translations');

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
            'source' => $aiService->lastSource(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function preferredLocales(): array
    {
        $locales = [
            strtolower((string) request()->header('X-Locale', '')),
            app()->getLocale(),
            config('app.fallback_locale', 'en'),
            'en',
            'hy',
            'ru',
            'fr',
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
        $translations = $formula->translations
            ->mapWithKeys(fn ($item) => [
                $item->locale => [
                    'name' => $this->restoreMojibake($item->name),
                    'description' => $this->restoreMojibake($item->description),
                ],
            ])
            ->all();

        return [
            'id' => $formula->id,
            'name' => $this->restoreMojibake($translation?->name ?? ''),
            'category' => $formula->category,
            'formula' => $formula->formula,
            'description' => $this->restoreMojibake($translation?->description),
            'example' => $this->restoreMojibake($formula->example),
            'locale' => $translation?->locale,
            'translations' => $translations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformGroup(FormulaGroup $group): array
    {
        return [
            'id' => $group->slug,
            'label' => $this->restoreMojibake($group->label) ?? '',
            'color' => $group->color,
            'sections' => [
                [
                    'id' => $group->section_id,
                    'title' => $this->restoreMojibake($group->section_title) ?? '',
                ],
            ],
        ];
    }

    private function restoreMojibake(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (preg_match('/\p{Armenian}/u', $value) || !preg_match('/\p{Cyrillic}/u', $value)) {
            return $value;
        }

        $candidate = @mb_convert_encoding($value, 'Windows-1251', 'UTF-8');
        if (!is_string($candidate) || $candidate === '' || !mb_check_encoding($candidate, 'UTF-8')) {
            return $value;
        }

        return preg_match('/\p{Armenian}/u', $candidate) ? $candidate : $value;
    }
}
