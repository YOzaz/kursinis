<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeTextJob;
use App\Jobs\BatchAnalysisJob;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Services\MetricsService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Analizės kontroleris.
 * 
 * Valdomas visas analizės procesą ir API endpointus.
 */
class AnalysisController extends Controller
{
    private MetricsService $metricsService;
    private ExportService $exportService;

    public function __construct(MetricsService $metricsService, ExportService $exportService)
    {
        $this->metricsService = $metricsService;
        $this->exportService = $exportService;
    }

    /**
     * Analizuoti vieną tekstą.
     */
    public function analyzeSingle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text_id' => 'required|string',
            'content' => 'required|string|min:10',
            'models' => 'required|array|min:1',
            'models.*' => 'required|string|in:claude-4,gemini-2.5-pro,gpt-4.1',
            'expert_annotations' => 'nullable|array', // Pasirinktinės ekspertų anotacijos tyrimo tikslams
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Neteisingi duomenys',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $jobId = Str::uuid();
            
            // Sukurti analizės darbą
            $job = AnalysisJob::create([
                'job_id' => $jobId,
                'status' => AnalysisJob::STATUS_PROCESSING,
                'total_texts' => 1,
                'processed_texts' => 0,
            ]);

            // Sukurti tekstų analizės įrašą
            $textAnalysis = TextAnalysis::create([
                'job_id' => $jobId,
                'text_id' => $request->text_id,
                'content' => $request->content,
                'expert_annotations' => $request->input('expert_annotations', []), // Pasirinktinės ekspertų anotacijos
            ]);

            // Paleisti analizės darbus
            foreach ($request->models as $model) {
                AnalyzeTextJob::dispatch($textAnalysis->id, $model, $jobId);
            }

            Log::info('Paleista vieno teksto analizė', [
                'job_id' => $jobId,
                'text_id' => $request->text_id,
                'models' => $request->models
            ]);

            return response()->json([
                'job_id' => $jobId,
                'status' => 'processing',
                'text_id' => $request->text_id
            ]);

        } catch (\Exception $e) {
            Log::error('Vieno teksto analizės klaida', [
                'error' => $e->getMessage(),
                'text_id' => $request->text_id ?? 'nežinomas'
            ]);

            return response()->json([
                'error' => 'Analizės paleidimo klaida',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Paleisti batch analizę.
     */
    public function analyzeBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_content' => 'required|array',
            'models' => 'required|array|min:1',
            'models.*' => 'required|string|in:claude-4,gemini-2.5-pro,gpt-4.1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Neteisingi duomenys',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $fileContent = $request->file_content;
            $models = $request->models;
            
            // Validuoti JSON struktūrą
            if (!$this->validateBatchFileStructure($fileContent)) {
                return response()->json([
                    'error' => 'Neteisingas failo formatas',
                    'message' => 'Failas neatitinka reikalavimų struktūros'
                ], 422);
            }

            $jobId = Str::uuid();
            $totalTexts = count($fileContent);

            // Sukurti analizės darbą
            $job = AnalysisJob::create([
                'job_id' => $jobId,
                'status' => AnalysisJob::STATUS_PENDING,
                'total_texts' => $totalTexts,
                'processed_texts' => 0,
            ]);

            // Paleisti batch analizės darbą
            BatchAnalysisJob::dispatch($jobId, $fileContent, $models);

            Log::info('Paleista batch analizė', [
                'job_id' => $jobId,
                'total_texts' => $totalTexts,
                'models' => $models
            ]);

            return response()->json([
                'job_id' => $jobId,
                'status' => 'processing',
                'total_texts' => $totalTexts
            ]);

        } catch (\Exception $e) {
            Log::error('Batch analizės paleidimo klaida', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Batch analizės paleidimo klaida',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gauti analizės rezultatus.
     */
    public function getResults(string $jobId): JsonResponse
    {
        try {
            $job = AnalysisJob::with(['textAnalyses', 'comparisonMetrics'])
                ->where('job_id', $jobId)
                ->first();

            if (!$job) {
                return response()->json([
                    'error' => 'Darbas nerastas'
                ], 404);
            }

            if (!$job->isCompleted()) {
                return response()->json([
                    'job_id' => $jobId,
                    'status' => $job->status,
                    'progress' => $job->getProgressPercentage(),
                    'processed_texts' => $job->processed_texts,
                    'total_texts' => $job->total_texts
                ]);
            }

            // Apskaičiuoti bendrąsias metrikas (tik jei yra ekspertų anotacijų)
            $hasExpertAnnotations = $job->textAnalyses->some(function ($textAnalysis) {
                return !empty($textAnalysis->expert_annotations);
            });

            $response = [
                'job_id' => $jobId,
                'status' => $job->status,
                'has_expert_annotations' => $hasExpertAnnotations,
                'detailed_results' => route('api.results.export', ['jobId' => $jobId])
            ];

            if ($hasExpertAnnotations) {
                $response['comparison_metrics'] = $this->metricsService->calculateAggregatedMetrics($jobId);
            } else {
                // Tik LLM analizės rezultatai be palyginimo metrikų
                $response['llm_results'] = $job->textAnalyses->map(function ($textAnalysis) {
                    return [
                        'text_id' => $textAnalysis->text_id,
                        'claude_result' => $textAnalysis->claude_annotations,
                        'gemini_result' => $textAnalysis->gemini_annotations,
                        'gpt_result' => $textAnalysis->gpt_annotations,
                    ];
                });
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Rezultatų gavimo klaida', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Rezultatų gavimo klaida',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eksportuoti rezultatus į CSV.
     */
    public function exportResults(string $jobId)
    {
        try {
            $job = AnalysisJob::where('job_id', $jobId)->first();

            if (!$job) {
                return response()->json(['error' => 'Darbas nerastas'], 404);
            }

            if (!$job->isCompleted()) {
                return response()->json(['error' => 'Analizė dar nebaigta'], 400);
            }

            return $this->exportService->exportToCsv($jobId);

        } catch (\Exception $e) {
            Log::error('CSV eksporto klaida', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Eksporto klaida',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gauti darbo statusą.
     */
    public function getStatus(string $jobId): JsonResponse
    {
        try {
            $job = AnalysisJob::where('job_id', $jobId)->first();

            if (!$job) {
                return response()->json(['error' => 'Darbas nerastas'], 404);
            }

            return response()->json([
                'job_id' => $jobId,
                'status' => $job->status,
                'progress' => $job->getProgressPercentage(),
                'processed_texts' => $job->processed_texts,
                'total_texts' => $job->total_texts,
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
                'error_message' => $job->error_message
            ]);

        } catch (\Exception $e) {
            Log::error('Statuso gavimo klaida', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Statuso gavimo klaida',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validuoti batch failo struktūrą.
     */
    private function validateBatchFileStructure(array $data): bool
    {
        foreach ($data as $item) {
            if (!isset($item['id'], $item['data']['content'])) {
                return false;
            }

            // Patikrinti ar yra annotations ekspertų anotacijoms
            if (!isset($item['annotations']) || !is_array($item['annotations'])) {
                return false;
            }
        }

        return true;
    }
}