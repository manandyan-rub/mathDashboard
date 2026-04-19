<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MathLevelAnswerRequest;
use App\Http\Requests\MathLevelFinishRequest;
use App\Http\Requests\MathLevelStartRequest;
use App\Models\MathLevel;
use App\Models\MathLevelAttempt;
use App\Models\MathLevelAttemptAnswer;
use App\Models\MathLevelAttemptQuestion;
use App\Models\MathLevelAttemptQuestionOption;
use App\Models\MathPath;
use App\Models\MathUserLevelProgress;
use App\Models\MathUserProfile;
use App\Services\MathLevelGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MathLevelController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => __('Unauthorized')], 401);
        }

        $locales = $this->preferredLocales();

        $paths = MathPath::where('active', true)
            ->with([
                'translations' => function ($query) use ($locales) {
                    $query->whereIn('locale', $locales);
                },
                'levels' => function ($query) use ($locales) {
                    $query->where('active', true)
                        ->orderBy('order')
                        ->with(['translations' => function ($translationQuery) use ($locales) {
                            $translationQuery->whereIn('locale', $locales);
                        }]);
                },
            ])
            ->orderBy('order')
            ->get();

        $levelIds = $paths->flatMap(fn ($path) => $path->levels->pluck('id'))->values();

        $progress = MathUserLevelProgress::where('user_id', $user->id)
            ->whereIn('level_id', $levelIds)
            ->get()
            ->keyBy('level_id');

        $toInsert = [];
        foreach ($levelIds as $levelId) {
            if (!$progress->has($levelId)) {
                $toInsert[] = [
                    'user_id' => $user->id,
                    'level_id' => $levelId,
                    'unlocked' => false,
                    'completed' => false,
                    'best_score' => 0,
                    'best_stars' => 0,
                    'total_attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if (!empty($toInsert)) {
            MathUserLevelProgress::insert($toInsert);
            $progress = MathUserLevelProgress::where('user_id', $user->id)
                ->whereIn('level_id', $levelIds)
                ->get()
                ->keyBy('level_id');
        }

        foreach ($paths as $path) {
            $firstLevel = $path->levels->sortBy('order')->first();
            if ($firstLevel) {
                $entry = $progress->get($firstLevel->id);
                if ($entry && !$entry->unlocked) {
                    $entry->update(['unlocked' => true]);
                }
            }
        }

        $profile = MathUserProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'total_xp' => 0,
                'current_rank' => 'Beginner',
                'streak_days' => 0,
                'completed_levels' => 0,
                'total_stars' => 0,
            ]
        );

        return response()->json([
            'profile' => [
                'total_xp' => $profile->total_xp,
                'current_rank' => $profile->current_rank,
                'completed_levels' => $profile->completed_levels,
                'total_stars' => $profile->total_stars,
            ],
            'paths' => $paths->map(fn (MathPath $path) => $this->transformPath($path, $progress, $locales))->values(),
        ]);
    }

    public function leaderboard(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => __('Unauthorized')], 401);
        }

        $limit = max(10, min(200, (int) $request->integer('limit', 50)));

        $profiles = MathUserProfile::with('user:id,name,email')
            ->orderByDesc('total_xp')
            ->orderByDesc('total_stars')
            ->limit($limit)
            ->get();

        $leaderboard = $profiles->values()->map(function (MathUserProfile $profile, int $index) {
            $name = trim((string) optional($profile->user)->name);
            $email = trim((string) optional($profile->user)->email);
            $handleBase = $name !== '' ? $name : ($email !== '' ? strstr($email, '@', true) ?: $email : 'User');
            $handle = '@' . strtolower(preg_replace('/[^a-z0-9_]+/i', '', $handleBase) ?: ('user' . $profile->user_id));
            $avatar = mb_strtoupper(mb_substr($handleBase, 0, 1) ?: 'U');
            $rank = $index + 1;

            return [
                'user_id' => $profile->user_id,
                'rank' => $rank,
                'name' => $handleBase !== '' ? $handleBase : ('User #' . $profile->user_id),
                'handle' => $handle,
                'avatar' => $avatar,
                'level' => max(1, (int) floor(((int) $profile->total_xp) / 150) + 1),
                'xp' => (int) $profile->total_xp,
                'trend' => $this->trendForUser((int) $profile->user_id),
            ];
        });

        $currentProfile = MathUserProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['total_xp' => 0, 'current_rank' => 'Beginner', 'streak_days' => 0, 'completed_levels' => 0, 'total_stars' => 0]
        );

        $currentRank = (int) MathUserProfile::query()
                ->where(function ($q) use ($currentProfile) {
                    $q->where('total_xp', '>', (int) $currentProfile->total_xp)
                        ->orWhere(function ($q2) use ($currentProfile) {
                            $q2->where('total_xp', '=', (int) $currentProfile->total_xp)
                                ->where('total_stars', '>', (int) $currentProfile->total_stars);
                        });
                })
                ->count() + 1;

        $currentEntry = $leaderboard->firstWhere('user_id', $user->id);
        if (!$currentEntry) {
            $name = trim((string) $user->name);
            $email = trim((string) $user->email);
            $handleBase = $name !== '' ? $name : ($email !== '' ? strstr($email, '@', true) ?: $email : 'User');
            $currentEntry = [
                'user_id' => $user->id,
                'rank' => $currentRank,
                'name' => $handleBase !== '' ? $handleBase : ('User #' . $user->id),
                'handle' => '@' . strtolower(preg_replace('/[^a-z0-9_]+/i', '', $handleBase) ?: ('user' . $user->id)),
                'avatar' => mb_strtoupper(mb_substr($handleBase, 0, 1) ?: 'U'),
                'level' => max(1, (int) floor(((int) $currentProfile->total_xp) / 150) + 1),
                'xp' => (int) $currentProfile->total_xp,
                'trend' => $this->trendForUser((int) $user->id),
            ];
        }

        return response()->json([
            'season' => [
                'name' => 'Season 1',
                'ends_at' => now()->endOfMonth()->toIso8601String(),
            ],
            'leaderboard' => $leaderboard->values(),
            'current_user' => $currentEntry,
        ]);
    }

    public function start(MathLevelStartRequest $request, MathLevelGeneratorService $generator)
    {
        $user = $request->user();
        $locales = $this->preferredLocales();
        $level = MathLevel::where('active', true)
            ->with([
                'translations' => function ($query) use ($locales) {
                    $query->whereIn('locale', $locales);
                },
                'prompts' => function ($query) use ($locales) {
                    $query->whereIn('locale', $locales);
                },
            ])
            ->findOrFail($request->integer('level_id'));

        $progress = MathUserLevelProgress::firstOrCreate(
            ['user_id' => $user->id, 'level_id' => $level->id],
            ['unlocked' => false]
        );

        if (!$progress->unlocked) {
            return response()->json([
                'message' => __('This level is locked.'),
            ], 403);
        }

        $prompt = $this->resolvePrompt($level, $locales);

        if (!$prompt) {
            return response()->json([
                'message' => __('Prompt for this level was not found.'),
            ], 422);
        }

        $attempt = MathLevelAttempt::create([
            'user_id' => $user->id,
            'level_id' => $level->id,
            'started_at' => now(),
        ]);

        try {
            $generated = $generator->generate($prompt);
        } catch (\Throwable $e) {
            $attempt->delete();
            $message = $e->getMessage();
            if (str_contains($message, 'BUSY')) {
                return response()->json([
                    'busy' => true,
                    'message' => __('The generator is busy. Please try again shortly.'),
                ], 200);
            }
            return response()->json([
                'message' => __('Failed to generate questions. Please try again.'),
                'details' => $e->getMessage(),
            ], 502);
        }

        DB::transaction(function () use ($attempt, $generated) {
            foreach ($generated as $index => $item) {
                $question = MathLevelAttemptQuestion::create([
                    'attempt_id' => $attempt->id,
                    'question' => $item['question'],
                    'explanation' => $item['explanation'] ?? null,
                    'order' => $index + 1,
                    'points' => 1,
                ]);

                foreach ($item['options'] as $optIndex => $option) {
                    MathLevelAttemptQuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => $option,
                        'is_correct' => $optIndex === (int) $item['correct_index'],
                        'order' => $optIndex + 1,
                    ]);
                }
            }
        });

        return response()->json([
            'attempt_id' => $attempt->id,
        ]);
    }

    public function showAttempt(MathLevelAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        $locales = $this->preferredLocales();

        $attempt->load([
            'level.translations' => function ($query) use ($locales) {
                $query->whereIn('locale', $locales);
            },
            'questions.options',
            'answers',
        ]);

        $answers = $attempt->answers->keyBy('attempt_question_id');
        $levelTitle = $this->translatedLevelTitle($attempt->level, $locales);

        return response()->json([
            'attempt' => [
                'id' => $attempt->id,
                'level_id' => $attempt->level_id,
            ],
            'level' => [
                'id' => $attempt->level->id,
                'title' => $levelTitle,
                'difficulty' => $attempt->level->difficulty,
                'passing_score' => $this->normalizedPassingScore($attempt->level),
                'xp_reward' => $attempt->level->xp_reward,
            ],
            'questions' => $attempt->questions->map(function ($question) use ($answers) {
                $answer = $answers->get($question->id);
                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'options' => $question->options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'text' => $option->option_text,
                        ];
                    })->values(),
                    'selected_option_id' => $answer?->attempt_option_id,
                ];
            })->values(),
        ]);
    }

    public function answer(MathLevelAnswerRequest $request)
    {
        $attempt = MathLevelAttempt::with('questions.options')->findOrFail($request->integer('attempt_id'));
        $this->authorizeAttempt($attempt);

        $questionId = $request->integer('question_id');
        $optionId = $request->input('option_id');

        $question = $attempt->questions->firstWhere('id', $questionId);
        if (!$question) {
            return response()->json(['message' => __('Question not found.')], 404);
        }

        $selectedOption = $optionId
            ? $question->options->firstWhere('id', (int) $optionId)
            : null;

        $isCorrect = $selectedOption?->is_correct ?? false;

        MathLevelAttemptAnswer::updateOrCreate(
            [
                'attempt_id' => $attempt->id,
                'attempt_question_id' => $question->id,
            ],
            [
                'attempt_option_id' => $selectedOption?->id,
                'is_correct' => $isCorrect,
            ]
        );

        return response()->json([
            'is_correct' => $isCorrect,
        ]);
    }

    public function finish(MathLevelFinishRequest $request)
    {
        $attempt = MathLevelAttempt::with(['level', 'questions', 'answers'])->findOrFail($request->integer('attempt_id'));
        $this->authorizeAttempt($attempt);

        if ($attempt->finished_at) {
            return response()->json(['message' => __('The game is already finished.')], 422);
        }

        $total = $attempt->questions->count();
        $correct = $attempt->answers->where('is_correct', true)->count();
        $wrong = max(0, $total - $correct);
        $score = $total > 0 ? (int) round(($correct / $total) * 100) : 0;
        $passingScore = $this->normalizedPassingScore($attempt->level);
        $stars = $this->calculateStars($score, $passingScore);
        $passed = $score >= $passingScore;
        $xpEarned = 0;

        DB::transaction(function () use ($attempt, $score, $correct, $wrong, $stars, $passed, &$xpEarned) {
            $progress = MathUserLevelProgress::firstOrCreate(
                ['user_id' => $attempt->user_id, 'level_id' => $attempt->level_id],
                ['unlocked' => true]
            );

            $previousBestScore = (int) $progress->best_score;
            $wasCompleted = (bool) $progress->completed;

            if ($passed) {
                if (!$wasCompleted) {
                    // First successful pass gives full reward.
                    $xpEarned = (int) $attempt->level->xp_reward;
                } else {
                    // Replays give XP only for improving the best score (anti-farm).
                    $improvement = max(0, $score - $previousBestScore);
                    $xpEarned = (int) floor(($improvement / 100) * (int) $attempt->level->xp_reward);
                }
            }

            $attempt->update([
                'score' => $score,
                'correct_answers' => $correct,
                'wrong_answers' => $wrong,
                'stars' => $stars,
                'passed' => $passed,
                'xp_earned' => $xpEarned,
                'finished_at' => now(),
            ]);

            $progress->update([
                'completed' => $progress->completed || $passed,
                'best_score' => max($progress->best_score, $score),
                'best_stars' => max($progress->best_stars, $stars),
                'total_attempts' => $progress->total_attempts + 1,
                'last_attempted_at' => now(),
            ]);

            if ($passed) {
                $this->unlockNextLevel($attempt->level, $attempt->user_id);
            }

            $profile = MathUserProfile::firstOrCreate(
                ['user_id' => $attempt->user_id],
                ['total_xp' => 0, 'current_rank' => 'Beginner']
            );

            $profile->update([
                'total_xp' => $profile->total_xp + $xpEarned,
                'completed_levels' => $progress->completed ? max($profile->completed_levels, $this->countCompletedLevels($attempt->user_id)) : $profile->completed_levels,
                'total_stars' => $this->countTotalStars($attempt->user_id),
                'current_rank' => $this->rankForXp($profile->total_xp + $xpEarned),
            ]);
        });

        return response()->json([
            'attempt_id' => $attempt->id,
            'score' => $score,
            'correct_answers' => $correct,
            'wrong_answers' => $wrong,
            'stars' => $stars,
            'passed' => $passed,
            'xp_earned' => $xpEarned,
        ]);
    }

    public function result(MathLevelAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        $locales = $this->preferredLocales();

        $attempt->load([
            'level.translations' => function ($query) use ($locales) {
                $query->whereIn('locale', $locales);
            },
            'answers.question.options',
            'answers.selectedOption',
        ]);

        return response()->json([
            'attempt' => [
                'id' => $attempt->id,
                'score' => $attempt->score,
                'stars' => $attempt->stars,
                'passed' => $attempt->passed,
                'xp_earned' => $attempt->xp_earned,
                'correct_answers' => $attempt->correct_answers,
                'wrong_answers' => $attempt->wrong_answers,
                'level_title' => $this->translatedLevelTitle($attempt->level, $locales),
            ],
            'answers' => $attempt->answers->map(function ($answer) {
                $question = $answer->question;
                return [
                    'question_id' => $question->id,
                    'question' => $question->question,
                    'explanation' => $question->explanation,
                    'selected_option_id' => $answer->attempt_option_id,
                    'is_correct' => $answer->is_correct,
                    'options' => $question->options->map(function ($option) use ($answer) {
                        return [
                            'id' => $option->id,
                            'text' => $option->option_text,
                            'is_correct' => $option->is_correct,
                            'is_selected' => $option->id === $answer->attempt_option_id,
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function preferredLocales(): array
    {
        return array_values(array_unique(array_filter([
            app()->getLocale(),
            config('app.fallback_locale', 'en'),
            'en',
            'hy',
        ])));
    }

    /**
     * @param array<int, string> $locales
     * @return array<string, mixed>
     */
    private function transformPath(MathPath $path, $progress, array $locales): array
    {
        $translation = $path->resolveTranslation($locales);

        return [
            'id' => $path->id,
            'title' => $translation?->title ?? $path->slug,
            'description' => $translation?->description,
            'levels' => $path->levels->map(
                fn (MathLevel $level) => $this->transformLevel($level, $progress->get($level->id), $locales)
            )->values(),
        ];
    }

    /**
     * @param array<int, string> $locales
     * @return array<string, mixed>
     */
    private function transformLevel(MathLevel $level, ?MathUserLevelProgress $entry, array $locales): array
    {
        $translation = $level->resolveTranslation($locales);
        $passingScore = $this->normalizedPassingScore($level);

        return [
            'id' => $level->id,
            'title' => $translation?->title ?? $level->slug,
            'topic' => $translation?->topic,
            'description' => $translation?->description,
            'difficulty' => $level->difficulty,
            'order' => $level->order,
            'passing_score' => $passingScore,
            'xp_reward' => $level->xp_reward,
            'unlocked' => $entry?->unlocked ?? false,
            'completed' => $entry?->completed ?? false,
            'best_score' => $entry?->best_score ?? 0,
            'best_stars' => $entry?->best_stars ?? 0,
        ];
    }

    /**
     * @param array<int, string> $locales
     */
    private function translatedLevelTitle(MathLevel $level, array $locales): string
    {
        return $level->resolveTranslation($locales)?->title ?? $level->slug;
    }

    /**
     * @param array<int, string> $locales
     */
    private function resolvePrompt(MathLevel $level, array $locales): ?\App\Models\MathLevelPrompt
    {
        foreach ($locales as $locale) {
            $prompt = $level->prompts->firstWhere('locale', $locale);
            if ($prompt) {
                return $prompt;
            }
        }

        return $level->prompts->first();
    }

    private function calculateStars(int $score, int $passingScore): int
    {
        if ($score >= 95) {
            return 3;
        }
        if ($score >= max(80, $passingScore)) {
            return 2;
        }
        if ($score >= $passingScore) {
            return 1;
        }
        return 0;
    }

    private function unlockNextLevel(MathLevel $level, int $userId): void
    {
        $nextLevel = MathLevel::where('path_id', $level->path_id)
            ->where('order', '>', $level->order)
            ->orderBy('order')
            ->first();

        if (!$nextLevel) {
            return;
        }

        MathUserLevelProgress::updateOrCreate(
            ['user_id' => $userId, 'level_id' => $nextLevel->id],
            ['unlocked' => true]
        )->update(['unlocked' => true]);
    }

    private function countCompletedLevels(int $userId): int
    {
        return MathUserLevelProgress::where('user_id', $userId)
            ->where('completed', true)
            ->count();
    }

    private function countTotalStars(int $userId): int
    {
        return (int) MathUserLevelProgress::where('user_id', $userId)->sum('best_stars');
    }

    private function rankForXp(int $xp): string
    {
        if ($xp >= 5000) return 'Grandmaster';
        if ($xp >= 2500) return 'Master';
        if ($xp >= 1500) return 'Expert';
        if ($xp >= 800) return 'Advanced';
        if ($xp >= 300) return 'Solver';
        if ($xp >= 150) return 'Learner';
        return 'Beginner';
    }

    private function trendForUser(int $userId): string
    {
        $scores = MathLevelAttempt::query()
            ->where('user_id', $userId)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->limit(2)
            ->pluck('score')
            ->values();

        if ($scores->count() < 2) {
            return 'neutral';
        }

        if ($scores[0] > $scores[1]) {
            return 'up';
        }
        if ($scores[0] < $scores[1]) {
            return 'down';
        }

        return 'neutral';
    }

    private function normalizedPassingScore(MathLevel $level): int
    {
        $raw = max(1, (int) $level->passing_score);
        $difficulty = strtolower((string) $level->difficulty);

        if (str_contains($difficulty, 'hard')) {
            return max(80, $raw);
        }

        if (str_contains($difficulty, 'medium')) {
            return max(75, $raw);
        }

        if (str_contains($difficulty, 'beginner') || str_contains($difficulty, 'easy')) {
            return max(70, $raw);
        }

        return $raw;
    }

    private function authorizeAttempt(MathLevelAttempt $attempt): void
    {
        $userId = optional(request()->user())->id;
        if (!$userId || $attempt->user_id !== $userId) {
            abort(403);
        }
    }
}

