<?php

use App\Http\Controllers\Api\FormulaController;
use Illuminate\Support\Facades\Route;

Route::get('formulas', [FormulaController::class, 'index']);
Route::get('formulas/{formula}', [FormulaController::class, 'show']);
Route::get('formulas/{formula}/steps', [FormulaController::class, 'steps']);
