<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfQuizAttemptAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_id',
        'is_correct',
    ];

    public function attempt()
    {
        return $this->belongsTo(PdfQuizAttempt::class, 'attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(PdfQuizQuestion::class, 'question_id');
    }

    public function selectedOption()
    {
        return $this->belongsTo(PdfQuizQuestionOption::class, 'selected_option_id');
    }
}
