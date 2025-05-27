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

// Analizės pakartojimas
Route::post('/repeat-analysis', [AnalysisController::class, 'repeatAnalysis'])
    ->name('api.analysis.repeat');

// Sistemos statusas
Route::get('/health', [AnalysisController::class, 'health'])
    ->name('api.health');

// Galimų modelių sąrašas
Route::get('/models', [AnalysisController::class, 'models'])
    ->name('api.models');

// Standartinio prompt'o gavimas
Route::get('/default-prompt', function() {
    $promptService = app(\App\Services\PromptService::class);
    return response()->json([
        'prompt' => $promptService->generateAnalysisPrompt('PAVYZDINIS TEKSTAS', 'claude-opus-4')
    ]);
})->name('api.default-prompt');

// Dashboard statistikų eksportas
Route::get('/dashboard/export', function() {
    $statisticsService = app(\App\Services\StatisticsService::class);
    $data = $statisticsService->getDashboardExportData();
    
    $filename = 'dashboard_statistics_' . date('Y-m-d_H-i-s') . '.json';
    
    return response()->json($data, 200, [
        'Content-Type' => 'application/json',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
})->name('api.dashboard.export');