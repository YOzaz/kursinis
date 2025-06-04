<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Exceptions\ClaudeErrorHandler;
use App\Services\Exceptions\LLMException;

/**
 * Claude API servisas.
 * 
 * Integruoja su Anthropic Claude API propagandos analizei.
 * Naudoja HTTP klientą su teisingais endpoint'ais.
 */
class ClaudeService implements LLMServiceInterface
{
    private PromptService $promptService;
    private ?array $config;
    private ?string $modelKey;
    private ClaudeErrorHandler $errorHandler;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
        $this->errorHandler = new ClaudeErrorHandler();
        $models = config('llm.models', []);
        
        // Rasti bet kurį Claude modelį kaip numatytąjį
        foreach ($models as $key => $config) {
            if (str_starts_with($key, 'claude')) {
                $this->config = $config;
                $this->modelKey = $key;
                break;
            }
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

        $retries = config('llm.error_handling.max_retries_per_model', 3);
        $baseRetryDelay = config('llm.error_handling.retry_delay_seconds', 2);
        $useExponentialBackoff = config('llm.error_handling.exponential_backoff', true);

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->config['api_key'],
                    'Content-Type' => 'application/json',
                    'anthropic-version' => '2023-06-01',
                ])
                ->timeout(config('llm.error_handling.timeout_seconds', 120))
                ->post($this->config['base_url'] . 'messages', [
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
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Claude API grąžino klaidą: ' . $response->status() . ' - ' . $response->body());
                }

                $responseData = $response->json();
                
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
                        $delay = $useExponentialBackoff 
                            ? $baseRetryDelay * pow(2, $attempt - 1)
                            : $baseRetryDelay * $attempt;
                        sleep($delay);
                        continue;
                    }
                    
                    throw new \Exception('Claude grąžino netinkamą atsakymo formatą');
                }

                Log::info('Claude sėkmingai išanalizavo tekstą', [
                    'text_length' => strlen($text),
                    'annotations_count' => count($jsonResponse['annotations'] ?? []),
                    'model_used' => $this->config['model']
                ]);

                return $jsonResponse;

            } catch (\Exception $e) {
                $llmException = $this->errorHandler->handleException($e);
                
                Log::error('Claude API klaida', [
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
                    
                    Log::info('Retrying Claude API request', [
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

        throw new \Exception('Claude analizė nepavyko po visų bandymų');
    }

    /**
     * Gauti modelio pavadinimą.
     */
    public function getModelName(): string
    {
        return $this->modelKey ?? 'claude-opus-4';
    }

    /**
     * Gauti tikrą modelio pavadinimą.
     */
    public function getActualModelName(): string
    {
        return $this->config['model'] ?? 'claude-sonnet-4-20250514';
    }

    /**
     * Patikrinti ar servisas sukonfigūruotas.
     */
    public function isConfigured(): bool
    {
        // Check current config dynamically for testing
        $currentConfig = config("llm.models.{$this->modelKey}.api_key");
        return !empty($currentConfig);
    }

    /**
     * Nustatyti dabartinį modelį.
     */
    public function setModel(string $modelKey): bool
    {
        $models = config('llm.models', []);
        $this->config = $models[$modelKey] ?? null;
        
        if ($this->config) {
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
        $claudeModels = [];
        
        foreach ($models as $key => $config) {
            if (strpos($key, 'claude') === 0) {
                $claudeModels[$key] = [
                    'name' => $config['model'] ?? $key,
                    'provider' => 'Anthropic',
                    'configured' => !empty($config['api_key'])
                ];
            }
        }
        
        return $claudeModels;
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
            if ($originalConfig) {
                $this->httpClient = new Client([
                    'base_uri' => $originalConfig['base_url'],
                    'timeout' => config('llm.request_timeout'),
                    'headers' => [
                        'x-api-key' => $originalConfig['api_key'],
                        'Content-Type' => 'application/json',
                        'anthropic-version' => '2023-06-01',
                    ],
                ]);
            }
        }
    }

    /**
     * Išgauti JSON iš atsakymo teksto.
     */
    private function extractJsonFromResponse(string $content): array
    {
        $originalContent = $content;
        $content = trim($content);
        
        // Log the raw response for debugging
        Log::debug('Claude raw response', [
            'content_length' => strlen($content),
            'first_100_chars' => substr($content, 0, 100),
            'last_100_chars' => substr($content, -100)
        ]);
        
        $jsonString = null;
        
        // Method 1: Try to find JSON block in code fences
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonString = trim($matches[1]);
            Log::debug('Found JSON in code fence', ['length' => strlen($jsonString)]);
        }
        
        // Method 2: Try to find JSON object with proper brace matching
        if (!$jsonString) {
            $jsonString = $this->extractJsonWithBraceMatching($content);
            if ($jsonString) {
                Log::debug('Found JSON with brace matching', ['length' => strlen($jsonString)]);
            }
        }
        
        // Method 3: Try to find JSON array
        if (!$jsonString) {
            if (preg_match('/\[.*\]/s', $content, $matches)) {
                $jsonString = trim($matches[0]);
                Log::debug('Found JSON array', ['length' => strlen($jsonString)]);
            }
        }
        
        // Method 4: Look for JSON starting after a known marker
        if (!$jsonString) {
            $markers = ['JSON:', 'json:', 'Response:', 'Output:', '{', '['];
            foreach ($markers as $marker) {
                $pos = strpos($content, $marker);
                if ($pos !== false) {
                    $substring = substr($content, $pos + strlen($marker));
                    $extracted = $this->extractJsonWithBraceMatching($substring);
                    if ($extracted) {
                        $jsonString = $extracted;
                        Log::debug('Found JSON after marker', ['marker' => $marker, 'length' => strlen($jsonString)]);
                        break;
                    }
                }
            }
        }
        
        // Method 5: Use entire content as last resort
        if (!$jsonString) {
            $jsonString = $content;
            Log::debug('Using entire content as JSON', ['length' => strlen($jsonString)]);
        }
        
        // Clean and validate JSON string
        $jsonString = $this->cleanJsonString($jsonString);
        
        // Try to decode
        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error_msg();
            
            Log::error('JSON parsing failed', [
                'error' => $error,
                'json_string_length' => strlen($jsonString),
                'json_string_preview' => substr($jsonString, 0, 200),
                'original_content_preview' => substr($originalContent, 0, 500)
            ]);
            
            // Try to fix common JSON issues
            $fixedJson = $this->attemptJsonFix($jsonString);
            if ($fixedJson) {
                $decoded = json_decode($fixedJson, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    Log::info('JSON successfully fixed and parsed');
                    return $decoded;
                }
            }
            
            throw new \Exception('Nepavyko išgauti JSON iš Claude atsakymo: ' . $error);
        }

        return $decoded;
    }
    
    /**
     * Extract JSON using proper brace matching.
     */
    private function extractJsonWithBraceMatching(string $content): ?string
    {
        $startPos = strpos($content, '{');
        if ($startPos === false) {
            return null;
        }
        
        $braceCount = 0;
        $inString = false;
        $escaped = false;
        
        for ($i = $startPos; $i < strlen($content); $i++) {
            $char = $content[$i];
            
            if ($escaped) {
                $escaped = false;
                continue;
            }
            
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            
            if ($char === '"' && !$escaped) {
                $inString = !$inString;
                continue;
            }
            
            if (!$inString) {
                if ($char === '{') {
                    $braceCount++;
                } elseif ($char === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        return substr($content, $startPos, $i - $startPos + 1);
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Clean JSON string of common issues.
     */
    private function cleanJsonString(string $jsonString): string
    {
        // Remove BOM if present
        $jsonString = preg_replace('/^\xEF\xBB\xBF/', '', $jsonString);
        
        // Trim whitespace
        $jsonString = trim($jsonString);
        
        // Remove trailing commas before closing braces/brackets
        $jsonString = preg_replace('/,(\s*[}\]])/', '$1', $jsonString);
        
        // Fix single quotes to double quotes (common AI mistake)
        $jsonString = preg_replace("/(?<!\\\\)'([^']*?)'/", '"$1"', $jsonString);
        
        return $jsonString;
    }
    
    /**
     * Attempt to fix common JSON formatting issues.
     */
    private function attemptJsonFix(string $jsonString): ?string
    {
        // Try removing everything before first { or [
        if (preg_match('/[{\[].*[}\]]/s', $jsonString, $matches)) {
            $cleaned = $matches[0];
            
            // Additional cleanup
            $cleaned = $this->cleanJsonString($cleaned);
            
            // Test if this parses
            json_decode($cleaned, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $cleaned;
            }
        }
        
        // Try to fix incomplete JSON by adding missing closing braces
        $openBraces = substr_count($jsonString, '{') - substr_count($jsonString, '}');
        if ($openBraces > 0) {
            $fixed = $jsonString . str_repeat('}', $openBraces);
            json_decode($fixed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $fixed;
            }
        }
        
        return null;
    }
}