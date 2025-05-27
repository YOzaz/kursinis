<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini API servisas.
 * 
 * Integruoja su Google Gemini API propagandos analizei.
 */
class GeminiService implements LLMServiceInterface
{
    private Client $httpClient;
    private PromptService $promptService;
    private ?array $config;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
        $models = config('llm.models', []);
        $this->config = $models['gemini-2.5-pro'] ?? null;
        
        if ($this->config) {
            $this->httpClient = new Client([
                'base_uri' => $this->config['base_url'],
                'timeout' => config('llm.request_timeout'),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);
        }
    }

    /**
     * Analizuoti tekstą naudojant Gemini API.
     */
    public function analyzeText(string $text): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Gemini API nėra sukonfigūruotas');
        }

        $prompt = $this->promptService->generateAnalysisPrompt($text);
        $systemMessage = $this->promptService->getSystemMessage();

        $retries = config('llm.retry_attempts');
        $retryDelay = config('llm.retry_delay');

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $response = $this->httpClient->post("/models/{$this->config['model']}:generateContent", [
                    'query' => ['key' => $this->config['api_key']],
                    'json' => [
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
                    ]
                ]);

                $responseData = json_decode($response->getBody()->getContents(), true);
                
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

            } catch (GuzzleException $e) {
                Log::error('Gemini API klaida', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $retries) {
                    sleep($retryDelay * $attempt);
                    continue;
                }

                throw new \Exception('Gemini API neprieinamas po ' . $retries . ' bandymų: ' . $e->getMessage());
            }
        }

        throw new \Exception('Gemini analizė nepavyko po visų bandymų');
    }

    /**
     * Gauti modelio pavadinimą.
     */
    public function getModelName(): string
    {
        return 'gemini-2.5-pro';
    }

    /**
     * Patikrinti ar servisas sukonfigūruotas.
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['api_key'] ?? '');
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