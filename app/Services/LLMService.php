<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserApiKey;
use App\Models\AuditLog;

class LLMService
{
    public function getApiKey(string $provider, ?User $user = null): ?string
    {
        $user = $user ?? auth()->user();
        
        if (!$user) {
            // Fallback to config for non-authenticated requests
            return config("llm.models.{$this->getDefaultModelForProvider($provider)}.api_key");
        }

        $apiKey = $user->getApiKey($provider);
        
        if ($apiKey) {
            // Update usage stats
            $userApiKey = $user->apiKeys()->where('provider', $provider)->where('is_active', true)->first();
            if ($userApiKey) {
                $userApiKey->updateUsageStats([
                    'last_request_at' => now()->toISOString(),
                    'total_requests' => ($userApiKey->usage_stats['total_requests'] ?? 0) + 1,
                ]);
            }
            
            return $apiKey;
        }

        // Fallback to config if user doesn't have API key
        return config("llm.models.{$this->getDefaultModelForProvider($provider)}.api_key");
    }

    public function getModelConfig(string $modelName, ?User $user = null): array
    {
        $config = config("llm.models.{$modelName}");
        
        if (!$config) {
            throw new \InvalidArgumentException("Model {$modelName} not found in configuration");
        }

        // Override API key with user-specific key if available
        $provider = $config['provider'];
        $userApiKey = $this->getApiKey($provider, $user);
        
        if ($userApiKey) {
            $config['api_key'] = $userApiKey;
        }

        return $config;
    }

    public function hasValidApiKey(string $provider, ?User $user = null): bool
    {
        $apiKey = $this->getApiKey($provider, $user);
        return !empty($apiKey);
    }

    public function logApiUsage(string $provider, array $usage, ?User $user = null): void
    {
        $user = $user ?? auth()->user();
        
        if (!$user) {
            return;
        }

        $userApiKey = $user->apiKeys()->where('provider', $provider)->where('is_active', true)->first();
        
        if ($userApiKey) {
            $userApiKey->updateUsageStats($usage);
            
            AuditLog::log('api_usage', 'UserApiKey', $userApiKey->id, [], $usage);
        }
    }

    private function getDefaultModelForProvider(string $provider): string
    {
        $providers = config('llm.providers');
        return $providers[$provider]['default_model'] ?? '';
    }

    public function getAvailableModelsForUser(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $allModels = config('llm.models', []);
        
        if (!$user) {
            // Return only models with config API keys
            return array_filter($allModels, function($config) {
                return !empty($config['api_key']);
            });
        }

        $availableModels = [];
        
        foreach ($allModels as $modelName => $config) {
            $provider = $config['provider'];
            
            // Check if user has API key for this provider OR config has API key
            if ($user->hasApiKey($provider) || !empty($config['api_key'])) {
                $availableModels[$modelName] = $config;
            }
        }

        return $availableModels;
    }
}