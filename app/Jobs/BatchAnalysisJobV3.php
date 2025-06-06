<?php

namespace App\Jobs;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Services\MetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Smart chunking batch analysis job with optimized performance and reliability.
 * 
 * Uses smaller batch sizes (5-10 texts) with parallel processing and progressive saving
 * to achieve reliability while maintaining significant performance improvements.
 */
class BatchAnalysisJobV3 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public array $fileContent;
    public array $models;

    public int $tries = 3;
    public int $timeout = 3600; // 1 hour for large batches

    public function __construct(string $jobId, array $fileContent, array $models)
    {
        $this->jobId = $jobId;
        $this->fileContent = $fileContent;
        $this->models = $models;
        
        // Set the queue to 'batch' for this job
        $this->onQueue('batch');
    }

    public function handle(): void
    {
        $metricsService = app(MetricsService::class);
        
        try {
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            
            if (!$job) {
                Log::error('Analysis job not found', ['job_id' => $this->jobId]);
                return;
            }
            
            // Check if job has been cancelled before starting
            if ($job->status === 'cancelled') {
                Log::info('Batch analysis job V3 cancelled before processing', [
                    'job_id' => $this->jobId,
                    'status' => $job->status
                ]);
                return;
            }

            $job->status = AnalysisJob::STATUS_PROCESSING;
            $job->save();

            Log::info('Starting smart chunking batch analysis', [
                'job_id' => $this->jobId,
                'texts_count' => count($this->fileContent),
                'models' => $this->models,
                'strategy' => 'smart_chunking'
            ]);

            // Create TextAnalysis records first
            $textAnalyses = [];
            foreach ($this->fileContent as $item) {
                $textAnalysis = TextAnalysis::create([
                    'job_id' => $this->jobId,
                    'text_id' => (string) $item['id'],
                    'content' => $item['data']['content'],
                    'expert_annotations' => $item['annotations'] ?? [],
                ]);
                
                $textAnalyses[$item['id']] = $textAnalysis;
            }

            $totalTexts = count($this->fileContent);
            $totalModels = count($this->models);
            $processedTexts = 0;

            // Process each model with smart chunking
            foreach ($this->models as $modelIndex => $modelKey) {
                try {
                    Log::info("Processing model with smart chunking", [
                        'model' => $modelKey,
                        'texts_count' => $totalTexts,
                        'model_progress' => ($modelIndex + 1) . "/{$totalModels}"
                    ]);

                    $modelResults = $this->processModelWithChunking($modelKey, $this->fileContent, $job->custom_prompt);
                    
                    // Save results progressively for this model
                    foreach ($modelResults as $textId => $result) {
                        if (isset($textAnalyses[$textId])) {
                            $textAnalysis = $textAnalyses[$textId];
                            
                            // Update the text analysis with results
                            $this->saveModelResults($textAnalysis, $modelKey, $result);
                            
                            // Create comparison metrics if expert annotations exist
                            if (!empty($textAnalysis->expert_annotations) && !isset($result['error'])) {
                                $this->createComparisonMetrics(
                                    $textAnalysis, 
                                    $modelKey, 
                                    $result, 
                                    $metricsService
                                );
                            }
                        }
                    }

                    // Update progress after each model
                    $processedTexts = ($modelIndex + 1) * $totalTexts;
                    $job->processed_texts = $processedTexts;
                    $job->save();

                    Log::info("Model processing completed", [
                        'model' => $modelKey,
                        'successful_analyses' => count($modelResults),
                        'progress' => "{$processedTexts}/" . ($totalTexts * $totalModels)
                    ]);

                } catch (\Exception $e) {
                    Log::error("Model processing failed", [
                        'model' => $modelKey,
                        'error' => $e->getMessage(),
                        'job_id' => $this->jobId
                    ]);
                    
                    // Mark text analyses as failed for this model
                    foreach ($textAnalyses as $textAnalysis) {
                        $this->markModelAsFailed($textAnalysis, $modelKey, $e->getMessage());
                    }
                }
            }

            // Mark job as completed
            $job->status = AnalysisJob::STATUS_COMPLETED;
            $job->processed_texts = $totalTexts * $totalModels;
            $job->total_texts = $totalTexts * $totalModels;
            $job->save();

            Log::info('Smart chunking batch analysis completed', [
                'job_id' => $this->jobId,
                'total_texts' => $totalTexts,
                'models_processed' => count($this->models),
                'total_analyses' => $totalTexts * $totalModels
            ]);

        } catch (\Exception $e) {
            Log::error('Batch analysis job failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            if ($job) {
                $job->status = AnalysisJob::STATUS_FAILED;
                $job->error_message = $e->getMessage();
                $job->save();
            }

            throw $e;
        }
    }

    /**
     * Process a single model using smart chunking strategy.
     */
    private function processModelWithChunking(string $modelKey, array $fileContent, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        
        if (!$modelConfig) {
            throw new \Exception("Model {$modelKey} not found in configuration");
        }
        
        // Smart chunking: only chunk if there are multiple texts
        $totalTexts = count($fileContent);
        
        if ($totalTexts === 1) {
            // For single text, process individually to avoid unnecessary chunking overhead
            return $this->processChunkIndividually($fileContent, $modelKey, $customPrompt);
        }
        
        // For multiple texts, use small batch sizes for maximum reliability
        $chunkSize = 3; // Reduced to 3 for faster API responses and no timeouts
        $chunks = array_chunk($fileContent, $chunkSize);
        $allResults = [];
        
        Log::info("Starting chunked processing", [
            'model' => $modelKey,
            'total_texts' => count($fileContent),
            'chunk_size' => $chunkSize,
            'total_chunks' => count($chunks)
        ]);
        
        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $chunkResults = $this->processChunk($chunk, $modelKey, $customPrompt);
                $allResults = array_merge($allResults, $chunkResults);
                
                Log::info("Chunk processed successfully", [
                    'model' => $modelKey,
                    'chunk' => ($chunkIndex + 1) . "/" . count($chunks),
                    'chunk_size' => count($chunk),
                    'results_count' => count($chunkResults)
                ]);
                
                // Small delay to avoid rate limiting
                usleep(250000); // 0.25 second delay between chunks
                
            } catch (\Exception $e) {
                Log::warning("Chunk processing failed, falling back to individual", [
                    'model' => $modelKey,
                    'chunk_index' => $chunkIndex + 1,
                    'error' => $e->getMessage()
                ]);
                
                // Fallback to individual processing for this chunk
                $individualResults = $this->processChunkIndividually($chunk, $modelKey, $customPrompt);
                $allResults = array_merge($allResults, $individualResults);
            }
        }
        
        return $allResults;
    }
    
    /**
     * Process a single chunk using batch API.
     */
    private function processChunk(array $chunk, string $modelKey, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        $provider = $modelConfig['provider'];
        
        // Prepare texts for batch processing
        $textsForBatch = array_map(function($item) {
            return [
                'id' => $item['id'],
                'content' => $item['data']['content'],
                'annotations' => $item['annotations'] ?? []
            ];
        }, $chunk);
        
        switch ($provider) {
            case 'anthropic':
                return $this->processClaudeChunk($textsForBatch, $modelConfig, $customPrompt);
            case 'openai':
                return $this->processOpenAIChunk($textsForBatch, $modelConfig, $customPrompt);
            case 'google':
                return $this->processGeminiChunk($textsForBatch, $modelConfig, $customPrompt);
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }
    
    /**
     * Process chunk individually if batch processing fails.
     */
    private function processChunkIndividually(array $chunk, string $modelKey, ?string $customPrompt = null): array
    {
        $service = $this->getServiceForModel($modelKey);
        $results = [];
        
        foreach ($chunk as $item) {
            try {
                $result = $service->analyzeText($item['data']['content'], $customPrompt);
                $result['text_id'] = $item['id'];
                $results[$item['id']] = $result;
                
                // Small delay between individual requests
                usleep(500000); // 0.5 second delay
                
            } catch (\Exception $e) {
                Log::error("Individual text processing failed", [
                    'text_id' => $item['id'],
                    'model' => $modelKey,
                    'error' => $e->getMessage()
                ]);
                
                $results[$item['id']] = [
                    'text_id' => $item['id'],
                    'error' => $e->getMessage(),
                    'primaryChoice' => ['choices' => ['no']],
                    'annotations' => [],
                    'desinformationTechnique' => ['choices' => []]
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Process chunk using Claude API.
     */
    private function processClaudeChunk(array $texts, array $config, ?string $customPrompt = null): array
    {
        $batchPrompt = $this->createChunkPrompt($texts, $customPrompt);
        $systemMessage = app(\App\Services\PromptService::class)->getSystemMessage();
        
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(300) // Longer timeout for API reliability
        ->post($config['base_url'] . 'messages', [
            'model' => $config['model'],
            'max_tokens' => $config['max_tokens'],
            'temperature' => $config['temperature'],
            'system' => $systemMessage,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $batchPrompt
                ]
            ]
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Claude API error: ' . $response->status() . ' - ' . $response->body());
        }
        
        $responseData = $response->json();
        
        if (!isset($responseData['content'][0]['text'])) {
            throw new \Exception('Invalid Claude API response format');
        }
        
        return $this->parseChunkResponse($responseData['content'][0]['text'], $texts);
    }
    
    /**
     * Process chunk using OpenAI API.
     */
    private function processOpenAIChunk(array $texts, array $config, ?string $customPrompt = null): array
    {
        $batchPrompt = $this->createChunkPrompt($texts, $customPrompt);
        
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ])
        ->timeout(300)
        ->post($config['base_url'] . '/chat/completions', [
            'model' => $config['model'],
            'max_tokens' => $config['max_tokens'],
            'temperature' => $config['temperature'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => app(\App\Services\PromptService::class)->getSystemMessage()
                ],
                [
                    'role' => 'user',
                    'content' => $batchPrompt
                ]
            ]
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('OpenAI API error: ' . $response->status() . ' - ' . $response->body());
        }
        
        $responseData = $response->json();
        
        if (!isset($responseData['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid OpenAI API response format');
        }
        
        return $this->parseChunkResponse($responseData['choices'][0]['message']['content'], $texts);
    }
    
    /**
     * Process chunk using Gemini API.
     */
    private function processGeminiChunk(array $texts, array $config, ?string $customPrompt = null): array
    {
        $batchPrompt = $this->createChunkPrompt($texts, $customPrompt);
        $url = $config['base_url'] . 'v1beta/models/' . $config['model'] . ':generateContent?key=' . $config['api_key'];
        
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->timeout(300)
        ->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $batchPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => $config['max_tokens'],
                'temperature' => $config['temperature'],
                'topP' => $config['top_p'],
                'topK' => $config['top_k'],
            ]
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Gemini API error: ' . $response->status() . ' - ' . $response->body());
        }
        
        $responseData = $response->json();
        
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid Gemini API response format');
        }
        
        return $this->parseChunkResponse($responseData['candidates'][0]['content']['parts'][0]['text'], $texts);
    }
    
    /**
     * Create a prompt for a small chunk of texts.
     */
    private function createChunkPrompt(array $texts, ?string $customPrompt = null): string
    {
        $basePrompt = $customPrompt ?: app(\App\Services\PromptService::class)->getAnalysisPromptTemplate();
        
        $prompt = $basePrompt . "\n\n";
        $prompt .= "SVARBU: Analizuojami " . count($texts) . " tekstai. Grąžinkite JSON masyvą su tiksliai " . count($texts) . " elementais.\n\n";
        $prompt .= "TEKSTŲ RINKINYS ANALIZEI:\n\n";
        
        foreach ($texts as $index => $textItem) {
            $textNumber = $index + 1;
            $prompt .= "=== TEKSTAS #{$textNumber} (ID: {$textItem['id']}) ===\n";
            $prompt .= $textItem['content'] . "\n\n";
        }
        
        $prompt .= "\n\nATSAKYMO FORMATAS:\n";
        $prompt .= "Grąžinkite JSON masyvą su tiksliai " . count($texts) . " elementais:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"text_id\": \"{$texts[0]['id']}\",\n";
        $prompt .= "    \"primaryChoice\": {\"choices\": [\"yes\" arba \"no\"]},\n";
        $prompt .= "    \"annotations\": [...],\n";
        $prompt .= "    \"desinformationTechnique\": {\"choices\": [...]}\n";
        $prompt .= "  }\n";
        $prompt .= "  // ... likusių " . (count($texts) - 1) . " tekstų rezultatai\n";
        $prompt .= "]\n\n";
        $prompt .= "KRITIŠKAI SVARBU: Masyve turi būti TIKSLIAI " . count($texts) . " elementų!";
        
        return $prompt;
    }
    
    /**
     * Parse chunk response and extract results.
     */
    private function parseChunkResponse(string $responseContent, array $texts): array
    {
        // Try to extract JSON from response
        if (preg_match('/```json\s*(.*?)\s*```/s', $responseContent, $matches)) {
            $jsonString = $matches[1];
        } elseif (preg_match('/\[.*\]/s', $responseContent, $matches)) {
            $jsonString = $matches[0];
        } else {
            $jsonString = $responseContent;
        }
        
        $jsonString = trim($jsonString);
        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse chunk JSON response: ' . json_last_error_msg());
        }
        
        if (!is_array($decoded)) {
            throw new \Exception('Chunk response is not an array');
        }
        
        $results = [];
        
        // Map results back to text IDs
        foreach ($decoded as $index => $result) {
            if (isset($texts[$index])) {
                $textId = $texts[$index]['id'];
                
                if (!isset($result['text_id'])) {
                    $result['text_id'] = $textId;
                }
                
                $results[$textId] = $result;
            }
        }
        
        // Fill in missing results with errors
        foreach ($texts as $textItem) {
            if (!isset($results[$textItem['id']])) {
                $results[$textItem['id']] = [
                    'text_id' => $textItem['id'],
                    'error' => 'No result found in chunk response',
                    'primaryChoice' => ['choices' => ['no']],
                    'annotations' => [],
                    'desinformationTechnique' => ['choices' => []]
                ];
            }
        }
        
        return $results;
    }

    /**
     * Save model results to the appropriate field in TextAnalysis.
     */
    private function saveModelResults(TextAnalysis $textAnalysis, string $modelKey, array $result): void
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        $provider = $modelConfig['provider'] ?? 'unknown';
        
        $cleanResult = $result;
        unset($cleanResult['error']);
        unset($cleanResult['text_id']);
        
        $modelName = $modelConfig['model'] ?? $modelKey;
        
        switch ($provider) {
            case 'anthropic':
                $textAnalysis->claude_annotations = $cleanResult;
                $textAnalysis->claude_model_name = $modelName;
                if (isset($result['error'])) {
                    $textAnalysis->claude_error = $result['error'];
                }
                break;
                
            case 'openai':
                $textAnalysis->gpt_annotations = $cleanResult;
                $textAnalysis->gpt_model_name = $modelName;
                if (isset($result['error'])) {
                    $textAnalysis->gpt_error = $result['error'];
                }
                break;
                
            case 'google':
                $textAnalysis->gemini_annotations = $cleanResult;
                $textAnalysis->gemini_model_name = $modelName;
                if (isset($result['error'])) {
                    $textAnalysis->gemini_error = $result['error'];
                }
                break;
        }
        
        $textAnalysis->save();
    }

    /**
     * Mark a model as failed for a text analysis.
     */
    private function markModelAsFailed(TextAnalysis $textAnalysis, string $modelKey, string $error): void
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        $provider = $modelConfig['provider'] ?? 'unknown';
        
        switch ($provider) {
            case 'anthropic':
                $textAnalysis->claude_error = $error;
                break;
            case 'openai':
                $textAnalysis->gpt_error = $error;
                break;
            case 'google':
                $textAnalysis->gemini_error = $error;
                break;
        }
        
        $textAnalysis->save();
    }

    /**
     * Create comparison metrics for expert vs AI annotations.
     */
    private function createComparisonMetrics(
        TextAnalysis $textAnalysis, 
        string $modelKey, 
        array $result,
        MetricsService $metricsService
    ): void {
        try {
            if (empty($textAnalysis->expert_annotations) || isset($result['error'])) {
                return;
            }

            $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
            $modelName = $modelConfig['model'] ?? $modelKey;

            // Set the model annotations for calculation
            $textAnalysis->setModelAnnotations($modelKey, $result, $modelName);
            $textAnalysis->save();

            // Calculate and save comparison metrics (already creates the ComparisonMetric record)
            $metricsService->calculateMetricsForText(
                $textAnalysis,
                $modelKey,
                $this->jobId,
                $modelName
            );

        } catch (\Exception $e) {
            Log::error('Failed to create comparison metrics', [
                'text_analysis_id' => $textAnalysis->id,
                'model' => $modelKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the appropriate service for a model.
     */
    private function getServiceForModel(string $modelKey): \App\Services\LLMServiceInterface
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        $provider = $modelConfig['provider'];
        
        switch ($provider) {
            case 'anthropic':
                return app(\App\Services\ClaudeService::class);
            case 'openai':
                return app(\App\Services\OpenAIService::class);
            case 'google':
                return app(\App\Services\GeminiService::class);
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Smart chunking batch analysis job failed permanently', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $job = AnalysisJob::where('job_id', $this->jobId)->first();
        if ($job) {
            $job->status = AnalysisJob::STATUS_FAILED;
            $job->error_message = $exception->getMessage();
            $job->save();
        }
    }
}