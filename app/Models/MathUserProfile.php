<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MathUserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_xp',
        'current_rank',
        'streak_days',
        'completed_levels',
        'total_stars',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
