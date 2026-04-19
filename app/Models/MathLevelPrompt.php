<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MathLevelPrompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'level_id',
        'prompt',
        'question_count',
        'options_count',
        'locale',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(MathLevel::class, 'level_id');
    }
}
