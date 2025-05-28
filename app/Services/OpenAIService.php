<?php

namespace App\Services;

use OpenAI;
use OpenAI\Client;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI ChatGPT API servisas.
 * 
 * Integruoja su OpenAI API propagandos analizei.
 * Naudoja oficialų OpenAI PHP klientą.
 */
class OpenAIService implements LLMServiceInterface
{
    private ?Client $client;
    private PromptService $promptService;
    private ?array $config;
    private ?string $modelKey;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
        $this->config = null;
        $this->modelKey = null;
        $models = config('llm.models', []);
        
        // Rasti bet kurį GPT modelį kaip numatytąjį
        foreach ($models as $key => $config) {
            if (str_starts_with($key, 'gpt')) {
                $this->config = $config;
                $this->modelKey = $key;
                break;
            }
        }
        
        if ($this->config && !empty($this->config['api_key'])) {
            $this->client = OpenAI::client($this->config['api_key']);
        } else {
            $this->client = null;
        }
    }

    /**
     * Analizuoti tekstą naudojant OpenAI API.
     */
    public function analyzeText(string $text, ?string $customPrompt = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('OpenAI API nėra sukonfigūruotas');
        }

        $prompt = $this->promptService->generateAnalysisPrompt($text, $customPrompt);
        $systemMessage = $this->promptService->getSystemMessage();

        $retries = config('llm.retry_attempts');
        $retryDelay = config('llm.retry_delay');

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $response = $this->client->chat()->create([
                    'model' => $this->config['model'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemMessage
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => $this->config['max_tokens'],
                    'temperature' => $this->config['temperature'],
                    'response_format' => [
                        'type' => 'json_object'
                    ]
                ]);

                if (empty($response->choices)) {
                    throw new \Exception('OpenAI API grąžino tuščią atsakymą');
                }

                $content = $response->choices[0]->message->content;
                $jsonResponse = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('OpenAI grąžino netinkamą JSON: ' . json_last_error_msg());
                }

                if (!$this->promptService->validateResponse($jsonResponse)) {
                    Log::warning('OpenAI grąžino netinkamą atsakymą', [
                        'attempt' => $attempt,
                        'response' => $jsonResponse
                    ]);
                    
                    if ($attempt < $retries) {
                        sleep($retryDelay * $attempt);
                        continue;
                    }
                    
                    throw new \Exception('OpenAI grąžino netinkamą atsakymo formatą');
                }

                Log::info('OpenAI sėkmingai išanalizavo tekstą', [
                    'text_length' => strlen($text),
                    'annotations_count' => count($jsonResponse['annotations'] ?? []),
                    'model_used' => $this->config['model']
                ]);

                return $jsonResponse;

            } catch (\Exception $e) {
                Log::error('OpenAI API klaida', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'model' => $this->config['model']
                ]);

                if ($attempt < $retries) {
                    sleep($retryDelay * $attempt);
                    continue;
                }

                throw new \Exception('OpenAI API neprieinamas po ' . $retries . ' bandymų: ' . $e->getMessage());
            }
        }

        throw new \Exception('OpenAI analizė nepavyko po visų bandymų');
    }

    /**
     * Gauti modelio pavadinimą.
     */
    public function getModelName(): string
    {
        return $this->modelKey ?? 'gpt-4.1';
    }

    /**
     * Gauti tikrą modelio pavadinimą.
     */
    public function getActualModelName(): string
    {
        return $this->config['model'] ?? 'gpt-4o';
    }

    /**
     * Patikrinti ar servisas sukonfigūruotas.
     */
    public function isConfigured(): bool
    {
        return $this->client !== null && isset($this->config) && !empty($this->config['api_key'] ?? '');
    }

    /**
     * Nustatyti dabartinį modelį.
     */
    public function setModel(string $modelKey): bool
    {
        $models = config('llm.models', []);
        $this->config = $models[$modelKey] ?? null;
        
        if ($this->config && !empty($this->config['api_key'])) {
            $this->client = \OpenAI::client($this->config['api_key']);
            return true;
        }
        
        return false;
    }

    /**
     * Gauti visus galimus modelius.
     */
    public function getAvailableModels(): array
    {
        $models = config('llm.models', []);
        $openaiModels = [];
        
        foreach ($models as $key => $config) {
            if (strpos($key, 'gpt') === 0 || strpos($key, 'openai') === 0) {
                $openaiModels[$key] = [
                    'name' => $config['model'] ?? $key,
                    'provider' => 'OpenAI',
                    'configured' => !empty($config['api_key'])
                ];
            }
        }
        
        return $openaiModels;
    }

    /**
     * Pakartoti analizę su konkrečiu modeliu.
     */
    public function retryWithModel(string $modelKey, string $text, ?string $customPrompt = null): array
    {
        $originalConfig = $this->config;
        $originalClient = $this->client;
        
        try {
            if ($this->setModel($modelKey)) {
                return $this->analyzeText($text, $customPrompt);
            } else {
                throw new \Exception("Modelis {$modelKey} neprieinamas");
            }
        } finally {
            $this->config = $originalConfig;
            $this->client = $originalClient;
        }
    }
}