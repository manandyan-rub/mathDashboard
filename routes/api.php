<?php

use App\Http\Controllers\Api\FormulaController;
use App\Http\Controllers\Api\ExerciseController;
use Illuminate\Support\Facades\Route;

Route::get('formulas', [FormulaController::class, 'index']);
Route::get('formulas/{formula}', [FormulaController::class, 'show']);
Route::get('formulas/{formula}/steps', [FormulaController::class, 'steps']);

Route::middleware(['auth:sanctum', 'role:admin,lecturer'])->group(function () {
    Route::post('formulas/{formula}/steps/regenerate', [FormulaController::class, 'regenerateSteps']);
    Route::post('exercises/generate', [ExerciseController::class, 'generate']);
    Route::post('exercises/pdf', [ExerciseController::class, 'pdf']);
});
