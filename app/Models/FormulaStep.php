<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormulaStep extends Model
{
    protected $fillable = [
        'formula_id',
        'steps_json',
    ];

    protected $casts = [
        'steps_json' => 'array',
    ];

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }
}

