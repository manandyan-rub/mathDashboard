<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MathLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'path_id',
        'slug',
        'difficulty',
        'order',
        'passing_score',
        'xp_reward',
        'time_limit',
        'active',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(MathLevelTranslation::class, 'math_level_id');
    }

    public function path()
    {
        return $this->belongsTo(MathPath::class, 'path_id');
    }

    public function questions()
    {
        return $this->hasMany(MathLevelQuestion::class, 'level_id')->orderBy('order');
    }

    public function prompt()
    {
        return $this->hasOne(MathLevelPrompt::class, 'level_id');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(MathLevelPrompt::class, 'level_id');
    }

    public function attempts()
    {
        return $this->hasMany(MathLevelAttempt::class, 'level_id');
    }

    public function resolveTranslation(array $locales): ?MathLevelTranslation
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
