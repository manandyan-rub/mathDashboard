<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseGenerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'document_id',
        'status',
        'prompt',
        'request_json',
        'response_raw',
        'parsed_json',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ExerciseDocument::class, 'document_id');
    }
}

