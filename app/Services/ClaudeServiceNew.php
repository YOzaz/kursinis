<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Modernizuotas Claude API servisas su keliais modeliais.
 */
class ClaudeServiceNew extends AbstractLLMService
{
    private Client $httpClient;

    public function __construct(PromptService $promptService)
    {
        parent::__construct($promptService);
        
        // Nustatyti default modelį
        $this->currentModelKey = $this->getDefaultModelKey();
        
        // Sukurti HTTP klientą
        $this->initializeHttpClient();
    }

    protected function getProviderName(): string
    {
        return 'anthropic';
    }

    protected function initializeHttpClient(): void
    {
        $config = $this->getCurrentConfig();
        if ($config) {
            $this->httpClient = new Client([
                'base_uri' => $config['base_url'],
                'timeout' => $this->errorHandlingConfig['timeout_seconds'] ?? 60,
                'headers' => [
                    'x-api-key' => $config['api_key'],
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ]
            ]);
        }
    }

    public function setModel(string $modelKey): bool
    {
        if (parent::setModel($modelKey)) {
            $this->initializeHttpClient();
            return true;
        }
        return false;
    }

    protected function performAnalysis(string $text, ?string $customPrompt = null): array
    {
        $config = $this->getCurrentConfig();
        if (!$config) {
            throw new \Exception('Claude modelis nėra konfigūruotas');
        }

        $prompt = $this->promptService->generateAnalysisPrompt($text, $customPrompt);
        $systemMessage = $this->promptService->getSystemMessage();

        $requestData = [
            'model' => $config['model'],
            'max_tokens' => $config['max_tokens'],
            'temperature' => $config['temperature'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'system' => $systemMessage
        ];

        try {
            $response = $this->httpClient->post('messages', [
                'json' => $requestData
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);

            if (empty($responseBody['content'])) {
                throw new \Exception('Claude API grąžino tuščią atsakymą');
            }

            $content = $responseBody['content'][0]['text'] ?? '';
            $jsonResponse = $this->extractJsonFromResponse($content);
            
            if (!$this->promptService->validateResponse($jsonResponse)) {
                throw new \Exception('Claude grąžino netinkamą atsakymo formatą');
            }

            return $jsonResponse;

        } catch (GuzzleException $e) {
            throw new \Exception('Claude API klaida: ' . $e->getMessage());
        }
    }

    private function extractJsonFromResponse(string $content): array
    {
        // Bandyti išgauti JSON iš atsakymo
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $jsonString = $matches[0];
            $decoded = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Jei nepavyko, bandyti dekodoti visą content
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        throw new \Exception('Nepavyko išgauti JSON iš Claude atsakymo: ' . json_last_error_msg());
    }
}