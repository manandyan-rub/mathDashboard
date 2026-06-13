<?php

namespace Tests\Feature;

use App\Models\ExerciseDocument;
use App\Models\ExerciseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherExerciseStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_cannot_use_teacher_editor_routes(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($student)
            ->get('/teacher/exercises/studio')
            ->assertForbidden();

        $this->actingAs($student)
            ->postJson('/api/teacher/exercises/save-draft', $this->draftPayload())
            ->assertForbidden();

        $this->actingAs($student)
            ->post('/api/teacher/exercises/images', [
                'image' => $this->fakePngUpload('graph.png'),
            ])
            ->assertForbidden();
    }

    public function test_lecturer_can_upload_an_image_for_the_editor(): void
    {
        Storage::fake('public');
        $lecturer = User::factory()->create(['role' => User::ROLE_LECTURER]);

        $response = $this->actingAs($lecturer)
            ->post('/api/teacher/exercises/images', [
                'image' => $this->fakePngUpload('triangle.png'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'triangle.png')
            ->assertJsonStructure(['url', 'path', 'name']);

        Storage::disk('public')->assertExists($response->json('path'));
        $this->assertStringStartsWith('/storage/teacher-exercises/', $response->json('url'));
    }

    public function test_teacher_image_upload_rejects_non_images(): void
    {
        Storage::fake('public');
        $lecturer = User::factory()->create(['role' => User::ROLE_LECTURER]);

        $this->actingAs($lecturer)
            ->post('/api/teacher/exercises/images', [
                'image' => UploadedFile::fake()->create('notes.txt', 2, 'text/plain'),
            ])
            ->assertSessionHasErrors('image');
    }

    public function test_lecturer_can_save_a_rich_editor_draft_with_items(): void
    {
        $lecturer = User::factory()->create(['role' => User::ROLE_LECTURER]);

        $response = $this->actingAs($lecturer)
            ->postJson('/api/teacher/exercises/save-draft', $this->draftPayload());

        $response->assertOk()
            ->assertJsonPath('document.title', 'Coordinate Geometry Warmup')
            ->assertJsonPath('document.status', 'draft')
            ->assertJsonPath('document.items.0.answer', '<strong>4</strong>');

        $this->assertDatabaseHas('exercise_documents', [
            'user_id' => $lecturer->id,
            'title' => 'Coordinate Geometry Warmup',
            'status' => 'draft',
            'language' => 'en',
        ]);

        $document = ExerciseDocument::query()->firstOrFail();

        $this->assertStringContainsString('data-editor-image-frame', (string) $document->content_html);
        $this->assertDatabaseHas('exercise_items', [
            'document_id' => $document->id,
            'item_order' => 1,
            'type' => 'mcq',
            'answer' => '<strong>4</strong>',
            'points' => 3,
            'source' => 'manual',
        ]);
    }

    public function test_lecturer_can_export_pdf_with_latex_and_freehand_drawing(): void
    {
        $lecturer = User::factory()->create(['role' => User::ROLE_LECTURER]);
        $lines = htmlspecialchars(json_encode([
            [
                'id' => 'line-1',
                'path' => 'M 40 50 Q 120 10 210 85',
                'color' => '#4f46e5',
                'size' => 4,
            ],
        ], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');

        $response = $this->actingAs($lecturer)
            ->post('/api/teacher/exercises/pdf', [
                'title' => 'Printable Math Sheet',
                'language' => 'en',
                'content_html' => '<h2>Integral practice</h2><p><span data-editor-math="true" data-latex="\\frac{x^2}{2} + \\sqrt{x}"></span></p><div data-type="freehand-drawing" data-lines="' . $lines . '"></div>',
                'show_answers' => true,
            ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_lecturer_cannot_update_another_teachers_document(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_LECTURER]);
        $otherLecturer = User::factory()->create(['role' => User::ROLE_LECTURER]);
        $document = ExerciseDocument::query()->create([
            'user_id' => $owner->id,
            'title' => 'Original',
            'status' => 'draft',
            'language' => 'en',
        ]);

        $payload = $this->draftPayload([
            'document_id' => $document->id,
            'title' => 'Stolen Draft',
        ]);

        $this->actingAs($otherLecturer)
            ->postJson('/api/teacher/exercises/save-draft', $payload)
            ->assertForbidden();

        $this->assertDatabaseHas('exercise_documents', [
            'id' => $document->id,
            'title' => 'Original',
        ]);
        $this->assertSame(0, ExerciseItem::query()->where('document_id', $document->id)->count());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function draftPayload(array $overrides = []): array
    {
        return [
            ...[
                'title' => 'Coordinate Geometry Warmup',
                'language' => 'en',
                'content_json' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Find the missing coordinate.'],
                            ],
                        ],
                    ],
                ],
                'content_html' => '<h2>Coordinate Geometry Warmup</h2><img src="/storage/teacher-exercises/1/images/graph.png" alt="Graph" data-editor-image-frame="polaroid">',
                'meta' => [
                    'grade' => '9',
                    'difficulty' => 'medium',
                ],
                'items' => [
                    [
                        'item_order' => 1,
                        'type' => 'mcq',
                        'question' => 'What is 2 + 2?',
                        'question_html' => '<p>What is 2 + 2?</p>',
                        'options' => ['3', '4', '5'],
                        'options_html' => ['<span>3</span>', '<span>4</span>', '<span>5</span>'],
                        'answer' => '4',
                        'answer_html' => '<strong>4</strong>',
                        'explanation' => 'Two pairs make four.',
                        'explanation_html' => '<p>Two pairs make four.</p>',
                        'difficulty' => 'easy',
                        'points' => 3,
                        'source' => 'manual',
                    ],
                ],
            ],
            ...$overrides,
        ];
    }

    private function fakePngUpload(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/axp4i0AAAAASUVORK5CYII=',
            true
        );

        return UploadedFile::fake()->createWithContent($name, $png ?: '');
    }
}
