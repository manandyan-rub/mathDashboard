<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\MathUserProfile;
use App\Models\MathUserXpEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XpAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_distribution_is_returned_for_global_map(): void
    {
        $country = Country::query()->create([
            'name' => 'Armenia',
            'iso_code' => 'AM',
            'latitude' => 40.069099,
            'longitude' => 45.038189,
        ]);

        $viewer = User::factory()->create([
            'country_id' => $country->id,
        ]);

        $otherUser = User::factory()->create([
            'country_id' => $country->id,
        ]);

        MathUserProfile::query()->create([
            'user_id' => $viewer->id,
            'total_xp' => 120,
            'current_rank' => 'Learner',
            'streak_days' => 2,
            'completed_levels' => 1,
            'total_stars' => 2,
        ]);

        MathUserProfile::query()->create([
            'user_id' => $otherUser->id,
            'total_xp' => 160,
            'current_rank' => 'Learner',
            'streak_days' => 3,
            'completed_levels' => 1,
            'total_stars' => 3,
        ]);

        MathUserXpEvent::query()->create([
            'user_id' => $viewer->id,
            'amount' => 10,
            'source' => 'game',
            'source_label' => 'Game practice',
            'metadata' => ['mode' => 'student'],
            'earned_at' => now()->subDay(),
        ]);

        MathUserXpEvent::query()->create([
            'user_id' => $otherUser->id,
            'amount' => 25,
            'source' => 'math_level',
            'source_label' => 'Math level',
            'metadata' => ['level_slug' => 'addition'],
            'earned_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($viewer)
            ->getJson('/api/dashboard/xp-analytics');

        $response
            ->assertOk()
            ->assertJsonPath('country_distribution.0.name', 'Armenia')
            ->assertJsonPath('country_distribution.0.iso_code', 'AM')
            ->assertJsonPath('country_distribution.0.users', 2)
            ->assertJsonPath('country_distribution.0.claims', 2)
            ->assertJsonPath('country_distribution.0.xp', 35);
    }
}
