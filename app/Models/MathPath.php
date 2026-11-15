<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MathPath extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'order',
        'active',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(MathPathTranslation::class, 'math_path_id');
    }

    public function levels()
    {
        return $this->hasMany(MathLevel::class, 'path_id')->orderBy('order');
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function resolveTranslation(array $locales): ?MathPathTranslation
    {
        foreach ($locales as $locale) {
            $translation = $this->translations->firstWhere('locale', $locale);
            if ($translation) {
                return $translation;
            }
        }

        return $this->translations->first();
    }
}
