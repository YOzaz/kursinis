<?php

namespace App\Http\Controllers;

use App\Models\Experiment;
use App\Services\PromptBuilderService;
use App\Services\StatisticsService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ExperimentController extends Controller
{
    public function __construct(
        private PromptBuilderService $promptBuilder,
        private StatisticsService $statisticsService,
        private ExportService $exportService
    ) {}

    public function index(): View
    {
        $experiments = Experiment::orderBy('created_at', 'desc')->get();
        return view('experiments.index', compact('experiments'));
    }

    public function create(): View
    {
        $defaultConfig = $this->promptBuilder->getDefaultRisenConfig();
        return view('experiments.create', compact('defaultConfig'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'risen_config' => 'required|array',
            'risen_config.role' => 'required|string',
            'risen_config.instructions' => 'required|string',
            'risen_config.situation' => 'required|string',
            'risen_config.execution' => 'required|string',
            'risen_config.needle' => 'required|string',
        ]);

        $customPrompt = $this->promptBuilder->buildRisenPrompt($validated['risen_config']);

        $experiment = Experiment::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'custom_prompt' => $customPrompt,
            'risen_config' => $validated['risen_config'],
            'status' => 'draft',
        ]);

        return response()->json($experiment);
    }

    public function show(Experiment $experiment): View
    {
        $experiment->load(['results', 'analysisJobs']);
        $statistics = $this->statisticsService->getExperimentStatistics($experiment);
        return view('experiments.show', compact('experiment', 'statistics'));
    }

    public function edit(Experiment $experiment): View
    {
        return view('experiments.edit', compact('experiment'));
    }

    public function update(Request $request, Experiment $experiment): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'risen_config' => 'required|array',
            'risen_config.role' => 'required|string',
            'risen_config.instructions' => 'required|string',
            'risen_config.situation' => 'required|string',
            'risen_config.execution' => 'required|string',
            'risen_config.needle' => 'required|string',
        ]);

        $customPrompt = $this->promptBuilder->buildRisenPrompt($validated['risen_config']);

        $experiment->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'custom_prompt' => $customPrompt,
            'risen_config' => $validated['risen_config'],
        ]);

        return response()->json($experiment);
    }

    public function destroy(Experiment $experiment): JsonResponse
    {
        $experiment->delete();
        return response()->json(['message' => 'Eksperimentas sėkmingai ištrintas']);
    }

    public function previewPrompt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'risen_config' => 'required|array',
        ]);

        $prompt = $this->promptBuilder->buildRisenPrompt($validated['risen_config']);
        
        return response()->json(['prompt' => $prompt]);
    }

    public function exportCsv(Experiment $experiment): Response
    {
        $csv = $this->exportService->exportExperimentToCsv($experiment);
        $filename = $this->exportService->getExportFilename('results', $experiment->name) . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function exportStatsCsv(Experiment $experiment): Response
    {
        $statistics = $this->statisticsService->getExperimentStatistics($experiment);
        $csv = $this->exportService->exportExperimentStatisticsToCsv($experiment, $statistics);
        $filename = $this->exportService->getExportFilename('statistics', $experiment->name) . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function exportJson(Experiment $experiment): Response
    {
        $json = $this->exportService->exportExperimentToJson($experiment);
        $filename = $this->exportService->getExportFilename('complete', $experiment->name) . '.json';

        return response($json)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
