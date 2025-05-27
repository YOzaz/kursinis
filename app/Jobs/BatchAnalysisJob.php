<?php

namespace App\Jobs;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Batch analizės darbo klasė.
 * 
 * Atsakinga už didelio kiekio tekstų apdorojimo organizavimą.
 */
class BatchAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public array $fileContent;
    public array $models;

    /**
     * Maksimalus bandymų skaičius.
     */
    public int $tries = 3;

    /**
     * Darbo timeout sekundėmis.
     */
    public int $timeout = 1800; // 30 minučių

    /**
     * Sukurti naują darbo instanciją.
     */
    public function __construct(string $jobId, array $fileContent, array $models)
    {
        $this->jobId = $jobId;
        $this->fileContent = $fileContent;
        $this->models = $models;
    }

    /**
     * Vykdyti darbą.
     */
    public function handle(): void
    {
        try {
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            
            if (!$job) {
                Log::error('Analizės darbas nerastas', ['job_id' => $this->jobId]);
                return;
            }

            // Pakeisti statusą į apdorojamą
            $job->status = AnalysisJob::STATUS_PROCESSING;
            $job->save();

            Log::info('Pradedama batch analizė', [
                'job_id' => $this->jobId,
                'texts_count' => count($this->fileContent),
                'models' => $this->models
            ]);

            $processedTexts = 0;
            $totalJobsToProcess = count($this->fileContent) * count($this->models);

            // Apdoroti kiekvieną tekstą
            foreach ($this->fileContent as $item) {
                try {
                    // Sukurti tekstų analizės įrašą
                    $textAnalysis = TextAnalysis::create([
                        'job_id' => $this->jobId,
                        'text_id' => (string) $item['id'],
                        'content' => $item['data']['content'],
                        'expert_annotations' => $item['annotations'] ?? [],
                    ]);

                    // Paleisti analizės darbus kiekvienam modeliui
                    foreach ($this->models as $model) {
                        AnalyzeTextJob::dispatch($textAnalysis->id, $model, $this->jobId);
                    }

                    $processedTexts++;

                    // Loginti progresą kas 100 tekstų
                    if ($processedTexts % 100 === 0) {
                        Log::info('Batch analizės progresas', [
                            'job_id' => $this->jobId,
                            'processed' => $processedTexts,
                            'total' => count($this->fileContent)
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error('Klaida apdorojant tekstą batch analizėje', [
                        'job_id' => $this->jobId,
                        'text_id' => $item['id'] ?? 'nežinomas',
                        'error' => $e->getMessage()
                    ]);
                    
                    // Tęsti su kitais tekstais
                    continue;
                }
            }

            // Atnaujinti darbo informaciją
            $job->update([
                'total_texts' => $totalJobsToProcess, // Apskaičiuoti pagal modelių skaičių
                'processed_texts' => 0, // Bus atnaujinta per AnalyzeTextJob
            ]);

            Log::info('Batch analizės darbai paleisti', [
                'job_id' => $this->jobId,
                'texts_processed' => $processedTexts,
                'total_jobs_queued' => $totalJobsToProcess
            ]);

        } catch (\Exception $e) {
            Log::error('Batch analizės klaida', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage()
            ]);

            // Žymėti darbą kaip nepavykusį
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            if ($job) {
                $job->status = AnalysisJob::STATUS_FAILED;
                $job->error_message = $e->getMessage();
                $job->save();
            }

            throw $e;
        }
    }

    /**
     * Apdoroti darbo nesėkmę.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Batch analizės darbas nepavyko galutinai', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage()
        ]);

        $job = AnalysisJob::where('job_id', $this->jobId)->first();
        if ($job) {
            $job->status = AnalysisJob::STATUS_FAILED;
            $job->error_message = $exception->getMessage();
            $job->save();
        }
    }
}