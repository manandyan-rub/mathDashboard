<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\ExerciseDocument;
use App\Models\ExerciseGenerationLog;
use App\Models\ExerciseItem;
use App\Models\Formula;
use App\Models\FormulaTranslation;
use App\Models\MathLevel;
use App\Models\MathLevelAttempt;
use App\Models\MathLevelAttemptQuestion;
use App\Models\MathLevelAttemptQuestionOption;
use App\Models\MathLevelTranslation;
use App\Models\MathPath;
use App\Models\MathPathTranslation;
use App\Models\MathUserLevelProgress;
use App\Models\MathUserProfile;
use App\Models\MathUserXpEvent;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\TeacherExerciseAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantScopeSecurityTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantSequence = 0;

    public function test_org_admin_leaderboard_contains_only_students_from_their_organization(): void
    {
        $orgA = $this->createTenant('org-a');
        $orgB = $this->createTenant('org-b');

        $this->createProfile($orgA['student'], 240);
        $this->createProfile($orgB['student'], 980);

        $response = $this->actingAs($orgA['admin'])
            ->getJson('/api/math-levels/leaderboard');

        $response->assertOk();

        $this->assertSame(
            [$orgA['student']->id],
            collect($response->json('leaderboard'))->pluck('user_id')->all()
        );
    }

    public function test_org_admin_xp_aggregates_exclude_every_other_organization(): void
    {
        $country = Country::query()->create([
            'name' => 'Armenia',
            'iso_code' => 'AM',
            'latitude' => 40.069099,
            'longitude' => 45.038189,
        ]);
        $orgA = $this->createTenant('analytics-a');
        $orgB = $this->createTenant('analytics-b');
        $global = $this->createUnassignedPath('analytics-global');

        $orgA['student']->forceFill(['country_id' => $country->id])->save();
        $orgB['student']->forceFill(['country_id' => $country->id])->save();

        $this->createProfile($orgA['student'], 180);
        $this->createProfile($orgB['student'], 620);
        $this->createXpEvent($orgA['student'], 10, 'student');
        $this->createXpEvent($orgB['student'], 90, 'einstein');

        $this->createCompletedProgress($orgA['student'], $global['level']);
        $this->createCompletedProgress($orgB['student'], $global['level']);

        $response = $this->actingAs($orgA['admin'])
            ->getJson('/api/dashboard/xp-analytics');

        $response->assertOk();

        $this->assertSame(
            ['student'],
            collect($response->json('popular_modes'))->pluck('mode')->all()
        );
        $response
            ->assertJsonPath('country_distribution.0.users', 1)
            ->assertJsonPath('country_distribution.0.claims', 1)
            ->assertJsonPath('country_distribution.0.xp', 10);

        $this->assertSame(
            2,
            collect($response->json('user_level_distribution'))->sum('users'),
            'The visible student and the requesting org admin should be the only visible profiles.'
        );

        $completed = collect($response->json('completed_level_distribution'))
            ->firstWhere('level_id', $global['level']->id);
        $this->assertSame(1, $completed['users'] ?? null);
    }

    public function test_org_admin_sees_global_and_own_org_paths_but_cannot_start_another_org_level(): void
    {
        $orgA = $this->createTenant('content-a');
        $orgB = $this->createTenant('content-b');
        $global = $this->createUnassignedPath('content-global');

        $response = $this->actingAs($orgA['admin'])->getJson('/api/math-levels');

        $response->assertOk();
        $this->assertSame(
            collect([$global['path']->id, $orgA['path']->id])->sort()->values()->all(),
            collect($response->json('paths'))->pluck('id')->sort()->values()->all()
        );

        $response = $this->actingAs($orgA['admin'])
            ->postJson('/api/math-levels/start', ['level_id' => $orgB['level']->id])
            ->assertNotFound();

        $this->assertDatabaseMissing('math_level_attempts', [
            'user_id' => $orgA['admin']->id,
            'level_id' => $orgB['level']->id,
        ]);
        $this->assertDatabaseMissing('math_user_level_progress', [
            'user_id' => $orgA['admin']->id,
            'level_id' => $orgB['level']->id,
        ]);
    }

    public function test_teacher_sees_only_assigned_class_students_and_content(): void
    {
        $tenant = $this->createTenant('teacher-scope');
        $global = $this->createUnassignedPath('teacher-global');
        $otherPath = $this->createPath('teacher-other');
        $otherClass = SchoolClass::query()->create([
            'school_id' => $tenant['school']->id,
            'name' => 'Other Class',
            'academic_year' => '2026-2027',
            'math_path_id' => $otherPath['path']->id,
        ]);
        $otherStudent = $this->createOrganizationUser(
            $tenant['organization'],
            User::ROLE_STUDENT,
            'other-student@teacher-scope.test'
        );
        $otherClass->students()->attach($otherStudent->id, [
            'role_in_class' => 'student',
            'joined_at' => now(),
        ]);

        $this->createProfile($tenant['student'], 300);
        $this->createProfile($otherStudent, 900);

        $leaderboard = $this->actingAs($tenant['teacher'])
            ->getJson('/api/math-levels/leaderboard');

        $leaderboard->assertOk();
        $this->assertSame(
            [$tenant['student']->id],
            collect($leaderboard->json('leaderboard'))->pluck('user_id')->all()
        );

        $content = $this->actingAs($tenant['teacher'])->getJson('/api/math-levels');

        $content->assertOk();
        $this->assertSame(
            collect([$global['path']->id, $tenant['path']->id])->sort()->values()->all(),
            collect($content->json('paths'))->pluck('id')->sort()->values()->all()
        );
    }

    public function test_legacy_user_sees_only_unassigned_content_and_other_legacy_profiles(): void
    {
        $tenant = $this->createTenant('legacy-tenant');
        $global = $this->createUnassignedPath('legacy-global');
        $formula = Formula::query()->create([
            'category' => 'public',
            'formula' => 'a + b = c',
            'example' => '2 + 2 = 4',
        ]);
        FormulaTranslation::query()->create([
            'formula_id' => $formula->id,
            'locale' => 'en',
            'name' => 'Public Addition Formula',
            'description' => 'A formula available to legacy users.',
        ]);
        $legacyViewer = User::factory()->create([
            'name' => 'Legacy Viewer',
            'email' => 'legacy-viewer@example.test',
        ]);
        $legacyPeer = User::factory()->create([
            'name' => 'Legacy Peer',
            'email' => 'legacy-peer@example.test',
        ]);

        $this->createProfile($legacyViewer, 160);
        $this->createProfile($legacyPeer, 120);
        $this->createProfile($tenant['student'], 800);

        $content = $this->actingAs($legacyViewer)->getJson('/api/math-levels');
        $content->assertOk();
        $this->assertSame(
            [$global['path']->id],
            collect($content->json('paths'))->pluck('id')->all()
        );

        $this->actingAs($legacyViewer)
            ->getJson('/api/formulas')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $formula->id,
                'name' => 'Public Addition Formula',
            ]);

        $leaderboard = $this->actingAs($legacyViewer)
            ->getJson('/api/math-levels/leaderboard');
        $leaderboard->assertOk();
        $this->assertSame(
            collect([$legacyViewer->id, $legacyPeer->id])->sort()->values()->all(),
            collect($leaderboard->json('leaderboard'))->pluck('user_id')->sort()->values()->all()
        );
    }

    public function test_org_admin_without_an_organization_is_denied_org_scoped_data(): void
    {
        $global = $this->createUnassignedPath('misconfigured-global');
        $misconfiguredAdmin = User::factory()->create([
            'role' => User::ROLE_ORG_ADMIN,
            'email' => 'misconfigured-org-admin@example.test',
        ]);

        $this->actingAs($misconfiguredAdmin)
            ->getJson('/api/math-levels')
            ->assertForbidden();

        $this->actingAs($misconfiguredAdmin)
            ->getJson('/api/dashboard/xp-analytics')
            ->assertForbidden();

        $this->assertDatabaseMissing('math_user_profiles', [
            'user_id' => $misconfiguredAdmin->id,
        ]);
        $this->assertDatabaseMissing('math_user_level_progress', [
            'user_id' => $misconfiguredAdmin->id,
            'level_id' => $global['level']->id,
        ]);
    }

    public function test_org_admin_cannot_read_or_mutate_another_organizations_attempt(): void
    {
        $orgA = $this->createTenant('attempt-a');
        $orgB = $this->createTenant('attempt-b');
        [$attempt, $question, $option] = $this->createAttempt($orgB['student'], $orgB['level']);

        $this->actingAs($orgA['admin'])
            ->getJson("/api/math-levels/attempts/{$attempt->id}")
            ->assertForbidden();

        $this->actingAs($orgA['admin'])
            ->get("/math-levels/attempts/{$attempt->id}")
            ->assertForbidden();

        $this->actingAs($orgA['admin'])
            ->get("/math-levels/attempts/{$attempt->id}/result")
            ->assertForbidden();

        $this->actingAs($orgA['admin'])
            ->postJson('/api/math-levels/answer', [
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'option_id' => $option->id,
            ])
            ->assertForbidden();

        $this->actingAs($orgA['admin'])
            ->postJson('/api/math-levels/finish', ['attempt_id' => $attempt->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('math_level_attempt_answers', [
            'attempt_id' => $attempt->id,
        ]);
    }

    public function test_org_admin_can_manage_own_org_document_but_not_another_org_document(): void
    {
        $orgA = $this->createTenant('document-a');
        $orgB = $this->createTenant('document-b');
        $documentA = $this->createDocument($orgA['teacher'], 'Org A Document');
        $documentB = $this->createDocument($orgB['teacher'], 'Org B Document');
        $itemB = $this->createExerciseItem($documentB);
        $documentCount = ExerciseDocument::query()->count();
        $generationCount = ExerciseGenerationLog::query()->count();
        $originalItem = $itemB->only(['question', 'answer', 'explanation', 'source']);

        Bus::fake();
        $this->mock(TeacherExerciseAiService::class)->shouldNotReceive('generate');

        $this->actingAs($orgA['admin'])
            ->getJson("/api/teacher/exercises/{$documentA->id}")
            ->assertOk()
            ->assertJsonPath('document.id', $documentA->id);

        $this->actingAs($orgA['admin'])
            ->postJson('/api/teacher/exercises/save-draft', [
                ...$this->draftPayload(),
                'document_id' => $documentA->id,
                'title' => 'Org A Updated',
            ])
            ->assertOk();

        $deniedResponses = [
            $this->actingAs($orgA['admin'])->getJson("/api/teacher/exercises/{$documentB->id}"),
            $this->actingAs($orgA['admin'])->postJson('/api/teacher/exercises/generate', [
                'topic' => 'Blocked generation',
                'difficulty' => 'easy',
                'count' => 1,
                'language' => 'en',
                'document_id' => $documentB->id,
            ]),
            $this->actingAs($orgA['admin'])->postJson('/api/teacher/exercises/save-draft', [
                ...$this->draftPayload(),
                'document_id' => $documentB->id,
                'title' => 'Cross-org overwrite',
            ]),
            $this->actingAs($orgA['admin'])->postJson('/api/teacher/exercises/publish', [
                'document_id' => $documentB->id,
            ]),
            $this->actingAs($orgA['admin'])->post('/api/teacher/exercises/pdf', [
                'document_id' => $documentB->id,
                'show_answers' => true,
            ]),
            $this->actingAs($orgA['admin'])->postJson('/api/teacher/exercises/regenerate-item', [
                'document_id' => $documentB->id,
                'item_id' => $itemB->id,
            ]),
        ];

        foreach ($deniedResponses as $response) {
            $this->assertTenantDenied($response);
        }

        $this->assertDatabaseHas('exercise_documents', [
            'id' => $documentB->id,
            'title' => 'Org B Document',
            'status' => 'draft',
        ]);
        $this->assertSame($documentCount, ExerciseDocument::query()->count());
        $this->assertSame($generationCount, ExerciseGenerationLog::query()->count());
        $this->assertSame(
            $originalItem,
            $itemB->fresh()->only(['question', 'answer', 'explanation', 'source'])
        );
    }

    public function test_org_admin_cannot_read_another_organizations_generation_log(): void
    {
        $orgA = $this->createTenant('log-a');
        $orgB = $this->createTenant('log-b');
        $documentA = $this->createDocument($orgA['teacher'], 'Log A Document');
        $documentB = $this->createDocument($orgB['teacher'], 'Log B Document');
        $logA = $this->createGenerationLog($orgA['teacher'], $documentA);
        $logB = $this->createGenerationLog($orgB['teacher'], $documentB);

        $this->actingAs($orgA['admin'])
            ->getJson("/api/teacher/exercises/generations/{$logA->id}")
            ->assertOk()
            ->assertJsonPath('generation.id', $logA->id);

        $this->assertTenantDenied(
            $this->actingAs($orgA['admin'])
                ->getJson("/api/teacher/exercises/generations/{$logB->id}")
        );
    }

    public function test_teacher_can_assign_documents_only_to_their_assigned_classes(): void
    {
        $orgA = $this->createTenant('class-document-a');
        $orgB = $this->createTenant('class-document-b');

        $teacherClasses = $this->actingAs($orgA['teacher'])
            ->getJson('/api/teacher/exercises/classes')
            ->assertOk();
        $this->assertSame(
            [$orgA['class']->id],
            collect($teacherClasses->json('classes'))->pluck('id')->all()
        );

        $adminClasses = $this->actingAs($orgA['admin'])
            ->getJson('/api/teacher/exercises/classes')
            ->assertOk();
        $this->assertSame(
            [$orgA['class']->id],
            collect($adminClasses->json('classes'))->pluck('id')->all()
        );

        $documentId = $this->actingAs($orgA['teacher'])
            ->postJson('/api/teacher/exercises/save-draft', [
                ...$this->draftPayload(),
                'school_class_id' => $orgA['class']->id,
            ])
            ->assertOk()
            ->assertJsonPath('document.school_class_id', $orgA['class']->id)
            ->json('document.id');

        $this->assertTenantDenied(
            $this->actingAs($orgA['teacher'])
                ->postJson('/api/teacher/exercises/save-draft', [
                    ...$this->draftPayload(),
                    'title' => 'Foreign Class Document',
                    'school_class_id' => $orgB['class']->id,
                ])
        );

        $this->actingAs($orgA['teacher'])
            ->postJson('/api/teacher/exercises/publish', [
                'document_id' => $documentId,
                'school_class_id' => $orgB['class']->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('exercise_documents', [
            'id' => $documentId,
            'school_class_id' => $orgA['class']->id,
            'status' => 'draft',
        ]);
    }

    public function test_platform_admin_retains_unscoped_access(): void
    {
        $orgA = $this->createTenant('platform-a');
        $orgB = $this->createTenant('platform-b');
        $platformAdmin = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'email' => 'platform-admin@example.test',
        ]);
        $this->createProfile($orgA['student'], 200);
        $this->createProfile($orgB['student'], 400);
        $this->createXpEvent($orgA['student'], 10, 'student');
        $this->createXpEvent($orgB['student'], 20, 'einstein');
        $documentB = $this->createDocument($orgB['teacher'], 'Platform Visible Document');

        $leaderboard = $this->actingAs($platformAdmin)
            ->getJson('/api/math-levels/leaderboard');
        $leaderboard->assertOk();
        $this->assertSame(
            collect([$orgA['student']->id, $orgB['student']->id])->sort()->values()->all(),
            collect($leaderboard->json('leaderboard'))->pluck('user_id')->sort()->values()->all()
        );

        $content = $this->actingAs($platformAdmin)->getJson('/api/math-levels');
        $content->assertOk();
        $this->assertSame(
            collect([$orgA['path']->id, $orgB['path']->id])->sort()->values()->all(),
            collect($content->json('paths'))->pluck('id')->sort()->values()->all()
        );

        $analytics = $this->actingAs($platformAdmin)
            ->getJson('/api/dashboard/xp-analytics');
        $analytics->assertOk();
        $this->assertSame(
            collect(['student', 'einstein'])->sort()->values()->all(),
            collect($analytics->json('popular_modes'))->pluck('mode')->sort()->values()->all()
        );

        $this->actingAs($platformAdmin)
            ->getJson("/api/teacher/exercises/{$documentB->id}")
            ->assertOk()
            ->assertJsonPath('document.id', $documentB->id);
    }

    public function test_registration_rejects_role_and_organization_assignment(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Protected Organization',
            'slug' => 'protected-registration-org',
        ]);

        $response = $this->post('/register', [
            'name' => 'Attacker',
            'email' => 'registration-attacker@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => User::ROLE_ORG_ADMIN,
            'organization_id' => $organization->id,
        ]);

        $response->assertSessionHasErrors(['role', 'organization_id']);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'registration-attacker@example.test',
        ]);
    }

    public function test_profile_update_rejects_role_and_organization_assignment(): void
    {
        $orgA = $this->createTenant('profile-a');
        $orgB = $this->createTenant('profile-b');
        $student = $orgA['student'];

        $response = $this->actingAs($student)->patch('/profile', [
            'name' => $student->name,
            'email' => $student->email,
            'role' => User::ROLE_ORG_ADMIN,
            'organization_id' => $orgB['organization']->id,
        ]);

        $response->assertSessionHasErrors(['role', 'organization_id']);
        $student->refresh();
        $this->assertSame(User::ROLE_STUDENT, $student->role);
        $this->assertSame($orgA['organization']->id, $student->organization_id);
    }

    public function test_tenant_context_shares_organization_school_and_class_names(): void
    {
        $tenant = $this->createTenant('shared-context');

        $this->actingAs($tenant['student'])
            ->get('/dashboard/local')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.tenant.organization.id', $tenant['organization']->id)
                ->where('auth.tenant.organization.name', $tenant['organization']->name)
                ->where('auth.tenant.memberships.0.school_name', $tenant['school']->name)
                ->where('auth.tenant.memberships.0.class_name', $tenant['class']->name)
                ->where('auth.tenant.memberships.0.role_in_class', 'student'));

        $legacyUser = User::factory()->create();

        $this->actingAs($legacyUser)
            ->get('/dashboard/local')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.tenant', null));
    }

    /**
     * @return array{
     *     organization: Organization,
     *     school: School,
     *     class: SchoolClass,
     *     path: MathPath,
     *     level: MathLevel,
     *     admin: User,
     *     teacher: User,
     *     student: User
     * }
     */
    private function createTenant(string $key): array
    {
        $sequence = ++$this->tenantSequence;
        $organization = new Organization;
        $organization->forceFill([
            'id' => 1000 + ($sequence * 100),
            'name' => strtoupper($key),
            'slug' => $key,
        ])->save();
        $organization->refresh();

        $school = new School;
        $school->forceFill([
            'id' => 2000 + ($sequence * 100),
            'organization_id' => $organization->id,
            'name' => 'Main School',
        ])->save();
        $school->refresh();

        $content = $this->createPath("{$key}-path");
        $class = new SchoolClass;
        $class->forceFill([
            'id' => 3000 + ($sequence * 100),
            'school_id' => $school->id,
            'name' => 'Class 1',
            'academic_year' => '2026-2027',
            'math_path_id' => $content['path']->id,
        ])->save();
        $class->refresh();

        $this->assertNotSame($organization->id, $school->id);
        $this->assertNotSame($organization->id, $class->id);
        $this->assertNotSame($school->id, $class->id);
        $this->assertNotSame($class->id, $content['path']->id);
        $admin = $this->createOrganizationUser(
            $organization,
            User::ROLE_ORG_ADMIN,
            "admin@{$key}.test"
        );
        $teacher = $this->createOrganizationUser(
            $organization,
            User::ROLE_LECTURER,
            "teacher@{$key}.test"
        );
        $student = $this->createOrganizationUser(
            $organization,
            User::ROLE_STUDENT,
            "student@{$key}.test"
        );

        $class->teachers()->attach($teacher->id, [
            'role_in_class' => 'teacher',
            'joined_at' => now(),
        ]);
        $class->students()->attach($student->id, [
            'role_in_class' => 'student',
            'joined_at' => now(),
        ]);

        return [
            'organization' => $organization,
            'school' => $school,
            'class' => $class,
            'path' => $content['path'],
            'level' => $content['level'],
            'admin' => $admin,
            'teacher' => $teacher,
            'student' => $student,
        ];
    }

    private function createOrganizationUser(
        Organization $organization,
        string $role,
        string $email
    ): User {
        $user = User::factory()->create([
            'role' => $role,
            'email' => $email,
        ]);

        $user->forceFill(['organization_id' => $organization->id])->save();

        return $user->refresh();
    }

    /**
     * @return array{path: MathPath, level: MathLevel}
     */
    private function createPath(string $key): array
    {
        $path = MathPath::query()->create([
            'slug' => $key,
            'order' => 1,
            'active' => true,
        ]);
        MathPathTranslation::query()->create([
            'math_path_id' => $path->id,
            'locale' => 'en',
            'title' => strtoupper($key),
            'description' => "{$key} description",
        ]);
        $level = MathLevel::query()->create([
            'path_id' => $path->id,
            'slug' => "{$key}-level",
            'difficulty' => 'easy',
            'order' => 1,
            'passing_score' => 70,
            'xp_reward' => 50,
            'active' => true,
        ]);
        MathLevelTranslation::query()->create([
            'math_level_id' => $level->id,
            'locale' => 'en',
            'title' => strtoupper("{$key}-level"),
            'description' => "{$key} level description",
            'topic' => $key,
        ]);

        return ['path' => $path, 'level' => $level];
    }

    /**
     * @return array{path: MathPath, level: MathLevel}
     */
    private function createUnassignedPath(string $key): array
    {
        return $this->createPath($key);
    }

    private function createProfile(User $user, int $xp): MathUserProfile
    {
        return MathUserProfile::query()->create([
            'user_id' => $user->id,
            'total_xp' => $xp,
            'current_rank' => 'Learner',
            'streak_days' => 1,
            'completed_levels' => 0,
            'total_stars' => 0,
        ]);
    }

    private function createXpEvent(User $user, int $amount, string $mode): MathUserXpEvent
    {
        return MathUserXpEvent::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'source' => 'game',
            'source_label' => 'Game practice',
            'metadata' => ['mode' => $mode],
            'earned_at' => now(),
        ]);
    }

    private function createCompletedProgress(User $user, MathLevel $level): MathUserLevelProgress
    {
        return MathUserLevelProgress::query()->create([
            'user_id' => $user->id,
            'level_id' => $level->id,
            'unlocked' => true,
            'completed' => true,
            'best_score' => 90,
            'best_stars' => 2,
            'total_attempts' => 1,
            'last_attempted_at' => now(),
        ]);
    }

    /**
     * @return array{MathLevelAttempt, MathLevelAttemptQuestion, MathLevelAttemptQuestionOption}
     */
    private function createAttempt(User $user, MathLevel $level): array
    {
        $attempt = MathLevelAttempt::query()->create([
            'user_id' => $user->id,
            'level_id' => $level->id,
            'started_at' => now(),
        ]);
        $question = MathLevelAttemptQuestion::query()->create([
            'attempt_id' => $attempt->id,
            'question' => 'What is 2 + 2?',
            'order' => 1,
            'points' => 1,
        ]);
        $option = MathLevelAttemptQuestionOption::query()->create([
            'question_id' => $question->id,
            'option_text' => '4',
            'is_correct' => true,
            'order' => 1,
        ]);

        return [$attempt, $question, $option];
    }

    private function createDocument(User $author, string $title): ExerciseDocument
    {
        return ExerciseDocument::query()->create([
            'user_id' => $author->id,
            'title' => $title,
            'status' => 'draft',
            'language' => 'en',
        ]);
    }

    private function createExerciseItem(ExerciseDocument $document): ExerciseItem
    {
        return ExerciseItem::query()->create([
            'document_id' => $document->id,
            'item_order' => 1,
            'type' => 'mcq',
            'question' => 'Question',
            'options' => ['A', 'B'],
            'answer' => 'A',
            'points' => 1,
            'source' => 'manual',
        ]);
    }

    private function createGenerationLog(
        User $author,
        ExerciseDocument $document
    ): ExerciseGenerationLog {
        return ExerciseGenerationLog::query()->create([
            'user_id' => $author->id,
            'document_id' => $document->id,
            'status' => 'ready',
            'prompt' => 'Generate one question.',
            'started_at' => now()->subSecond(),
            'finished_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(): array
    {
        return [
            'title' => 'Scoped Exercise Draft',
            'language' => 'en',
            'content_json' => ['type' => 'doc', 'content' => []],
            'content_html' => '<p>Scoped exercise</p>',
        ];
    }

    private function assertTenantDenied(TestResponse $response): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            'Cross-tenant requests must return 403 or 404.'
        );
    }
}
