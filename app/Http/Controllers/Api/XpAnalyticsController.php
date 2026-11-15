<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MathUserLevelProgress;
use App\Models\MathUserProfile;
use App\Models\MathUserXpEvent;
use App\Support\TenantVisibility;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class XpAnalyticsController extends Controller
{
    public function __construct(private readonly TenantVisibility $tenantVisibility) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => __('Unauthorized')], 401);
        }

        $this->tenantVisibility->assertConfigured($user);

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

        [$from, $to] = $this->resolveDateRange($request);

        $periodEvents = MathUserXpEvent::query()
            ->where('user_id', $user->id)
            ->whereBetween('earned_at', [$from, $to])
            ->orderBy('earned_at')
            ->get();

        $dailyEvents = $periodEvents
            ->groupBy(fn (MathUserXpEvent $event) => $event->earned_at?->toDateString());

        $daily = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function (Carbon $date) use ($dailyEvents) {
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
            ->whereBetween('earned_at', [$from, $to])
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
            ->whereBetween('earned_at', [$from, $to])
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
            ->whereIn('math_user_xp_events.user_id', $this->tenantVisibility->visibleUserIds($user))
            ->where('source', 'game')
            ->whereBetween('earned_at', [$from, $to])
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

        $countryDistribution = MathUserXpEvent::query()
            ->join('users', 'math_user_xp_events.user_id', '=', 'users.id')
            ->join('countries', 'users.country_id', '=', 'countries.id')
            ->whereIn('math_user_xp_events.user_id', $this->tenantVisibility->visibleUserIds($user))
            ->whereBetween('math_user_xp_events.earned_at', [$from, $to])
            ->selectRaw('
                countries.id as country_id,
                countries.name,
                countries.iso_code,
                countries.latitude,
                countries.longitude,
                COUNT(DISTINCT math_user_xp_events.user_id) as learners_count,
                COUNT(*) as claims_count,
                SUM(math_user_xp_events.amount) as xp_total
            ')
            ->groupBy(
                'countries.id',
                'countries.name',
                'countries.iso_code',
                'countries.latitude',
                'countries.longitude'
            )
            ->get()
            ->map(fn ($row) => [
                'country_id' => (int) $row->country_id,
                'name' => (string) $row->name,
                'iso_code' => (string) $row->iso_code,
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'users' => (int) $row->learners_count,
                'claims' => (int) $row->claims_count,
                'xp' => (int) $row->xp_total,
            ])
            ->sortByDesc('users')
            ->values();

        $userLevelDistribution = MathUserProfile::query()
            ->whereIn('math_user_profiles.user_id', $this->tenantVisibility->visibleUserIds($user))
            ->get()
            ->groupBy(fn (MathUserProfile $profile) => max(1, (int) floor(((int) $profile->total_xp) / 150) + 1))
            ->map(fn ($profiles, int $level) => [
                'level' => $level,
                'label' => 'Level '.$level,
                'users' => $profiles->count(),
                'total_xp' => (int) $profiles->sum('total_xp'),
            ])
            ->sortBy('level')
            ->values();

        $completedLevelDistribution = MathUserLevelProgress::query()
            ->whereIn('math_user_level_progress.user_id', $this->tenantVisibility->visibleUserIds($user))
            ->where('completed', true)
            ->whereBetween('last_attempted_at', [$from, $to])
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
                'best_day_xp' => (int) ($daily->max('xp') ?? 0),
            ],
            'period' => [
                'start' => $from->toDateString(),
                'end' => $to->toDateString(),
            ],
            'daily' => $daily,
            'sources' => $sources,
            'popular_modes' => $popularModes,
            'country_distribution' => $countryDistribution,
            'user_level_distribution' => $userLevelDistribution,
            'completed_level_distribution' => $completedLevelDistribution,
            'activity_heatmap' => $this->activityHeatmap($periodEvents),
            'recent' => $recent,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
        ]);

        $start = $validated['start'] ?? null;
        $end = $validated['end'] ?? null;

        if (! $start && ! $end) {
            return [
                now()->subDays(13)->startOfDay(),
                now()->endOfDay(),
            ];
        }

        $from = Carbon::parse($start ?? $end)->startOfDay();
        $to = Carbon::parse($end ?? $start)->endOfDay();

        if ($from->gt($to)) {
            return [
                $to->copy()->startOfDay(),
                $from->copy()->endOfDay(),
            ];
        }

        return [$from, $to];
    }

    private function activityHeatmap($events): array
    {
        $hours = [
            '12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM', '6 AM',
            '7 AM', '8 AM', '9 AM', '10 AM', '11 AM',
            '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM',
            '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM',
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
            if (! $earnedAt) {
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
