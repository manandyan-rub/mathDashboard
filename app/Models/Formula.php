<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Formula extends Model
{
    protected $fillable = [
        'category',
        'formula',
        'example',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(FormulaTranslation::class);
    }

    public function step(): HasOne
    {
        return $this->hasOne(FormulaStep::class);
    }
}
