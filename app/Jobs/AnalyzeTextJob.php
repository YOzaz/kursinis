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

            // Gauti custom prompt jei eksperimentas nurodytas
            $customPrompt = null;
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            if ($job && $job->experiment_id) {
                $experiment = $job->experiment;
                $customPrompt = $experiment?->custom_prompt;
            }

            // Analizuoti tekstą
            $annotations = $service->analyzeText($textAnalysis->content, $customPrompt);
            
            // Išsaugoti rezultatus su tikru modelio pavadinimu
            $actualModelName = $service->getActualModelName();
            $textAnalysis->setModelAnnotations($this->modelName, $annotations, $actualModelName);
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
            Log::error('Teksto analizės klaida', [
                'text_analysis_id' => $this->textAnalysisId,
                'model' => $this->modelName,
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
        ClaudeService $claudeService, 
        GeminiService $geminiService, 
        OpenAIService $openAIService
    ) {
        return match ($this->modelName) {
            'claude-4' => $claudeService,
            'gemini-2.5-pro' => $geminiService,
            'gpt-4.1' => $openAIService,
            default => null,
        };
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
     * Apdoroti darbo nesėkmę.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Analizės darbas nepavyko galutinai', [
            'text_analysis_id' => $this->textAnalysisId,
            'model' => $this->modelName,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage()
        ]);

        $this->markJobAsFailed($exception->getMessage());
    }
}