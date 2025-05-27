<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

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