<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeTextJob;
use App\Jobs\BatchAnalysisJob;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Services\MetricsService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * Analizės kontroleris.
 * 
 * Valdomas visas analizės procesą ir API endpointus.
 */
#[OA\Info(
    version: "1.0.0",
    title: "Propagandos analizės API",
    description: "API sistema propagandos ir dezinformacijos analizei lietuvių kalbos tekstuose naudojant AI modelius ir ATSPARA metodologiją",
    contact: new OA\Contact(
        name: "Marijus Plančiūnas",
        email: "marijus.planciunas@mif.stud.vu.lt"
    ),
    license: new OA\License(
        name: "MIT",
        url: "https://opensource.org/licenses/MIT"
    )
)]
#[OA\Server(
    url: "http://propaganda.local",
    description: "Development server"
)]
#[OA\Tag(
    name: "analysis",
    description: "Propaganda analizės operacijos"
)]
#[OA\Tag(
    name: "system",
    description: "Sistemos informacija ir modelių valdymas"
)]
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
    public function show(string $jobId, Request $request)
    {
        $analysis = AnalysisJob::where('job_id', $jobId)->firstOrFail();
        
        // Paginate text analyses for large datasets with filtered metrics
        $textAnalyses = TextAnalysis::where('job_id', $analysis->job_id)
            ->with(['comparisonMetrics' => function($query) use ($analysis) {
                $query->where('job_id', $analysis->job_id);
            }])
            ->paginate(50, ['*'], 'page', $request->get('page', 1));
            
        $statistics = $this->metricsService->calculateJobStatistics($analysis);
        
        // Get used models from comparison metrics (most accurate way)
        $usedModels = ComparisonMetric::where('job_id', $analysis->job_id)
            ->distinct('model_name')
            ->pluck('model_name')
            ->toArray();
        
        // Calculate actual text count and model count for better clarity
        $actualTextCount = TextAnalysis::where('job_id', $analysis->job_id)->distinct('text_id')->count();
        $modelCount = count($usedModels);
        
        return view('analyses.show', compact('analysis', 'statistics', 'textAnalyses', 'usedModels', 'actualTextCount', 'modelCount'));
    }

    /**
     * Analizuoti vieną tekstą.
     */
    #[OA\Post(
        path: "/api/analyze",
        operationId: "analyzeSingle",
        description: "Paleisti vieno teksto propagandos analizę naudojant pasirinktus AI modelius",
        summary: "Vieno teksto analizė",
        tags: ["analysis"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["text_id", "content", "models"],
                properties: [
                    new OA\Property(property: "text_id", type: "string", example: "text_001"),
                    new OA\Property(property: "content", type: "string", example: "Analizuojamas tekstas..."),
                    new OA\Property(
                        property: "models", 
                        type: "array", 
                        items: new OA\Items(type: "string"),
                        example: ["claude-opus-4", "gpt-4.1"]
                    ),
                    new OA\Property(property: "custom_prompt", type: "string", nullable: true),
                    new OA\Property(property: "expert_annotations", type: "object", nullable: true),
                    new OA\Property(property: "name", type: "string", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Analizė sėkmingai pradėta",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "job_id", type: "string", example: "uuid-here"),
                        new OA\Property(property: "message", type: "string", example: "Analizė pradėta"),
                        new OA\Property(property: "progress_url", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function analyzeSingle(Request $request): JsonResponse
    {
        $availableModels = collect(config('llm.models', []))->keys()->implode(',');
        
        $validator = Validator::make($request->all(), [
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
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
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
                'text_id' => 'single_' . uniqid(),
                'content' => $request->content,
                'expert_annotations' => $request->input('expert_annotations', []), // Pasirinktinės ekspertų anotacijos
            ]);

            // Paleisti analizės darbus
            foreach ($request->models as $model) {
                AnalyzeTextJob::dispatch($textAnalysis->id, $model, $jobId);
            }

            Log::info('Paleista vieno teksto analizė', [
                'job_id' => $jobId,
                'text_id' => $textAnalysis->text_id,
                'models' => $request->models
            ]);

            return response()->json([
                'job_id' => $jobId,
                'message' => 'Analizė sėkmingai pradėta',
                'status' => 'processing',
                'text_id' => $textAnalysis->text_id,
                'progress_url' => route('progress', ['jobId' => $jobId])
            ]);

        } catch (\Exception $e) {
            Log::error('Vieno teksto analizės klaida', [
                'error' => $e->getMessage(),
                'content' => substr($request->content ?? 'nežinomas', 0, 50)
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
    #[OA\Post(
        path: "/api/analyze-batch",
        operationId: "analyzeBatch",
        description: "Paleisti kelių tekstų propagandos analizę iš JSON failo naudojant pasirinktus AI modelius",
        summary: "Batch tekstų analizė",
        tags: ["analysis"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["file_content", "models"],
                properties: [
                    new OA\Property(
                        property: "file_content", 
                        type: "array", 
                        items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "string"),
                                new OA\Property(
                                    property: "data", 
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "content", type: "string")
                                    ]
                                ),
                                new OA\Property(property: "annotations", type: "object")
                            ]
                        )
                    ),
                    new OA\Property(
                        property: "models", 
                        type: "array", 
                        items: new OA\Items(type: "string"),
                        example: ["claude-opus-4", "gpt-4.1"]
                    ),
                    new OA\Property(property: "custom_prompt", type: "string", nullable: true),
                    new OA\Property(property: "reference_analysis_id", type: "string", nullable: true),
                    new OA\Property(property: "name", type: "string", nullable: true),
                    new OA\Property(property: "description", type: "string", nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Batch analizė sėkmingai pradėta",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "job_id", type: "string"),
                        new OA\Property(property: "status", type: "string"),
                        new OA\Property(property: "total_texts", type: "integer")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
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
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
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
                'message' => 'Batch analizė sėkmingai pradėta',
                'status' => 'processing',
                'total_texts' => $totalTexts,
                'progress_url' => route('progress', ['jobId' => $jobId])
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
    #[OA\Get(
        path: "/api/results/{jobId}",
        operationId: "getResults",
        description: "Gauti detalius analizės rezultatus JSON formatu",
        summary: "Gauti analizės rezultatus",
        tags: ["analysis"],
        parameters: [
            new OA\Parameter(
                name: "jobId",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string"),
                example: "uuid-here"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Analizės rezultatai",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "job_id", type: "string"),
                        new OA\Property(property: "status", type: "string"),
                        new OA\Property(property: "results", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "statistics", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Analizė nerasta")
        ]
    )]
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

            // Build results array for API compatibility
            $results = $job->textAnalyses->map(function ($textAnalysis) {
                return [
                    'text_id' => $textAnalysis->text_id,
                    'content' => $textAnalysis->content,
                    'claude_result' => $textAnalysis->claude_annotations,
                    'gemini_result' => $textAnalysis->gemini_annotations,
                    'gpt_result' => $textAnalysis->gpt_annotations,
                    'expert_annotations' => $textAnalysis->expert_annotations,
                ];
            });

            $response = [
                'job_id' => $jobId,
                'status' => $job->status,
                'results' => $results,
                'has_expert_annotations' => $hasExpertAnnotations,
                'detailed_results' => route('api.results.export', ['jobId' => $jobId])
            ];

            if ($hasExpertAnnotations) {
                $response['comparison_metrics'] = $this->metricsService->calculateAggregatedMetrics($jobId);
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
    #[OA\Get(
        path: "/api/results/{jobId}/export",
        operationId: "exportResults",
        description: "Eksportuoti analizės rezultatus į CSV formatą",
        summary: "Eksportuoti rezultatus",
        tags: ["analysis"],
        parameters: [
            new OA\Parameter(
                name: "jobId",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string"),
                example: "uuid-here"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "CSV failas",
                content: new OA\MediaType(
                    mediaType: "text/csv",
                    schema: new OA\Schema(type: "string")
                )
            ),
            new OA\Response(response: 404, description: "Analizė nerasta"),
            new OA\Response(response: 400, description: "Analizė dar nebaigta")
        ]
    )]
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
    #[OA\Get(
        path: "/api/status/{jobId}",
        operationId: "getStatus",
        description: "Gauti analizės darbo esamą statusą ir progresą",
        summary: "Gauti analizės statusą",
        tags: ["analysis"],
        parameters: [
            new OA\Parameter(
                name: "jobId",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string"),
                example: "uuid-here"
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Analizės statusas",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "job_id", type: "string"),
                        new OA\Property(property: "status", type: "string", enum: ["pending", "processing", "completed", "failed"]),
                        new OA\Property(property: "progress", type: "number", format: "float"),
                        new OA\Property(property: "processed_texts", type: "integer"),
                        new OA\Property(property: "total_texts", type: "integer"),
                        new OA\Property(property: "created_at", type: "string", format: "datetime"),
                        new OA\Property(property: "updated_at", type: "string", format: "datetime"),
                        new OA\Property(property: "error_message", type: "string", nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Analizė nerasta")
        ]
    )]
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
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
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
    #[OA\Get(
        path: "/api/health",
        operationId: "systemHealth",
        description: "Patikrinti sistemos būklę ir AI modelių prieinamumą",
        summary: "Sistemos sveikatos tikrinimas",
        tags: ["system"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Sistemos būklė",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", enum: ["healthy", "unhealthy"]),
                        new OA\Property(property: "timestamp", type: "string", format: "datetime"),
                        new OA\Property(
                            property: "services", 
                            type: "object",
                            properties: [
                                new OA\Property(property: "database", type: "string"),
                                new OA\Property(property: "queue", type: "string")
                            ]
                        ),
                        new OA\Property(
                            property: "models", 
                            type: "object",
                            additionalProperties: new OA\AdditionalProperties(
                                properties: [
                                    new OA\Property(property: "status", type: "string"),
                                    new OA\Property(property: "configured", type: "boolean"),
                                    new OA\Property(property: "rate_limit", type: "integer")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Sistemos klaida")
        ]
    )]
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
    #[OA\Get(
        path: "/api/models",
        operationId: "getModels",
        description: "Gauti visų konfigūruotų AI modelių sąrašą su jų informacija",
        summary: "Gauti modelių sąrašą",
        tags: ["system"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Modelių sąrašas",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "models", 
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "key", type: "string"),
                                    new OA\Property(property: "name", type: "string"),
                                    new OA\Property(property: "provider", type: "string"),
                                    new OA\Property(property: "model", type: "string"),
                                    new OA\Property(property: "configured", type: "boolean"),
                                    new OA\Property(property: "available", type: "boolean"),
                                    new OA\Property(property: "rate_limit", type: "integer"),
                                    new OA\Property(property: "max_tokens", type: "integer")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Sistemos klaida")
        ]
    )]
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
                    'description' => $config['description'] ?? '',
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

    /**
     * Get text annotations for highlighting propaganda techniques
     */
    public function getTextAnnotations(Request $request, $textAnalysisId): JsonResponse
    {
        try {
            $textAnalysis = TextAnalysis::find($textAnalysisId);
            
            if (!$textAnalysis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tekstas nerastas'
                ], 404);
            }
            
            $viewType = $request->get('view', 'ai'); // 'ai' or 'expert'
            $annotationsEnabled = $request->get('enabled', 'true') === 'true';
            
            $originalText = $textAnalysis->content;
            $annotations = [];
            $legend = [];
            
            // If annotations are disabled, return plain text
            if (!$annotationsEnabled) {
                return response()->json([
                    'success' => true,
                    'content' => $originalText,
                    'text' => $originalText,
                    'annotations' => [],
                    'legend' => [],
                    'view_type' => $viewType
                ]);
            }
            
            if ($viewType === 'expert') {
                // Get expert annotations
                $expertAnnotations = $textAnalysis->expert_annotations;
                
                if (empty($expertAnnotations)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Šiam tekstui nėra ekspertų anotacijų'
                    ]);
                }
                
                // Process expert annotations - handle Label Studio format
                $annotationsToProcess = [];
                
                // Check if this is Label Studio export format (array with result field)
                if (is_array($expertAnnotations) && isset($expertAnnotations[0]['result'])) {
                    $annotationsToProcess = $expertAnnotations[0]['result'];
                } elseif (isset($expertAnnotations['annotations'])) {
                    $annotationsToProcess = $expertAnnotations['annotations'];
                } else {
                    $annotationsToProcess = $expertAnnotations;
                }
                
                if (!empty($annotationsToProcess)) {
                    $techniqueCount = [];
                    
                    foreach ($annotationsToProcess as $annotation) {
                        // Handle Label Studio format
                        if (isset($annotation['type']) && $annotation['type'] === 'labels' && isset($annotation['value'])) {
                            $labels = $annotation['value']['labels'] ?? [];
                            foreach ($labels as $technique) {
                                if (!isset($techniqueCount[$technique])) {
                                    $techniqueCount[$technique] = 0;
                                }
                                $techniqueCount[$technique]++;
                                
                                $annotations[] = [
                                    'start' => $annotation['value']['start'],
                                    'end' => $annotation['value']['end'],
                                    'technique' => $technique,
                                    'text' => $annotation['value']['text'] ?? ''
                                ];
                            }
                        }
                        // Handle old format
                        elseif (isset($annotation['value'])) {
                            $labels = $annotation['value']['labels'] ?? [];
                            foreach ($labels as $technique) {
                                if (!isset($techniqueCount[$technique])) {
                                    $techniqueCount[$technique] = 0;
                                }
                                $techniqueCount[$technique]++;
                                
                                $annotations[] = [
                                    'start' => $annotation['value']['start'],
                                    'end' => $annotation['value']['end'],
                                    'technique' => $technique,
                                    'text' => $annotation['value']['text'] ?? ''
                                ];
                            }
                        }
                    }
                    
                    // Create legend for expert annotations
                    $legend = $this->createLegend(array_keys($techniqueCount));
                }
            } else {
                // Get AI annotations (merge from all models or specific model)
                $selectedModel = $request->get('model', 'all');
                $modelAnnotations = $textAnalysis->getAllModelAnnotations();
                $allTechniques = [];
                $techniquePositions = [];
                
                // Filter by specific model if requested
                if ($selectedModel !== 'all' && isset($modelAnnotations[$selectedModel])) {
                    $modelAnnotations = [$selectedModel => $modelAnnotations[$selectedModel]];
                } elseif ($selectedModel !== 'all') {
                    // Model not found, return empty annotations
                    $modelAnnotations = [];
                }
                
                foreach ($modelAnnotations as $modelName => $modelData) {
                    if (isset($modelData['annotations'])) {
                        foreach ($modelData['annotations'] as $annotation) {
                            if (isset($annotation['value']['labels'])) {
                                foreach ($annotation['value']['labels'] as $technique) {
                                    $key = $annotation['value']['start'] . '-' . $annotation['value']['end'] . '-' . $technique;
                                    
                                    if (!isset($techniquePositions[$key])) {
                                        $techniquePositions[$key] = [
                                            'start' => $annotation['value']['start'],
                                            'end' => $annotation['value']['end'],
                                            'technique' => $technique,
                                            'text' => $annotation['value']['text'] ?? '',
                                            'count' => 0,
                                            'models' => []
                                        ];
                                    }
                                    $techniquePositions[$key]['count']++;
                                    $techniquePositions[$key]['models'][] = $modelName;
                                    $allTechniques[$technique] = true;
                                }
                            }
                        }
                    }
                }
                
                // Filter annotations that appear in at least 1 model (or majority)
                foreach ($techniquePositions as $annotation) {
                    if ($annotation['count'] >= 1) { // At least 1 model found this
                        $annotations[] = [
                            'start' => $annotation['start'],
                            'end' => $annotation['end'],
                            'technique' => $annotation['technique'],
                            'text' => $annotation['text'],
                            'models' => $annotation['models']
                        ];
                    }
                }
                
                // Create legend for AI annotations
                $legend = $this->createLegend(array_keys($allTechniques));
            }
            
            return response()->json([
                'success' => true,
                'content' => $originalText,
                'text' => $originalText,
                'annotations' => $annotations,
                'legend' => $legend,
                'view_type' => $viewType
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting text annotations', [
                'text_analysis_id' => $textAnalysisId,
                'view' => $request->get('view'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Nepavyko įkelti anotacijų'
            ], 500);
        }
    }
    
    /**
     * Create legend with colors for propaganda techniques
     */
    private function createLegend(array $techniques): array
    {
        // Color palette for different techniques
        $colors = [
            '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57',
            '#ff9ff3', '#54a0ff', '#5f27cd', '#00d2d3', '#ff9f43',
            '#ee5a24', '#0abde3', '#006ba6', '#f38ba8', '#a8e6cf',
            '#ff8b94', '#ffaaa5', '#ff677d', '#d63031', '#74b9ff',
            '#fdcb6e'
        ];
        
        $legend = [];
        $sortedTechniques = array_values($techniques);
        sort($sortedTechniques);
        
        foreach ($sortedTechniques as $index => $technique) {
            $legend[] = [
                'technique' => $technique,
                'color' => $colors[$index % count($colors)],
                'number' => $index + 1
            ];
        }
        
        return $legend;
    }
}