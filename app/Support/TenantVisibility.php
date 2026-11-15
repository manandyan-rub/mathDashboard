<?php

namespace App\Support;

use App\Models\ExerciseDocument;
use App\Models\ExerciseGenerationLog;
use App\Models\MathLevel;
use App\Models\MathPath;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class TenantVisibility
{
    public function visibleUserIds(User $viewer): Builder
    {
        $this->assertConfigured($viewer);

        $query = User::query()->select('users.id');

        if ($viewer->isPlatformAdmin()) {
            return $query;
        }

        if ($viewer->isLecturer()) {
            return $query
                ->where('users.role', User::ROLE_STUDENT)
                ->whereHas('schoolClasses', function (Builder $classes) use ($viewer) {
                    $classes
                        ->where('class_user.role_in_class', 'student')
                        ->whereHas('teachers', fn (Builder $teachers) => $teachers->whereKey($viewer->id));
                });
        }

        if ($viewer->organization_id !== null) {
            return $query->where('users.organization_id', $viewer->organization_id);
        }

        return $query->whereNull('users.organization_id');
    }

    public function mathPathsFor(User $viewer): Builder
    {
        return $this->scopeMathPaths(MathPath::query(), $viewer);
    }

    public function mathLevelsFor(User $viewer): Builder
    {
        $this->assertConfigured($viewer);

        return MathLevel::query()->whereHas(
            'path',
            fn (Builder $paths) => $this->scopeMathPaths($paths, $viewer)
        );
    }

    public function schoolClassesFor(User $viewer): Builder
    {
        $this->assertConfigured($viewer);

        $query = SchoolClass::query();

        if ($viewer->isPlatformAdmin()) {
            return $query;
        }

        if ($viewer->isOrgAdmin()) {
            return $query->whereHas(
                'school',
                fn (Builder $schools) => $schools->where('organization_id', $viewer->organization_id)
            );
        }

        if ($viewer->isLecturer()) {
            return $query->whereHas(
                'teachers',
                fn (Builder $teachers) => $teachers->whereKey($viewer->id)
            );
        }

        if ($viewer->role === User::ROLE_STUDENT) {
            return $query->whereHas(
                'students',
                fn (Builder $students) => $students->whereKey($viewer->id)
            );
        }

        return $query->whereRaw('1 = 0');
    }

    public function exerciseDocumentsFor(User $viewer): Builder
    {
        return $this->scopeExerciseDocuments(ExerciseDocument::query(), $viewer);
    }

    public function generationLogsFor(User $viewer): Builder
    {
        $this->assertConfigured($viewer);

        $query = ExerciseGenerationLog::query();

        if ($viewer->isPlatformAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $logs) use ($viewer) {
            $logs->whereHas(
                'document',
                fn (Builder $documents) => $this->scopeExerciseDocuments($documents, $viewer)
            );

            if ($viewer->isOrgAdmin()) {
                $logs->orWhere(function (Builder $detached) use ($viewer) {
                    $detached
                        ->whereNull('document_id')
                        ->whereHas(
                            'user',
                            fn (Builder $users) => $users->where('organization_id', $viewer->organization_id)
                        );
                });
            } elseif ($viewer->isLecturer()) {
                $logs->orWhere(function (Builder $detached) use ($viewer) {
                    $detached
                        ->whereNull('document_id')
                        ->where('user_id', $viewer->id);
                });
            }
        });
    }

    public function assertConfigured(User $viewer): void
    {
        if ($viewer->isOrgAdmin() && $viewer->organization_id === null) {
            abort(403, 'Organization administrator is not assigned to an organization.');
        }
    }

    private function scopeMathPaths(Builder $query, User $viewer): Builder
    {
        $this->assertConfigured($viewer);

        if ($viewer->isPlatformAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $paths) use ($viewer) {
            $paths->whereDoesntHave('schoolClasses');

            if ($viewer->isOrgAdmin()) {
                $paths->orWhereHas(
                    'schoolClasses.school',
                    fn (Builder $schools) => $schools->where('organization_id', $viewer->organization_id)
                );
            } elseif ($viewer->isLecturer()) {
                $paths->orWhereHas(
                    'schoolClasses.teachers',
                    fn (Builder $teachers) => $teachers->whereKey($viewer->id)
                );
            } elseif ($viewer->role === User::ROLE_STUDENT) {
                $paths->orWhereHas(
                    'schoolClasses.students',
                    fn (Builder $students) => $students->whereKey($viewer->id)
                );
            }
        });
    }

    private function scopeExerciseDocuments(Builder $query, User $viewer): Builder
    {
        $this->assertConfigured($viewer);

        if ($viewer->isPlatformAdmin()) {
            return $query;
        }

        if ($viewer->isOrgAdmin()) {
            return $query->where(function (Builder $documents) use ($viewer) {
                $documents
                    ->where(function (Builder $unassigned) use ($viewer) {
                        $unassigned
                            ->whereNull('school_class_id')
                            ->whereHas(
                                'user',
                                fn (Builder $users) => $users->where('organization_id', $viewer->organization_id)
                            );
                    })
                    ->orWhereHas(
                        'schoolClass.school',
                        fn (Builder $schools) => $schools->where('organization_id', $viewer->organization_id)
                    );
            });
        }

        if ($viewer->isLecturer()) {
            return $query
                ->where('exercise_documents.user_id', $viewer->id)
                ->where(function (Builder $documents) use ($viewer) {
                    $documents
                        ->whereNull('school_class_id')
                        ->orWhereHas(
                            'schoolClass.teachers',
                            fn (Builder $teachers) => $teachers->whereKey($viewer->id)
                        );
                });
        }

        return $query->whereRaw('1 = 0');
    }
}
