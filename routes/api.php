<?php

use App\Http\Controllers\Api\FormulaController;
use App\Http\Controllers\Api\ExerciseController;
use Illuminate\Support\Facades\Route;

Route::get('formulas', [FormulaController::class, 'index']);
Route::get('formulas/{formula}', [FormulaController::class, 'show']);
Route::post('exercises/generate', [ExerciseController::class, 'generate']);
Route::post('exercises/pdf', [ExerciseController::class, 'pdf']);
