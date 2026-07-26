<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MathWorkspaceCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'topic',
        'problem_text',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
