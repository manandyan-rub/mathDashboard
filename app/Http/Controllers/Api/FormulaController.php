<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\FormulaGroup;
use App\Models\FormulaStep;
use App\Services\FormulaStepAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormulaController extends Controller
{
    private const FORMULA_GROUP_COLORS = [
        'blue',
        'violet',
        'emerald',
        'cyan',
        'amber',
        'rose',
        'indigo',
        'teal',
    ];

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

    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'section_title' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'in:'.implode(',', self::FORMULA_GROUP_COLORS)],
        ]);

        $baseSlug = Str::slug($validated['label']) ?: 'formula-group';
        $slug = $baseSlug;
        $suffix = 2;

        while (FormulaGroup::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        $group = FormulaGroup::query()->create([
            'slug' => $slug,
            'label' => $validated['label'],
            'section_id' => 'general',
            'section_title' => ($validated['section_title'] ?? null) ?: 'General',
            'color' => $validated['color'] ?? 'indigo',
            'sort_order' => ((int) FormulaGroup::query()->max('sort_order')) + 10,
        ]);

        return response()->json($this->transformGroup($group), 201);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9_-]+:[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'formula' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'example' => ['nullable', 'string'],
            'locale' => ['nullable', 'string', 'max:12'],
        ]);

        $formula = Formula::query()->create([
            'category' => $validated['category'],
            'formula' => $validated['formula'],
            'example' => $validated['example'] ?? null,
        ]);

        $locale = strtolower($validated['locale'] ?? app()->getLocale());

        $formula->translations()->create([
            'locale' => $locale,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $locales = array_values(array_unique([...$this->preferredLocales(), $locale]));
        $formula->load(['translations' => function ($query) use ($locales) {
            $query->whereIn('locale', $locales);
        }]);

        return response()->json($this->transformFormula($formula, $locales), 201);
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
            'source' => $aiService->lastSource(),
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
            'source' => $aiService->lastSource(),
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

    /**
     * @return array<string, mixed>
     */
    private function transformGroup(FormulaGroup $group): array
    {
        return [
            'id' => $group->slug,
            'label' => $group->label,
            'color' => $group->color,
            'sections' => [
                [
                    'id' => $group->section_id,
                    'title' => $group->section_title,
                ],
            ],
        ];
    }
}
