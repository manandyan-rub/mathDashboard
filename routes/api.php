<?php

use App\Http\Controllers\Api\FormulaController;
use App\Http\Controllers\Api\GameXpController;
use App\Http\Controllers\Api\MathLevelController;
use App\Http\Controllers\Api\TeacherExerciseStudioController;
use App\Http\Controllers\Api\XpAnalyticsController;
use App\Http\Controllers\IntegralController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('dashboard/xp-analytics', [XpAnalyticsController::class, 'index']);

    Route::post('mathematics/integral/calculate', [IntegralController::class, 'calculate']);

    Route::get('math-levels', [MathLevelController::class, 'index']);
    Route::get('math-levels/leaderboard', [MathLevelController::class, 'leaderboard']);
    Route::post('math-levels/start', [MathLevelController::class, 'start']);
    Route::get('math-levels/attempts/{attempt}', [MathLevelController::class, 'showAttempt']);
    Route::post('math-levels/answer', [MathLevelController::class, 'answer']);
    Route::post('math-levels/finish', [MathLevelController::class, 'finish']);
    Route::get('math-levels/attempts/{attempt}/result', [MathLevelController::class, 'result']);

    Route::post('games/xp', [GameXpController::class, 'store']);

    Route::get('formula-groups', [FormulaController::class, 'groups']);
    Route::get('formulas', [FormulaController::class, 'index']);
    Route::get('formulas/{formula}', [FormulaController::class, 'show']);
    Route::get('formulas/{formula}/steps', [FormulaController::class, 'steps']);

    Route::middleware('role:admin,org_admin,lecturer')->group(function () {
        Route::get('teacher/exercises/classes', [TeacherExerciseStudioController::class, 'classes']);
        Route::post('teacher/exercises/generate', [TeacherExerciseStudioController::class, 'generate']);
        Route::get('teacher/exercises/generations/{generationLog}', [TeacherExerciseStudioController::class, 'generationStatus']);
        Route::post('teacher/exercises/regenerate-item', [TeacherExerciseStudioController::class, 'regenerateItem']);
        Route::post('teacher/exercises/images', [TeacherExerciseStudioController::class, 'uploadImage']);
        Route::post('teacher/exercises/save-draft', [TeacherExerciseStudioController::class, 'saveDraft']);
        Route::post('teacher/exercises/publish', [TeacherExerciseStudioController::class, 'publish']);
        Route::post('teacher/exercises/pdf', [TeacherExerciseStudioController::class, 'pdf']);
        Route::get('teacher/exercises/{document}', [TeacherExerciseStudioController::class, 'show']);
    });
});
