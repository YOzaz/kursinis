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
     * Rodyti analizių sąrašą.
     */
    public function index()
    {
        $analyses = AnalysisJob::with(['textAnalyses'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        return view('analyses.index', compact('analyses'));
    }

    /**
     * Rodyti konkrečios analizės rezultatus.
     */
    public function show(string $jobId)
    {
        $analysis = AnalysisJob::where('job_id', $jobId)->firstOrFail();
        $analysis->load(['textAnalyses.comparisonMetrics']);
        $statistics = $this->metricsService->calculateJobStatistics($analysis);
        
        return view('analyses.show', compact('analysis', 'statistics'));
    }

    /**
     * Analizuoti vieną tekstą.
     */
    public function analyzeSingle(Request $request): JsonResponse
    {
        $availableModels = collect(config('llm.models', []))->keys()->implode(',');
        
        $validator = Validator::make($request->all(), [
            'text_id' => 'required|string',
            'content' => 'required|string|min:10',
            'models' => 'required|array|min:1',
            'models.*' => "required|string|in:{$availableModels}",
            'expert_annotations' => 'nullable|array',
            'custom_prompt' => 'nullable|string',
            'reference_analysis_id' => 'nullable|string|exists:analysis_jobs,job_id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
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
                'custom_prompt' => $request->custom_prompt,
                'reference_analysis_id' => $request->reference_analysis_id,
                'name' => $request->name,
                'description' => $request->description,
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
        $availableModels = collect(config('llm.models', []))->keys()->implode(',');
        
        $validator = Validator::make($request->all(), [
            'file_content' => 'required|array',
            'models' => 'required|array|min:1',
            'models.*' => "required|string|in:{$availableModels}",
            'custom_prompt' => 'nullable|string',
            'reference_analysis_id' => 'nullable|string|exists:analysis_jobs,job_id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
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
                'custom_prompt' => $request->custom_prompt,
                'reference_analysis_id' => $request->reference_analysis_id,
                'name' => $request->name,
                'description' => $request->description,
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
     * Pakartoti analizę su nauju prompt'u.
     */
    public function repeatAnalysis(Request $request): JsonResponse
    {
        $availableModels = collect(config('llm.models', []))->keys()->implode(',');
        
        $validator = Validator::make($request->all(), [
            'reference_analysis_id' => 'required|string|exists:analysis_jobs,job_id',
            'models' => 'required|array|min:1',
            'models.*' => "required|string|in:{$availableModels}",
            'custom_prompt' => 'nullable|string',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Neteisingi duomenys',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $referenceAnalysis = AnalysisJob::where('job_id', $request->reference_analysis_id)->firstOrFail();
            
            if (!$referenceAnalysis->isCompleted()) {
                return response()->json([
                    'error' => 'Nuorodos analizė dar nebaigta'
                ], 400);
            }

            $jobId = Str::uuid();
            $textAnalyses = $referenceAnalysis->textAnalyses;

            // Sukurti naują analizės darbą
            $job = AnalysisJob::create([
                'job_id' => $jobId,
                'status' => AnalysisJob::STATUS_PENDING,
                'total_texts' => $textAnalyses->count(),
                'processed_texts' => 0,
                'custom_prompt' => $request->custom_prompt,
                'reference_analysis_id' => $request->reference_analysis_id,
                'name' => $request->name,
                'description' => $request->description,
            ]);

            // Kopijuoti tekstus iš nuorodos analizės
            foreach ($textAnalyses as $originalText) {
                $newTextAnalysis = TextAnalysis::create([
                    'job_id' => $jobId,
                    'text_id' => $originalText->text_id,
                    'content' => $originalText->content,
                    'expert_annotations' => $originalText->expert_annotations,
                ]);

                // Paleisti analizės darbus su nauju prompt'u
                foreach ($request->models as $model) {
                    AnalyzeTextJob::dispatch($newTextAnalysis->id, $model, $jobId);
                }
            }

            Log::info('Pakartota analizė su nauju prompt\'u', [
                'job_id' => $jobId,
                'reference_analysis_id' => $request->reference_analysis_id,
                'models' => $request->models,
                'has_custom_prompt' => !empty($request->custom_prompt)
            ]);

            return response()->json([
                'job_id' => $jobId,
                'status' => 'processing',
                'reference_analysis_id' => $request->reference_analysis_id,
                'total_texts' => $textAnalyses->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Analizės pakartojimo klaida', [
                'error' => $e->getMessage(),
                'reference_analysis_id' => $request->reference_analysis_id ?? 'nežinomas'
            ]);

            return response()->json([
                'error' => 'Analizės pakartojimo klaida',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gauti sistemos statusą ir modelių prieinamumą.
     */
    public function health(): JsonResponse
    {
        try {
            $models = config('llm.models', []);
            $modelStatus = [];
            
            foreach ($models as $key => $config) {
                $modelStatus[$key] = [
                    'status' => !empty($config['api_key']) ? 'available' : 'not_configured',
                    'configured' => !empty($config['api_key']),
                    'rate_limit' => $config['rate_limit'] ?? 50
                ];
            }
            
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'services' => [
                    'database' => 'connected',
                    'queue' => 'operational'
                ],
                'models' => $modelStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gauti galimų modelių sąrašą.
     */
    public function models(): JsonResponse
    {
        try {
            $models = config('llm.models', []);
            $availableModels = [];
            
            foreach ($models as $key => $config) {
                $provider = 'Unknown';
                if (strpos($key, 'claude') === 0) $provider = 'Anthropic';
                elseif (strpos($key, 'gemini') === 0) $provider = 'Google';
                elseif (strpos($key, 'gpt') === 0) $provider = 'OpenAI';
                
                $availableModels[] = [
                    'key' => $key,
                    'name' => ucfirst(str_replace('-', ' ', $key)),
                    'provider' => $provider,
                    'model' => $config['model'] ?? $key,
                    'configured' => !empty($config['api_key']),
                    'available' => !empty($config['api_key']),
                    'rate_limit' => $config['rate_limit'] ?? 50,
                    'max_tokens' => $config['max_tokens'] ?? 4096
                ];
            }
            
            return response()->json([
                'models' => $availableModels
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Modelių sąrašo gavimo klaida',
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

    /**
     * Pakartoti analizę su tais pačiais tekstais ir modeliais.
     */
    public function repeat(Request $request)
    {
        $request->validate([
            'reference_job_id' => 'required|string|exists:analysis_jobs,job_id',
            'prompt_type' => 'required|in:keep,standard,custom',
            'custom_prompt' => 'nullable|string|max:10000',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            // Gauti originalią analizę
            $originalJob = AnalysisJob::where('job_id', $request->reference_job_id)
                ->with('textAnalyses')
                ->firstOrFail();

            if ($originalJob->status !== 'completed') {
                return redirect()->back()->with('error', 'Galima pakartoti tik sėkmingai baigtą analizę.');
            }

            // Nustatyti prompt'ą
            $customPrompt = null;
            switch ($request->prompt_type) {
                case 'keep':
                    $customPrompt = $originalJob->custom_prompt;
                    break;
                case 'custom':
                    $customPrompt = $request->custom_prompt;
                    break;
                case 'standard':
                default:
                    $customPrompt = null;
                    break;
            }

            // Sukurti naują analizės darbą
            $newJobId = Str::uuid();
            $newJob = AnalysisJob::create([
                'job_id' => $newJobId,
                'status' => 'pending',
                'custom_prompt' => $customPrompt,
                'reference_analysis_id' => $request->reference_job_id,
                'name' => $request->name,
                'description' => $request->description,
                'total_texts' => $originalJob->textAnalyses->count(),
                'processed_texts' => 0,
            ]);

            // Kopijuoti tekstų analizės su tais pačiais duomenimis
            $textAnalysesToCreate = [];
            $modelsUsed = [];

            foreach ($originalJob->textAnalyses as $originalTextAnalysis) {
                $textAnalysesToCreate[] = [
                    'job_id' => $newJobId,
                    'text_id' => $originalTextAnalysis->text_id,
                    'content' => $originalTextAnalysis->content,
                    'expert_annotations' => json_encode($originalTextAnalysis->expert_annotations),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Surinkti naudotus modelius
                $models = $originalTextAnalysis->getAllModelAnnotations();
                $modelsUsed = array_merge($modelsUsed, array_keys($models));
            }

            // Sukurti naujus tekstų analizės įrašus
            TextAnalysis::insert($textAnalysesToCreate);
            
            $modelsUsed = array_unique($modelsUsed);

            // Paleisti analizės darbus
            $createdTextAnalyses = TextAnalysis::where('job_id', $newJobId)->get();
            
            foreach ($createdTextAnalyses as $textAnalysis) {
                foreach ($modelsUsed as $modelName) {
                    AnalyzeTextJob::dispatch($textAnalysis->id, $modelName, $newJobId)
                        ->onQueue('analysis');
                }
            }

            // Atnaujinti darbo statusą
            $newJob->update(['status' => 'processing']);

            Log::info('Pakartotinė analizė paleista', [
                'original_job_id' => $request->reference_job_id,
                'new_job_id' => $newJobId,
                'texts_count' => count($textAnalysesToCreate),
                'models_count' => count($modelsUsed),
                'prompt_type' => $request->prompt_type
            ]);

            return redirect()->route('progress', ['jobId' => $newJobId])
                ->with('success', 'Pakartotinė analizė sėkmingai paleista!');

        } catch (\Exception $e) {
            Log::error('Pakartotinės analizės klaida', [
                'reference_job_id' => $request->reference_job_id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Įvyko klaida paleidžiant pakartotinę analizę: ' . $e->getMessage());
        }
    }
}