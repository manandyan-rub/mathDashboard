<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\IntegralController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\MathematicsControlller;
use App\Http\Controllers\FormulaController;
use App\Http\Controllers\PdfQuizController;
use App\Http\Controllers\PdfQuizApiController;
use App\Http\Controllers\MathLevelController;
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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    Route::get('mathematics', [MathematicsControlller::class, 'index'])->name('mathematics.index');
    Route::get('mathematics/threeD', [MathematicsControlller::class, 'threeD'])->name('mathematics.threeD');
    Route::get('mathematics/integral', [MathematicsControlller::class, 'integral'])->name('mathematics.integral');
    Route::post('mathematics/integral/calculate', [IntegralController::class, 'calculate'])
        ->name('mathematics.integral.calculate');
    Route::get('game/oneplusone', [GameController::class, 'oneplusone'])->name('game.oneplusone');
    Route::get('game/equilibrium', [GameController::class, 'equilibrium'])->name('game.equilibrium');
    Route::get('fomula', [FormulaController::class, 'index'])->name('formula.index');
    Route::get('teacher/exercises/studio', [TeacherExerciseStudioController::class, 'index'])->name('teacher.exercises.studio');
    Route::get('math-levels', [MathLevelController::class, 'index'])->name('math-levels.index');
    Route::get('math-levels/rewards', [MathLevelController::class, 'rewards'])->name('math-levels.rewards');
    Route::get('math-levels/attempts/{attempt}', [MathLevelController::class, 'session'])->name('math-levels.session');
    Route::get('math-levels/attempts/{attempt}/result', [MathLevelController::class, 'result'])->name('math-levels.result');
    Route::get('pdf-quiz/upload', [PdfQuizController::class, 'upload'])->name('pdf-quiz.upload');
    Route::get('pdf-quiz/{quiz}/preview', [PdfQuizController::class, 'preview'])->name('pdf-quiz.preview');
    Route::get('pdf-quiz/attempts/{attempt}', [PdfQuizController::class, 'session'])->name('pdf-quiz.session');
    Route::get('pdf-quiz/attempts/{attempt}/result', [PdfQuizController::class, 'result'])->name('pdf-quiz.result');

    Route::post('pdf-quiz/imports', [PdfQuizApiController::class, 'store']);
    Route::get('pdf-quiz/quizzes/{quiz}', [PdfQuizApiController::class, 'showQuiz']);
    Route::post('pdf-quiz/quizzes/{quiz}/attempts', [PdfQuizApiController::class, 'startAttempt']);
    Route::post('pdf-quiz/attempts/{attempt}/submit', [PdfQuizApiController::class, 'submitAttempt']);
    Route::get('pdf-quiz/attempts/{attempt}', [PdfQuizApiController::class, 'showAttempt']);

});

Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('math-levels', [\App\Http\Controllers\Api\MathLevelController::class, 'index']);
    Route::get('math-levels/leaderboard', [\App\Http\Controllers\Api\MathLevelController::class, 'leaderboard']);
    Route::post('math-levels/start', [\App\Http\Controllers\Api\MathLevelController::class, 'start']);
    Route::get('math-levels/attempts/{attempt}', [\App\Http\Controllers\Api\MathLevelController::class, 'showAttempt']);
    Route::post('math-levels/answer', [\App\Http\Controllers\Api\MathLevelController::class, 'answer']);
    Route::post('math-levels/finish', [\App\Http\Controllers\Api\MathLevelController::class, 'finish']);
    Route::get('math-levels/attempts/{attempt}/result', [\App\Http\Controllers\Api\MathLevelController::class, 'result']);
    Route::post('games/xp', [\App\Http\Controllers\Api\GameXpController::class, 'store']);

    Route::post('teacher/exercises/generate', [\App\Http\Controllers\Api\TeacherExerciseStudioController::class, 'generate']);
    Route::get('teacher/exercises/generations/{generationLog}', [\App\Http\Controllers\Api\TeacherExerciseStudioController::class, 'generationStatus']);
    Route::post('teacher/exercises/regenerate-item', [\App\Http\Controllers\Api\TeacherExerciseStudioController::class, 'regenerateItem']);
    Route::post('teacher/exercises/save-draft', [\App\Http\Controllers\Api\TeacherExerciseStudioController::class, 'saveDraft']);
    Route::post('teacher/exercises/publish', [\App\Http\Controllers\Api\TeacherExerciseStudioController::class, 'publish']);
    Route::post('teacher/exercises/pdf', [\App\Http\Controllers\Api\TeacherExerciseStudioController::class, 'pdf']);
    Route::get('teacher/exercises/{document}', [\App\Http\Controllers\Api\TeacherExerciseStudioController::class, 'show']);
});

require __DIR__.'/auth.php';
