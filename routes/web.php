<?php

use App\Http\Controllers\FormulaController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\MathematicsControlller;
use App\Http\Controllers\MathLevelController;
use App\Http\Controllers\MathWorkspaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeacherExerciseStudioController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('dashboard.local'))->name('dashboard');
    Route::get('/dashboard/local', fn () => Inertia::render('Dashboard', ['scope' => 'local']))
        ->name('dashboard.local');
    Route::get('/dashboard/global', fn () => Inertia::render('Dashboard', ['scope' => 'global']))
        ->name('dashboard.global');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/two-factor', [ProfileController::class, 'updateTwoFactorPreference'])
        ->name('profile.two-factor.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('mathematics', [MathematicsControlller::class, 'index'])->name('mathematics.index');
    Route::get('mathematics/workspace', [MathWorkspaceController::class, 'index'])
        ->name('mathematics.workspace');
    Route::post('mathematics/workspace/saves', [MathWorkspaceController::class, 'store'])
        ->name('mathematics.workspace.saves.store');
    Route::get('mathematics/twoD', [MathematicsControlller::class, 'twoD'])->name('mathematics.twoD');
    Route::get('mathematics/threeD', [MathematicsControlller::class, 'threeD'])->name('mathematics.threeD');
    Route::get('mathematics/integral', [MathematicsControlller::class, 'integral'])->name('mathematics.integral');
    Route::get('game/oneplusone', [GameController::class, 'oneplusone'])->name('game.oneplusone');
    Route::get('game/equilibrium', [GameController::class, 'equilibrium'])->name('game.equilibrium');
    Route::get('fomula', [FormulaController::class, 'index'])->name('formula.index');
    Route::get('teacher/exercises/studio', [TeacherExerciseStudioController::class, 'index'])
        ->middleware('role:admin,org_admin,lecturer')
        ->name('teacher.exercises.studio');
    Route::get('math-levels', [MathLevelController::class, 'index'])->name('math-levels.index');
    Route::get('math-levels/rewards', [MathLevelController::class, 'rewards'])->name('math-levels.rewards');
    Route::get('math-levels/attempts/{attempt}', [MathLevelController::class, 'session'])->name('math-levels.session');
    Route::get('math-levels/attempts/{attempt}/result', [MathLevelController::class, 'result'])->name('math-levels.result');

});

require __DIR__.'/auth.php';
