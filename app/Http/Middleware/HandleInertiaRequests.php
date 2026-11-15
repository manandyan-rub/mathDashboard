<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'must_enable_2fa' => (bool) $user->must_enable_2fa,
                    'profile_photo_url' => $user->profile_photo_url ?? null,
                ] : null,
                'permissions' => [
                    'use_editors' => $user?->canUseEditors() ?? false,
                ],
                'tenant' => $this->tenantContext($user),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tenantContext(?User $user): ?array
    {
        if (! $user || $user->organization_id === null) {
            return null;
        }

        $user->loadMissing([
            'organization:id,name,plan',
            'schoolClasses:id,school_id,name,academic_year',
            'schoolClasses.school:id,name',
        ]);

        if (! $user->organization) {
            return null;
        }

        $memberships = $user->schoolClasses
            ->map(fn ($schoolClass) => [
                'class_id' => $schoolClass->id,
                'class_name' => $schoolClass->name,
                'academic_year' => $schoolClass->academic_year,
                'school_id' => $schoolClass->school->id,
                'school_name' => $schoolClass->school->name,
                'role_in_class' => $schoolClass->pivot->role_in_class,
            ])
            ->sortBy([
                ['school_name', 'asc'],
                ['class_name', 'asc'],
            ])
            ->values();

        return [
            'organization' => [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'plan' => $user->organization->plan,
            ],
            'memberships' => $memberships,
        ];
    }
}
