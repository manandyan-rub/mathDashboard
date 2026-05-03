<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MathUserXpEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'source',
        'source_label',
        'reference_type',
        'reference_id',
        'metadata',
        'earned_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'earned_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
