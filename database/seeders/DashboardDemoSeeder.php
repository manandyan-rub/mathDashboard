<?php

namespace Database\Seeders;

use App\Models\MathLevel;
use App\Models\MathUserLevelProgress;
use App\Models\MathUserProfile;
use App\Models\MathUserXpEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardDemoSeeder extends Seeder
{
    /**
     * Seed realistic dashboard data for analytics demos.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Admin', 'email' => 'admin@example.com', 'completed' => 8, 'modes' => ['student', 'einstein', 'middle']],
            ['name' => 'Ani Sargsyan', 'email' => 'ani.sargsyan@example.com', 'completed' => 6, 'modes' => ['student', 'middle', 'easy']],
            ['name' => 'Narek Petrosyan', 'email' => 'narek.petrosyan@example.com', 'completed' => 5, 'modes' => ['middle', 'student', 'easy']],
            ['name' => 'Mariam Hakobyan', 'email' => 'mariam.hakobyan@example.com', 'completed' => 7, 'modes' => ['student', 'einstein', 'middle']],
            ['name' => 'Aram Grigoryan', 'email' => 'aram.grigoryan@example.com', 'completed' => 3, 'modes' => ['easy', 'child', 'scholastic']],
            ['name' => 'Lilit Karapetyan', 'email' => 'lilit.karapetyan@example.com', 'completed' => 4, 'modes' => ['middle', 'easy', 'student']],
            ['name' => 'Gor Vardanyan', 'email' => 'gor.vardanyan@example.com', 'completed' => 2, 'modes' => ['child', 'easy', 'scholastic']],
            ['name' => 'Sona Avetisyan', 'email' => 'sona.avetisyan@example.com', 'completed' => 9, 'modes' => ['einstein', 'student', 'middle']],
            ['name' => 'Hayk Martirosyan', 'email' => 'hayk.martirosyan@example.com', 'completed' => 5, 'modes' => ['middle', 'student', 'easy']],
            ['name' => 'Eva Hovhannisyan', 'email' => 'eva.hovhannisyan@example.com', 'completed' => 1, 'modes' => ['child', 'scholastic', 'easy']],
            ['name' => 'Tigran Manukyan', 'email' => 'tigran.manukyan@example.com', 'completed' => 6, 'modes' => ['student', 'middle', 'einstein']],
        ];

        $levels = MathLevel::query()
            ->where('active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($users, $levels) {
            $emails = collect($users)->pluck('email')->all();
            $demoUsers = User::query()->whereIn('email', $emails)->get();

            MathUserXpEvent::query()
                ->whereIn('user_id', $demoUsers->pluck('id'))
                ->delete();

            MathUserLevelProgress::query()
                ->whereIn('user_id', $demoUsers->pluck('id'))
                ->delete();

            foreach ($users as $index => $demoUser) {
                $user = User::query()->updateOrCreate(
                    ['email' => $demoUser['email']],
                    [
                        'name' => $demoUser['name'],
                        'password' => Hash::make('password'),
                    ]
                );
                $user->forceFill(['email_verified_at' => now()])->save();

                $completedLevelCount = min((int) $demoUser['completed'], $levels->count());
                $levelXp = $this->seedLevelProgress($user, $levels, $completedLevelCount, $index);
                $gameXp = $this->seedGameXpEvents($user, $demoUser['modes'], $index);
                $totalXp = $levelXp + $gameXp;

                MathUserProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'total_xp' => $totalXp,
                        'current_rank' => $this->rankForXp($totalXp),
                        'streak_days' => max(1, min(14, $completedLevelCount + ($index % 5))),
                        'completed_levels' => $completedLevelCount,
                        'total_stars' => $this->totalStarsFor($completedLevelCount, $index),
                    ]
                );
            }
        });
    }

    private function seedLevelProgress(User $user, $levels, int $completedLevelCount, int $userIndex): int
    {
        $xp = 0;

        foreach ($levels->take($completedLevelCount) as $levelIndex => $level) {
            $stars = $this->starsFor($levelIndex, $userIndex);
            $score = min(100, 72 + ($stars * 7) + (($userIndex + $levelIndex) % 6));
            $earnedAt = Carbon::now()
                ->subDays(13 - (($levelIndex + $userIndex) % 14))
                ->setTime(10 + ($levelIndex % 8), 15 + (($userIndex * 7) % 40));

            MathUserLevelProgress::query()->updateOrCreate(
                ['user_id' => $user->id, 'level_id' => $level->id],
                [
                    'unlocked' => true,
                    'completed' => true,
                    'best_score' => $score,
                    'best_stars' => $stars,
                    'total_attempts' => 1 + (($userIndex + $levelIndex) % 3),
                    'last_attempted_at' => $earnedAt,
                ]
            );

            $amount = (int) max(10, $level->xp_reward);
            $xp += $amount;

            MathUserXpEvent::query()->create([
                'user_id' => $user->id,
                'amount' => $amount,
                'source' => 'math_level',
                'source_label' => 'Math level',
                'reference_type' => 'demo_math_level',
                'reference_id' => $level->id,
                'metadata' => [
                    'level_id' => $level->id,
                    'level_slug' => $level->slug,
                    'score' => $score,
                    'stars' => $stars,
                    'passed' => true,
                ],
                'earned_at' => $earnedAt,
            ]);
        }

        if ($levels->count() > $completedLevelCount) {
            $nextLevel = $levels->get($completedLevelCount);
            MathUserLevelProgress::query()->updateOrCreate(
                ['user_id' => $user->id, 'level_id' => $nextLevel->id],
                [
                    'unlocked' => true,
                    'completed' => false,
                    'best_score' => 0,
                    'best_stars' => 0,
                    'total_attempts' => 0,
                    'last_attempted_at' => null,
                ]
            );
        }

        return $xp;
    }

    /**
     * @param array<int, string> $modes
     */
    private function seedGameXpEvents(User $user, array $modes, int $userIndex): int
    {
        $xp = 0;
        $games = ['oneplusone', 'equilibrium'];
        $xpByMode = [
            'child' => 1,
            'scholastic' => 1,
            'easy' => 1,
            'student' => 2,
            'middle' => 2,
            'einstein' => 5,
        ];

        foreach (range(0, 11) as $eventIndex) {
            $mode = $modes[$eventIndex % count($modes)];
            $game = $games[($eventIndex + $userIndex) % count($games)];
            $count = 2 + (($eventIndex + $userIndex) % 5);
            $amount = ($xpByMode[$mode] ?? 1) * $count;
            $earnedAt = Carbon::now()
                ->subDays(($eventIndex + ($userIndex * 2)) % 14)
                ->setTime(14 + ($eventIndex % 6), 5 + (($userIndex * 9 + $eventIndex) % 50));

            $xp += $amount;

            MathUserXpEvent::query()->create([
                'user_id' => $user->id,
                'amount' => $amount,
                'source' => 'game',
                'source_label' => 'Game practice',
                'reference_type' => 'demo_game',
                'metadata' => [
                    'game' => $game,
                    'mode' => $mode,
                    'count' => $count,
                    'xp_per_unit' => $xpByMode[$mode] ?? 1,
                ],
                'earned_at' => $earnedAt,
            ]);
        }

        return $xp;
    }

    private function totalStarsFor(int $completedLevelCount, int $userIndex): int
    {
        return collect(range(0, max(0, $completedLevelCount - 1)))
            ->sum(fn (int $levelIndex) => $this->starsFor($levelIndex, $userIndex));
    }

    private function starsFor(int $levelIndex, int $userIndex): int
    {
        return 1 + (($levelIndex + $userIndex) % 3);
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
}
