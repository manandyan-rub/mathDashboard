<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GameController extends Controller
{
    public function oneplusone(){
        return Inertia::render('Games/OnePlusOne');
    }

    public function equilibrium(){
        return Inertia::render('Games/EquiliBrium');
    }
}
