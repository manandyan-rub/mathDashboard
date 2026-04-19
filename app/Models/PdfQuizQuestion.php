<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfQuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question',
        'explanation',
        'correct_answer',
        'order',
    ];

    public function quiz()
    {
        return $this->belongsTo(PdfQuiz::class, 'quiz_id');
    }

    public function options()
    {
        return $this->hasMany(PdfQuizQuestionOption::class, 'question_id')->orderBy('order');
    }
}
