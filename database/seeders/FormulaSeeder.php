<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormulaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/formulas.json');
        $rows = json_decode(file_get_contents($path), true) ?? [];
        $now = now();
        $defaultLocale = (string) config('app.fallback_locale', 'en');

        DB::transaction(function () use ($rows, $now, $defaultLocale) {
            DB::table('formula_translations')->delete();
            DB::table('formulas')->delete();

            $translationBuffer = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $formulaId = DB::table('formulas')->insertGetId([
                    'category' => (string) ($row['category'] ?? ''),
                    'formula' => (string) ($row['formula'] ?? ''),
                    'example' => $row['example'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $translations = $this->extractTranslations($row, $defaultLocale);
                foreach ($translations as $translation) {
                    $translationBuffer[] = [
                        'formula_id' => $formulaId,
                        'locale' => $translation['locale'],
                        'name' => $translation['name'],
                        'description' => $translation['description'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($translationBuffer) >= 500) {
                    DB::table('formula_translations')->insert($translationBuffer);
                    $translationBuffer = [];
                }
            }

            if (!empty($translationBuffer)) {
                DB::table('formula_translations')->insert($translationBuffer);
            }
        });
    }

    /**
     * @return array<int, array{locale: string, name: string, description: string|null}>
     */
    private function extractTranslations(array $row, string $defaultLocale): array
    {
        $defaultLocale = strtolower($defaultLocale ?: 'en');
        $items = [];

        $rawTranslations = $row['translations'] ?? null;
        if (is_array($rawTranslations) && !empty($rawTranslations)) {
            if (array_is_list($rawTranslations)) {
                foreach ($rawTranslations as $translation) {
                    if (!is_array($translation)) {
                        continue;
                    }

                    $locale = strtolower((string) ($translation['locale'] ?? $defaultLocale));
                    $name = trim((string) ($translation['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $items[] = [
                        'locale' => $locale,
                        'name' => $name,
                        'description' => isset($translation['description']) ? (string) $translation['description'] : null,
                    ];
                }
            } else {
                foreach ($rawTranslations as $locale => $translation) {
                    if (is_array($translation)) {
                        $name = trim((string) ($translation['name'] ?? ''));
                        $description = isset($translation['description']) ? (string) $translation['description'] : null;
                    } else {
                        $name = trim((string) $translation);
                        $description = isset($row['description']) ? (string) $row['description'] : null;
                    }

                    if ($name === '') {
                        continue;
                    }

                    $items[] = [
                        'locale' => strtolower((string) $locale),
                        'name' => $name,
                        'description' => $description,
                    ];
                }
            }
        }

        if (empty($items)) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $items[] = [
                    'locale' => strtolower((string) ($row['locale'] ?? $defaultLocale)),
                    'name' => $name,
                    'description' => isset($row['description']) ? (string) $row['description'] : null,
                ];
            }
        }

        $seen = [];
        $normalized = [];
        foreach ($items as $item) {
            $locale = $item['locale'] !== '' ? $item['locale'] : $defaultLocale;
            if (isset($seen[$locale])) {
                continue;
            }

            $seen[$locale] = true;
            $normalized[] = [
                'locale' => $locale,
                'name' => $item['name'],
                'description' => $item['description'],
            ];
        }

        return $normalized;
    }
}
