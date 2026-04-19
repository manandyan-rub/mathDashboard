<?php

namespace Database\Seeders;

use App\Models\MathLevel;
use App\Models\MathLevelPrompt;
use App\Models\MathLevelQuestion;
use App\Models\MathLevelTranslation;
use App\Models\MathPath;
use App\Models\MathPathTranslation;
use Illuminate\Database\Seeder;

class MathLevelsSeeder extends Seeder
{
    public function run(): void
    {
        MathLevelQuestion::query()->delete();
        MathLevelPrompt::query()->delete();

        foreach ($this->paths() as $pathData) {
            $path = MathPath::updateOrCreate(
                ['slug' => $pathData['slug']],
                [
                    'order' => $pathData['order'],
                    'active' => true,
                ]
            );

            $this->syncPathTranslations($path->id, $pathData['translations']);

            foreach ($pathData['levels'] as $levelData) {
                $difficulty = (string) $levelData['difficulty'];
                $passingScore = (int) $levelData['passing_score'];

                if ($difficulty === 'Medium' && $passingScore < 75) {
                    $passingScore = 75;
                } elseif ($difficulty === 'Hard' && $passingScore < 80) {
                    $passingScore = 80;
                } elseif ($difficulty === 'Beginner' && $passingScore < 70) {
                    $passingScore = 70;
                }

                $level = MathLevel::updateOrCreate(
                    ['path_id' => $path->id, 'slug' => $levelData['slug']],
                    [
                        'difficulty' => $difficulty,
                        'order' => $levelData['order'],
                        'passing_score' => $passingScore,
                        'xp_reward' => $levelData['xp_reward'],
                        'time_limit' => $levelData['time_limit'],
                        'active' => true,
                    ]
                );

                $this->syncLevelTranslations($level->id, $levelData['titles']);
                $this->syncLevelPrompts(
                    $level->id,
                    $levelData['prompts'],
                    (int) ($levelData['question_count'] ?? 10)
                );
            }
        }
    }

    private function syncPathTranslations(int $pathId, array $translations): void
    {
        foreach ($translations as $locale => $translation) {
            MathPathTranslation::updateOrCreate(
                ['math_path_id' => $pathId, 'locale' => $locale],
                [
                    'title' => $translation['title'],
                    'description' => $translation['description'],
                ]
            );
        }
    }

    private function syncLevelTranslations(int $levelId, array $titles): void
    {
        foreach ($titles as $locale => $title) {
            MathLevelTranslation::updateOrCreate(
                ['math_level_id' => $levelId, 'locale' => $locale],
                [
                    'title' => $title,
                    'description' => $this->levelDescription($title, $locale),
                    'topic' => $title,
                ]
            );
        }
    }

    private function syncLevelPrompts(int $levelId, array $prompts, int $questionCount): void
    {
        $fallback = $prompts['en'] ?? $prompts['hy'] ?? '';

        foreach (['hy', 'en', 'ru', 'fr'] as $locale) {
            $prompt = $prompts[$locale] ?? $fallback;
            if ($prompt === '') {
                continue;
            }

            MathLevelPrompt::updateOrCreate(
                ['level_id' => $levelId, 'locale' => $locale],
                [
                    'prompt' => $prompt,
                    'question_count' => $questionCount,
                    'options_count' => 4,
                ]
            );
        }
    }

    private function levelDescription(string $title, string $locale): string
    {
        return match ($locale) {
            'hy' => $title . ' մակարդակ',
            'ru' => 'Уровень: ' . $title,
            'fr' => 'Niveau : ' . $title,
            default => $title . ' level',
        };
    }

    private function tr(string $hy, string $en, string $ru, string $fr): array
    {
        return [
            'hy' => $hy,
            'en' => $en,
            'ru' => $ru,
            'fr' => $fr,
        ];
    }

    private function pathTranslations(array $titles, array $descriptions): array
    {
        return [
            'hy' => [
                'title' => $titles['hy'],
                'description' => $descriptions['hy'],
            ],
            'en' => [
                'title' => $titles['en'],
                'description' => $descriptions['en'],
            ],
            'ru' => [
                'title' => $titles['ru'],
                'description' => $descriptions['ru'],
            ],
            'fr' => [
                'title' => $titles['fr'],
                'description' => $descriptions['fr'],
            ],
        ];
    }

    private function level(
        string $slug,
        array $titles,
        string $difficulty,
        int $order,
        int $passingScore,
        int $xpReward,
        ?int $timeLimit,
        array $prompts,
        int $questionCount = 10
    ): array {
        return [
            'slug' => $slug,
            'titles' => $titles,
            'difficulty' => $difficulty,
            'order' => $order,
            'passing_score' => $passingScore,
            'xp_reward' => $xpReward,
            'time_limit' => $timeLimit,
            'prompts' => $prompts,
            'question_count' => $questionCount,
        ];
    }

    private function paths(): array
    {
        return [
            [
                'slug' => 'arithmetic',
                'order' => 1,
                'translations' => $this->pathTranslations(
                    $this->tr('Թվաբանություն', 'Arithmetic', 'Арифметика', 'Arithmétique'),
                    $this->tr(
                        'Գումարում, հանում, բազմապատկում, բաժանում և խառը օրինակներ։',
                        'Addition, subtraction, multiplication, division, and mixed practice.',
                        'Сложение, вычитание, умножение, деление и смешанные примеры.',
                        'Addition, soustraction, multiplication, division et exercices mixtes.'
                    )
                ),
                'levels' => [
                    $this->level('addition', $this->tr('Գումարում', 'Addition', 'Сложение', 'Addition'), 'Beginner', 1, 70, 50, null, [
                        'hy' => 'Ստեղծիր հեշտ գումարման բազմընտրանի հարցեր։',
                        'en' => 'Generate easy multiple-choice questions about addition.',
                    ]),
                    $this->level('subtraction', $this->tr('Հանում', 'Subtraction', 'Вычитание', 'Soustraction'), 'Beginner', 2, 70, 60, null, [
                        'hy' => 'Ստեղծիր հեշտ հանման բազմընտրանի հարցեր։',
                        'en' => 'Generate easy multiple-choice questions about subtraction.',
                    ]),
                    $this->level('multiplication', $this->tr('Բազմապատկում', 'Multiplication', 'Умножение', 'Multiplication'), 'Medium', 3, 75, 70, null, [
                        'hy' => 'Ստեղծիր բազմապատկման բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about multiplication.',
                    ]),
                    $this->level('division', $this->tr('Բաժանում', 'Division', 'Деление', 'Division'), 'Medium', 4, 75, 80, null, [
                        'hy' => 'Ստեղծիր բաժանման բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about division.',
                    ]),
                    $this->level('mixed-operations', $this->tr('Խառը գործողություններ', 'Mixed Operations', 'Смешанные операции', 'Opérations mixtes'), 'Medium', 5, 75, 100, null, [
                        'hy' => 'Ստեղծիր խառը գործողություններով բազմընտրանի հարցեր՝ փակագծերով։',
                        'en' => 'Generate multiple-choice questions with mixed operations and parentheses.',
                    ]),
                    $this->level('mixed-boss', $this->tr('Խառը գործողություններ (Boss)', 'Mixed Operations (Boss)', 'Смешанные операции (Босс)', 'Opérations mixtes (Boss)'), 'Hard', 6, 80, 120, 90, [
                        'hy' => 'Ստեղծիր բարդ խառը գործողություններով բազմընտրանի հարցեր։',
                        'en' => 'Generate difficult multiple-choice questions with mixed operations.',
                    ]),
                ],
            ],
            [
                'slug' => 'fractions-and-decimals',
                'order' => 2,
                'translations' => $this->pathTranslations(
                    $this->tr('Կոտորակներ և տասնորդականներ', 'Fractions and Decimals', 'Дроби и десятичные числа', 'Fractions et nombres décimaux'),
                    $this->tr(
                        'Սովորական կոտորակներ, տասնորդականներ և տոկոսներ։',
                        'Common fractions, decimals, and percentages.',
                        'Обыкновенные дроби, десятичные числа и проценты.',
                        'Fractions usuelles, nombres décimaux et pourcentages.'
                    )
                ),
                'levels' => [
                    $this->level('fraction-basics', $this->tr('Կոտորակների հիմունքներ', 'Fraction Basics', 'Основы дробей', 'Bases des fractions'), 'Beginner', 1, 70, 70, null, [
                        'hy' => 'Ստեղծիր կոտորակների հիմունքների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about fraction basics.',
                    ]),
                    $this->level('fraction-operations', $this->tr('Կոտորակների գործողություններ', 'Fraction Operations', 'Операции с дробями', 'Opérations sur les fractions'), 'Medium', 2, 75, 90, null, [
                        'hy' => 'Ստեղծիր կոտորակների գործողությունների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about fraction operations.',
                    ]),
                    $this->level('decimals', $this->tr('Տասնորդականներ', 'Decimals', 'Десятичные числа', 'Nombres décimaux'), 'Medium', 3, 75, 90, null, [
                        'hy' => 'Ստեղծիր տասնորդական թվերի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about decimal numbers.',
                    ]),
                    $this->level('percentages', $this->tr('Տոկոսներ', 'Percentages', 'Проценты', 'Pourcentages'), 'Medium', 4, 75, 100, null, [
                        'hy' => 'Ստեղծիր տոկոսների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about percentages.',
                    ]),
                ],
            ],
            [
                'slug' => 'algebra',
                'order' => 3,
                'translations' => $this->pathTranslations(
                    $this->tr('Հանրահաշիվ', 'Algebra', 'Алгебра', 'Algèbre'),
                    $this->tr(
                        'Հավասարումներ, անհավասարումներ, արտահայտություններ և համակարգեր։',
                        'Equations, inequalities, expressions, and systems.',
                        'Уравнения, неравенства, выражения и системы.',
                        'Équations, inégalités, expressions et systèmes.'
                    )
                ),
                'levels' => [
                    $this->level('expressions', $this->tr('Արտահայտություններ', 'Expressions', 'Выражения', 'Expressions'), 'Beginner', 1, 70, 80, null, [
                        'hy' => 'Ստեղծիր արտահայտությունների պարզեցման բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about simplifying expressions.',
                    ]),
                    $this->level('linear-equations', $this->tr('Գծային հավասարումներ', 'Linear Equations', 'Линейные уравнения', 'Équations linéaires'), 'Beginner', 2, 70, 90, null, [
                        'hy' => 'Ստեղծիր գծային հավասարումների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about linear equations.',
                    ]),
                    $this->level('inequalities', $this->tr('Անհավասարումներ', 'Inequalities', 'Неравенства', 'Inégalités'), 'Medium', 3, 75, 100, null, [
                        'hy' => 'Ստեղծիր գծային անհավասարումների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about linear inequalities.',
                    ]),
                    $this->level('quadratic-equations', $this->tr('Քառակուսային հավասարումներ', 'Quadratic Equations', 'Квадратные уравнения', 'Équations quadratiques'), 'Hard', 4, 80, 130, null, [
                        'hy' => 'Ստեղծիր քառակուսային հավասարումների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about quadratic equations.',
                    ]),
                    $this->level('systems-of-equations', $this->tr('Հավասարումների համակարգեր', 'Systems of Equations', 'Системы уравнений', 'Systèmes d’équations'), 'Hard', 5, 80, 140, 120, [
                        'hy' => 'Ստեղծիր երկու անհայտով համակարգերի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about systems with two unknowns.',
                    ]),
                ],
            ],
            [
                'slug' => 'functions',
                'order' => 4,
                'translations' => $this->pathTranslations(
                    $this->tr('Ֆունկցիաներ', 'Functions', 'Функции', 'Fonctions'),
                    $this->tr(
                        'Տիրույթ, արժեքների բազմություն և գրաֆիկներ։',
                        'Domain, range, and graph reading.',
                        'Область определения, значения и графики.',
                        'Domaine, image et lecture de graphiques.'
                    )
                ),
                'levels' => [
                    $this->level('function-basics', $this->tr('Ֆունկցիայի հիմունքներ', 'Function Basics', 'Основы функций', 'Bases des fonctions'), 'Beginner', 1, 70, 90, null, [
                        'hy' => 'Ստեղծիր ֆունկցիայի հիմունքների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about function basics.',
                    ]),
                    $this->level('domain-range', $this->tr('Տիրույթ և արժեքների բազմություն', 'Domain and Range', 'Область определения и значения', 'Domaine et image'), 'Medium', 2, 75, 100, null, [
                        'hy' => 'Ստեղծիր տիրույթի և արժեքների բազմության բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about domain and range.',
                    ]),
                    $this->level('graph-reading', $this->tr('Գրաֆիկի ընթերցում', 'Graph Reading', 'Чтение графиков', 'Lecture de graphiques'), 'Medium', 3, 75, 110, null, [
                        'hy' => 'Ստեղծիր ֆունկցիայի գրաֆիկի ընթերցման բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about reading function graphs.',
                    ]),
                    $this->level('transformations', $this->tr('Ֆունկցիայի ձևափոխություններ', 'Function Transformations', 'Преобразования функций', 'Transformations de fonctions'), 'Hard', 4, 80, 130, null, [
                        'hy' => 'Ստեղծիր ֆունկցիաների ձևափոխությունների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about function transformations.',
                    ]),
                ],
            ],
            [
                'slug' => 'geometry',
                'order' => 5,
                'translations' => $this->pathTranslations(
                    $this->tr('Երկրաչափություն', 'Geometry', 'Геометрия', 'Géométrie'),
                    $this->tr(
                        'Անկյուններ, եռանկյուններ, շրջան, մակերես և ծավալ։',
                        'Angles, triangles, circles, area, and volume.',
                        'Углы, треугольники, окружности, площадь и объем.',
                        'Angles, triangles, cercles, aire et volume.'
                    )
                ),
                'levels' => [
                    $this->level('angles', $this->tr('Անկյուններ', 'Angles', 'Углы', 'Angles'), 'Beginner', 1, 70, 80, null, [
                        'hy' => 'Ստեղծիր անկյունների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about angles.',
                    ]),
                    $this->level('triangles', $this->tr('Եռանկյուններ', 'Triangles', 'Треугольники', 'Triangles'), 'Medium', 2, 75, 100, null, [
                        'hy' => 'Ստեղծիր եռանկյունների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about triangles.',
                    ]),
                    $this->level('pythagorean-theorem', $this->tr('Պյութագորասի թեորեմ', 'Pythagorean Theorem', 'Теорема Пифагора', 'Théorème de Pythagore'), 'Medium', 3, 75, 110, null, [
                        'hy' => 'Ստեղծիր Պյութագորասի թեորեմի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about the Pythagorean theorem.',
                    ]),
                    $this->level('circles', $this->tr('Շրջան', 'Circles', 'Окружности', 'Cercles'), 'Medium', 4, 75, 110, null, [
                        'hy' => 'Ստեղծիր շրջանի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about circles.',
                    ]),
                    $this->level('area-and-volume', $this->tr('Մակերես և ծավալ', 'Area and Volume', 'Площадь и объем', 'Aire et volume'), 'Hard', 5, 80, 140, 120, [
                        'hy' => 'Ստեղծիր մակերեսի և ծավալի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about area and volume.',
                    ]),
                ],
            ],
            [
                'slug' => 'statistics-and-probability',
                'order' => 6,
                'translations' => $this->pathTranslations(
                    $this->tr('Վիճակագրություն և հավանականություն', 'Statistics and Probability', 'Статистика и вероятность', 'Statistiques et probabilités'),
                    $this->tr(
                        'Միջին, մեդիանա, մոդա և հավանականություն։',
                        'Mean, median, mode, and probability.',
                        'Среднее, медиана, мода и вероятность.',
                        'Moyenne, médiane, mode et probabilités.'
                    )
                ),
                'levels' => [
                    $this->level('mean-median-mode', $this->tr('Միջին, մեդիանա, մոդա', 'Mean, Median, Mode', 'Среднее, медиана, мода', 'Moyenne, médiane, mode'), 'Beginner', 1, 70, 90, null, [
                        'hy' => 'Ստեղծիր միջինի, մեդիանայի և մոդայի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about mean, median, and mode.',
                    ]),
                    $this->level('basic-probability', $this->tr('Հավանականության հիմունքներ', 'Probability Basics', 'Основы вероятности', 'Bases des probabilités'), 'Medium', 2, 75, 100, null, [
                        'hy' => 'Ստեղծիր հավանականության բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about basic probability.',
                    ]),
                    $this->level('combinatorics', $this->tr('Կոմբինատորիկա', 'Combinatorics', 'Комбинаторика', 'Combinatoire'), 'Hard', 3, 80, 130, null, [
                        'hy' => 'Ստեղծիր կոմբինատորիկայի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about combinatorics.',
                    ]),
                ],
            ],
            [
                'slug' => 'logic-and-patterns',
                'order' => 7,
                'translations' => $this->pathTranslations(
                    $this->tr('Տրամաբանություն և օրինաչափություններ', 'Logic and Patterns', 'Логика и закономерности', 'Logique et motifs'),
                    $this->tr(
                        'Թվային շարքեր և տրամաբանական խնդիրներ։',
                        'Number sequences and logic challenges.',
                        'Числовые последовательности и логические задачи.',
                        'Suites numériques et défis logiques.'
                    )
                ),
                'levels' => [
                    $this->level('number-sequences', $this->tr('Թվային շարքեր', 'Number Sequences', 'Числовые последовательности', 'Suites numériques'), 'Beginner', 1, 70, 80, null, [
                        'hy' => 'Ստեղծիր թվային շարքերի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about number sequences.',
                    ]),
                    $this->level('missing-number', $this->tr('Բաց թողնված թիվ', 'Missing Number', 'Пропущенное число', 'Nombre manquant'), 'Medium', 2, 75, 90, null, [
                        'hy' => 'Ստեղծիր բաց թողնված թվի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about missing-number patterns.',
                    ]),
                    $this->level('logic-boss', $this->tr('Տրամաբանական Boss', 'Logic Boss', 'Логический босс', 'Boss logique'), 'Hard', 3, 80, 140, 90, [
                        'hy' => 'Ստեղծիր բարդ տրամաբանական բազմընտրանի հարցեր։',
                        'en' => 'Generate difficult multiple-choice logic questions.',
                    ]),
                ],
            ],
            [
                'slug' => 'speed-math',
                'order' => 8,
                'translations' => $this->pathTranslations(
                    $this->tr('Արագ հաշվարկ', 'Speed Math', 'Быстрый счет', 'Calcul rapide'),
                    $this->tr(
                        'Ժամանակով արագ հաշվարկի վարժություններ։',
                        'Timed mental-math practice.',
                        'Упражнения на быстрый счет на время.',
                        'Exercices de calcul rapide chronométrés.'
                    )
                ),
                'levels' => [
                    $this->level('speed-addition', $this->tr('Արագ գումարում', 'Speed Addition', 'Быстрое сложение', 'Addition rapide'), 'Beginner', 1, 80, 90, 30, [
                        'hy' => 'Ստեղծիր արագ գումարման բազմընտրանի հարցեր։',
                        'en' => 'Generate timed multiple-choice questions about addition.',
                    ], 12),
                    $this->level('speed-multiplication', $this->tr('Արագ բազմապատկում', 'Speed Multiplication', 'Быстрое умножение', 'Multiplication rapide'), 'Medium', 2, 80, 110, 30, [
                        'hy' => 'Ստեղծիր արագ բազմապատկման բազմընտրանի հարցեր։',
                        'en' => 'Generate timed multiple-choice questions about multiplication.',
                    ], 12),
                    $this->level('speed-mixed', $this->tr('Արագ խառը', 'Speed Mixed', 'Быстрые смешанные операции', 'Calcul mixte rapide'), 'Hard', 3, 85, 140, 25, [
                        'hy' => 'Ստեղծիր արագ խառը գործողությունների բազմընտրանի հարցեր։',
                        'en' => 'Generate timed multiple-choice questions with mixed operations.',
                    ], 15),
                ],
            ],
            [
                'slug' => 'trigonometry',
                'order' => 9,
                'translations' => $this->pathTranslations(
                    $this->tr('Եռանկյունաչափություն', 'Trigonometry', 'Тригонометрия', 'Trigonométrie'),
                    $this->tr(
                        'Սինուս, կոսինուս, տանգենս և ինքնություններ։',
                        'Sine, cosine, tangent, and identities.',
                        'Синус, косинус, тангенс и тождества.',
                        'Sinus, cosinus, tangente et identités.'
                    )
                ),
                'levels' => [
                    $this->level('trig-basics', $this->tr('Եռանկյունաչափության հիմունքներ', 'Trigonometry Basics', 'Основы тригонометрии', 'Bases de la trigonométrie'), 'Medium', 1, 75, 120, null, [
                        'hy' => 'Ստեղծիր Եռանկյունաչափությանյի հիմունքների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about trigonometry basics.',
                    ], 8),
                    $this->level('trig-identities', $this->tr('Եռանկյունաչափություն', 'Trigonometric Identities', 'Тригонометрические тождества', 'Identités trigonométriques'), 'Hard', 2, 80, 150, null, [
                        'hy' => 'Ստեղծիր Եռանկյունաչափության բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about trigonometric identities.',
                    ], 8),
                ],
            ],
            [
                'slug' => 'calculus',
                'order' => 10,
                'translations' => $this->pathTranslations(
                    $this->tr('Վերլուծություն', 'Calculus', 'Математический анализ', 'Analyse'),
                    $this->tr(
                        'Ածանցյալներ և ինտեգրալներ։',
                        'Derivatives and integrals.',
                        'Производные и интегралы.',
                        'Dérivées et intégrales.'
                    )
                ),
                'levels' => [
                    $this->level('derivative-basics', $this->tr('Ածանցյալի հիմունքներ', 'Derivative Basics', 'Основы производной', 'Bases des dérivées'), 'Medium', 1, 75, 130, null, [
                        'hy' => 'Ստեղծիր ածանցյալների հիմունքների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about derivative basics.',
                    ], 8),
                    $this->level('integral-basics', $this->tr('Ինտեգրալի հիմունքներ', 'Integral Basics', 'Основы интеграла', 'Bases des intégrales'), 'Hard', 2, 80, 150, null, [
                        'hy' => 'Ստեղծիր ինտեգրալների բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions about integral basics.',
                    ], 8),
                    $this->level('einstein-integrals', $this->tr('Einstein', 'Einstein', 'Einstein', 'Einstein'), 'Hard', 3, 85, 180, 180, [
                        'hy' => 'Ստեղծիր բարդ ինտեգրալների բազմընտրանի հարցեր։',
                        'en' => 'Generate difficult multiple-choice questions about integrals.',
                    ], 8),
                ],
            ],
            [
                'slug' => 'exam-tests',
                'order' => 11,
                'translations' => $this->pathTranslations(
                    $this->tr('Քննական թեստեր', 'Exam Tests', 'Экзаменационные тесты', 'Tests d’examen'),
                    $this->tr(
                        'Միասնական քննության տիպի հարցեր։',
                        'Questions in a national-exam style.',
                        'Вопросы в формате национального экзамена.',
                        'Questions de type examen national.'
                    )
                ),
                'levels' => [
                    $this->level('exam-math-level-1', $this->tr('Թեստային մակարդակ 1', 'Test Level 1', 'Тестовый уровень 1', 'Niveau de test 1'), 'Medium', 1, 75, 150, 600, [
                        'hy' => 'Ստեղծիր միասնական քննության տիպի բազմընտրանի հարցեր։',
                        'en' => 'Generate multiple-choice questions in a standardized exam style.',
                    ]),
                    $this->level('exam-math-level-2', $this->tr('Թեստային մակարդակ 2', 'Test Level 2', 'Тестовый уровень 2', 'Niveau de test 2'), 'Hard', 2, 75, 180, 720, [
                        'hy' => 'Ստեղծիր ավելի բարդ քննական բազմընտրանի հարցեր։',
                        'en' => 'Generate more difficult multiple-choice exam questions.',
                    ], 12),
                    $this->level('exam-boss', $this->tr('Քննական Boss', 'Exam Boss', 'Экзаменационный босс', 'Boss d’examen'), 'Hard', 3, 80, 220, 900, [
                        'hy' => 'Ստեղծիր խառը բարդ քննական բազմընտրանի հարցեր։',
                        'en' => 'Generate difficult mixed multiple-choice exam questions.',
                    ], 15),
                ],
            ],
            [
                'slug' => 'daily-challenges',
                'order' => 12,
                'translations' => $this->pathTranslations(
                    $this->tr('Օրվա մարտահրավերներ', 'Daily Challenges', 'Ежедневные испытания', 'Défis quotidiens'),
                    $this->tr(
                        'Կարճ ամենօրյա թեստեր։',
                        'Short daily practice sets.',
                        'Короткие ежедневные задания.',
                        'Courtes séries d’exercices quotidiens.'
                    )
                ),
                'levels' => [
                    $this->level('daily-challenge-easy', $this->tr('Օրվա մարտահրավեր — Easy', 'Daily Challenge - Easy', 'Ежедневный вызов - Easy', 'Défi du jour - Facile'), 'Beginner', 1, 70, 60, 120, [
                        'hy' => 'Ստեղծիր 5 կարճ ամենօրյա բազմընտրանի հարցեր։',
                        'en' => 'Generate 5 short daily multiple-choice questions.',
                    ], 5),
                    $this->level('daily-challenge-hard', $this->tr('Օրվա մարտահրավեր — Hard', 'Daily Challenge - Hard', 'Ежедневный вызов - Hard', 'Défi du jour - Difficile'), 'Hard', 2, 80, 120, 120, [
                        'hy' => 'Ստեղծիր 5 բարդ ամենօրյա բազմընտրանի հարցեր։',
                        'en' => 'Generate 5 difficult daily multiple-choice questions.',
                    ], 5),
                ],
            ],
        ];
    }
}
