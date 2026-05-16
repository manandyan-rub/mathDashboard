<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormulaGroup extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'section_id',
        'section_title',
        'color',
        'sort_order',
    ];
}
