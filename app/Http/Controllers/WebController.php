<?php

namespace App\Http\Controllers;

use App\Jobs\BatchAnalysisJob;
use App\Jobs\BatchAnalysisJobV2;
use App\Jobs\BatchAnalysisJobV3;
use App\Jobs\BatchAnalysisJobV4;
use App\Models\AnalysisJob;
use App\Services\PromptService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
            'json_files' => 'required|array|min:1',
            'json_files.*' => 'required|file|mimetypes:application/json,text/plain|max:102400', // 100MB each
            'models' => 'required|array|min:1',
            'models.*' => "required|string|in:{$availableModels}"
        ], [
            'json_files.required' => 'Prašome pasirinkti bent vieną JSON failą.',
            'json_files.min' => 'Prašome pasirinkti bent vieną JSON failą.',
            'json_files.*.required' => 'Kiekvienas failas yra privalomas.',
            'json_files.*.file' => 'Įkeltas failas nėra tinkamas.',
            'json_files.*.mimetypes' => 'Kiekvienas failas turi būti JSON formato.',
            'json_files.*.max' => 'Kiekvieno failo dydis negali viršyti 100MB.',
            'models.min' => 'Pasirinkite bent vieną modelį.',
            'models.required' => 'Pasirinkite bent vieną modelį.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $models = $request->input('models');
            $files = $request->file('json_files');
            $createdJobs = [];
            
            // Apdoroti custom prompt
            $customPrompt = null;
            if ($request->has('custom_prompt_parts')) {
                $promptService = app(PromptService::class);
                $customParts = json_decode($request->input('custom_prompt_parts'), true);
                $customPrompt = $promptService->generateCustomRisenPrompt($customParts);
            } elseif ($request->has('custom_prompt')) {
                $customPrompt = $request->input('custom_prompt');
            }
            
            // Process each file separately
            foreach ($files as $index => $file) {
                // Perskaityti JSON failą
                $fileContent = file_get_contents($file->getRealPath());
                $jsonData = json_decode($fileContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return back()->withErrors(['json_files.*' => "Failas '{$file->getClientOriginalName()}' turi neteisingą JSON formatą"])
                               ->withInput();
                }

                // Validuoti JSON struktūrą
                if (!$this->validateJsonStructure($jsonData)) {
                    return back()->withErrors(['json_files.*' => "Failas '{$file->getClientOriginalName()}' neatitinka reikalavimų struktūros"])
                               ->withInput();
                }

                $jobId = Str::uuid();
                $textCount = count($jsonData);
                $modelCount = count($models);
                $totalTexts = $textCount * $modelCount; // Each text needs to be processed by each model
                
                // Generate unique name for each file
                $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $jobName = $request->input('name', 'Multi-file analizė') . " - {$fileName}";

                // Sukurti analizės darbą
                AnalysisJob::create([
                    'job_id' => $jobId,
                    'status' => AnalysisJob::STATUS_PENDING,
                    'total_texts' => $totalTexts,
                    'processed_texts' => 0,
                    'name' => $jobName,
                    'description' => $request->input('description'),
                    'custom_prompt' => $customPrompt,
                    'requested_models' => $models,
                ]);

                // Use file attachment processing for optimal performance and reliability
                BatchAnalysisJobV4::dispatch($jobId, $jsonData, $models);
                
                $createdJobs[] = [
                    'job_id' => $jobId,
                    'file_name' => $file->getClientOriginalName(),
                    'text_count' => $textCount // Show actual text count, not total processes
                ];
            }
            
            // Log overall operation
            Log::info('Multi-file analizė paleista per web sąsają', [
                'files_count' => count($files),
                'jobs_created' => count($createdJobs),
                'models' => $models
            ]);
            
            // If only one file, redirect to its progress page
            if (count($createdJobs) === 1) {
                return redirect()->route('progress', ['jobId' => $createdJobs[0]['job_id']])
                               ->with('success', 'Analizė sėkmingai paleista!');
            }
            
            // For multiple files, redirect to analyses list with success message
            $totalTexts = array_sum(array_column($createdJobs, 'text_count'));
            $successMessage = sprintf(
                'Sėkmingai sukurta %d analizės iš %d failų (%d tekstų iš viso)',
                count($createdJobs),
                count($files),
                $totalTexts
            );
            
            return redirect()->route('analyses.index')
                           ->with('success', $successMessage)
                           ->with('created_jobs', $createdJobs);

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
     * Mission Control system-wide monitoring dashboard.
     */
    public function missionControl(Request $request): JsonResponse
    {
        $jobFilter = $request->get('job_id'); // Optional job filter
        
        return $this->getSystemWideStatus($jobFilter);
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

            // Use ModelResult records for accurate tracking (new approach)
            $modelResults = \App\Models\ModelResult::where('job_id', $jobId)
                ->where('model_key', $modelKey)
                ->get();
            
            $completed = $modelResults->where('status', 'completed')->count();
            $errors = $modelResults->where('status', 'failed')->count();
            $totalForModel = $textAnalyses->count(); // Total texts for this model
            
            $modelStats['completed'] = $completed;
            $modelStats['errors'] = $errors;
            $modelStats['success_rate'] = $totalForModel > 0 ? round(($completed / $totalForModel) * 100, 1) : 0;
            
            // Get last activity from ModelResult timestamps
            $lastResult = $modelResults->whereNotNull('updated_at')->sortByDesc('updated_at')->first();
            $modelStats['last_activity'] = $lastResult ? $lastResult->updated_at->diffForHumans() : null;

            // Individual processing approach - each text gets its own API call
            $totalTexts = $textAnalyses->unique('text_id')->count();
            $modelStats['estimated_chunks'] = 0; // No chunking in individual processing
            $modelStats['api_calls_made'] = $completed + $errors; // Actual API calls made (completed + failed)

            // Determine status based on ModelResult records
            $totalProcessed = $completed + $errors;
            if ($totalProcessed == 0) {
                $modelStats['status'] = 'pending';
            } elseif ($totalProcessed < $totalTexts) {
                $modelStats['status'] = 'processing';
            } elseif ($completed == $totalTexts && $errors == 0) {
                $modelStats['status'] = 'completed';
            } elseif ($totalProcessed == $totalTexts && $errors > 0) {
                $modelStats['status'] = 'partial_failure';
            } else {
                $modelStats['status'] = 'processing';
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
        try {
            // Read actual Laravel log file and filter for this job
            $logFile = storage_path('logs/laravel.log');
            if (!file_exists($logFile)) {
                return [];
            }
            
            $logs = [];
            $file = new \SplFileObject($logFile);
            $file->seek(PHP_INT_MAX);
            $totalLines = $file->key() + 1;
            
            // Read last 1000 lines to find relevant entries
            $startLine = max(0, $totalLines - 1000);
            $file->seek($startLine);
            
            while (!$file->eof()) {
                $line = trim($file->current());
                $file->next();
                
                // Skip empty lines
                if (empty($line)) continue;
                
                // Parse Laravel log format: [timestamp] level.channel: message context
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+?)( \{.*\})?$/', $line, $matches)) {
                    $timestamp = $matches[1];
                    $level = strtoupper($matches[2]);
                    $message = $matches[3];
                    $contextJson = $matches[4] ?? '{}';
                    
                    // Try to parse context
                    $context = [];
                    if (!empty($contextJson)) {
                        $decoded = json_decode($contextJson, true);
                        if (is_array($decoded)) {
                            $context = $decoded;
                        }
                    }
                    
                    // Check if this log entry is relevant to our job
                    $isRelevant = false;
                    if (stripos($line, $jobId) !== false) {
                        $isRelevant = true;
                    } elseif (isset($context['job_id']) && 
                              ((is_string($context['job_id']) && $context['job_id'] === $jobId) ||
                              (is_object($context['job_id']) && property_exists($context['job_id'], 'Ramsey\\Uuid\\Lazy\\LazyUuidFromString') && $context['job_id']->{'Ramsey\\Uuid\\Lazy\\LazyUuidFromString'} === $jobId))) {
                        $isRelevant = true;
                    }
                    
                    if ($isRelevant) {
                        // Enhance message with file processing context
                        $enhancedMessage = $this->enhanceLogMessage($message, $context);
                        
                        $logs[] = [
                            'timestamp' => \Carbon\Carbon::parse($timestamp),
                            'level' => $level,
                            'message' => $enhancedMessage,
                            'context' => $context
                        ];
                    }
                }
            }
            
            // Return last 20 relevant entries
            return array_slice(array_reverse($logs), 0, 20);
            
        } catch (\Exception $e) {
            Log::warning('Error reading logs for status view', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to simulated logs for file-based processing
            return [
                [
                    'timestamp' => now()->subSeconds(10),
                    'level' => 'INFO',
                    'message' => 'File-based analysis in progress - check individual model status',
                    'context' => ['processing_type' => 'file_attachment']
                ]
            ];
        }
    }

    /**
     * Enhance log messages with better descriptions for file processing.
     */
    private function enhanceLogMessage(string $message, array $context): string
    {
        // Enhance messages based on context
        if (isset($context['status'])) {
            switch ($context['status']) {
                case 'uploading':
                    if (isset($context['model'])) {
                        return "📤 Uploading file to {$context['model']} API...";
                    }
                    break;
                case 'processing':
                    if (isset($context['model'])) {
                        return "🤖 {$context['model']} is analyzing the file...";
                    }
                    break;
                case 'completed':
                    if (isset($context['model'])) {
                        return "✅ {$context['model']} analysis completed";
                    }
                    break;
                case 'failed':
                    if (isset($context['model'])) {
                        return "❌ {$context['model']} analysis failed";
                    }
                    break;
            }
        }
        
        // Enhance by job type
        if (isset($context['job_type']) && $context['job_type'] === 'BatchAnalysisJobV4') {
            if (stripos($message, 'starting') !== false) {
                return "🚀 Starting file-based batch analysis";
            } elseif (stripos($message, 'completed') !== false) {
                return "🎉 File-based batch analysis completed";
            }
        }
        
        // Default enhancement based on keywords
        if (stripos($message, 'gemini file api') !== false) {
            return "📁 " . $message . " (using File API)";
        } elseif (stripos($message, 'claude') !== false && stripos($message, 'structured') !== false) {
            return "📄 " . $message . " (JSON in message)";
        } elseif (stripos($message, 'openai') !== false && stripos($message, 'structured') !== false) {
            return "📊 " . $message . " (full JSON)";
        } elseif (stripos($message, 'chunk failed') !== false) {
            $chunkInfo = "";
            if (isset($context['chunk'])) {
                $chunkInfo = " (Chunk {$context['chunk']})";
            }
            if (isset($context['error']) && stripos($context['error'], 'quota') !== false) {
                return "💳 Chunk processing failed - API quota exceeded{$chunkInfo}";
            } elseif (isset($context['error']) && stripos($context['error'], 'auth') !== false) {
                return "🔐 Chunk processing failed - Authentication error{$chunkInfo}";
            } elseif (isset($context['error']) && stripos($context['error'], 'prompt is too long') !== false) {
                // Extract token information if available
                if (preg_match('/(\d+) tokens > (\d+) maximum/', $context['error'], $matches)) {
                    $used = number_format($matches[1]);
                    $limit = number_format($matches[2]);
                    return "📏 Chunk too large - {$used} tokens > {$limit} limit{$chunkInfo}";
                } else {
                    return "📏 Chunk too large - Exceeds token limit{$chunkInfo}";
                }
            } elseif (isset($context['error']) && stripos($context['error'], 'Syntax error') !== false) {
                return "🔧 Chunk processing failed - JSON parsing error{$chunkInfo}";
            } else {
                return "⚠️ " . $message . $chunkInfo . " - Check API status";
            }
        }
        
        return $message;
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

    /**
     * Get system-wide status for Mission Control dashboard.
     */
    private function getSystemWideStatus(?string $jobFilter = null): JsonResponse
    {
        // Get all recent jobs (or filtered by job ID)
        $jobsQuery = AnalysisJob::orderBy('created_at', 'desc');
        
        if ($jobFilter) {
            $jobsQuery->where('job_id', $jobFilter);
        } else {
            $jobsQuery->limit(10); // Show last 10 jobs if no filter
        }
        
        $jobs = $jobsQuery->get();
        
        // Get all text analyses for these jobs with analysisJob relationship
        $jobIds = $jobs->pluck('job_id')->toArray();
        $textAnalyses = \App\Models\TextAnalysis::whereIn('job_id', $jobIds)->with('analysisJob')->get();
        
        // Model configurations
        $models = config('llm.models', []);
        
        // Calculate system-wide statistics
        $systemStats = [
            'overview' => [
                'total_jobs' => $jobs->count(),
                'active_jobs' => $jobs->whereIn('status', ['pending', 'processing'])->count(),
                'completed_jobs' => $jobs->where('status', 'completed')->count(),
                'failed_jobs' => $jobs->where('status', 'failed')->count(),
                'total_texts_processed' => $textAnalyses->count(),
                'unique_texts' => $textAnalyses->unique('text_id')->count(),
            ],
            'queue' => [
                'batch_workers_active' => $this->checkBatchWorkers(),
                'jobs_in_queue' => \DB::table('jobs')->count(),
                'failed_jobs' => \DB::table('failed_jobs')->count(),
                'last_activity' => now()->subMinutes(1) // TODO: Get actual last activity
            ],
            'models' => []
        ];

        // Calculate model statistics across all jobs
        foreach ($models as $modelKey => $modelConfig) {
            $provider = $modelConfig['provider'] ?? 'unknown';
            $modelName = $modelConfig['model'] ?? $modelKey;
            
            $modelStats = [
                'key' => $modelKey,
                'name' => $modelName,
                'provider' => $provider,
                'total_analyses' => 0,
                'successful' => 0,
                'failed' => 0,
                'pending' => 0,
                'success_rate' => 0,
                'avg_response_time' => null,
                'status' => 'idle'
            ];
            
            // Count analyses for this model across all jobs
            foreach ($textAnalyses as $analysis) {
                $hasResult = false;
                $hasFailed = false;
                $modelWasUsed = false;
                
                // FIRST: Check new architecture model results
                $modelResult = \App\Models\ModelResult::where('job_id', $analysis->job_id)
                    ->where('text_id', $analysis->text_id)
                    ->where('model_key', $modelKey)
                    ->first();
                
                if ($modelResult) {
                    $modelWasUsed = true;
                    if ($modelResult->isSuccessful()) {
                        $modelStats['successful']++;
                        $hasResult = true;
                    } else {
                        $modelStats['failed']++;
                        $hasFailed = true;
                    }
                } else {
                    // FALLBACK: Check legacy architecture only if no new results exist
                    switch ($provider) {
                        case 'anthropic':
                            if ($analysis->claude_annotations || $analysis->claude_error) {
                                // Determine which Claude model was actually used
                                $actualModel = $this->determineActualClaudeModel($analysis, $modelKey);
                                if ($actualModel === $modelKey) {
                                    $modelWasUsed = true;
                                    if ($analysis->claude_annotations) {
                                        $modelStats['successful']++;
                                        $hasResult = true;
                                    } else {
                                        $modelStats['failed']++;
                                        $hasFailed = true;
                                    }
                                }
                            } else {
                                // Check if this model was actually requested for this job
                                if ($analysis->analysisJob && 
                                    in_array($analysis->analysisJob->status, ['pending', 'processing'])) {
                                    
                                    // Check if model was in requested models
                                    $wasRequested = $analysis->analysisJob->requested_models &&
                                        in_array($modelKey, $analysis->analysisJob->requested_models);
                                    
                                    if ($wasRequested) {
                                        $modelWasUsed = true;
                                        // This is pending
                                    }
                                }
                            }
                            break;
                        case 'openai':
                            if ($analysis->gpt_annotations || $analysis->gpt_error) {
                                // Determine which GPT model was actually used
                                $actualModel = $this->determineActualGptModel($analysis, $modelKey);
                                if ($actualModel === $modelKey) {
                                    $modelWasUsed = true;
                                    if ($analysis->gpt_annotations) {
                                        $modelStats['successful']++;
                                        $hasResult = true;
                                    } else {
                                        $modelStats['failed']++;
                                        $hasFailed = true;
                                    }
                                }
                            } else {
                                // Check if this model was actually requested for this job
                                if ($analysis->analysisJob && 
                                    in_array($analysis->analysisJob->status, ['pending', 'processing'])) {
                                    
                                    // Check if model was in requested models
                                    $wasRequested = $analysis->analysisJob->requested_models &&
                                        in_array($modelKey, $analysis->analysisJob->requested_models);
                                    
                                    if ($wasRequested) {
                                        $modelWasUsed = true;
                                        // This is pending
                                    }
                                }
                            }
                            break;
                        case 'google':
                            if ($analysis->gemini_annotations || $analysis->gemini_error) {
                                // Determine which Gemini model was actually used
                                $actualModel = $this->determineActualGeminiModel($analysis, $modelKey);
                                if ($actualModel === $modelKey) {
                                    $modelWasUsed = true;
                                    if ($analysis->gemini_annotations) {
                                        $modelStats['successful']++;
                                        $hasResult = true;
                                    } else {
                                        $modelStats['failed']++;
                                        $hasFailed = true;
                                    }
                                }
                            } else {
                                // Check if this model was actually requested for this job
                                if ($analysis->analysisJob && 
                                    in_array($analysis->analysisJob->status, ['pending', 'processing'])) {
                                    
                                    // Check if model was in requested models
                                    $wasRequested = $analysis->analysisJob->requested_models &&
                                        in_array($modelKey, $analysis->analysisJob->requested_models);
                                    
                                    if ($wasRequested) {
                                        $modelWasUsed = true;
                                        // This is pending
                                    }
                                }
                            }
                            break;
                    }
                }
                
                if ($modelWasUsed) {
                    $modelStats['total_analyses']++;
                    if (!$hasResult && !$hasFailed) {
                        // This analysis was intended for this model but hasn't been processed
                        $modelStats['pending']++;
                    }
                }
            }
            
            // Calculate success rate
            if ($modelStats['total_analyses'] > 0) {
                $modelStats['success_rate'] = round(($modelStats['successful'] / $modelStats['total_analyses']) * 100, 1);
            }
            
            // Determine overall status
            if ($modelStats['pending'] > 0) {
                $modelStats['status'] = 'processing';
            } elseif ($modelStats['total_analyses'] === 0) {
                $modelStats['status'] = 'idle';
            } elseif ($modelStats['failed'] > 0 && $modelStats['successful'] === 0) {
                $modelStats['status'] = 'failed';
            } elseif ($modelStats['failed'] > 0) {
                $modelStats['status'] = 'partial_failure';
            } elseif ($modelStats['successful'] > 0) {
                $modelStats['status'] = 'operational';
            } else {
                $modelStats['status'] = 'idle';
            }
            
            $systemStats['models'][$modelKey] = $modelStats;
        }

        // Get recent system logs (filtered or system-wide)
        $recentLogs = $this->getSystemLogs($jobFilter);
        
        // Job details (if filtering by specific job)
        $jobDetails = null;
        if ($jobFilter && $jobs->isNotEmpty()) {
            $job = $jobs->first();
            $jobDetails = [
                'id' => $job->job_id,
                'name' => $job->name,
                'status' => $job->status,
                'created_at' => $job->created_at,
                'duration' => $job->created_at->diffForHumans(),
                'progress_percentage' => $job->total_texts > 0 ? round(($job->processed_texts / $job->total_texts) * 100, 2) : 0,
                'total_texts' => $job->total_texts,
                'processed_texts' => $job->processed_texts,
                'error_message' => $job->error_message
            ];
        }

        return response()->json([
            'system' => $systemStats,
            'job_details' => $jobDetails,
            'logs' => $recentLogs,
            'timestamp' => now(),
            'filtered_by_job' => $jobFilter,
            'refresh_interval' => 5
        ]);
    }

    /**
     * Determine which Claude model was actually used for an analysis.
     */
    private function determineActualClaudeModel($analysis, $expectedModelKey): string
    {
        // If we have actual model info, use it to determine the config key
        if ($analysis->claude_actual_model) {
            if (str_contains($analysis->claude_actual_model, 'sonnet')) {
                return 'claude-sonnet-4';
            } elseif (str_contains($analysis->claude_actual_model, 'opus')) {
                return 'claude-opus-4';
            }
        }
        
        // If we have model name info, use it
        if ($analysis->claude_model_name) {
            if (str_contains($analysis->claude_model_name, 'sonnet')) {
                return 'claude-sonnet-4';
            } elseif (str_contains($analysis->claude_model_name, 'opus')) {
                return 'claude-opus-4';
            }
        }
        
        // Default fallback - assume claude-opus-4 for any Claude analysis
        return 'claude-opus-4';
    }
    
    /**
     * Determine which GPT model was actually used for an analysis.
     */
    private function determineActualGptModel($analysis, $expectedModelKey): string
    {
        if ($analysis->gpt_actual_model) {
            if (str_contains($analysis->gpt_actual_model, 'gpt-4o')) {
                return 'gpt-4o-latest';
            } elseif (str_contains($analysis->gpt_actual_model, 'gpt-4.1')) {
                return 'gpt-4.1';
            }
        }
        
        if ($analysis->gpt_model_name) {
            if (str_contains($analysis->gpt_model_name, 'gpt-4o')) {
                return 'gpt-4o-latest';
            } elseif (str_contains($analysis->gpt_model_name, 'gpt-4.1')) {
                return 'gpt-4.1';
            }
        }
        
        // Default fallback
        return 'gpt-4.1';
    }
    
    /**
     * Determine which Gemini model was actually used for an analysis.
     */
    private function determineActualGeminiModel($analysis, $expectedModelKey): string
    {
        if ($analysis->gemini_actual_model) {
            if (str_contains($analysis->gemini_actual_model, 'flash')) {
                return 'gemini-2.5-flash';
            } elseif (str_contains($analysis->gemini_actual_model, 'pro')) {
                return 'gemini-2.5-pro';
            }
        }
        
        if ($analysis->gemini_model_name) {
            if (str_contains($analysis->gemini_model_name, 'flash')) {
                return 'gemini-2.5-flash';
            } elseif (str_contains($analysis->gemini_model_name, 'pro')) {
                return 'gemini-2.5-pro';
            }
        }
        
        // Default fallback
        return 'gemini-2.5-pro';
    }

    /**
     * Get system logs (optionally filtered by job ID).
     */
    private function getSystemLogs(?string $jobFilter = null): array
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            if (!file_exists($logFile)) {
                return [];
            }
            
            $logs = [];
            $file = new \SplFileObject($logFile);
            $file->seek(PHP_INT_MAX);
            $totalLines = $file->key() + 1;
            
            // Read last 1000 lines
            $startLine = max(0, $totalLines - 1000);
            $file->seek($startLine);
            
            while (!$file->eof()) {
                $line = trim($file->current());
                $file->next();
                
                if (empty($line)) continue;
                
                // Parse Laravel log format
                if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)/', $line, $matches)) {
                    $timestamp = $matches[1];
                    $level = strtoupper($matches[2]);
                    $message = $matches[3];
                    
                    // Extract context if available
                    $context = [];
                    if (preg_match('/^(.+?) (\{.+\})$/', $message, $contextMatches)) {
                        $message = trim($contextMatches[1]);
                        try {
                            $context = json_decode($contextMatches[2], true) ?? [];
                        } catch (\Exception $e) {
                            // Ignore JSON parsing errors
                        }
                    }
                    
                    // Filter by job ID if specified
                    $isRelevant = true;
                    if ($jobFilter) {
                        $isRelevant = false;
                        if (stripos($line, $jobFilter) !== false ||
                            (isset($context['job_id']) && $context['job_id'] === $jobFilter)) {
                            $isRelevant = true;
                        }
                    } else {
                        // For system-wide view, include logs related to analysis system
                        if (stripos($line, 'BatchAnalysisJob') === false && 
                            stripos($line, 'ModelAnalysisJob') === false &&
                            stripos($line, 'AnalyzeTextJob') === false &&
                            stripos($line, 'ModelStatusService') === false &&
                            !isset($context['job_type'])) {
                            $isRelevant = false;
                        }
                    }
                    
                    if ($isRelevant) {
                        $enhancedMessage = $this->enhanceLogMessage($message, $context);
                        
                        $logs[] = [
                            'timestamp' => \Carbon\Carbon::parse($timestamp),
                            'level' => $level,
                            'message' => $enhancedMessage,
                            'context' => $context,
                            'job_id' => $context['job_id'] ?? null
                        ];
                    }
                }
            }
            
            // Return last 50 relevant entries
            return array_reverse(array_slice($logs, -50));
            
        } catch (\Exception $e) {
            return [
                [
                    'timestamp' => now(),
                    'level' => 'ERROR',
                    'message' => 'Failed to read system logs: ' . $e->getMessage(),
                    'context' => [],
                    'job_id' => null
                ]
            ];
        }
    }
}