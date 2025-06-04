<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Batch analysis service for processing multiple texts in single API requests.
 * 
 * This service optimizes API usage by batching multiple texts together
 * instead of making individual requests for each text.
 */
class BatchAnalysisService
{
    private PromptService $promptService;
    
    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
    }
    
    /**
     * Process multiple texts in a single batch request.
     * 
     * @param array $texts Array of text items with id, content, and annotations
     * @param string $modelKey The model to use for analysis
     * @param string|null $customPrompt Optional custom prompt
     * @return array Results indexed by text ID
     */
    public function analyzeBatch(array $texts, string $modelKey, ?string $customPrompt = null): array
    {
        $modelConfig = config("llm.models.{$modelKey}");
        
        if (!$modelConfig) {
            throw new \Exception("Model {$modelKey} not found in configuration");
        }
        
        $batchSize = $modelConfig['batch_size'] ?? 50;
        $provider = $modelConfig['provider'];
        
        // Split texts into batches
        $batches = array_chunk($texts, $batchSize);
        $results = [];
        
        Log::info("Starting batch analysis", [
            'model' => $modelKey,
            'total_texts' => count($texts),
            'batch_size' => $batchSize,
            'total_batches' => count($batches),
            'provider' => $provider
        ]);
        
        foreach ($batches as $batchIndex => $batch) {
            try {
                $batchResults = $this->processSingleBatch($batch, $modelKey, $customPrompt);
                $results = array_merge($results, $batchResults);
                
                Log::info("Batch processed successfully", [
                    'batch_index' => $batchIndex + 1,
                    'batch_size' => count($batch),
                    'results_count' => count($batchResults)
                ]);
                
            } catch (\Exception $e) {
                Log::error("Batch processing failed", [
                    'batch_index' => $batchIndex + 1,
                    'error' => $e->getMessage(),
                    'model' => $modelKey
                ]);
                
                // If batch fails, fall back to individual processing
                $individualResults = $this->fallbackToIndividualProcessing($batch, $modelKey, $customPrompt);
                $results = array_merge($results, $individualResults);
            }
        }
        
        return $results;
    }
    
    /**
     * Process a single batch of texts.
     */
    private function processSingleBatch(array $batch, string $modelKey, ?string $customPrompt = null): array
    {
        $modelConfig = config("llm.models.{$modelKey}");
        $provider = $modelConfig['provider'];
        
        switch ($provider) {
            case 'anthropic':
                return $this->processClaudeBatch($batch, $modelConfig, $customPrompt);
            case 'openai':
                return $this->processOpenAIBatch($batch, $modelConfig, $customPrompt);
            case 'google':
                return $this->processGeminiBatch($batch, $modelConfig, $customPrompt);
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }
    
    /**
     * Process batch using Claude API.
     */
    private function processClaudeBatch(array $batch, array $config, ?string $customPrompt = null): array
    {
        $batchPrompt = $this->createBatchPrompt($batch, $customPrompt);
        $systemMessage = $this->promptService->getSystemMessage();
        
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(300) // Longer timeout for batch processing
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
        
        return $this->parseBatchResponse($responseData['content'][0]['text'], $batch);
    }
    
    /**
     * Process batch using OpenAI API.
     */
    private function processOpenAIBatch(array $batch, array $config, ?string $customPrompt = null): array
    {
        $batchPrompt = $this->createBatchPrompt($batch, $customPrompt);
        
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
                    'content' => $this->promptService->getSystemMessage()
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
        
        return $this->parseBatchResponse($responseData['choices'][0]['message']['content'], $batch);
    }
    
    /**
     * Process batch using Gemini API.
     */
    private function processGeminiBatch(array $batch, array $config, ?string $customPrompt = null): array
    {
        $batchPrompt = $this->createBatchPrompt($batch, $customPrompt);
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
        
        return $this->parseBatchResponse($responseData['candidates'][0]['content']['parts'][0]['text'], $batch);
    }
    
    /**
     * Create a batch prompt containing multiple texts.
     */
    private function createBatchPrompt(array $batch, ?string $customPrompt = null): string
    {
        $basePrompt = $customPrompt ?: $this->promptService->getAnalysisPromptTemplate();
        
        $prompt = $basePrompt . "\n\n";
        $prompt .= "SVARBU: Aš duosiu jums KELIŲ tekstų rinkinį analizei. Prašome pateikti atsakymą JSON masyvo formatu, kur kiekvienas elementas atitinka vieną tekstą.\n\n";
        $prompt .= "TEKSTŲ RINKINYS ANALIZEI:\n\n";
        
        foreach ($batch as $index => $textItem) {
            $textNumber = $index + 1;
            $prompt .= "=== TEKSTAS #{$textNumber} (ID: {$textItem['id']}) ===\n";
            $prompt .= $textItem['content'] . "\n\n";
        }
        
        $prompt .= "\n\nATSAKYMO FORMATAS:\n";
        $prompt .= "Grąžinkite JSON masyvą, kuriame kiekvienas elementas turi šią struktūrą:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"text_id\": \"{$batch[0]['id']}\",\n";
        $prompt .= "    \"primaryChoice\": {\"choices\": [\"yes\" arba \"no\"]},\n";
        $prompt .= "    \"annotations\": [...],\n";
        $prompt .= "    \"desinformationTechnique\": {\"choices\": [...]}\n";
        $prompt .= "  },\n";
        $prompt .= "  // ... kitų tekstų rezultatai\n";
        $prompt .= "]\n\n";
        $prompt .= "Svarbu: masyve turi būti tiksliai " . count($batch) . " elementų, atitinkančių pateiktų tekstų tvarką.";
        
        return $prompt;
    }
    
    /**
     * Parse batch response and extract results for each text.
     */
    private function parseBatchResponse(string $responseContent, array $batch): array
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
            throw new \Exception('Failed to parse batch JSON response: ' . json_last_error_msg());
        }
        
        if (!is_array($decoded)) {
            throw new \Exception('Batch response is not an array');
        }
        
        $results = [];
        
        // Map results back to text IDs
        foreach ($decoded as $index => $result) {
            if (isset($batch[$index])) {
                $textId = $batch[$index]['id'];
                
                // Ensure the result has the required structure
                if (!isset($result['text_id'])) {
                    $result['text_id'] = $textId;
                }
                
                $results[$textId] = $result;
            }
        }
        
        // Fill in missing results with errors
        foreach ($batch as $textItem) {
            if (!isset($results[$textItem['id']])) {
                $results[$textItem['id']] = [
                    'text_id' => $textItem['id'],
                    'error' => 'No result found in batch response',
                    'primaryChoice' => ['choices' => ['no']],
                    'annotations' => [],
                    'desinformationTechnique' => ['choices' => []]
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Fallback to individual processing if batch fails.
     */
    private function fallbackToIndividualProcessing(array $batch, string $modelKey, ?string $customPrompt = null): array
    {
        Log::warning("Falling back to individual processing for batch", [
            'batch_size' => count($batch),
            'model' => $modelKey
        ]);
        
        $results = [];
        
        foreach ($batch as $textItem) {
            try {
                // Use the existing individual processing logic
                $service = $this->getServiceForModel($modelKey);
                $result = $service->analyzeText($textItem['content'], $customPrompt);
                $result['text_id'] = $textItem['id'];
                $results[$textItem['id']] = $result;
                
            } catch (\Exception $e) {
                Log::error("Individual text processing failed", [
                    'text_id' => $textItem['id'],
                    'error' => $e->getMessage()
                ]);
                
                $results[$textItem['id']] = [
                    'text_id' => $textItem['id'],
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
     * Get the appropriate service for a model.
     */
    private function getServiceForModel(string $modelKey): LLMServiceInterface
    {
        $modelConfig = config("llm.models.{$modelKey}");
        $provider = $modelConfig['provider'];
        
        switch ($provider) {
            case 'anthropic':
                return app(ClaudeService::class);
            case 'openai':
                return app(OpenAIService::class);
            case 'google':
                return app(GeminiService::class);
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }
    
    /**
     * Estimate token count for a text (rough approximation).
     */
    private function estimateTokenCount(string $text): int
    {
        // Rough approximation: 1 token ≈ 4 characters for most languages
        return intval(strlen($text) / 4);
    }
    
    /**
     * Calculate optimal batch size based on text lengths and model context window.
     */
    public function calculateOptimalBatchSize(array $texts, string $modelKey): int
    {
        $modelConfig = config("llm.models.{$modelKey}");
        $contextWindow = $modelConfig['context_window'] ?? 50000;
        $maxBatchSize = $modelConfig['batch_size'] ?? 50;
        
        $avgTextLength = 0;
        if (!empty($texts)) {
            $totalLength = array_sum(array_map(fn($text) => strlen($text['content'] ?? ''), $texts));
            $avgTextLength = $totalLength / count($texts);
        }
        
        $avgTokens = $this->estimateTokenCount($avgTextLength);
        
        // Reserve 20% of context window for prompts and responses
        $availableTokens = intval($contextWindow * 0.8);
        $optimalBatchSize = intval($availableTokens / $avgTokens);
        
        // Cap at configured maximum
        return min($optimalBatchSize, $maxBatchSize);
    }
}