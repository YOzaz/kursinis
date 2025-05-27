<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Modernizuotas Gemini API servisas su keliais modeliais.
 */
class GeminiServiceNew extends AbstractLLMService
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
        return 'google';
    }

    protected function initializeHttpClient(): void
    {
        $this->httpClient = new Client([
            'timeout' => $this->errorHandlingConfig['timeout_seconds'] ?? 60,
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    protected function performAnalysis(string $text, ?string $customPrompt = null): array
    {
        $config = $this->getCurrentConfig();
        if (!$config) {
            throw new \Exception('Gemini modelis nėra konfigūruotas');
        }

        $prompt = $this->promptService->generateAnalysisPrompt($text, $customPrompt);
        $systemMessage = $this->promptService->getSystemMessage();
        
        // Kombinuoti system message su prompt
        $fullPrompt = $systemMessage . "\n\n" . $prompt;

        $requestData = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $config['temperature'],
                'maxOutputTokens' => $config['max_tokens'],
                'candidateCount' => 1,
            ]
        ];

        // Pridėti thinking budget jei palaikoma
        if (isset($config['thinking_budget'])) {
            $requestData['generationConfig']['thinkingBudget'] = $config['thinking_budget'];
        }

        $url = $config['base_url'] . "v1beta/models/{$config['model']}:generateContent";
        $url .= "?key=" . $config['api_key'];

        try {
            $response = $this->httpClient->post($url, [
                'json' => $requestData
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);

            if (empty($responseBody['candidates'])) {
                throw new \Exception('Gemini API grąžino tuščią atsakymą');
            }

            $candidate = $responseBody['candidates'][0];
            if (empty($candidate['content']['parts'])) {
                throw new \Exception('Gemini API grąžino netinkamą struktūrą');
            }

            $content = $candidate['content']['parts'][0]['text'];
            $jsonResponse = $this->extractJsonFromResponse($content);
            
            if (!$this->promptService->validateResponse($jsonResponse)) {
                throw new \Exception('Gemini grąžino netinkamą atsakymo formatą');
            }

            return $jsonResponse;

        } catch (GuzzleException $e) {
            throw new \Exception('Gemini API klaida: ' . $e->getMessage());
        }
    }

    private function extractJsonFromResponse(string $content): array
    {
        // Pašalinti markdown blokus jei yra
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);

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

        throw new \Exception('Nepavyko išgauti JSON iš Gemini atsakymo: ' . json_last_error_msg());
    }
}