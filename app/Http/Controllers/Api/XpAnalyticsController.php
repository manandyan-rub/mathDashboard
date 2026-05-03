<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MathUserLevelProgress;
use App\Models\MathUserProfile;
use App\Models\MathUserXpEvent;
use Illuminate\Http\Request;

class XpAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => __('Unauthorized')], 401);
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

        $from = now()->subDays(13)->startOfDay();
        $periodEvents = MathUserXpEvent::query()
            ->where('user_id', $user->id)
            ->where('earned_at', '>=', $from)
            ->orderBy('earned_at')
            ->get();

        $dailyEvents = $periodEvents
            ->groupBy(fn (MathUserXpEvent $event) => $event->earned_at?->toDateString());

        $daily = collect(range(13, 0))->map(function (int $daysAgo) use ($dailyEvents) {
            $date = now()->subDays($daysAgo);
            $key = $date->toDateString();
            $events = $dailyEvents->get($key, collect());

            return [
                'date' => $key,
                'label' => $date->format('M j'),
                'xp' => (int) $events->sum('amount'),
                'claims' => $events->count(),
            ];
        })->values();

        $sources = MathUserXpEvent::query()
            ->where('user_id', $user->id)
            ->selectRaw('source, source_label, SUM(amount) as xp, COUNT(*) as claims')
            ->groupBy('source', 'source_label')
            ->orderByDesc('xp')
            ->get()
            ->map(fn (MathUserXpEvent $event) => [
                'source' => $event->source,
                'label' => $event->source_label ?: str_replace('_', ' ', $event->source),
                'xp' => (int) $event->xp,
                'claims' => (int) $event->claims,
            ])
            ->values();

        $recent = MathUserXpEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('earned_at')
            ->limit(8)
            ->get()
            ->map(fn (MathUserXpEvent $event) => [
                'id' => $event->id,
                'amount' => $event->amount,
                'source' => $event->source,
                'label' => $event->source_label ?: str_replace('_', ' ', $event->source),
                'earned_at' => optional($event->earned_at)->toIso8601String(),
                'metadata' => $event->metadata ?? [],
            ]);

        $popularModes = MathUserXpEvent::query()
            ->where('source', 'game')
            ->get()
            ->groupBy(fn (MathUserXpEvent $event) => (string) data_get($event->metadata, 'mode', 'unknown'))
            ->map(fn ($events, string $mode) => [
                'mode' => $mode,
                'label' => ucwords(str_replace(['_', '-'], ' ', $mode)),
                'xp' => (int) $events->sum('amount'),
                'claims' => $events->count(),
            ])
            ->sortByDesc('claims')
            ->values();

        $userLevelDistribution = MathUserProfile::query()
            ->get()
            ->groupBy(fn (MathUserProfile $profile) => max(1, (int) floor(((int) $profile->total_xp) / 150) + 1))
            ->map(fn ($profiles, int $level) => [
                'level' => $level,
                'label' => 'Level ' . $level,
                'users' => $profiles->count(),
                'total_xp' => (int) $profiles->sum('total_xp'),
            ])
            ->sortBy('level')
            ->values();

        $completedLevelDistribution = MathUserLevelProgress::query()
            ->where('completed', true)
            ->with(['level.translations'])
            ->get()
            ->groupBy('level_id')
            ->map(function ($progressRows) {
                $level = $progressRows->first()?->level;
                $translation = $level?->resolveTranslation($this->preferredLocales());

                return [
                    'level_id' => $level?->id,
                    'label' => $translation?->title ?? $level?->slug ?? 'Level',
                    'users' => $progressRows->pluck('user_id')->unique()->count(),
                    'order' => (int) ($level?->order ?? 0),
                ];
            })
            ->sortBy('order')
            ->values();

        return response()->json([
            'profile' => [
                'total_xp' => (int) $profile->total_xp,
                'current_rank' => $profile->current_rank,
                'completed_levels' => (int) $profile->completed_levels,
                'total_stars' => (int) $profile->total_stars,
            ],
            'summary' => [
                'period_xp' => (int) $daily->sum('xp'),
                'period_claims' => (int) $daily->sum('claims'),
                'best_day_xp' => (int) $daily->max('xp'),
            ],
            'daily' => $daily,
            'sources' => $sources,
            'popular_modes' => $popularModes,
            'user_level_distribution' => $userLevelDistribution,
            'completed_level_distribution' => $completedLevelDistribution,
            'activity_heatmap' => $this->activityHeatmap($periodEvents),
            'recent' => $recent,
        ]);
    }

    private function activityHeatmap($events): array
    {
        $hours = [
            '12a', '1a', '2a', '3a', '4a', '5a', '6a',
            '7a', '8a', '9a', '10a', '11a',
            '12p', '1p', '2p', '3p', '4p', '5p',
            '6p', '7p', '8p', '9p', '10p', '11p',
        ];
        $days = [
            'Saturday', 'Friday', 'Thursday',
            'Wednesday', 'Tuesday', 'Monday', 'Sunday',
        ];
        $dayIndexes = array_flip($days);
        $matrix = [];

        foreach ($days as $dayIndex => $day) {
            for ($hourIndex = 0; $hourIndex < 24; $hourIndex++) {
                $matrix[$dayIndex][$hourIndex] = 0;
            }
        }

        foreach ($events as $event) {
            $earnedAt = $event->earned_at;
            if (!$earnedAt) {
                continue;
            }

            $dayIndex = $dayIndexes[$earnedAt->format('l')] ?? null;
            if ($dayIndex === null) {
                continue;
            }

            $hourIndex = (int) $earnedAt->format('G');
            $matrix[$dayIndex][$hourIndex]++;
        }

        $data = [];
        $max = 0;
        foreach ($days as $dayIndex => $day) {
            for ($hourIndex = 0; $hourIndex < 24; $hourIndex++) {
                $value = $matrix[$dayIndex][$hourIndex];
                $max = max($max, $value);
                $data[] = [$hourIndex, $dayIndex, $value];
            }
        }

        return [
            'hours' => $hours,
            'days' => $days,
            'data' => $data,
            'max' => $max,
        ];
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
}
