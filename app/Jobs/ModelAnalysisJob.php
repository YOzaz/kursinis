<?php

namespace App\Jobs;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Services\MetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Individual model analysis job for true parallel processing.
 * 
 * Each instance processes one model against all texts using file attachment strategy.
 */
class ModelAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public string $modelKey;
    public string $tempFile;
    public array $fileContent;
    public ?string $customPrompt;

    public int $tries = 3; // Allow retries for timeout issues
    public int $timeout = 1800; // 30 minutes for model processing

    public function __construct(string $jobId, string $modelKey, string $tempFile, array $fileContent, ?string $customPrompt = null)
    {
        $this->jobId = $jobId;
        $this->modelKey = $modelKey;
        $this->tempFile = $tempFile;
        $this->fileContent = $fileContent;
        $this->customPrompt = $customPrompt;
        
        // Set the queue to 'models' for this job
        $this->onQueue('models');
    }

    public function handle(): void
    {
        $metricsService = app(MetricsService::class);
        
        try {
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            
            if (!$job) {
                Log::error('Analysis job not found for model processing', [
                    'job_id' => $this->jobId,
                    'model' => $this->modelKey
                ]);
                return;
            }

            $this->logProgress("Starting model processing", [
                'model' => $this->modelKey,
                'status' => 'started'
            ]);

            // Create temporary file for this model processing
            $modelTempFile = tempnam(sys_get_temp_dir(), 'model_analysis_' . $this->modelKey . '_') . '.json';
            $jsonContent = json_encode($this->fileContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($modelTempFile, $jsonContent);

            // Process this specific model
            $modelResults = $this->processModelWithFileAttachment($this->modelKey, $modelTempFile, $this->customPrompt);

            // Get existing text analyses for this job
            $textAnalyses = TextAnalysis::where('job_id', $this->jobId)->get()->keyBy('text_id');
            
            // Save results for this model
            foreach ($modelResults as $textId => $result) {
                if (isset($textAnalyses[$textId])) {
                    $textAnalysis = $textAnalyses[$textId];
                    $this->saveModelResults($textAnalysis, $this->modelKey, $result);
                    
                    // Create comparison metrics if expert annotations exist
                    if (!empty($textAnalysis->expert_annotations) && !isset($result['error'])) {
                        $this->createComparisonMetrics(
                            $textAnalysis, 
                            $this->modelKey, 
                            $result, 
                            $metricsService
                        );
                    }
                }
            }

            // Update job progress
            $this->updateJobProgress($job);

            $this->logProgress("Model processing completed successfully", [
                'model' => $this->modelKey,
                'successful_analyses' => count($modelResults),
                'status' => 'completed'
            ]);

            // Clean up temporary file
            if (file_exists($modelTempFile)) {
                unlink($modelTempFile);
            }

        } catch (\Exception $e) {
            $this->logProgress("Model processing failed", [
                'model' => $this->modelKey,
                'error' => $e->getMessage(),
                'status' => 'failed'
            ], 'error');
            
            // Enhanced logging for system visibility
            Log::error("❌ {$this->modelKey} model processing completely failed", [
                'job_id' => $this->jobId,
                'model' => $this->modelKey,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            // Mark text analyses as failed for this model
            $textAnalyses = TextAnalysis::where('job_id', $this->jobId)->get();
            foreach ($textAnalyses as $textAnalysis) {
                $this->markModelAsFailed($textAnalysis, $this->modelKey, $e->getMessage());
            }
            
            // Update job progress even for failed models
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            if ($job) {
                $this->updateJobProgress($job);
            }

            throw $e;
        }
    }

    /**
     * Process a single model using file attachment strategy.
     */
    private function processModelWithFileAttachment(string $modelKey, string $tempFile, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        
        if (!$modelConfig) {
            throw new \Exception("Model {$modelKey} not found in configuration");
        }
        
        $provider = $modelConfig['provider'];
        
        // Measure execution time for the entire model processing
        $startTime = microtime(true);
        
        try {
            $results = match ($provider) {
                'anthropic' => $this->processClaudeWithAttachment($modelKey, $tempFile, $customPrompt),
                'openai' => $this->processOpenAIWithAttachment($modelKey, $tempFile, $customPrompt),
                'google' => $this->processGeminiWithFileAPI($modelKey, $tempFile, $customPrompt),
                default => throw new \Exception("Unsupported provider: {$provider}")
            };
            
            $endTime = microtime(true);
            $executionTimeMs = (int) round(($endTime - $startTime) * 1000);
            
            // Add execution time to all results
            foreach ($results as &$result) {
                $result['execution_time_ms'] = $executionTimeMs;
            }
            
            return $results;
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $executionTimeMs = (int) round(($endTime - $startTime) * 1000);
            
            // Add execution time even for failed requests
            throw new \Exception($e->getMessage() . " (Execution time: {$executionTimeMs}ms)");
        }
    }

    /**
     * Process using Claude API with structured JSON in message.
     * Automatically chunks large files to respect API limits.
     */
    private function processClaudeWithAttachment(string $modelKey, string $tempFile, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        $systemMessage = app(\App\Services\PromptService::class)->getSystemMessage();
        
        // Read file content and check size
        $fileContent = file_get_contents($tempFile);
        $fileSize = strlen($fileContent);
        
        // Claude API limit is ~9MB total text
        $claudeLimit = 8000000; // 8MB to be safe
        
        if ($fileSize > $claudeLimit) {
            $this->logProgress("File too large for single Claude API call, using chunking", [
                'model' => $modelKey,
                'file_size' => $fileSize,
                'limit' => $claudeLimit,
                'status' => 'chunking'
            ]);
            
            return $this->processClaudeWithChunking($modelKey, $tempFile, $customPrompt);
        }
        
        $this->logProgress("Sending structured data to Claude API", [
            'model' => $modelKey,
            'file_size' => $fileSize,
            'status' => 'processing'
        ]);

        $prompt = $this->createFileAnalysisPrompt($customPrompt) . "\n\nJSON DATA:\n" . $fileContent;
        
        $response = Http::withHeaders([
            'x-api-key' => $modelConfig['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(1500) // 25 minutes for file processing
        ->post($modelConfig['base_url'] . 'messages', [
            'model' => $modelConfig['model'],
            'max_tokens' => $modelConfig['max_tokens'],
            'temperature' => $modelConfig['temperature'],
            'system' => $systemMessage,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
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
        
        return $this->parseFileResponse($responseData['content'][0]['text'], $this->fileContent);
    }

    /**
     * Process using OpenAI API with structured chunks.
     */
    private function processOpenAIWithAttachment(string $modelKey, string $tempFile, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        
        $fileContent = file_get_contents($tempFile);
        $fileSize = strlen($fileContent);
        
        // OpenAI API limit is ~10MB message content
        $openaiLimit = 9000000; // 9MB to be safe
        
        if ($fileSize > $openaiLimit) {
            $this->logProgress("File too large for single OpenAI API call, using chunking", [
                'model' => $modelKey,
                'file_size' => $fileSize,
                'limit' => $openaiLimit,
                'status' => 'chunking'
            ]);
            
            return $this->processOpenAIWithChunking($modelKey, $tempFile, $customPrompt);
        }
        
        $this->logProgress("Sending structured data to OpenAI API", [
            'model' => $modelKey,
            'file_size' => $fileSize,
            'status' => 'processing'
        ]);

        $prompt = $this->createFileAnalysisPrompt($customPrompt) . "\n\nJSON DATA:\n" . $fileContent;
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $modelConfig['api_key'],
            'Content-Type' => 'application/json',
        ])
        ->timeout(1500)
        ->post($modelConfig['base_url'] . '/chat/completions', [
            'model' => $modelConfig['model'],
            'max_tokens' => $modelConfig['max_tokens'],
            'temperature' => $modelConfig['temperature'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => app(\App\Services\PromptService::class)->getSystemMessage()
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
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
        
        return $this->parseFileResponse($responseData['choices'][0]['message']['content'], $this->fileContent);
    }

    /**
     * Process using Gemini with inline JSON (File API has complex upload requirements).
     */
    private function processGeminiWithFileAPI(string $modelKey, string $tempFile, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        
        // Read file content and include inline in prompt
        $fileContent = file_get_contents($tempFile);
        
        $this->logProgress("Sending inline JSON data to Gemini API", [
            'model' => $modelKey,
            'file_size' => strlen($fileContent),
            'status' => 'processing'
        ]);

        $prompt = $this->createFileAnalysisPrompt($customPrompt) . "\n\nJSON DATA:\n" . $fileContent;
        
        // For very long content, try to use the improved GeminiService with better error handling
        if (strlen($prompt) > 15000) {
            try {
                $geminiService = app(\App\Services\GeminiService::class);
                if ($geminiService->setModel($modelKey)) {
                    $this->logProgress("Using improved GeminiService for long content", [
                        'model' => $modelKey,
                        'prompt_length' => strlen($prompt),
                        'status' => 'processing'
                    ]);
                    
                    // Since GeminiService expects single text analysis, we need to parse the JSON and analyze each text
                    $jsonData = json_decode($fileContent, true);
                    $results = [];
                    
                    foreach ($jsonData as $item) {
                        if (isset($item['data']['content'])) {
                            try {
                                $this->logProgress("Analyzing individual text with GeminiService", [
                                    'model' => $modelKey,
                                    'text_id' => $item['id'],
                                    'text_length' => strlen($item['data']['content']),
                                    'status' => 'analyzing_individual'
                                ]);
                                
                                $textResult = $geminiService->analyzeText($item['data']['content'], $customPrompt);
                                
                                $this->logProgress("Individual text analysis completed", [
                                    'model' => $modelKey,
                                    'text_id' => $item['id'],
                                    'annotations_count' => count($textResult['annotations'] ?? []),
                                    'status' => 'individual_completed'
                                ]);
                                
                                $results[] = [
                                    'text_id' => $item['id'],
                                    'primaryChoice' => $textResult['primaryChoice'] ?? ['choices' => ['no']],
                                    'annotations' => $textResult['annotations'] ?? [],
                                    'desinformationTechnique' => $textResult['desinformationTechnique'] ?? ['choices' => []]
                                ];
                            } catch (\Exception $e) {
                                $this->logProgress("GeminiService failed for individual text", [
                                    'model' => $modelKey,
                                    'text_id' => $item['id'],
                                    'error' => $e->getMessage(),
                                    'error_type' => get_class($e),
                                    'status' => 'individual_failed'
                                ], 'error');
                                
                                // Also log to Laravel's main log for visibility
                                Log::error("❌ {$modelKey} analysis failed for text {$item['id']}", [
                                    'job_id' => $this->jobId,
                                    'model' => $modelKey,
                                    'text_id' => $item['id'],
                                    'error' => $e->getMessage(),
                                    'error_type' => get_class($e)
                                ]);
                                
                                // Add failed result
                                $results[] = [
                                    'text_id' => $item['id'],
                                    'primaryChoice' => ['choices' => ['no']],
                                    'annotations' => [],
                                    'desinformationTechnique' => ['choices' => []],
                                    'error' => $e->getMessage()
                                ];
                            }
                        }
                    }
                    
                    // Convert to the expected format for main processing logic
                    $formattedResults = [];
                    foreach ($results as $result) {
                        $formattedResults[$result['text_id']] = $result;
                    }
                    return $formattedResults;
                }
            } catch (\Exception $e) {
                $this->logProgress("GeminiService fallback failed, using direct API", [
                    'error' => $e->getMessage(),
                    'status' => 'warning'
                ], 'warning');
            }
        }
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->timeout(600) // 10 minutes for Gemini API calls
        ->post('https://generativelanguage.googleapis.com/v1beta/models/' . $modelConfig['model'] . ':generateContent?key=' . $modelConfig['api_key'], [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => $modelConfig['max_tokens'],
                'temperature' => $modelConfig['temperature'],
                'topP' => $modelConfig['top_p'] ?? 0.95,
                'topK' => $modelConfig['top_k'] ?? 40,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_NONE'
                ]
            ]
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Gemini API error: ' . $response->status() . ' - ' . $response->body());
        }
        
        $responseData = $response->json();
        
        // Check for various Gemini API response issues
        if (!isset($responseData['candidates'][0])) {
            throw new \Exception('Gemini API error: No candidates in response. Response: ' . json_encode($responseData));
        }
        
        $candidate = $responseData['candidates'][0];
        
        // Check for specific finish reasons that indicate issues
        if (isset($candidate['finishReason'])) {
            switch ($candidate['finishReason']) {
                case 'MAX_TOKENS':
                    $this->logProgress("Gemini API MAX_TOKENS error - detailed info", [
                        'model' => $modelKey,
                        'finish_reason' => $candidate['finishReason'],
                        'prompt_tokens' => $responseData['usageMetadata']['promptTokenCount'] ?? 'unknown',
                        'total_tokens' => $responseData['usageMetadata']['totalTokenCount'] ?? 'unknown',
                        'max_output_tokens_config' => $modelConfig['max_tokens'],
                        'file_size' => strlen($fileContent),
                        'full_response' => json_encode($responseData)
                    ], 'error');
                    
                    throw new \Exception("Invalid Gemini API response format. Response: " . json_encode($responseData));
                case 'SAFETY':
                    throw new \Exception('Gemini API error: Response blocked by safety filters.');
                case 'RECITATION':
                    throw new \Exception('Gemini API error: Response blocked due to recitation.');
                case 'OTHER':
                    throw new \Exception('Gemini API error: Response blocked for other reasons.');
            }
        }
        
        if (!isset($candidate['content']['parts'][0]['text'])) {
            // Log the actual response for debugging
            $this->logProgress("Gemini API response debugging", [
                'model' => $modelKey,
                'status_code' => $response->status(),
                'finish_reason' => $candidate['finishReason'] ?? 'unknown',
                'response_data' => $responseData,
                'status' => 'debug'
            ], 'error');
            
            throw new \Exception('Invalid Gemini API response format: missing content.parts[0].text. Finish reason: ' . ($candidate['finishReason'] ?? 'unknown'));
        }
        
        return $this->parseFileResponse($responseData['candidates'][0]['content']['parts'][0]['text'], $this->fileContent);
    }

    /**
     * Create a prompt for file-based analysis.
     */
    private function createFileAnalysisPrompt(?string $customPrompt = null): string
    {
        $basePrompt = $customPrompt ?: app(\App\Services\PromptService::class)->getAnalysisPromptTemplate();
        
        $prompt = $basePrompt . "\n\n";
        $prompt .= "SVARBU: Analizuojamas JSON failas su tekstų rinkiniu. Grąžinkite JSON masyvą su rezultatais kiekvienam tekstui.\n\n";
        $prompt .= "JSON failo struktūra:\n";
        $prompt .= "- Kiekvienas objektas turi 'id' ir 'data.content' laukus\n";
        $prompt .= "- Analizuokite 'data.content' kiekviename objekte\n";
        $prompt .= "- Grąžinkite rezultatus su 'text_id' atitinkančiu 'id' iš failo\n\n";
        $prompt .= "ATSAKYMO FORMATAS:\n";
        $prompt .= "Grąžinkite JSON masyvą su tiksliai tiek elementų, kiek tekstų faile:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"text_id\": \"<id_iš_failo>\",\n";
        $prompt .= "    \"primaryChoice\": {\"choices\": [\"yes\" arba \"no\"]},\n";
        $prompt .= "    \"annotations\": [...],\n";
        $prompt .= "    \"desinformationTechnique\": {\"choices\": [...]}\n";
        $prompt .= "  }\n";
        $prompt .= "  // ... kitų tekstų rezultatai\n";
        $prompt .= "]\n\n";
        $prompt .= "KRITIŠKAI SVARBU: Masyve turi būti TIKSLIAI tiek elementų, kiek tekstų JSON faile!";
        
        return $prompt;
    }

    /**
     * Parse file-based response and extract results.
     */
    private function parseFileResponse(string $responseContent, array $texts): array
    {
        // Log the response content for debugging (truncated to avoid huge logs)
        $truncatedResponse = strlen($responseContent) > 1000 
            ? substr($responseContent, 0, 500) . '...[truncated]...' . substr($responseContent, -500)
            : $responseContent;
            
        $this->logProgress("Parsing response content", [
            'response_length' => strlen($responseContent),
            'response_preview' => $truncatedResponse,
            'status' => 'parsing'
        ], 'info');
        
        // First try to decode directly (for already valid JSON)
        $decoded = json_decode($responseContent, true);
        
        // If direct decode fails, try to extract JSON from response
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logProgress("Direct JSON decode failed, trying extraction", [
                'json_error' => json_last_error_msg(),
                'status' => 'extracting'
            ], 'info');
            
            // Try multiple extraction patterns
            $jsonString = null;
            
            // Pattern 1: ```json ... ```
            if (preg_match('/```json\s*(.*?)\s*```/s', $responseContent, $matches)) {
                $jsonString = $matches[1];
                $this->logProgress("Found JSON in code block", ['status' => 'extraction_success'], 'info');
            }
            // Pattern 2: Look for array starting with [ and ending with ]
            elseif (preg_match('/\[[\s\S]*\]/s', $responseContent, $matches)) {
                $jsonString = $matches[0];
                $this->logProgress("Found JSON array pattern", ['status' => 'extraction_success'], 'info');
            }
            // Pattern 3: Look for any valid JSON structure
            elseif (preg_match('/[\[\{][\s\S]*[\]\}]/s', $responseContent, $matches)) {
                $jsonString = $matches[0];
                $this->logProgress("Found generic JSON pattern", ['status' => 'extraction_success'], 'info');
            }
            else {
                // Try cleaning the response
                $jsonString = trim($responseContent);
                // Remove common prefixes/suffixes that models sometimes add
                $jsonString = preg_replace('/^[^[\{]*/', '', $jsonString);
                $jsonString = preg_replace('/[^}\]]*$/', '', $jsonString);
                $this->logProgress("Using cleaned response", ['status' => 'fallback_cleaning'], 'info');
            }
            
            $jsonString = trim($jsonString);
            
            // Clean control characters that cause "Control character error"
            $jsonString = preg_replace('/[\x00-\x1F\x7F]/', '', $jsonString);
            
            // Try to fix common JSON issues
            $jsonString = str_replace(["\n", "\r", "\t"], ['', '', ''], $jsonString);
            $jsonString = preg_replace('/,\s*([}\]])/', '$1', $jsonString); // Remove trailing commas
            
            $decoded = json_decode($jsonString, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Last resort: try to extract just a valid JSON array/object structure
                if (preg_match('/(\[.*?\]|\{.*?\})/s', $jsonString, $matches)) {
                    $cleanedJson = $matches[1];
                    $decoded = json_decode($cleanedJson, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->logProgress("Successfully parsed with fallback cleaning", [
                            'status' => 'fallback_success'
                        ], 'info');
                    }
                }
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logProgress("All JSON parsing attempts failed", [
                        'json_error' => json_last_error_msg(),
                        'cleaned_content_preview' => substr($jsonString, 0, 200),
                        'status' => 'parse_failed'
                    ], 'error');
                    
                    throw new \Exception('Failed to parse file response JSON: ' . json_last_error_msg() . '. Content preview: ' . substr($jsonString, 0, 200));
                }
            }
        }
        
        if (!is_array($decoded)) {
            throw new \Exception('File response is not an array');
        }
        
        $results = [];
        
        // Map results back to text IDs
        foreach ($decoded as $result) {
            if (isset($result['text_id'])) {
                $textId = $result['text_id'];
                $results[$textId] = $result;
            }
        }
        
        // Fill in missing results with errors
        foreach ($texts as $textItem) {
            if (!isset($results[$textItem['id']])) {
                $results[$textItem['id']] = [
                    'text_id' => $textItem['id'],
                    'error' => 'No result found in file response',
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
        $this->logProgress("Saving model results", [
            'model' => $modelKey,
            'text_id' => $textAnalysis->text_id,
            'has_annotations' => !empty($result['annotations'] ?? []),
            'has_error' => !empty($result['error'] ?? ''),
            'status' => 'saving'
        ]);
        
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        $provider = $modelConfig['provider'] ?? 'unknown';
        
        $cleanResult = $result;
        unset($cleanResult['error']);
        unset($cleanResult['text_id']);
        unset($cleanResult['execution_time_ms']);
        
        $modelName = $modelConfig['model'] ?? $modelKey;
        $errorMessage = $result['error'] ?? null;
        $executionTimeMs = $result['execution_time_ms'] ?? null;
        
        try {
            // Store in new ModelResult table for progress tracking
            $modelResult = $textAnalysis->storeModelResult(
                $modelKey, 
                $cleanResult, 
                $modelName, 
                $executionTimeMs,
                $errorMessage
            );
            
            $this->logProgress("ModelResult created successfully", [
                'model' => $modelKey,
                'text_id' => $textAnalysis->text_id,
                'model_result_id' => $modelResult->id,
                'status' => 'model_result_saved'
            ]);
        } catch (\Exception $e) {
            $this->logProgress("Failed to create ModelResult", [
                'model' => $modelKey,
                'text_id' => $textAnalysis->text_id,
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 'error');
            throw $e;
        }
        
        // Also store in legacy TextAnalysis columns for backward compatibility
        switch ($provider) {
            case 'anthropic':
                $textAnalysis->claude_annotations = $cleanResult;
                $textAnalysis->claude_model_name = $modelName;
                if ($errorMessage) {
                    $textAnalysis->claude_error = $errorMessage;
                }
                break;
                
            case 'openai':
                $textAnalysis->gpt_annotations = $cleanResult;
                $textAnalysis->gpt_model_name = $modelName;
                if ($errorMessage) {
                    $textAnalysis->gpt_error = $errorMessage;
                }
                break;
                
            case 'google':
                $textAnalysis->gemini_annotations = $cleanResult;
                $textAnalysis->gemini_model_name = $modelName;
                if ($errorMessage) {
                    $textAnalysis->gemini_error = $errorMessage;
                }
                break;
        }
        
        $textAnalysis->save();
    }

    /**
     * Mark a model as failed for all text analyses.
     */
    private function markModelAsFailed(TextAnalysis $textAnalysis, string $modelKey, string $error): void
    {
        // Use new structure to store the failure
        $textAnalysis->storeModelResult($modelKey, [], null, null, $error);
        
        // Also update legacy fields for backward compatibility
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

            // Calculate and save comparison metrics
            $metricsService->calculateMetricsForText(
                $textAnalysis,
                $modelKey,
                $this->jobId,
                $modelName
            );

        } catch (\Exception $e) {
            $this->logProgress('Failed to create comparison metrics', [
                'text_analysis_id' => $textAnalysis->id,
                'model' => $modelKey,
                'error' => $e->getMessage()
            ], 'error');
        }
    }

    /**
     * Update job progress when a model completes.
     */
    private function updateJobProgress(AnalysisJob $job): void
    {
        $totalModels = count($job->requested_models ?? []);
        
        // Use a more reliable method to count completed models
        // Query with a small delay to ensure transaction consistency
        usleep(100000); // 100ms delay to ensure database consistency
        
        $completedModels = 0;
        $models = $job->requested_models ?? [];
        
        foreach ($models as $modelKey) {
            // Only rely on ModelResult records for accurate per-model tracking
            // Legacy fields can't distinguish between different models of the same provider
            $modelResultsCount = \App\Models\ModelResult::where('job_id', $this->jobId)
                ->where('model_key', $modelKey)
                ->whereIn('status', ['completed', 'failed'])
                ->count();
                
            if ($modelResultsCount > 0) {
                $completedModels++;
            }
        }
        
        // Update job progress based on model completion
        $job->processed_texts = $completedModels;
        $job->total_texts = $totalModels;
        
        // Check if all models are completed
        if ($completedModels >= $totalModels) {
            $job->status = AnalysisJob::STATUS_COMPLETED;
        }
        
        $job->save();
        
        $this->logProgress("Job progress updated", [
            'completed_models' => $completedModels,
            'total_models' => $totalModels,
            'progress_percentage' => $totalModels > 0 ? round(($completedModels / $totalModels) * 100, 1) : 0,
            'job_status' => $job->status
        ]);
    }

    /**
     * Enhanced logging with status information.
     */
    private function logProgress(string $message, array $context = [], string $level = 'info'): void
    {
        $context['timestamp'] = now()->toISOString();
        $context['job_type'] = 'ModelAnalysisJob';
        $context['job_id'] = $this->jobId;
        $context['model'] = $this->modelKey;
        
        Log::{$level}($message, $context);
    }

    public function failed(\Throwable $exception): void
    {
        $this->logProgress('Model analysis job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ], 'error');

        // Update job progress even for failed models
        $job = AnalysisJob::where('job_id', $this->jobId)->first();
        if ($job) {
            $this->updateJobProgress($job);
        }
    }

    /**
     * Process Claude API with chunking for large files.
     */
    private function processClaudeWithChunking(string $modelKey, string $tempFile, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        $systemMessage = app(\App\Services\PromptService::class)->getSystemMessage();
        
        $jsonData = json_decode(file_get_contents($tempFile), true);
        $results = [];
        
        // Calculate optimal chunk size based on token limits
        $chunkSize = $this->calculateOptimalChunkSize($jsonData, $modelKey, $customPrompt);
        $chunks = array_chunk($jsonData, $chunkSize);
        
        $this->logProgress("Processing in chunks", [
            'model' => $modelKey,
            'total_texts' => count($jsonData),
            'chunks' => count($chunks),
            'chunk_size' => $chunkSize,
            'status' => 'chunking'
        ]);
        
        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkJson = json_encode($chunk, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $prompt = $this->createFileAnalysisPrompt($customPrompt) . "\n\nJSON DATA:\n" . $chunkJson;
            
            $this->logProgress("Processing chunk", [
                'model' => $modelKey,
                'chunk' => $chunkIndex + 1,
                'total_chunks' => count($chunks),
                'texts_in_chunk' => count($chunk),
                'chunk_size' => strlen($chunkJson),
                'status' => 'processing_chunk'
            ]);
            
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $modelConfig['api_key'],
                    'Content-Type' => 'application/json',
                    'anthropic-version' => '2023-06-01',
                ])
                ->timeout(1500)
                ->post($modelConfig['base_url'] . 'messages', [
                    'model' => $modelConfig['model'],
                    'max_tokens' => $modelConfig['max_tokens'],
                    'temperature' => $modelConfig['temperature'],
                    'system' => $systemMessage,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
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
                
                $chunkResults = $this->parseFileResponse($responseData['content'][0]['text'], $chunk);
                $results = array_merge($results, $chunkResults);
                
                $this->logProgress("Chunk completed", [
                    'model' => $modelKey,
                    'chunk' => $chunkIndex + 1,
                    'results_count' => count($chunkResults),
                    'status' => 'chunk_completed'
                ]);
                
            } catch (\Exception $e) {
                $this->logProgress("Chunk failed", [
                    'model' => $modelKey,
                    'chunk' => $chunkIndex + 1,
                    'error' => $e->getMessage(),
                    'status' => 'chunk_failed'
                ], 'error');
                
                // Add failed results for this chunk
                foreach ($chunk as $item) {
                    $results[$item['id']] = [
                        'text_id' => $item['id'],
                        'error' => 'Chunk processing failed: ' . $e->getMessage(),
                        'primaryChoice' => ['choices' => ['no']],
                        'annotations' => [],
                        'desinformationTechnique' => ['choices' => []]
                    ];
                }
            }
        }
        
        return $results;
    }

    /**
     * Process OpenAI API with chunking for large files.
     */
    private function processOpenAIWithChunking(string $modelKey, string $tempFile, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        
        $jsonData = json_decode(file_get_contents($tempFile), true);
        $results = [];
        
        // Calculate optimal chunk size based on token limits
        $chunkSize = $this->calculateOptimalChunkSize($jsonData, $modelKey, $customPrompt);
        $chunks = array_chunk($jsonData, $chunkSize);
        
        $this->logProgress("Processing in chunks", [
            'model' => $modelKey,
            'total_texts' => count($jsonData),
            'chunks' => count($chunks),
            'chunk_size' => $chunkSize,
            'status' => 'chunking'
        ]);
        
        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkJson = json_encode($chunk, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $prompt = $this->createFileAnalysisPrompt($customPrompt) . "\n\nJSON DATA:\n" . $chunkJson;
            
            $this->logProgress("Processing chunk", [
                'model' => $modelKey,
                'chunk' => $chunkIndex + 1,
                'total_chunks' => count($chunks),
                'texts_in_chunk' => count($chunk),
                'chunk_size' => strlen($chunkJson),
                'status' => 'processing_chunk'
            ]);
            
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $modelConfig['api_key'],
                    'Content-Type' => 'application/json',
                ])
                ->timeout(1500)
                ->post($modelConfig['base_url'] . '/chat/completions', [
                    'model' => $modelConfig['model'],
                    'max_tokens' => $modelConfig['max_tokens'],
                    'temperature' => $modelConfig['temperature'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => app(\App\Services\PromptService::class)->getSystemMessage()
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
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
                
                $chunkResults = $this->parseFileResponse($responseData['choices'][0]['message']['content'], $chunk);
                $results = array_merge($results, $chunkResults);
                
                $this->logProgress("Chunk completed", [
                    'model' => $modelKey,
                    'chunk' => $chunkIndex + 1,
                    'results_count' => count($chunkResults),
                    'status' => 'chunk_completed'
                ]);
                
            } catch (\Exception $e) {
                $this->logProgress("Chunk failed", [
                    'model' => $modelKey,
                    'chunk' => $chunkIndex + 1,
                    'error' => $e->getMessage(),
                    'status' => 'chunk_failed'
                ], 'error');
                
                // Add failed results for this chunk
                foreach ($chunk as $item) {
                    $results[$item['id']] = [
                        'text_id' => $item['id'],
                        'error' => 'Chunk processing failed: ' . $e->getMessage(),
                        'primaryChoice' => ['choices' => ['no']],
                        'annotations' => [],
                        'desinformationTechnique' => ['choices' => []]
                    ];
                }
            }
        }
        
        return $results;
    }

    /**
     * Calculate optimal chunk size to stay within token limits.
     */
    private function calculateOptimalChunkSize(array $jsonData, string $modelKey, ?string $customPrompt = null): int
    {
        // Get provider-specific token limits
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? [];
        $provider = $modelConfig['provider'] ?? 'unknown';
        
        // Token limits by provider (very conservative estimates based on real errors)
        $tokenLimits = [
            'anthropic' => 120000, // Claude: 200k limit, use 120k for safety (was getting 203k+ with 150k)
            'openai' => 80000,     // OpenAI: varies, use very conservative 80k
            'google' => 600000,    // Gemini: 2M limit, use 600k for safety
        ];
        
        $maxTokens = $tokenLimits[$provider] ?? 80000;
        
        // Sample more texts to get better average
        $sampleSize = min(10, count($jsonData));
        $totalSampleTokens = 0;
        $totalContentLength = 0;
        
        for ($i = 0; $i < $sampleSize; $i++) {
            $text = $jsonData[$i]['data']['content'] ?? '';
            $contentLength = strlen($text);
            $totalContentLength += $contentLength;
            
            // Very conservative token estimation: ~2.5 characters per token for safety
            $estimatedTokens = $contentLength / 2.5;
            $totalSampleTokens += $estimatedTokens;
        }
        
        $averageTokensPerText = $sampleSize > 0 ? ($totalSampleTokens / $sampleSize) : 1000;
        $averageContentLength = $sampleSize > 0 ? ($totalContentLength / $sampleSize) : 3000;
        
        // Calculate JSON overhead very conservatively
        // Each text in JSON adds: {"text_id":"ID","data":{"content":"TEXT"},"annotations":[...]}
        // Plus response structure overhead per text
        $jsonStructurePerText = strlen('{"text_id":"","data":{"content":""},"annotations":[]}') / 2.5; // ~48 tokens
        $responseStructurePerText = strlen('{"text_id":"","primaryChoice":{"choices":["yes"]},"annotations":[],"desinformationTechnique":{"choices":[]}}') / 2.5; // ~78 tokens
        $additionalSafetyBuffer = 50; // Additional safety buffer per text
        $totalOverheadPerText = $jsonStructurePerText + $responseStructurePerText + $additionalSafetyBuffer; // ~176 tokens per text
        
        $promptOverhead = $this->estimatePromptTokens($customPrompt);
        
        // Total tokens per text = content + JSON structure + response structure
        $totalTokensPerText = $averageTokensPerText + $totalOverheadPerText;
        
        // Calculate how many texts can fit
        $availableTokens = $maxTokens - $promptOverhead;
        $textsPerChunk = max(1, floor($availableTokens / $totalTokensPerText));
        
        // Cap chunk size very conservatively based on provider
        $maxChunkSizes = [
            'anthropic' => 15, // Claude chunks were failing at 25, try 15
            'openai' => 20,    // OpenAI was getting JSON parsing errors, try smaller chunks
            'google' => 30
        ];
        $maxChunkSize = $maxChunkSizes[$provider] ?? 10;
        
        $chunkSize = max(1, min($maxChunkSize, $textsPerChunk));
        
        $this->logProgress("Calculated optimal chunk size", [
            'model' => $modelKey,
            'provider' => $provider,
            'max_tokens' => $maxTokens,
            'avg_tokens_per_text' => round($averageTokensPerText),
            'avg_content_length' => round($averageContentLength),
            'total_tokens_per_text' => round($totalTokensPerText),
            'prompt_overhead' => $promptOverhead,
            'available_tokens' => $availableTokens,
            'calculated_texts_per_chunk' => $textsPerChunk,
            'final_chunk_size' => $chunkSize,
            'max_chunk_size_cap' => $maxChunkSize,
            'status' => 'chunk_calculation'
        ]);
        
        return (int) $chunkSize;
    }

    /**
     * Estimate token count for prompt overhead.
     */
    private function estimatePromptTokens(?string $customPrompt = null): int
    {
        $systemMessage = app(\App\Services\PromptService::class)->getSystemMessage();
        $basePrompt = $this->createFileAnalysisPrompt($customPrompt);
        
        // Very conservative estimation for multilingual content: ~2.5 characters per token
        $totalPromptLength = strlen($systemMessage) + strlen($basePrompt);
        $estimatedTokens = (int) ($totalPromptLength / 2.5);
        
        // Add larger buffer for potential prompt variations and safety
        return $estimatedTokens + 1000; // Add 1000 token buffer
    }

}