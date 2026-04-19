<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TeacherExerciseStudioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Teacher/ExerciseStudio');
    }
}

