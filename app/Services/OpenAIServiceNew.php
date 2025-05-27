<?php

namespace App\Services;

use OpenAI;
use OpenAI\Client;
use Illuminate\Support\Facades\Log;

/**
 * Modernizuotas OpenAI ChatGPT API servisas su keliais modeliais.
 */
class OpenAIServiceNew extends AbstractLLMService
{
    private ?Client $client;

    public function __construct(PromptService $promptService)
    {
        parent::__construct($promptService);
        
        // Nustatyti default modelį
        $this->currentModelKey = $this->getDefaultModelKey();
        
        // Sukurti OpenAI klientą
        $this->initializeClient();
    }

    protected function getProviderName(): string
    {
        return 'openai';
    }

    protected function initializeClient(): void
    {
        $config = $this->getCurrentConfig();
        if ($config && !empty($config['api_key'])) {
            $this->client = OpenAI::client($config['api_key']);
        } else {
            $this->client = null;
        }
    }

    public function setModel(string $modelKey): bool
    {
        if (parent::setModel($modelKey)) {
            $this->initializeClient();
            return true;
        }
        return false;
    }

    public function isConfigured(): bool
    {
        return $this->client !== null && parent::isConfigured();
    }

    protected function performAnalysis(string $text, ?string $customPrompt = null): array
    {
        $config = $this->getCurrentConfig();
        if (!$config) {
            throw new \Exception('OpenAI modelis nėra konfigūruotas');
        }

        $prompt = $this->promptService->generateAnalysisPrompt($text, $customPrompt);
        $systemMessage = $this->promptService->getSystemMessage();

        $requestData = [
            'model' => $config['model'],
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
            'max_tokens' => $config['max_tokens'],
            'temperature' => $config['temperature'],
            'response_format' => [
                'type' => 'json_object'
            ]
        ];

        $response = $this->client->chat()->create($requestData);

        if (empty($response->choices)) {
            throw new \Exception('OpenAI API grąžino tuščią atsakymą');
        }

        $content = $response->choices[0]->message->content;
        $jsonResponse = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('OpenAI grąžino netinkamą JSON: ' . json_last_error_msg());
        }

        if (!$this->promptService->validateResponse($jsonResponse)) {
            throw new \Exception('OpenAI grąžino netinkamą atsakymo formatą');
        }

        return $jsonResponse;
    }
}