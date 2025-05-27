<?php

namespace App\Http\Controllers;

use App\Models\AnalysisJob;
use App\Services\StatisticsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private StatisticsService $statisticsService
    ) {}

    public function index(): View
    {
        $globalStats = $this->statisticsService->getGlobalStatistics();
        $recentAnalyses = AnalysisJob::orderBy('created_at', 'desc')->limit(10)->get();
        
        return view('dashboard.index', compact('globalStats', 'recentAnalyses'));
    }
}
