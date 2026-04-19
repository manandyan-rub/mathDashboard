<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class FormulaTranslation extends Model
{
    protected $fillable = [
        'formula_id',
        'locale',
        'name',
        'description',
    ];

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }
}
