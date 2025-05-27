<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI ChatGPT API servisas.
 * 
 * Integruoja su OpenAI API propagandos analizei.
 */
class OpenAIService implements LLMServiceInterface
{
    private Client $httpClient;
    private PromptService $promptService;
    private ?array $config;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
        $models = config('llm.models', []);
        $this->config = $models['gpt-4.1'] ?? null;
        
        if ($this->config) {
            $this->httpClient = new Client([
                'base_uri' => $this->config['base_url'],
                'timeout' => config('llm.request_timeout'),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['api_key'],
                    'Content-Type' => 'application/json',
                ],
            ]);
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
                $response = $this->httpClient->post('/chat/completions', [
                    'json' => [
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
                    ]
                ]);

                $responseData = json_decode($response->getBody()->getContents(), true);
                
                if (!isset($responseData['choices'][0]['message']['content'])) {
                    throw new \Exception('Neteisingas OpenAI API atsakymo formatas');
                }

                $content = $responseData['choices'][0]['message']['content'];
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
                    'annotations_count' => count($jsonResponse['annotations'] ?? [])
                ]);

                return $jsonResponse;

            } catch (GuzzleException $e) {
                Log::error('OpenAI API klaida', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
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
        return 'gpt-4.1';
    }

    /**
     * Patikrinti ar servisas sukonfigūruotas.
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['api_key'] ?? '');
    }
}