<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MathWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_or_save_workspace_calculations(): void
    {
        $this->get(route('mathematics.workspace'))
            ->assertRedirect(route('login'));

        $this->post(route('mathematics.workspace.saves.store'), $this->payload())
            ->assertRedirect(route('login'));
    }

    public function test_workspace_only_lists_the_authenticated_users_calculations(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $owner->mathWorkspaceCalculations()->create($this->payload([
            'title' => 'My rectangle',
        ]));
        $otherUser->mathWorkspaceCalculations()->create($this->payload([
            'title' => 'Private cube',
            'topic' => 'cube',
            'state' => $this->state('cube'),
        ]));

        $this->actingAs($owner)
            ->get(route('mathematics.workspace'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mathematics/Workspace')
                ->has('savedCalculations', 1)
                ->where('savedCalculations.0.title', 'My rectangle')
                ->missing('savedCalculations.1'));
    }

    public function test_authenticated_user_can_save_normalized_workspace_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('mathematics.workspace.saves.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('math_workspace_calculations', [
            'user_id' => $user->id,
            'title' => 'Rectangle calculation',
            'topic' => 'rectangle',
        ]);

        $calculation = $user->mathWorkspaceCalculations()->firstOrFail();

        $this->assertSame(8, $calculation->state['calculatedValues'][0]['value']);
        $this->assertSame('rectangle', $calculation->state['selectedTopic']);
    }

    public function test_saved_state_topic_must_match_the_requested_topic(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('mathematics.workspace.saves.store'), $this->payload([
                'topic' => 'cube',
            ]))
            ->assertSessionHasErrors('state.selectedTopic');

        $this->assertDatabaseCount('math_workspace_calculations', 0);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'Rectangle calculation',
            'topic' => 'rectangle',
            'problem_text' => 'A rectangle has area 48 cm² and height 6 cm.',
            'state' => $this->state('rectangle'),
        ], $overrides);
    }

    private function state(string $topic): array
    {
        if ($topic === 'cube') {
            return [
                'version' => 1,
                'selectedTopic' => 'cube',
                'solveFor' => 'volume',
                'userEnteredValues' => [
                    $this->value('side', 'Side', 'a', 3, 1, 'entered', 'cm'),
                ],
                'calculatedValues' => [
                    $this->value('volume', 'Volume', 'V', 27, 3, 'calculated', 'cm³'),
                ],
                'units' => $this->units(),
                'formulas' => [],
                'calculationSteps' => [],
                'warnings' => [],
                'conflicts' => [],
                'graphConfiguration' => null,
                'visualizationConfiguration' => [
                    'kind' => 'geometry-3d',
                    'shape' => 'cube',
                    'values' => ['side' => 3, 'volume' => 27],
                ],
            ];
        }

        return [
            'version' => 1,
            'selectedTopic' => 'rectangle',
            'solveFor' => 'width',
            'userEnteredValues' => [
                $this->value('length', 'Length', 'l', 6, 1, 'entered', 'cm'),
                $this->value('area', 'Area', 'A', 48, 2, 'entered', 'cm²'),
            ],
            'calculatedValues' => [
                $this->value('width', 'Width', 'w', 8, 1, 'calculated', 'cm'),
            ],
            'units' => $this->units(),
            'formulas' => [
                [
                    'key' => 'rectangle-area',
                    'label' => 'Area',
                    'expression' => 'A = l × w',
                    'resultKey' => 'area',
                ],
            ],
            'calculationSteps' => [
                [
                    'id' => 'rectangle-source',
                    'title' => 'Resolve the shape',
                    'formula' => 'Use known measurements',
                    'substitution' => '48 ÷ 6',
                    'result' => '8 cm',
                    'rule' => null,
                ],
            ],
            'warnings' => [],
            'conflicts' => [],
            'graphConfiguration' => null,
            'visualizationConfiguration' => [
                'kind' => 'geometry-2d',
                'shape' => 'rectangle',
                'values' => ['length' => 6, 'width' => 8, 'area' => 48],
            ],
        ];
    }

    private function value(
        string $key,
        string $label,
        string $symbol,
        float|int $value,
        int $dimension,
        string $source,
        string $unit,
    ): array {
        return compact(
            'key',
            'label',
            'symbol',
            'value',
            'dimension',
            'source',
            'unit',
        );
    }

    private function units(): array
    {
        return [
            'length' => 'cm',
            'area' => 'cm²',
            'volume' => 'cm³',
            'angle' => '°',
        ];
    }
}
