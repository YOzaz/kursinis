<?php

namespace App\Jobs;

use App\Models\TextAnalysis;
use App\Models\AnalysisJob;
use App\Services\ClaudeServiceNew;
use App\Services\GeminiServiceNew;
use App\Services\OpenAIServiceNew;
use App\Services\MetricsService;
use App\Services\LLMServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Modernizuotas tekstų analizės darbas su klaidų valdymu.
 * 
 * Ši versija:
 * - Palaikomų kelių modelių viename darbe
 * - Tęsia analizę jei vienas modelis nepavyksta
 * - Leidžia pakartoti nepavykusius modelius
 * - Geresnį klaidų valdymą ir registravimą
 */
class AnalyzeTextJobNew implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $textAnalysisId;
    public array $modelNames;
    public string $jobId;
    public ?string $customPrompt;

    /**
     * Maksimalus bandymų skaičius.
     */
    public int $tries = 3;

    /**
     * Darbo timeout sekundėmis.
     */
    public int $timeout = 600; // Increased for multiple models

    /**
     * Sukurti naują darbo instanciją.
     */
    public function __construct(int $textAnalysisId, array $modelNames, string $jobId, ?string $customPrompt = null)
    {
        $this->textAnalysisId = $textAnalysisId;
        $this->modelNames = $modelNames;
        $this->jobId = $jobId;
        $this->customPrompt = $customPrompt;
    }

    /**
     * Vykdyti darbą.
     */
    public function handle(
        ClaudeServiceNew $claudeService, 
        GeminiServiceNew $geminiService, 
        OpenAIServiceNew $openAIService,
        MetricsService $metricsService
    ): void {
        try {
            $textAnalysis = TextAnalysis::find($this->textAnalysisId);
            
            if (!$textAnalysis) {
                Log::error('Tekstų analizės įrašas nerastas', [
                    'text_analysis_id' => $this->textAnalysisId,
                    'job_id' => $this->jobId
                ]);
                return;
            }

            Log::info('Pradedama teksto analizė su keliais modeliais', [
                'text_id' => $textAnalysis->text_id,
                'models' => $this->modelNames,
                'job_id' => $this->jobId
            ]);

            $results = [];
            $errors = [];

            // Analizuoti su kiekvienu modeliu
            foreach ($this->modelNames as $modelName) {
                try {
                    $service = $this->getLLMService($modelName, $claudeService, $geminiService, $openAIService);
                    
                    if (!$service) {
                        $errors[$modelName] = "Nežinomas modelis: {$modelName}";
                        continue;
                    }

                    // Nustatyti teisingą modelį servise
                    if (!$service->setModel($modelName)) {
                        $errors[$modelName] = "Nepavyko nustatyti modelio: {$modelName}";
                        continue;
                    }

                    if (!$service->isConfigured()) {
                        $errors[$modelName] = "Modelis nėra sukonfigūruotas: {$modelName}";
                        continue;
                    }

                    // Analizuoti tekstą
                    $annotations = $service->analyzeText($textAnalysis->content, $this->customPrompt);
                    $actualModelName = $service->getActualModelName();
                    
                    // Išsaugoti rezultatus
                    $textAnalysis->setModelAnnotations($modelName, $annotations, $actualModelName);
                    $results[$modelName] = [
                        'success' => true,
                        'annotations' => $annotations,
                        'actual_model' => $actualModelName
                    ];

                    // Apskaičiuoti metrikas, jei yra ekspertų anotacijos
                    if (!empty($textAnalysis->expert_annotations)) {
                        $metricsService->calculateMetricsForText(
                            $textAnalysis,
                            $modelName,
                            $this->jobId,
                            $actualModelName
                        );
                    }

                    Log::info('Modelio analizė sėkminga', [
                        'text_id' => $textAnalysis->text_id,
                        'model' => $modelName,
                        'actual_model' => $actualModelName
                    ]);

                } catch (\Exception $e) {
                    $errors[$modelName] = $e->getMessage();
                    $results[$modelName] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];

                    Log::error('Modelio analizės klaida', [
                        'text_id' => $textAnalysis->text_id,
                        'model' => $modelName,
                        'error' => $e->getMessage()
                    ]);

                    // Tęsti su kitais modeliais pagal konfigūraciją
                    if (!config('llm.error_handling.continue_on_failure', true)) {
                        throw $e;
                    }
                }
            }

            // Išsaugoti visus rezultatus
            $textAnalysis->save();

            // Patikrinti ar bent vienas modelis sėkmingai analizavo
            $successfulModels = array_filter($results, fn($result) => $result['success']);
            
            if (empty($successfulModels)) {
                throw new \Exception('Visi modeliai nepavyko: ' . implode(', ', $errors));
            }

            // Įrašyti sėkmės informaciją
            Log::info('Teksto analizė baigta', [
                'text_id' => $textAnalysis->text_id,
                'successful_models' => array_keys($successfulModels),
                'failed_models' => array_keys($errors),
                'job_id' => $this->jobId
            ]);

            // Atnaujinti darbo progresą
            $this->updateJobProgress($results, $errors);

        } catch (\Exception $e) {
            Log::error('Teksto analizės klaida', [
                'text_analysis_id' => $this->textAnalysisId,
                'models' => $this->modelNames,
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);

            // Žymėti darbą kaip nepavykusį
            $this->markJobAsFailed($e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Gauti LLM servisą pagal modelio pavadinimą.
     */
    private function getLLMService(
        string $modelName, 
        ClaudeServiceNew $claudeService, 
        GeminiServiceNew $geminiService, 
        OpenAIServiceNew $openAIService
    ): ?LLMServiceInterface {
        $config = config("llm.models.{$modelName}");
        if (!$config) {
            return null;
        }

        return match ($config['provider'] ?? '') {
            'anthropic' => $claudeService,
            'google' => $geminiService,
            'openai' => $openAIService,
            default => null,
        };
    }

    /**
     * Atnaujinti darbo progresą.
     */
    private function updateJobProgress(array $results, array $errors): void
    {
        $job = AnalysisJob::where('job_id', $this->jobId)->first();
        
        if ($job) {
            $job->increment('processed_texts');
            
            // Pridėti klaidų informaciją jei yra
            if (!empty($errors)) {
                $existingErrors = json_decode($job->error_message ?? '[]', true);
                $existingErrors[$this->textAnalysisId] = $errors;
                $job->error_message = json_encode($existingErrors);
            }
            
            // Patikrinti ar visas darbas baigtas
            if ($job->processed_texts >= $job->total_texts) {
                $job->status = AnalysisJob::STATUS_COMPLETED;
                Log::info('Analizės darbas baigtas', ['job_id' => $this->jobId]);
            }
            
            $job->save();
        }
    }

    /**
     * Žymėti darbą kaip nepavykusį.
     */
    private function markJobAsFailed(string $errorMessage): void
    {
        $job = AnalysisJob::where('job_id', $this->jobId)->first();
        
        if ($job) {
            $job->status = AnalysisJob::STATUS_FAILED;
            $job->error_message = $errorMessage;
            $job->save();
        }
    }

    /**
     * Apdoroti darbo nesėkmę.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Analizės darbas nepavyko galutinai', [
            'text_analysis_id' => $this->textAnalysisId,
            'models' => $this->modelNames,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage()
        ]);

        $this->markJobAsFailed($exception->getMessage());
    }
}