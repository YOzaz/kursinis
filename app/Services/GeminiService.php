<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Exceptions\GeminiErrorHandler;
use App\Services\Exceptions\LLMException;

/**
 * Google Gemini API servisas.
 * 
 * Integruoja su Google Gemini API propagandos analizei.
 */
class GeminiService implements LLMServiceInterface
{
    private PromptService $promptService;
    private ?array $config;
    private ?string $modelKey;
    private GeminiErrorHandler $errorHandler;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
        $this->errorHandler = new GeminiErrorHandler();
        $models = config('llm.models', []);
        
        // Rasti bet kurį Gemini modelį kaip numatytąjį
        foreach ($models as $key => $config) {
            if (str_starts_with($key, 'gemini')) {
                $this->config = $config;
                $this->modelKey = $key;
                break;
            }
        }
    }

    /**
     * Analizuoti tekstą naudojant Gemini API.
     */
    public function analyzeText(string $text, ?string $customPrompt = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Gemini API nėra sukonfigūruotas');
        }

        $prompt = $this->promptService->generateAnalysisPrompt($text, $customPrompt);
        $systemMessage = $this->promptService->getSystemMessage();

        $retries = config('llm.retry_attempts');
        $retryDelay = config('llm.retry_delay');

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $url = rtrim($this->config['base_url'], '/') . "/v1beta/models/{$this->config['model']}:generateContent";
                
                $response = Http::timeout(config('llm.request_timeout'))
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url . '?key=' . $this->config['api_key'], [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $systemMessage . "\n\n" . $prompt]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => $this->config['temperature'],
                            'maxOutputTokens' => $this->config['max_tokens'],
                            'responseMimeType' => 'application/json',
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
                    throw new \Exception('Gemini API klaida: HTTP ' . $response->status());
                }

                $responseData = $response->json();
                
                if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    throw new \Exception('Neteisingas Gemini API atsakymo formatas');
                }

                $content = $responseData['candidates'][0]['content']['parts'][0]['text'];
                $jsonResponse = $this->extractJsonFromResponse($content);

                if (!$this->promptService->validateResponse($jsonResponse)) {
                    Log::warning('Gemini grąžino netinkamą atsakymą', [
                        'attempt' => $attempt,
                        'response' => $jsonResponse
                    ]);
                    
                    if ($attempt < $retries) {
                        sleep($retryDelay * $attempt);
                        continue;
                    }
                    
                    throw new \Exception('Gemini grąžino netinkamą atsakymo formatą');
                }

                Log::info('Gemini sėkmingai išanalizavo tekstą', [
                    'text_length' => strlen($text),
                    'annotations_count' => count($jsonResponse['annotations'] ?? [])
                ]);

                return $jsonResponse;

            } catch (\Exception $e) {
                $llmException = $this->errorHandler->handleException($e);
                
                Log::error('Gemini API klaida', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'status_code' => $llmException->getStatusCode(),
                    'error_type' => $llmException->getErrorType(),
                    'is_quota_related' => $llmException->isQuotaRelated(),
                    'is_retryable' => $llmException->isRetryable()
                ]);

                // Only retry if it's retryable and we haven't exceeded attempts
                if ($llmException->isRetryable() && $attempt < $retries) {
                    sleep($retryDelay * $attempt);
                    continue;
                }

                // Throw the classified LLM exception instead of generic exception
                throw $llmException;
            }
        }

        throw new \Exception('Gemini analizė nepavyko po visų bandymų');
    }

    /**
     * Gauti modelio pavadinimą.
     */
    public function getModelName(): string
    {
        return $this->modelKey ?? 'gemini-2.5-pro';
    }

    /**
     * Gauti tikrą modelio pavadinimą.
     */
    public function getActualModelName(): string
    {
        return $this->config['model'] ?? 'gemini-2.5-pro-preview-05-06';
    }

    /**
     * Patikrinti ar servisas sukonfigūruotas.
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['api_key'] ?? '');
    }

    /**
     * Nustatyti dabartinį modelį.
     */
    public function setModel(string $modelKey): bool
    {
        $models = config('llm.models', []);
        $this->config = $models[$modelKey] ?? null;
        
        if ($this->config) {
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
        $geminiModels = [];
        
        foreach ($models as $key => $config) {
            if (strpos($key, 'gemini') === 0) {
                $geminiModels[$key] = [
                    'name' => $config['model'] ?? $key,
                    'provider' => 'Google',
                    'configured' => !empty($config['api_key'])
                ];
            }
        }
        
        return $geminiModels;
    }

    /**
     * Pakartoti analizę su konkrečiu modeliu.
     */
    public function retryWithModel(string $modelKey, string $text, ?string $customPrompt = null): array
    {
        $originalConfig = $this->config;
        
        try {
            if ($this->setModel($modelKey)) {
                return $this->analyzeText($text, $customPrompt);
            } else {
                throw new \Exception("Modelis {$modelKey} neprieinamas");
            }
        } finally {
            $this->config = $originalConfig;
        }
    }

    /**
     * Išgauti JSON iš atsakymo teksto.
     */
    private function extractJsonFromResponse(string $content): array
    {
        // Gemini paprastai grąžina tik JSON, bet kartais su ```json wrapper
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonString = $matches[1];
        } else {
            // Jei nėra wrapper, bandyti rasti JSON objektą
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $jsonString = $matches[0];
            } else {
                $jsonString = $content;
            }
        }

        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Nepavyko išgauti JSON iš Gemini atsakymo: ' . json_last_error_msg());
        }

        return $decoded;
    }
}