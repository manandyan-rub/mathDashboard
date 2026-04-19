<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfQuiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdf_import_id',
        'title',
        'description',
        'difficulty',
        'total_questions',
    ];

    public function import()
    {
        return $this->belongsTo(PdfImport::class, 'pdf_import_id');
    }

    public function questions()
    {
        return $this->hasMany(PdfQuizQuestion::class, 'quiz_id')->orderBy('order');
    }

    public function attempts()
    {
        return $this->hasMany(PdfQuizAttempt::class, 'quiz_id');
    }
}
