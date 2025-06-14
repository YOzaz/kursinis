<?php

namespace App\Services;

use OpenAI;
use OpenAI\Client;
use Illuminate\Support\Facades\Log;
use App\Services\Exceptions\OpenAIErrorHandler;
use App\Services\Exceptions\LLMException;
use App\Models\User;

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
    private OpenAIErrorHandler $errorHandler;
    private LLMService $llmService;
    private ?User $user = null;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
        $this->errorHandler = new OpenAIErrorHandler();
        $this->llmService = new LLMService();
        $this->config = null;
        $this->modelKey = null;
        
        // Get user from SimpleAuth session
        $username = session('username');
        if ($username) {
            $this->user = User::where('email', $username . '@local')->first();
        }
        
        $models = config('llm.models', []);
        
        // Rasti bet kurį GPT modelį kaip numatytąjį
        foreach ($models as $key => $config) {
            if (str_starts_with($key, 'gpt')) {
                // Get config with user-specific API key if available
                $this->config = $this->llmService->getModelConfig($key, $this->user);
                $this->modelKey = $key;
                break;
            }
        }
        
        if ($this->config && !empty($this->config['api_key'])) {
            $this->client = OpenAI::factory()
                ->withApiKey($this->config['api_key'])
                ->withHttpHeader('User-Agent', 'ATSPARA-Analysis/1.0')
                ->withBaseUri($this->config['base_url'])
                ->withHttpClient(new \GuzzleHttp\Client([
                    'timeout' => config('llm.error_handling.timeout_seconds', 120)
                ]))
                ->make();
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

        $retries = config('llm.error_handling.max_retries_per_model', 3);
        $baseRetryDelay = config('llm.error_handling.retry_delay_seconds', 2);
        $useExponentialBackoff = config('llm.error_handling.exponential_backoff', true);

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
                        $delay = $useExponentialBackoff 
                            ? $baseRetryDelay * pow(2, $attempt - 1)
                            : $baseRetryDelay * $attempt;
                        sleep($delay);
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
                $llmException = $this->errorHandler->handleException($e);
                
                Log::error('OpenAI API klaida', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'model' => $this->config['model'],
                    'status_code' => $llmException->getStatusCode(),
                    'error_type' => $llmException->getErrorType(),
                    'is_quota_related' => $llmException->isQuotaRelated(),
                    'is_retryable' => $llmException->isRetryable()
                ]);

                // Only retry if it's retryable and we haven't exceeded attempts
                if ($llmException->isRetryable() && $attempt < $retries) {
                    $delay = $useExponentialBackoff 
                        ? $baseRetryDelay * pow(2, $attempt - 1)
                        : $baseRetryDelay * $attempt;
                    
                    Log::info('Retrying OpenAI API request', [
                        'attempt' => $attempt,
                        'delay_seconds' => $delay,
                        'error_type' => $llmException->getErrorType(),
                        'status_code' => $llmException->getStatusCode()
                    ]);
                    
                    sleep($delay);
                    continue;
                }

                // Throw the classified LLM exception instead of generic exception
                throw $llmException;
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
        if (!isset($models[$modelKey])) {
            return false;
        }
        
        // Get config with user-specific API key if available
        $this->config = $this->llmService->getModelConfig($modelKey, $this->user);
        
        if ($this->config && !empty($this->config['api_key'])) {
            $this->client = \OpenAI::factory()
                ->withApiKey($this->config['api_key'])
                ->withHttpHeader('User-Agent', 'ATSPARA-Analysis/1.0')
                ->withBaseUri($this->config['base_url'])
                ->withHttpClient(new \GuzzleHttp\Client([
                    'timeout' => config('llm.error_handling.timeout_seconds', 120)
                ]))
                ->make();
            $this->modelKey = $modelKey;
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
                // Get config with user-specific API key if available
                $modelConfig = $this->llmService->getModelConfig($key, $this->user);
                $openaiModels[$key] = [
                    'name' => $config['model'] ?? $key,
                    'provider' => 'OpenAI',
                    'configured' => !empty($modelConfig['api_key'])
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