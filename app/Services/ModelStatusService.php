<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Model status checking service.
 * 
 * Checks connectivity and availability of configured AI models.
 */
class ModelStatusService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const HEALTH_CHECK_TIMEOUT = 15; // 15 seconds for more thorough checks
    private const HEALTH_CHECK_RETRIES = 2; // Number of retries for failed checks
    
    /**
     * Get status of all configured models.
     */
    public function getAllModelStatuses(): array
    {
        $models = config('llm.models', []);
        $providers = config('llm.providers', []);
        $statuses = [];
        
        foreach ($models as $modelKey => $modelConfig) {
            $cacheKey = "model_status_{$modelKey}";
            
            // Try to get from cache first
            $status = Cache::get($cacheKey);
            
            if ($status === null) {
                $status = $this->checkModelStatus($modelKey, $modelConfig);
                Cache::put($cacheKey, $status, self::CACHE_TTL);
            }
            
            $provider = $modelConfig['provider'] ?? 'unknown';
            $providerConfig = $providers[$provider] ?? [];
            
            $statuses[$modelKey] = array_merge($status, [
                'model_name' => $modelConfig['model'] ?? $modelKey,
                'provider' => $provider,
                'provider_name' => $providerConfig['name'] ?? ucfirst($provider),
                'provider_icon' => $providerConfig['icon'] ?? 'fas fa-robot',
                'provider_color' => $providerConfig['color'] ?? 'secondary',
                'tier' => $modelConfig['tier'] ?? 'standard',
                'description' => $modelConfig['description'] ?? '',
            ]);
        }
        
        return $statuses;
    }
    
    /**
     * Get status of a specific model.
     */
    public function getModelStatus(string $modelKey): array
    {
        $models = config('llm.models', []);
        
        if (!isset($models[$modelKey])) {
            return [
                'status' => 'not_found',
                'message' => 'Model not found in configuration',
                'online' => false,
                'last_checked' => now()->toISOString(),
            ];
        }
        
        $cacheKey = "model_status_{$modelKey}";
        $status = Cache::get($cacheKey);
        
        if ($status === null) {
            $status = $this->checkModelStatus($modelKey, $models[$modelKey]);
            Cache::put($cacheKey, $status, self::CACHE_TTL);
        }
        
        return $status;
    }
    
    /**
     * Force refresh status for a specific model.
     */
    public function refreshModelStatus(string $modelKey): array
    {
        $models = config('llm.models', []);
        
        if (!isset($models[$modelKey])) {
            return [
                'status' => 'not_found',
                'message' => 'Model not found in configuration',
                'online' => false,
                'last_checked' => now()->toISOString(),
            ];
        }
        
        $status = $this->checkModelStatus($modelKey, $models[$modelKey]);
        $cacheKey = "model_status_{$modelKey}";
        Cache::put($cacheKey, $status, self::CACHE_TTL);
        
        return $status;
    }
    
    /**
     * Force refresh all model statuses.
     */
    public function refreshAllModelStatuses(): array
    {
        $models = config('llm.models', []);
        $statuses = [];
        
        foreach ($models as $modelKey => $modelConfig) {
            $status = $this->checkModelStatus($modelKey, $modelConfig);
            $cacheKey = "model_status_{$modelKey}";
            Cache::put($cacheKey, $status, self::CACHE_TTL);
            $statuses[$modelKey] = $status;
        }
        
        return $statuses;
    }
    
    /**
     * Check if a specific model is online.
     */
    public function isModelOnline(string $modelKey): bool
    {
        $status = $this->getModelStatus($modelKey);
        return $status['online'] ?? false;
    }
    
    /**
     * Get count of online models.
     */
    public function getOnlineModelCount(): int
    {
        $statuses = $this->getAllModelStatuses();
        return count(array_filter($statuses, fn($status) => $status['online'] ?? false));
    }
    
    /**
     * Get overall system health.
     */
    public function getSystemHealth(): array
    {
        $statuses = $this->getAllModelStatuses();
        $totalModels = count($statuses);
        $onlineModels = count(array_filter($statuses, fn($status) => $status['online'] ?? false));
        
        $healthStatus = 'healthy';
        if ($onlineModels === 0) {
            $healthStatus = 'critical';
        } elseif ($onlineModels < $totalModels * 0.5) {
            $healthStatus = 'degraded';
        }
        
        return [
            'status' => $healthStatus,
            'total_models' => $totalModels,
            'online_models' => $onlineModels,
            'offline_models' => $totalModels - $onlineModels,
            'last_checked' => now()->toISOString(),
            'models' => $statuses,
        ];
    }
    
    /**
     * Perform actual status check for a model.
     */
    private function checkModelStatus(string $modelKey, array $modelConfig): array
    {
        $provider = $modelConfig['provider'] ?? 'unknown';
        $baseStatus = [
            'status' => 'unknown',
            'message' => '',
            'online' => false,
            'last_checked' => now()->toISOString(),
            'response_time' => null,
            'configured' => !empty($modelConfig['api_key']),
        ];
        
        // Check if API key is configured
        if (empty($modelConfig['api_key'])) {
            return array_merge($baseStatus, [
                'status' => 'not_configured',
                'message' => 'API key not configured',
                'online' => false,
            ]);
        }
        
        // Try the health check with retries
        $lastException = null;
        for ($attempt = 1; $attempt <= self::HEALTH_CHECK_RETRIES; $attempt++) {
            try {
                $startTime = microtime(true);
                
                switch ($provider) {
                    case 'anthropic':
                        $status = $this->checkClaudeStatus($modelConfig, $attempt);
                        break;
                    case 'openai':
                        $status = $this->checkOpenAIStatus($modelConfig, $attempt);
                        break;
                    case 'google':
                        $status = $this->checkGeminiStatus($modelConfig, $attempt);
                        break;
                    default:
                        return array_merge($baseStatus, [
                            'status' => 'unsupported',
                            'message' => 'Provider not supported for health checks',
                            'online' => false,
                        ]);
                }
                
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                $status['response_time'] = $responseTime;
                $status['attempts'] = $attempt;
                
                // If we got here, the check was successful
                return array_merge($baseStatus, $status);
                
            } catch (\Exception $e) {
                $lastException = $e;
                
                // Log the attempt failure
                Log::debug("Model status check attempt {$attempt} failed for {$modelKey}", [
                    'error' => $e->getMessage(),
                    'provider' => $provider,
                    'attempt' => $attempt,
                ]);
                
                // If this was the last attempt, we'll fall through to the error handling
                if ($attempt < self::HEALTH_CHECK_RETRIES) {
                    // Wait a bit before retrying
                    usleep(500000); // 0.5 seconds
                }
            }
        }
        
        // If we get here, all retries failed
        Log::warning("Model status check failed for {$modelKey} after {attempts} attempts", [
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'provider' => $provider,
            'attempts' => self::HEALTH_CHECK_RETRIES,
        ]);
        
        return array_merge($baseStatus, [
            'status' => 'error',
            'message' => $lastException ? $lastException->getMessage() : 'Health check failed after retries',
            'online' => false,
            'attempts' => self::HEALTH_CHECK_RETRIES,
        ]);
    }
    
    /**
     * Check Claude/Anthropic API status with meaningful test query.
     */
    private function checkClaudeStatus(array $config, int $attempt = 1): array
    {
        // Use a more comprehensive test that validates JSON response capability
        $testPrompt = $attempt === 1 ? 
            'Respond with valid JSON: {"status": "ok", "test": true}' :
            'Health check test - respond with JSON containing test: true';
            
        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(self::HEALTH_CHECK_TIMEOUT)
        ->post($config['base_url'] . 'messages', [
            'model' => $config['model'],
            'max_tokens' => 50,
            'temperature' => 0,
            'system' => 'You are a health check system. Respond only with valid JSON.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $testPrompt
                ]
            ]
        ]);
        
        if ($response->successful()) {
            $responseData = $response->json();
            $isValidResponse = isset($responseData['content'][0]['text']);
            
            // Try to validate the response content
            $responseText = $responseData['content'][0]['text'] ?? '';
            $hasValidJsonResponse = strpos($responseText, '"test"') !== false || 
                                  strpos($responseText, '"status"') !== false;
            
            return [
                'status' => 'online',
                'message' => $hasValidJsonResponse ? 
                    'API responding with valid JSON capability' : 
                    'API responding normally',
                'online' => true,
                'response_valid' => $isValidResponse,
                'json_capable' => $hasValidJsonResponse,
            ];
        } else {
            $statusCode = $response->status();
            $errorMessage = $this->parseErrorMessage($response->body(), $statusCode);
            
            return [
                'status' => $statusCode === 401 ? 'auth_error' : 'api_error',
                'message' => $errorMessage,
                'online' => false,
                'http_status' => $statusCode,
            ];
        }
    }
    
    /**
     * Check OpenAI API status with meaningful test query.
     */
    private function checkOpenAIStatus(array $config, int $attempt = 1): array
    {
        // Use a test that validates JSON response capability
        $testPrompt = $attempt === 1 ? 
            'Respond with valid JSON: {"status": "ok", "test": true}' :
            'Health check - reply with JSON format containing test: true';
            
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ])
        ->timeout(self::HEALTH_CHECK_TIMEOUT)
        ->post($config['base_url'] . '/chat/completions', [
            'model' => $config['model'],
            'max_tokens' => 50,
            'temperature' => 0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a health check system. Respond only with valid JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $testPrompt
                ]
            ]
        ]);
        
        if ($response->successful()) {
            $responseData = $response->json();
            $isValidResponse = isset($responseData['choices'][0]['message']['content']);
            
            // Try to validate the response content
            $responseText = $responseData['choices'][0]['message']['content'] ?? '';
            $hasValidJsonResponse = strpos($responseText, '"test"') !== false || 
                                  strpos($responseText, '"status"') !== false;
            
            return [
                'status' => 'online',
                'message' => $hasValidJsonResponse ? 
                    'API responding with valid JSON capability' : 
                    'API responding normally',
                'online' => true,
                'response_valid' => $isValidResponse,
                'json_capable' => $hasValidJsonResponse,
            ];
        } else {
            $statusCode = $response->status();
            $errorMessage = $this->parseErrorMessage($response->body(), $statusCode);
            
            return [
                'status' => $statusCode === 401 ? 'auth_error' : 'api_error',
                'message' => $errorMessage,
                'online' => false,
                'http_status' => $statusCode,
            ];
        }
    }
    
    /**
     * Check Gemini API status with meaningful test query.
     */
    private function checkGeminiStatus(array $config, int $attempt = 1): array
    {
        $url = $config['base_url'] . 'v1beta/models/' . $config['model'] . ':generateContent?key=' . $config['api_key'];
        
        // Use a test that validates JSON response capability
        $testPrompt = $attempt === 1 ? 
            'Respond with valid JSON: {"status": "ok", "test": true}' :
            'Health check test - respond with JSON containing test: true';
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->timeout(self::HEALTH_CHECK_TIMEOUT)
        ->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'You are a health check system. ' . $testPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 50,
                'temperature' => 0,
            ]
        ]);
        
        if ($response->successful()) {
            $responseData = $response->json();
            $isValidResponse = isset($responseData['candidates'][0]['content']['parts'][0]['text']);
            
            // Try to validate the response content
            $responseText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $hasValidJsonResponse = strpos($responseText, '"test"') !== false || 
                                  strpos($responseText, '"status"') !== false;
            
            return [
                'status' => 'online',
                'message' => $hasValidJsonResponse ? 
                    'API responding with valid JSON capability' : 
                    'API responding normally',
                'online' => true,
                'response_valid' => $isValidResponse,
                'json_capable' => $hasValidJsonResponse,
            ];
        } else {
            $statusCode = $response->status();
            $errorMessage = $this->parseErrorMessage($response->body(), $statusCode);
            
            return [
                'status' => $statusCode === 401 || $statusCode === 403 ? 'auth_error' : 'api_error',
                'message' => $errorMessage,
                'online' => false,
                'http_status' => $statusCode,
            ];
        }
    }
    
    /**
     * Parse error message from API response.
     */
    private function parseErrorMessage(string $responseBody, int $statusCode): string
    {
        try {
            $json = json_decode($responseBody, true);
            
            if (isset($json['error']['message'])) {
                return $json['error']['message'];
            } elseif (isset($json['error'])) {
                return is_string($json['error']) ? $json['error'] : 'API Error';
            }
        } catch (\Exception $e) {
            // Ignore JSON parsing errors
        }
        
        switch ($statusCode) {
            case 401:
                return 'Authentication failed - check API key';
            case 403:
                return 'Access forbidden - check API permissions';
            case 404:
                return 'API endpoint not found';
            case 429:
                return 'Rate limit exceeded';
            case 500:
                return 'Internal server error';
            case 503:
                return 'Service unavailable';
            default:
                return "HTTP {$statusCode} error";
        }
    }
    
    /**
     * Clear all cached model statuses.
     */
    public function clearCache(): void
    {
        $models = config('llm.models', []);
        
        foreach (array_keys($models) as $modelKey) {
            Cache::forget("model_status_{$modelKey}");
        }
    }
}