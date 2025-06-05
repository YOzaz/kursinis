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

    public int $tries = 3;
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
        
        switch ($provider) {
            case 'anthropic':
                return $this->processClaudeWithAttachment($modelKey, $tempFile, $customPrompt);
            case 'openai':
                return $this->processOpenAIWithAttachment($modelKey, $tempFile, $customPrompt);
            case 'google':
                return $this->processGeminiWithFileAPI($modelKey, $tempFile, $customPrompt);
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }

    /**
     * Process using Claude API with structured JSON in message.
     */
    private function processClaudeWithAttachment(string $modelKey, string $tempFile, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        $systemMessage = app(\App\Services\PromptService::class)->getSystemMessage();
        
        // Read file content and include in prompt
        $fileContent = file_get_contents($tempFile);
        
        $this->logProgress("Sending structured data to Claude API", [
            'model' => $modelKey,
            'file_size' => strlen($fileContent),
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
        
        $this->logProgress("Sending structured data to OpenAI API", [
            'model' => $modelKey,
            'file_size' => strlen($fileContent),
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
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->timeout(1500)
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
        
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid Gemini API response format');
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
            throw new \Exception('Failed to parse file response JSON: ' . json_last_error_msg());
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
        // Count how many models have completed for this job
        $textAnalyses = TextAnalysis::where('job_id', $this->jobId)->get();
        $totalModels = count($job->requested_models ?? []);
        $totalTexts = $textAnalyses->groupBy('text_id')->count();
        
        $completedModels = 0;
        
        // Check completion for each model using new architecture
        $models = $job->requested_models ?? [];
        foreach ($models as $modelKey) {
            // Check if this model has completed processing for all texts
            $modelResults = \App\Models\ModelResult::where('job_id', $this->jobId)
                ->where('model_key', $modelKey)
                ->whereIn('status', ['completed', 'failed'])
                ->count();
            
            if ($modelResults >= $totalTexts) {
                $completedModels++;
            }
        }
        
        // Update job progress
        $job->processed_texts = $completedModels * $totalTexts;
        
        // Check if all models are completed
        if ($completedModels >= $totalModels) {
            $job->status = AnalysisJob::STATUS_COMPLETED;
            $job->total_texts = $totalModels * $totalTexts;
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
}