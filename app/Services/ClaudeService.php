<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Claude API servisas.
 * 
 * Integruoja su Anthropic Claude API propagandos analizei.
 */
class ClaudeService implements LLMServiceInterface
{
    private Client $httpClient;
    private PromptService $promptService;
    private ?array $config;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
        $models = config('llm.models', []);
        $this->config = $models['claude-4'] ?? null;
        
        if ($this->config) {
            $this->httpClient = new Client([
                'base_uri' => $this->config['base_url'],
                'timeout' => config('llm.request_timeout'),
                'headers' => [
                    'x-api-key' => $this->config['api_key'],
                    'Content-Type' => 'application/json',
                    'anthropic-version' => '2023-06-01',
                ],
            ]);
        }
    }

    /**
     * Analizuoti tekstą naudojant Claude API.
     */
    public function analyzeText(string $text, ?string $customPrompt = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Claude API nėra sukonfigūruotas');
        }

        $prompt = $this->promptService->generateAnalysisPrompt($text, $customPrompt);
        $systemMessage = $this->promptService->getSystemMessage();

        $retries = config('llm.retry_attempts');
        $retryDelay = config('llm.retry_delay');

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $response = $this->httpClient->post('/messages', [
                    'json' => [
                        'model' => $this->config['model'],
                        'max_tokens' => $this->config['max_tokens'],
                        'temperature' => $this->config['temperature'],
                        'system' => $systemMessage,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ]
                    ]
                ]);

                $responseData = json_decode($response->getBody()->getContents(), true);
                
                if (!isset($responseData['content'][0]['text'])) {
                    throw new \Exception('Neteisingas Claude API atsakymo formatas');
                }

                $content = $responseData['content'][0]['text'];
                $jsonResponse = $this->extractJsonFromResponse($content);

                if (!$this->promptService->validateResponse($jsonResponse)) {
                    Log::warning('Claude grąžino netinkamą atsakymą', [
                        'attempt' => $attempt,
                        'response' => $jsonResponse
                    ]);
                    
                    if ($attempt < $retries) {
                        sleep($retryDelay * $attempt);
                        continue;
                    }
                    
                    throw new \Exception('Claude grąžino netinkamą atsakymo formatą');
                }

                Log::info('Claude sėkmingai išanalizavo tekstą', [
                    'text_length' => strlen($text),
                    'annotations_count' => count($jsonResponse['annotations'] ?? [])
                ]);

                return $jsonResponse;

            } catch (GuzzleException $e) {
                Log::error('Claude API klaida', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $retries) {
                    sleep($retryDelay * $attempt);
                    continue;
                }

                throw new \Exception('Claude API neprieinamas po ' . $retries . ' bandymų: ' . $e->getMessage());
            }
        }

        throw new \Exception('Claude analizė nepavyko po visų bandymų');
    }

    /**
     * Gauti modelio pavadinimą.
     */
    public function getModelName(): string
    {
        return 'claude-4';
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
        // Bandyti rasti JSON bloką
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonString = $matches[1];
        } else {
            // Jei nėra json bloko, bandyti rasti JSON objektą
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $jsonString = $matches[0];
            } else {
                $jsonString = $content;
            }
        }

        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Nepavyko išgauti JSON iš Claude atsakymo: ' . json_last_error_msg());
        }

        return $decoded;
    }
}