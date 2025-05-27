<?php

namespace App\Http\Controllers;

use App\Models\Experiment;
use App\Services\StatisticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private StatisticsService $statisticsService
    ) {}

    public function index(): View
    {
        $globalStats = $this->statisticsService->getGlobalStatistics();
        $experiments = Experiment::orderBy('created_at', 'desc')->limit(5)->get();
        
        return view('dashboard.index', compact('globalStats', 'experiments'));
    }

    public function experimentStats(Experiment $experiment): JsonResponse
    {
        $stats = $this->statisticsService->getExperimentStatistics($experiment);
        return response()->json($stats);
    }

    public function compareExperiments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'experiment_ids' => 'required|array|min:2|max:5',
            'experiment_ids.*' => 'exists:experiments,id',
        ]);

        $experiments = Experiment::whereIn('id', $validated['experiment_ids'])
            ->with(['results'])
            ->get();

        $comparison = [];
        foreach ($experiments as $experiment) {
            $stats = $this->statisticsService->getExperimentStatistics($experiment);
            $comparison[$experiment->id] = [
                'name' => $experiment->name,
                'stats' => $stats,
            ];
        }

        return response()->json($comparison);
    }
}
