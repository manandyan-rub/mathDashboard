<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MathPathTranslation extends Model
{
    protected $fillable = [
        'math_path_id',
        'locale',
        'title',
        'description',
    ];

    public function path(): BelongsTo
    {
        return $this->belongsTo(MathPath::class, 'math_path_id');
    }
}
