<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MathLevelTranslation extends Model
{
    protected $fillable = [
        'math_level_id',
        'locale',
        'title',
        'description',
        'topic',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(MathLevel::class, 'math_level_id');
    }
}
