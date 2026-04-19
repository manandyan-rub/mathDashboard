<?php

namespace Tests\Feature;

use App\Models\MathLevel;
use App\Models\MathLevelPrompt;
use App\Models\MathLevelTranslation;
use App\Models\MathPath;
use App\Models\MathPathTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MathLevelLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_math_levels_endpoint_returns_translated_path_and_level_content(): void
    {
        $user = User::factory()->create();

        $path = MathPath::create([
            'slug' => 'arithmetic',
            'order' => 1,
            'active' => true,
        ]);

        MathPathTranslation::create([
            'math_path_id' => $path->id,
            'locale' => 'hy',
            'title' => 'Թվաբանություն',
            'description' => 'Գումարում, հանում, բազմապատկում, բաժանում և խառը օրինակներ։',
        ]);

        MathPathTranslation::create([
            'math_path_id' => $path->id,
            'locale' => 'en',
            'title' => 'Arithmetic',
            'description' => 'Addition, subtraction, multiplication, division, and mixed practice.',
        ]);

        $level = MathLevel::create([
            'path_id' => $path->id,
            'slug' => 'addition',
            'difficulty' => 'Beginner',
            'order' => 1,
            'passing_score' => 70,
            'xp_reward' => 50,
            'time_limit' => null,
            'active' => true,
        ]);

        MathLevelTranslation::create([
            'math_level_id' => $level->id,
            'locale' => 'hy',
            'title' => 'Գումարում',
            'description' => 'Գումարում մակարդակ',
            'topic' => 'Գումարում',
        ]);

        MathLevelTranslation::create([
            'math_level_id' => $level->id,
            'locale' => 'en',
            'title' => 'Addition',
            'description' => 'Addition level',
            'topic' => 'Addition',
        ]);

        MathLevelPrompt::create([
            'level_id' => $level->id,
            'locale' => 'en',
            'prompt' => 'Generate easy addition multiple-choice questions.',
            'question_count' => 10,
            'options_count' => 4,
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Locale', 'en')
            ->getJson('/api/math-levels');

        $response->assertOk()
            ->assertJsonPath('paths.0.title', 'Arithmetic')
            ->assertJsonPath('paths.0.description', 'Addition, subtraction, multiplication, division, and mixed practice.')
            ->assertJsonPath('paths.0.levels.0.title', 'Addition')
            ->assertJsonPath('paths.0.levels.0.topic', 'Addition');
    }
}
