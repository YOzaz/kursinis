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
    private const HEALTH_CHECK_TIMEOUT = 10; // 10 seconds
    
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
        
        try {
            $startTime = microtime(true);
            
            switch ($provider) {
                case 'anthropic':
                    $status = $this->checkClaudeStatus($modelConfig);
                    break;
                case 'openai':
                    $status = $this->checkOpenAIStatus($modelConfig);
                    break;
                case 'google':
                    $status = $this->checkGeminiStatus($modelConfig);
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
            
            return array_merge($baseStatus, $status);
            
        } catch (\Exception $e) {
            Log::warning("Model status check failed for {$modelKey}", [
                'error' => $e->getMessage(),
                'provider' => $provider,
            ]);
            
            return array_merge($baseStatus, [
                'status' => 'error',
                'message' => $e->getMessage(),
                'online' => false,
            ]);
        }
    }
    
    /**
     * Check Claude/Anthropic API status.
     */
    private function checkClaudeStatus(array $config): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(self::HEALTH_CHECK_TIMEOUT)
        ->post($config['base_url'] . 'messages', [
            'model' => $config['model'],
            'max_tokens' => 10,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Hello'
                ]
            ]
        ]);
        
        if ($response->successful()) {
            return [
                'status' => 'online',
                'message' => 'API responding normally',
                'online' => true,
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
     * Check OpenAI API status.
     */
    private function checkOpenAIStatus(array $config): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ])
        ->timeout(self::HEALTH_CHECK_TIMEOUT)
        ->post($config['base_url'] . '/chat/completions', [
            'model' => $config['model'],
            'max_tokens' => 10,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Hello'
                ]
            ]
        ]);
        
        if ($response->successful()) {
            return [
                'status' => 'online',
                'message' => 'API responding normally',
                'online' => true,
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
     * Check Gemini API status.
     */
    private function checkGeminiStatus(array $config): array
    {
        $url = $config['base_url'] . 'v1beta/models/' . $config['model'] . ':generateContent?key=' . $config['api_key'];
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->timeout(self::HEALTH_CHECK_TIMEOUT)
        ->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Hello']
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 10,
            ]
        ]);
        
        if ($response->successful()) {
            return [
                'status' => 'online',
                'message' => 'API responding normally',
                'online' => true,
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