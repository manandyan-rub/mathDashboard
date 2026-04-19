<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MathUserLevelProgress extends Model
{
    use HasFactory;

    protected $table = 'math_user_level_progress';

    protected $fillable = [
        'user_id',
        'level_id',
        'unlocked',
        'completed',
        'best_score',
        'best_stars',
        'total_attempts',
        'last_attempted_at',
    ];

    protected $casts = [
        'unlocked' => 'boolean',
        'completed' => 'boolean',
        'last_attempted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function level()
    {
        return $this->belongsTo(MathLevel::class, 'level_id');
    }
}
