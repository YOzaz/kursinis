<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\HelpController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Vartotojo sąsajos maršrutai.
|
*/

Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::get('/create', [WebController::class, 'index'])->name('create');
Route::post('/upload', [WebController::class, 'upload'])->name('upload');
Route::get('/progress/{jobId}', [WebController::class, 'progress'])->name('progress');
Route::get('/status/{jobId}', [WebController::class, 'detailedStatus'])->name('status.detailed');
Route::get('/status-view/{jobId}', function($jobId) {
    return view('detailed-status', compact('jobId'));
})->name('status.view');

// Mission Control system-wide monitoring
Route::get('/mission-control', function() {
    return view('mission-control');
})->name('mission-control');
Route::get('/api/mission-control', [WebController::class, 'missionControl'])->name('api.mission-control');

Route::get('/analyses', [AnalysisController::class, 'index'])->name('analyses.index');
Route::get('/analyses/{jobId}', [AnalysisController::class, 'show'])->name('analyses.show');
Route::post('/analysis/repeat', [AnalysisController::class, 'repeat'])->name('analysis.repeat');
Route::post('/analysis/stop', [AnalysisController::class, 'stop'])->name('analysis.stop');
Route::delete('/analysis/delete', [AnalysisController::class, 'delete'])->name('analysis.delete');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings/defaults', [SettingsController::class, 'updateDefaults'])->name('settings.updateDefaults');
Route::post('/settings/reset', [SettingsController::class, 'resetDefaults'])->name('settings.resetDefaults');

Route::get('/help', [HelpController::class, 'index'])->name('help.index');
Route::get('/help/faq', [HelpController::class, 'faq'])->name('help.faq');

Route::get('/contact', function() { return view('contact'); })->name('contact');
Route::get('/legal', function() { return view('legal'); })->name('legal');

// Authentication routes
Route::get('/login', function() { return view('login'); })->name('login');
Route::post('/login', function() { return redirect('/'); }); // Handled by middleware
Route::post('/logout', function() { 
    session()->flush(); 
    return redirect('/login')->with('success', 'Sėkmingai atsijungėte.'); 
})->name('logout');

// API Documentation
Route::get('/api/documentation', function() {
    return view('vendor.l5-swagger.index');
})->name('api.documentation');

// JSON Format Documentation
Route::get('/docs/json-format', function() {
    $content = file_get_contents(base_path('docs/JSON-FORMAT.md'));
    return response($content, 200, [
        'Content-Type' => 'text/markdown; charset=UTF-8',
        'Content-Disposition' => 'inline'
    ]);
})->name('docs.json-format');