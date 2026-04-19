<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExerciseDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'status',
        'content_json',
        'content_html',
        'language',
        'meta',
        'generated_at',
        'published_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'generated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExerciseItem::class, 'document_id')->orderBy('item_order');
    }

    public function generationLogs(): HasMany
    {
        return $this->hasMany(ExerciseGenerationLog::class, 'document_id')->latest('id');
    }
}

