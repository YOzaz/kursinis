<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalysisController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Čia registruojami API maršrutai pagal reikalavimų specifikaciją.
|
*/

// Vieno teksto analizė
Route::post('/analyze', [AnalysisController::class, 'analyzeSingle'])
    ->name('api.analyze.single');

// Batch analizės paleidimas
Route::post('/batch-analyze', [AnalysisController::class, 'analyzeBatch'])
    ->name('api.analyze.batch');

// Rezultatų gavimas
Route::get('/results/{jobId}', [AnalysisController::class, 'getResults'])
    ->name('api.results.get');

// CSV eksportas
Route::get('/results/{jobId}/export', [AnalysisController::class, 'exportResults'])
    ->name('api.results.export');

// Darbo statuso tikrinimas
Route::get('/status/{jobId}', [AnalysisController::class, 'getStatus'])
    ->name('api.status.get');