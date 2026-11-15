<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'academic_year',
        'math_path_id',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function mathPath(): BelongsTo
    {
        return $this->belongsTo(MathPath::class);
    }

    public function users(): BelongsToMany
    {
        return $this->classUsers();
    }

    public function exerciseDocuments(): HasMany
    {
        return $this->hasMany(ExerciseDocument::class);
    }

    public function students(): BelongsToMany
    {
        return $this->classUsers()->wherePivot('role_in_class', 'student');
    }

    public function teachers(): BelongsToMany
    {
        return $this->classUsers()->wherePivot('role_in_class', 'teacher');
    }

    private function classUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_user', 'class_id', 'user_id')
            ->withPivot('role_in_class', 'joined_at')
            ->withTimestamps();
    }
}
