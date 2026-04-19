<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MathLevelAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'level_id',
        'score',
        'correct_answers',
        'wrong_answers',
        'stars',
        'passed',
        'xp_earned',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function level()
    {
        return $this->belongsTo(MathLevel::class, 'level_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(MathLevelAttemptAnswer::class, 'attempt_id');
    }

    public function questions()
    {
        return $this->hasMany(MathLevelAttemptQuestion::class, 'attempt_id')->orderBy('order');
    }
}
