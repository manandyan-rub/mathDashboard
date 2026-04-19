<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MathLevelAttemptAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_id',
        'attempt_question_id',
        'attempt_option_id',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function attempt()
    {
        return $this->belongsTo(MathLevelAttempt::class, 'attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(MathLevelAttemptQuestion::class, 'attempt_question_id');
    }

    public function selectedOption()
    {
        return $this->belongsTo(MathLevelAttemptQuestionOption::class, 'attempt_option_id');
    }
}
