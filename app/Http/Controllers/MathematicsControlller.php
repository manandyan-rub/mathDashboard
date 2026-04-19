<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MathematicsControlller extends Controller
{
    public function index(){
        return Inertia::render('Mathematics/Index');
    }

    public function threeD(){
        return Inertia::render('Mathematics/ThreeD');
    }

    public function integral(){
        return Inertia::render('Mathematics/Integral');
    }
}
