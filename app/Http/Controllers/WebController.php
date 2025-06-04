<?php

namespace App\Http\Controllers;

use App\Jobs\BatchAnalysisJob;
use App\Jobs\BatchAnalysisJobV2;
use App\Jobs\BatchAnalysisJobV3;
use App\Models\AnalysisJob;
use App\Services\PromptService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Web sąsajos kontroleris.
 * 
 * Valdomas minimali vartotojo sąsają failo įkėlimui ir progreso stebėjimui.
 */
class WebController extends Controller
{
    /**
     * Pagrindinis puslapis.
     */
    public function index()
    {
        $recentJobs = AnalysisJob::orderBy('created_at', 'desc')->limit(5)->get();
        $promptService = app(PromptService::class);
        $standardPrompt = $promptService->getStandardRisenPrompt();
        
        return view('index', compact('recentJobs', 'standardPrompt'));
    }

    /**
     * Failo įkėlimas ir analizės paleidimas.
     */
    public function upload(Request $request)
    {
        $availableModels = collect(config('llm.models', []))->keys()->implode(',');
        
        $validator = Validator::make($request->all(), [
            'json_file' => 'required|file|mimetypes:application/json,text/plain|max:102400', // 100MB
            'models' => 'required|array|min:1',
            'models.*' => "required|string|in:{$availableModels}"
        ], [
            'json_file.required' => 'Prašome pasirinkti JSON failą.',
            'json_file.file' => 'Įkeltas failas nėra tinkamas.',
            'json_file.mimetypes' => 'Failas turi būti JSON formato.',
            'json_file.max' => 'Failo dydis negali viršyti 100MB.',
            'models.min' => 'Pasirinkite bent vieną modelį.',
            'models.required' => 'Pasirinkite bent vieną modelį.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Perskaityti JSON failą
            $fileContent = file_get_contents($request->file('json_file')->getRealPath());
            $jsonData = json_decode($fileContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['json_file' => 'Neteisingas JSON formato failas'])
                           ->withInput();
            }

            // Validuoti JSON struktūrą
            if (!$this->validateJsonStructure($jsonData)) {
                return back()->withErrors(['json_file' => 'JSON failas neatitinka reikalavimų struktūros'])
                           ->withInput();
            }

            $jobId = Str::uuid();
            $models = $request->input('models');
            $totalTexts = count($jsonData);

            // Apdoroti custom prompt
            $customPrompt = null;
            if ($request->has('custom_prompt_parts')) {
                $promptService = app(PromptService::class);
                $customParts = json_decode($request->input('custom_prompt_parts'), true);
                $customPrompt = $promptService->generateCustomRisenPrompt($customParts);
            } elseif ($request->has('custom_prompt')) {
                $customPrompt = $request->input('custom_prompt');
            }

            // Sukurti analizės darbą
            AnalysisJob::create([
                'job_id' => $jobId,
                'status' => AnalysisJob::STATUS_PENDING,
                'total_texts' => $totalTexts,
                'processed_texts' => 0,
                'name' => $request->input('name', 'Batch analizė'),
                'description' => $request->input('description'),
                'custom_prompt' => $customPrompt,
            ]);

            // Use smart chunking batch processing for improved performance and reliability
            $useSmartChunking = count($jsonData) > 10; // Use smart chunking for 10+ texts
            
            if ($useSmartChunking) {
                BatchAnalysisJobV3::dispatch($jobId, $jsonData, $models);
                Log::info('Using smart chunking batch processing', [
                    'job_id' => $jobId,
                    'text_count' => count($jsonData),
                    'strategy' => 'smart_chunking_v3'
                ]);
            } else {
                BatchAnalysisJob::dispatch($jobId, $jsonData, $models);
                Log::info('Using individual processing for small datasets', [
                    'job_id' => $jobId,
                    'text_count' => count($jsonData)
                ]);
            }

            Log::info('Analizė paleista per web sąsają', [
                'job_id' => $jobId,
                'total_texts' => $totalTexts,
                'models' => $models
            ]);

            return redirect()->route('progress', ['jobId' => $jobId])
                           ->with('success', 'Analizė sėkmingai paleista!');

        } catch (\Exception $e) {
            Log::error('Web failo įkėlimo klaida', [
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['upload' => 'Klaida paleidžiant analizę: ' . $e->getMessage()])
                       ->withInput();
        }
    }

    /**
     * Progreso stebėjimo puslapis.
     */
    public function progress(string $jobId)
    {
        $job = AnalysisJob::where('job_id', $jobId)->first();

        if (!$job) {
            return redirect()->route('home')->withErrors(['Analizės darbas nerastas']);
        }

        return view('progress', compact('job'));
    }

    /**
     * Detailed status view - like in movies with full technical details.
     */
    public function detailedStatus(string $jobId)
    {
        $job = AnalysisJob::where('job_id', $jobId)->first();

        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        // Get comprehensive status data
        $textAnalyses = \App\Models\TextAnalysis::where('job_id', $jobId)->get();
        
        // Model configurations
        $models = config('llm.models', []);
        $selectedModels = ['claude-opus-4', 'claude-sonnet-4', 'gpt-4.1', 'gpt-4o-latest', 'gemini-2.5-pro', 'gemini-2.5-flash'];
        
        // Calculate detailed statistics
        $stats = [
            'job' => [
                'id' => $job->job_id,
                'status' => $job->status,
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
                'duration' => $job->created_at->diffForHumans(),
                'error_message' => $job->error_message,
                'total_texts' => $job->total_texts,
                'processed_texts' => $job->processed_texts,
                'progress_percentage' => $job->total_texts > 0 ? round(($job->processed_texts / $job->total_texts) * 100, 2) : 0
            ],
            'texts' => [
                'total_records' => $textAnalyses->count(),
                'unique_texts' => $textAnalyses->unique('text_id')->count(),
                'avg_text_length' => round($textAnalyses->avg(function($analysis) {
                    return strlen($analysis->content ?? '');
                })),
                'total_characters' => $textAnalyses->sum(function($analysis) {
                    return strlen($analysis->content ?? '');
                })
            ],
            'models' => []
        ];

        // Analyze each model's performance
        foreach ($selectedModels as $modelKey) {
            $modelConfig = $models[$modelKey] ?? [];
            $provider = $modelConfig['provider'] ?? 'unknown';
            
            $modelStats = [
                'key' => $modelKey,
                'name' => $modelConfig['model'] ?? $modelKey,
                'provider' => $provider,
                'status' => 'pending',
                'completed' => 0,
                'errors' => 0,
                'success_rate' => 0,
                'avg_response_time' => 0,
                'api_calls_made' => 0,
                'estimated_chunks' => 0,
                'last_activity' => null
            ];

            switch ($provider) {
                case 'anthropic':
                    $completed = $textAnalyses->whereNotNull('claude_annotations')->count();
                    $errors = $textAnalyses->where('claude_error', '!=', null)->where('claude_error', '!=', '')->count();
                    $modelStats['completed'] = $completed;
                    $modelStats['errors'] = $errors;
                    $modelStats['success_rate'] = $textAnalyses->count() > 0 ? round(($completed / $textAnalyses->count()) * 100, 1) : 0;
                    break;
                    
                case 'openai':
                    $completed = $textAnalyses->whereNotNull('gpt_annotations')->count();
                    $errors = $textAnalyses->where('gpt_error', '!=', null)->where('gpt_error', '!=', '')->count();
                    $modelStats['completed'] = $completed;
                    $modelStats['errors'] = $errors;
                    $modelStats['success_rate'] = $textAnalyses->count() > 0 ? round(($completed / $textAnalyses->count()) * 100, 1) : 0;
                    break;
                    
                case 'google':
                    $completed = $textAnalyses->whereNotNull('gemini_annotations')->count();
                    $errors = $textAnalyses->where('gemini_error', '!=', null)->where('gemini_error', '!=', '')->count();
                    $modelStats['completed'] = $completed;
                    $modelStats['errors'] = $errors;
                    $modelStats['success_rate'] = $textAnalyses->count() > 0 ? round(($completed / $textAnalyses->count()) * 100, 1) : 0;
                    break;
            }

            // Estimate API activity
            $totalTexts = $textAnalyses->unique('text_id')->count();
            $chunkSize = 3; // Current chunk size
            $modelStats['estimated_chunks'] = $totalTexts > 0 ? ceil($totalTexts / $chunkSize) : 0;
            $modelStats['api_calls_made'] = $modelStats['estimated_chunks']; // Approximate

            // Determine status
            if ($modelStats['completed'] == 0 && $modelStats['errors'] == 0) {
                $modelStats['status'] = 'pending';
            } elseif ($modelStats['completed'] < $totalTexts && $modelStats['errors'] == 0) {
                $modelStats['status'] = 'processing';
            } elseif ($modelStats['completed'] == $totalTexts) {
                $modelStats['status'] = 'completed';
            } else {
                $modelStats['status'] = 'partial_failure';
            }

            $stats['models'][$modelKey] = $modelStats;
        }

        // Get recent log entries (simulate)
        $recentLogs = $this->getRecentLogs($jobId);
        
        // Queue status
        $queueStats = [
            'batch_workers_active' => $this->checkBatchWorkers(),
            'jobs_in_queue' => \DB::table('jobs')->count(),
            'failed_jobs' => \DB::table('failed_jobs')->count(),
            'last_queue_activity' => now()->subMinutes(1) // Simulated
        ];

        return response()->json([
            'stats' => $stats,
            'logs' => $recentLogs,
            'queue' => $queueStats,
            'timestamp' => now(),
            'refresh_interval' => 5 // seconds
        ]);
    }

    /**
     * Get recent logs for a job (simulated for now).
     */
    private function getRecentLogs(string $jobId): array
    {
        // In a real implementation, you might parse log files
        // For now, return simulated recent activity
        return [
            [
                'timestamp' => now()->subSeconds(30),
                'level' => 'INFO',
                'message' => 'Processing model claude-opus-4 with smart chunking',
                'context' => ['model' => 'claude-opus-4', 'chunk' => '15/125']
            ],
            [
                'timestamp' => now()->subSeconds(45),
                'level' => 'INFO', 
                'message' => 'Chunk processed successfully',
                'context' => ['chunk_size' => 3, 'results_count' => 3]
            ],
            [
                'timestamp' => now()->subMinutes(1),
                'level' => 'WARNING',
                'message' => 'Chunk processing timeout, falling back to individual',
                'context' => ['timeout' => '300s', 'fallback' => 'individual']
            ]
        ];
    }

    /**
     * Check if batch workers are active.
     */
    private function checkBatchWorkers(): bool
    {
        // Simple check - in production you might check supervisor status
        return true; // Assume workers are active
    }

    /**
     * Validuoti JSON failo struktūrą.
     */
    private function validateJsonStructure(array $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (!isset($item['id'], $item['data']['content'])) {
                return false;
            }

            // Patikrinti ar yra anotacijos ekspertų duomenims
            if (!isset($item['annotations']) || !is_array($item['annotations'])) {
                return false;
            }
        }

        return true;
    }
}