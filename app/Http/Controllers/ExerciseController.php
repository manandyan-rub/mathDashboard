<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class ExerciseController extends Controller
{
    public function generate()
    {
        return Inertia::render('Exercises/Generate');
    }
}
