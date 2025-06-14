<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\User;

/**
 * Abstraktus LLM servisas su klaidų valdymu ir pakartojimo funkcionalumu.
 */
abstract class AbstractLLMService implements LLMServiceInterface
{
    protected PromptService $promptService;
    protected array $models = [];
    protected string $currentModelKey;
    protected array $errorHandlingConfig;
    protected LLMService $llmService;
    protected ?User $user = null;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
        $this->errorHandlingConfig = config('llm.error_handling', []);
        $this->llmService = new LLMService();
        $this->setUser();
        $this->loadModels();
    }
    
    /**
     * Set user from SimpleAuth session.
     */
    protected function setUser(): void
    {
        // Get user from SimpleAuth session
        $username = session('username');
        if ($username) {
            $this->user = User::where('email', $username . '@local')->first();
        }
    }

    /**
     * Gauti visus konfigūruotus modelius šiam tiekėjui.
     */
    abstract protected function getProviderName(): string;

    /**
     * Įkelti modelius iš konfigūracijos su nustatymų perrašymais.
     */
    protected function loadModels(): void
    {
        // Get models with settings overrides
        $allModels = \App\Http\Controllers\SettingsController::getModelSettings();
        $providerName = $this->getProviderName();
        
        foreach ($allModels as $key => $config) {
            if (($config['provider'] ?? '') === $providerName) {
                // Override API key with user-specific key if available
                $userConfig = $this->llmService->getModelConfig($key, $this->user);
                $config['api_key'] = $userConfig['api_key'];
                $this->models[$key] = $config;
            }
        }
    }

    /**
     * Gauti default modelio raktą.
     */
    protected function getDefaultModelKey(): string
    {
        foreach ($this->models as $key => $config) {
            if ($config['is_default'] ?? false) {
                return $key;
            }
        }
        
        // Jei nėra default, grąžinti pirmą
        return array_key_first($this->models) ?? '';
    }

    /**
     * Nustatyti dabartinį modelį.
     */
    public function setModel(string $modelKey): bool
    {
        if (isset($this->models[$modelKey])) {
            $this->currentModelKey = $modelKey;
            return true;
        }
        return false;
    }

    /**
     * Gauti dabartinio modelio konfigūraciją.
     */
    protected function getCurrentConfig(): ?array
    {
        return isset($this->currentModelKey) ? ($this->models[$this->currentModelKey] ?? null) : null;
    }

    /**
     * Patikrinti ar modelis sukonfigūruotas.
     */
    public function isConfigured(): bool
    {
        $config = $this->getCurrentConfig();
        return $config && !empty($config['api_key'] ?? '');
    }

    /**
     * Gauti modelio pavadinimą.
     */
    public function getModelName(): string
    {
        return isset($this->currentModelKey) ? $this->currentModelKey : $this->getDefaultModelKey();
    }

    /**
     * Gauti tikrą modelio pavadinimą.
     */
    public function getActualModelName(): string
    {
        $config = $this->getCurrentConfig();
        return $config['model'] ?? '';
    }

    /**
     * Gauti visus galimus modelius.
     */
    public function getAvailableModels(): array
    {
        return array_map(function($config, $key) {
            return [
                'key' => $key,
                'name' => $config['model'] ?? $key,
                'description' => $config['description'] ?? '',
                'tier' => $config['tier'] ?? 'standard',
                'is_default' => $config['is_default'] ?? false,
            ];
        }, $this->models, array_keys($this->models));
    }

    /**
     * Analizuoti tekstą su pakartojimo logika.
     */
    public function analyzeText(string $text, ?string $customPrompt = null): array
    {
        if (!isset($this->currentModelKey) || !$this->currentModelKey) {
            $this->currentModelKey = $this->getDefaultModelKey();
        }

        if (!$this->isConfigured()) {
            throw new \Exception("{$this->getProviderName()} API nėra sukonfigūruotas modeliui {$this->currentModelKey}");
        }

        $maxRetries = $this->errorHandlingConfig['max_retries_per_model'] ?? 3;
        $retryDelay = $this->errorHandlingConfig['retry_delay_seconds'] ?? 2;
        $exponentialBackoff = $this->errorHandlingConfig['exponential_backoff'] ?? true;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = $this->performAnalysis($text, $customPrompt);
                
                Log::info("{$this->getProviderName()} sėkmingai išanalizavo tekstą", [
                    'model_key' => $this->currentModelKey,
                    'actual_model' => $this->getActualModelName(),
                    'text_length' => strlen($text),
                    'attempt' => $attempt
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::warning("{$this->getProviderName()} analizės klaida", [
                    'model_key' => $this->currentModelKey,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $maxRetries) {
                    $delay = $exponentialBackoff ? $retryDelay * pow(2, $attempt - 1) : $retryDelay;
                    sleep($delay);
                    continue;
                }

                throw new \Exception("{$this->getProviderName()} API neprieinamas po {$maxRetries} bandymų: " . $e->getMessage());
            }
        }

        throw new \Exception("{$this->getProviderName()} analizė nepavyko po visų bandymų");
    }

    /**
     * Atlikti faktinę analizę (implementuoja kiekvienas servisas).
     */
    abstract protected function performAnalysis(string $text, ?string $customPrompt = null): array;

    /**
     * Pakartoti analizę su konkrečiu modeliu.
     */
    public function retryWithModel(string $modelKey, string $text, ?string $customPrompt = null): array
    {
        $originalModel = isset($this->currentModelKey) ? $this->currentModelKey : null;
        
        try {
            if (!$this->setModel($modelKey)) {
                throw new \Exception("Modelis {$modelKey} nerastas");
            }

            $result = $this->analyzeText($text, $customPrompt);
            
            // Grąžinti originalų modelį
            if ($originalModel) {
                $this->setModel($originalModel);
            }
            
            return $result;

        } catch (\Exception $e) {
            // Grąžinti originalų modelį
            if ($originalModel) {
                $this->setModel($originalModel);
            }
            throw $e;
        }
    }
}