<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MathUserProfile;
use Illuminate\Http\Request;

class GameXpController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => __('Unauthorized')], 401);
        }

        $data = $request->validate([
            'game' => 'required|string|in:oneplusone,equilibrium',
            'mode' => 'required|string|in:child,scholastic,student,einstein,easy,middle',
            'count' => 'nullable|integer|min:1|max:20',
        ]);

        $mode = strtolower((string) $data['mode']);
        $count = (int) ($data['count'] ?? 1);

        $xpByMode = [
            'child' => 1,
            'scholastic' => 1,
            'easy' => 1,
            'student' => 2,
            'middle' => 2,
            'einstein' => 5,
        ];

        $xpPerUnit = $xpByMode[$mode] ?? 1;
        $earned = $xpPerUnit * max(1, min(20, $count));

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

        $profile->increment('total_xp', $earned);
        $profile->refresh();
        $newRank = $this->rankForXp((int) $profile->total_xp);

        if ($profile->current_rank !== $newRank) {
            $profile->update(['current_rank' => $newRank]);
        }

        return response()->json([
            'xp_earned' => $earned,
            'total_xp' => (int) $profile->total_xp,
            'current_rank' => $newRank,
        ]);
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

