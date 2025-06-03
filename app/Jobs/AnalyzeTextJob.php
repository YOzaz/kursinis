<?php

namespace App\Jobs;

use App\Models\TextAnalysis;
use App\Models\AnalysisJob;
use App\Services\ClaudeService;
use App\Services\GeminiService;
use App\Services\OpenAIService;
use App\Services\MetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\Exceptions\LLMException;

/**
 * Vieno teksto analizės darbo klasė.
 * 
 * Atsakinga už vieno teksto analizavimą su pasirinktu LLM modeliu.
 */
class AnalyzeTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $textAnalysisId;
    public string $modelName;
    public string $jobId;

    /**
     * Maksimalus bandymų skaičius.
     */
    public int $tries = 3;

    /**
     * Darbo timeout sekundėmis.
     */
    public int $timeout = 300;

    /**
     * Sukurti naują darbo instanciją.
     */
    public function __construct(int $textAnalysisId, string $modelName, string $jobId)
    {
        $this->textAnalysisId = $textAnalysisId;
        $this->modelName = $modelName;
        $this->jobId = $jobId;
    }

    /**
     * Vykdyti darbą.
     */
    public function handle(
        ClaudeService $claudeService, 
        GeminiService $geminiService, 
        OpenAIService $openAIService,
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

            // Gauti atitinkamą LLM servisą
            $service = $this->getLLMService($claudeService, $geminiService, $openAIService);
            
            if (!$service || !$service->isConfigured()) {
                throw new \Exception("LLM servisas {$this->modelName} nėra sukonfigūruotas");
            }

            Log::info('Pradedama teksto analizė', [
                'text_id' => $textAnalysis->text_id,
                'model' => $this->modelName,
                'job_id' => $this->jobId
            ]);

            // Gauti custom prompt iš analizės darbo
            $customPrompt = null;
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            if ($job && $job->custom_prompt) {
                $customPrompt = $job->custom_prompt;
            }

            // Analizuoti tekstą su laiko sekimu
            $startTime = microtime(true);
            $annotations = $service->analyzeText($textAnalysis->content, $customPrompt);
            $endTime = microtime(true);
            $executionTimeMs = (int) round(($endTime - $startTime) * 1000);
            
            // Išsaugoti rezultatus su tikru modelio pavadinimu ir vykdymo laiku
            $actualModelName = $service->getActualModelName();
            $textAnalysis->setModelAnnotations($this->modelName, $annotations, $actualModelName, $executionTimeMs);
            $textAnalysis->save();

            // Apskaičiuoti metrikas, jei yra ekspertų anotacijos
            if (!empty($textAnalysis->expert_annotations)) {
                $metricsService->calculateMetricsForText(
                    $textAnalysis,
                    $this->modelName,
                    $this->jobId,
                    $actualModelName
                );
            }

            Log::info('Teksto analizė baigta sėkmingai', [
                'text_id' => $textAnalysis->text_id,
                'model' => $this->modelName,
                'job_id' => $this->jobId,
                'annotations_count' => count($annotations['annotations'] ?? [])
            ]);

            // Atnaujinti darbo progresą
            $this->updateJobProgress();

        } catch (\Exception $e) {
            // Check if it's already an LLMException, otherwise treat as generic error
            $llmException = $e instanceof LLMException ? $e : null;
            
            Log::error('Teksto analizės klaida', [
                'text_analysis_id' => $this->textAnalysisId,
                'model' => $this->modelName,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'status_code' => $llmException ? $llmException->getStatusCode() : null,
                'error_type' => $llmException ? $llmException->getErrorType() : 'unknown',
                'is_quota_related' => $llmException ? $llmException->isQuotaRelated() : false,
                'should_fail_batch' => $llmException ? $llmException->shouldFailBatch() : true
            ]);

            // Use LLM exception information to determine how to handle the error
            if ($llmException && !$llmException->shouldFailBatch()) {
                Log::warning('API error that should not fail entire batch', [
                    'text_analysis_id' => $this->textAnalysisId,
                    'model' => $this->modelName,
                    'job_id' => $this->jobId,
                    'error_type' => $llmException->getErrorType()
                ]);
                
                // Pažymėti šį teksto-modelio apdorojimą kaip nepavykusį
                $this->markTextAnalysisAsFailed($e->getMessage());
                
                // Atnaujinti progresą (kad procesas tęstųsi)
                $this->updateJobProgress();
                
                // Nemetame exception, kad nedaužtų visos eilės
                return;
            }

            // Serious errors should fail the entire batch
            $this->markJobAsFailed($e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Gauti LLM servisą pagal modelio pavadinimą.
     */
    private function getLLMService(
        ClaudeService $claudeService, 
        GeminiService $geminiService, 
        OpenAIService $openAIService
    ) {
        // Dinamiškai nustatyti servisą pagal modelio pavadinimą
        if (str_starts_with($this->modelName, 'claude')) {
            if ($claudeService->setModel($this->modelName)) {
                return $claudeService;
            }
        } elseif (str_starts_with($this->modelName, 'gemini')) {
            if ($geminiService->setModel($this->modelName)) {
                return $geminiService;
            }
        } elseif (str_starts_with($this->modelName, 'gpt')) {
            if ($openAIService->setModel($this->modelName)) {
                return $openAIService;
            }
        }
        
        return null;
    }

    /**
     * Atnaujinti darbo progresą.
     */
    private function updateJobProgress(): void
    {
        $job = AnalysisJob::where('job_id', $this->jobId)->first();
        
        if ($job) {
            $job->increment('processed_texts');
            
            // Patikrinti ar visas darbas baigtas
            if ($job->processed_texts >= $job->total_texts) {
                $job->status = AnalysisJob::STATUS_COMPLETED;
                $job->save();
                
                Log::info('Analizės darbas baigtas', ['job_id' => $this->jobId]);
            }
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
     * Žymėti tik šį teksto-modelio apdorojimą kaip nepavykusį.
     */
    private function markTextAnalysisAsFailed(string $errorMessage): void
    {
        $textAnalysis = TextAnalysis::find($this->textAnalysisId);
        
        if ($textAnalysis) {
            // Išsaugoti klaidos pranešimą tam tikram modeliui
            $failedAnnotations = [
                'error' => $errorMessage,
                'failed_at' => now()->toISOString(),
                'model' => $this->modelName
            ];
            
            $textAnalysis->setModelAnnotations($this->modelName, $failedAnnotations, null, 0);
            $textAnalysis->save();
        }
    }


    /**
     * Apdoroti darbo nesėkmę.
     */
    public function failed(\Throwable $exception): void
    {
        $llmException = $exception instanceof LLMException ? $exception : null;
        
        Log::error('Analizės darbas nepavyko galutinai', [
            'text_analysis_id' => $this->textAnalysisId,
            'model' => $this->modelName,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
            'status_code' => $llmException ? $llmException->getStatusCode() : null,
            'error_type' => $llmException ? $llmException->getErrorType() : 'unknown',
            'should_fail_batch' => $llmException ? $llmException->shouldFailBatch() : true
        ]);

        // Use LLM exception information to determine how to handle the failure
        if ($llmException && !$llmException->shouldFailBatch()) {
            $this->markTextAnalysisAsFailed($exception->getMessage());
            $this->updateJobProgress();
        } else {
            $this->markJobAsFailed($exception->getMessage());
        }
    }
}