<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MathLevelQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'level_id',
        'question',
        'type',
        'explanation',
        'correct_answer',
        'order',
        'points',
        'active',
    ];

    public function level()
    {
        return $this->belongsTo(MathLevel::class, 'level_id');
    }

    public function options()
    {
        return $this->hasMany(MathLevelQuestionOption::class, 'question_id')->orderBy('order');
    }
}
