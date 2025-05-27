<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalysisController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Vartotojo sąsajos maršrutai.
|
*/

Route::get('/', [WebController::class, 'index'])->name('home');
Route::post('/upload', [WebController::class, 'upload'])->name('upload');
Route::get('/progress/{jobId}', [WebController::class, 'progress'])->name('progress');

Route::get('/analyses', [AnalysisController::class, 'index'])->name('analyses.index');
Route::get('/analyses/{jobId}', [AnalysisController::class, 'show'])->name('analyses.show');
Route::post('/analysis/repeat', [AnalysisController::class, 'repeat'])->name('analysis.repeat');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');