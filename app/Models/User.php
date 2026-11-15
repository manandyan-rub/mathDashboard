<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_STUDENT = 'student';

    public const ROLE_LECTURER = 'lecturer';

    public const ROLE_PLATFORM_ADMIN = 'admin';

    public const ROLE_ORG_ADMIN = 'org_admin';

    public const ROLE_ADMIN = self::ROLE_PLATFORM_ADMIN;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'must_enable_2fa',
        'country_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
            'must_enable_2fa' => 'boolean',
            'country_id' => 'integer',
            'organization_id' => 'integer',
        ];
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_user', 'user_id', 'class_id')
            ->withPivot('role_in_class', 'joined_at')
            ->withTimestamps();
    }

    public function profile()
    {
        return $this->hasOne(MathUserProfile::class);
    }

    public function mathWorkspaceCalculations(): HasMany
    {
        return $this->hasMany(MathWorkspaceCalculation::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === self::ROLE_PLATFORM_ADMIN;
    }

    public function isOrgAdmin(): bool
    {
        return $this->role === self::ROLE_ORG_ADMIN;
    }

    public function isLecturer(): bool
    {
        return $this->role === self::ROLE_LECTURER;
    }

    public function canUseEditors(): bool
    {
        return $this->isAdmin() || $this->isOrgAdmin() || $this->isLecturer();
    }
}
