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

// Model status endpoints
Route::get('/models/status', [AnalysisController::class, 'getModelStatus'])
    ->name('api.models.status');

Route::post('/models/status/refresh', [AnalysisController::class, 'refreshModelStatus'])
    ->name('api.models.status.refresh');

// Standartinio prompt'o gavimas
Route::get('/default-prompt', function() {
    $promptService = app(\App\Services\PromptService::class);
    $standardPrompt = $promptService->getStandardRisenPrompt();
    
    // Build full RISEN prompt structure
    $fullPrompt = "**ROLE**: {$standardPrompt['role']}\n\n";
    $fullPrompt .= "**INSTRUCTIONS**: {$standardPrompt['instructions']}\n\n";
    $fullPrompt .= "**SITUATION**: {$standardPrompt['situation']}\n\n";
    $fullPrompt .= "**EXECUTION**: {$standardPrompt['execution']}\n\n";
    
    // Add propaganda techniques
    $techniques = config('llm.propaganda_techniques');
    $fullPrompt .= "**PROPAGANDOS TECHNIKOS (ATSPARA metodologija)**:\n";
    foreach ($techniques as $key => $description) {
        $fullPrompt .= "- {$key}: {$description}\n";
    }
    
    // Add disinformation narratives
    $narratives = config('llm.disinformation_narratives');
    $fullPrompt .= "\n**DEZINFORMACIJOS NARATYVAI**:\n";
    foreach ($narratives as $key => $description) {
        $fullPrompt .= "- {$key}: {$description}\n";
    }
    
    $fullPrompt .= "\n**NEEDLE**: {$standardPrompt['needle']}\n\n";
    $fullPrompt .= "**ATSAKYMO FORMATAS**:\n";
    $fullPrompt .= "```json\n";
    $fullPrompt .= "{\n";
    $fullPrompt .= "  \"primaryChoice\": {\n";
    $fullPrompt .= "    \"choices\": [\"yes\" arba \"no\"] // ar propaganda dominuoja (>40% teksto)\n";
    $fullPrompt .= "  },\n";
    $fullPrompt .= "  \"annotations\": [\n";
    $fullPrompt .= "    {\n";
    $fullPrompt .= "      \"type\": \"labels\",\n";
    $fullPrompt .= "      \"value\": {\n";
    $fullPrompt .= "        \"start\": pozicijos_pradžia,\n";
    $fullPrompt .= "        \"end\": pozicijos_pabaiga,\n";
    $fullPrompt .= "        \"text\": \"tikslus_tekstas\",\n";
    $fullPrompt .= "        \"labels\": [\"techniką1\", \"techniką2\"]\n";
    $fullPrompt .= "      }\n";
    $fullPrompt .= "    }\n";
    $fullPrompt .= "  ],\n";
    $fullPrompt .= "  \"desinformationTechnique\": {\n";
    $fullPrompt .= "    \"choices\": [\"naratyvas1\", \"naratyvas2\"] // arba []\n";
    $fullPrompt .= "  }\n";
    $fullPrompt .= "}\n";
    $fullPrompt .= "```\n\n";
    $fullPrompt .= "**ANALIZUOJAMAS TEKSTAS**:\n[TEKSTAS BUS ĮSTATYTAS ČIA]";
    
    return response()->json([
        'prompt' => $fullPrompt
    ]);
})->name('api.default-prompt');

// Text annotations for highlighting
Route::get('/text-annotations/{textAnalysisId}', [AnalysisController::class, 'getTextAnnotations'])
    ->name('api.text.annotations');

// Advanced metrics for research analysis
Route::get('/results/{jobId}/advanced-metrics', [AnalysisController::class, 'getAdvancedMetrics'])
    ->name('api.results.advanced-metrics');

// Dashboard statistikų eksportas
Route::get('/dashboard/export', function(\Illuminate\Http\Request $request) {
    $format = $request->get('format', 'json');
    $statisticsService = app(\App\Services\StatisticsService::class);
    $exportService = app(\App\Services\ExportService::class);
    
    // Validate format
    if (!in_array($format, ['json', 'csv', 'excel'])) {
        return response()->json([
            'error' => 'Invalid export format. Supported formats: csv, json, excel'
        ], 400);
    }
    
    $data = $statisticsService->getDashboardExportData();
    
    if ($format === 'csv') {
        $filename = 'dashboard_statistics.csv';
        $csvContent = $exportService->dashboardDataToCSV($data);
        
        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    } elseif ($format === 'excel') {
        $filename = 'dashboard_statistics.xlsx';
        $excelContent = $exportService->dashboardDataToExcel($data);
        
        return response($excelContent, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    } else {
        // Default to JSON
        $filename = 'dashboard_statistics_' . date('Y-m-d_H-i-s') . '.json';
        
        return response()->json($data, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
})->name('api.dashboard.export');