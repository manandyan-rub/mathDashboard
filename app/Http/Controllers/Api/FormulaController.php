<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use Illuminate\Http\JsonResponse;

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
