<?php

namespace App\Services;

use App\Models\Experiment;
use App\Models\ExperimentResult;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Illuminate\Support\Collection;
use Illuminate\Http\Response;

class ExportService
{
    public function exportExperimentToCsv(Experiment $experiment): string
    {
        $experiment->load(['results.analysisJob', 'results']);
        
        $headers = [
            'experiment_name',
            'experiment_id',
            'analysis_job_id',
            'llm_model',
            'execution_time',
            'precision',
            'recall',
            'f1_score',
            'cohens_kappa',
            'created_at',
        ];

        $rows = [];
        $rows[] = $headers;

        foreach ($experiment->results as $result) {
            $metrics = $result->metrics;
            
            $rows[] = [
                $experiment->name,
                $experiment->id,
                $result->analysis_job_id,
                $result->llm_model,
                $result->execution_time,
                $metrics['precision'] ?? '',
                $metrics['recall'] ?? '',
                $metrics['f1_score'] ?? '',
                $metrics['cohens_kappa'] ?? '',
                $result->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $this->arrayToCsv($rows);
    }

    public function exportExperimentStatisticsToCsv(Experiment $experiment, array $statistics): string
    {
        $headers = [
            'model',
            'total_analyses',
            'avg_execution_time',
            'avg_precision',
            'avg_recall',
            'avg_f1',
            'avg_kappa',
            'precision_std',
            'recall_std',
            'f1_std',
            'kappa_std',
        ];

        $rows = [];
        $rows[] = $headers;

        foreach ($statistics['models'] as $model => $modelStats) {
            $comparison = $statistics['comparison'][$model] ?? [];
            
            $rows[] = [
                $model,
                $modelStats['total_analyses'],
                $modelStats['avg_execution_time'],
                $modelStats['avg_precision'],
                $modelStats['avg_recall'],
                $modelStats['avg_f1'],
                $modelStats['avg_kappa'],
                $comparison['precision']['std'] ?? '',
                $comparison['recall']['std'] ?? '',
                $comparison['f1_score']['std'] ?? '',
                $comparison['cohens_kappa']['std'] ?? '',
            ];
        }

        return $this->arrayToCsv($rows);
    }

    public function exportMultipleExperimentsToCsv(Collection $experiments): string
    {
        $headers = [
            'experiment_id',
            'experiment_name',
            'experiment_status',
            'experiment_created_at',
            'analysis_job_id',
            'llm_model',
            'execution_time',
            'precision',
            'recall',
            'f1_score',
            'cohens_kappa',
            'result_created_at',
        ];

        $rows = [];
        $rows[] = $headers;

        foreach ($experiments as $experiment) {
            $experiment->load(['results.analysisJob']);
            
            if ($experiment->results->isEmpty()) {
                // Include experiment even if no results
                $rows[] = [
                    $experiment->id,
                    $experiment->name,
                    $experiment->status,
                    $experiment->created_at->format('Y-m-d H:i:s'),
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                ];
            } else {
                foreach ($experiment->results as $result) {
                    $metrics = $result->metrics;
                    
                    $rows[] = [
                        $experiment->id,
                        $experiment->name,
                        $experiment->status,
                        $experiment->created_at->format('Y-m-d H:i:s'),
                        $result->analysis_job_id,
                        $result->llm_model,
                        $result->execution_time,
                        $metrics['precision'] ?? '',
                        $metrics['recall'] ?? '',
                        $metrics['f1_score'] ?? '',
                        $metrics['cohens_kappa'] ?? '',
                        $result->created_at->format('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        return $this->arrayToCsv($rows);
    }

    public function exportExperimentToJson(Experiment $experiment): string
    {
        $experiment->load(['results.analysisJob', 'analysisJobs']);
        
        $export = [
            'experiment' => [
                'id' => $experiment->id,
                'name' => $experiment->name,
                'description' => $experiment->description,
                'custom_prompt' => $experiment->custom_prompt,
                'risen_config' => $experiment->risen_config,
                'status' => $experiment->status,
                'created_at' => $experiment->created_at->toISOString(),
                'started_at' => $experiment->started_at?->toISOString(),
                'completed_at' => $experiment->completed_at?->toISOString(),
            ],
            'analysis_jobs' => $experiment->analysisJobs->map(function ($job) {
                return [
                    'job_id' => $job->job_id,
                    'status' => $job->status,
                    'total_texts' => $job->total_texts,
                    'processed_texts' => $job->processed_texts,
                    'error_message' => $job->error_message,
                    'created_at' => $job->created_at->toISOString(),
                    'updated_at' => $job->updated_at->toISOString(),
                ];
            }),
            'results' => $experiment->results->map(function ($result) {
                return [
                    'id' => $result->id,
                    'analysis_job_id' => $result->analysis_job_id,
                    'llm_model' => $result->llm_model,
                    'metrics' => $result->metrics,
                    'raw_results' => $result->raw_results,
                    'execution_time' => $result->execution_time,
                    'created_at' => $result->created_at->toISOString(),
                ];
            }),
            'exported_at' => now()->toISOString(),
        ];

        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function exportGlobalStatisticsToJson(array $globalStats): string
    {
        $export = [
            'global_statistics' => $globalStats,
            'exported_at' => now()->toISOString(),
        ];

        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function arrayToCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Eksportuoti analizės darbo rezultatus į CSV.
     */
    public function exportToCsv(string $jobId): Response
    {
        $job = AnalysisJob::with(['textAnalyses', 'comparisonMetrics'])->where('job_id', $jobId)->first();
        
        if (!$job) {
            abort(404, 'Analizės darbas nerastas');
        }

        $hasExpertAnnotations = $job->textAnalyses->some(function ($textAnalysis) {
            return !empty($textAnalysis->expert_annotations);
        });

        if ($hasExpertAnnotations) {
            // Eksportuoti su palyginimo metrikomis
            return $this->exportAnalysisWithMetrics($job);
        } else {
            // Eksportuoti tik LLM rezultatus
            return $this->exportAnalysisResultsOnly($job);
        }
    }

    /**
     * Eksportuoti analizės rezultatus su metrikomis.
     */
    private function exportAnalysisWithMetrics(AnalysisJob $job): Response
    {
        $headers = [
            'job_id',
            'text_id',
            'text_content',
            'model_name',
            'expert_annotations',
            'llm_annotations',
            'true_positives',
            'false_positives',
            'false_negatives',
            'precision',
            'recall',
            'f1_score',
            'position_accuracy'
        ];

        $rows = [$headers];

        foreach ($job->textAnalyses as $textAnalysis) {
            // Get all models that have annotations for this text
            $availableModels = [];
            foreach (['claude-4', 'claude-opus-4', 'gemini-2.5-pro', 'gemini-pro', 'gpt-4.1', 'gpt-4'] as $model) {
                if ($textAnalysis->getModelAnnotations($model)) {
                    $availableModels[] = $model;
                }
            }
            
            foreach ($availableModels as $model) {
                $modelAnnotations = $textAnalysis->getModelAnnotations($model);

                $metric = $job->comparisonMetrics
                    ->where('text_id', $textAnalysis->text_id)
                    ->where('model_name', $model)
                    ->first();

                $rows[] = [
                    $job->job_id,
                    $textAnalysis->text_id,
                    $textAnalysis->content,
                    $model,
                    json_encode($textAnalysis->expert_annotations),
                    json_encode($modelAnnotations),
                    $metric?->true_positives ?? 0,
                    $metric?->false_positives ?? 0,
                    $metric?->false_negatives ?? 0,
                    $metric?->precision ?? 0,
                    $metric?->recall ?? 0,
                    $metric?->f1_score ?? 0,
                    $metric?->position_accuracy ?? 0,
                ];
            }
        }

        $csv = $this->arrayToCsv($rows);
        $filename = $this->getExportFilename('analysis_with_metrics') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\""
        ]);
    }

    /**
     * Eksportuoti tik LLM analizės rezultatus.
     */
    private function exportAnalysisResultsOnly(AnalysisJob $job): Response
    {
        $headers = [
            'job_id',
            'text_id',
            'text_content',
            'model_name',
            'llm_annotations',
            'propaganda_detected',
            'techniques_found',
            'narratives_found'
        ];

        $rows = [$headers];

        foreach ($job->textAnalyses as $textAnalysis) {
            // Get all models that have annotations for this text
            $availableModels = [];
            foreach (['claude-4', 'claude-opus-4', 'gemini-2.5-pro', 'gemini-pro', 'gpt-4.1', 'gpt-4'] as $model) {
                if ($textAnalysis->getModelAnnotations($model)) {
                    $availableModels[] = $model;
                }
            }
            
            foreach ($availableModels as $model) {
                $modelAnnotations = $textAnalysis->getModelAnnotations($model);

                $propagandaDetected = ($modelAnnotations['primaryChoice']['choices'][0] ?? 'no') === 'yes';
                $techniques = [];
                $narratives = $modelAnnotations['desinformationTechnique']['choices'] ?? [];

                foreach ($modelAnnotations['annotations'] ?? [] as $annotation) {
                    $techniques = array_merge($techniques, $annotation['value']['labels'] ?? []);
                }

                $rows[] = [
                    $job->job_id,
                    $textAnalysis->text_id,
                    $textAnalysis->content,
                    $model,
                    json_encode($modelAnnotations),
                    $propagandaDetected ? 'yes' : 'no',
                    implode(';', array_unique($techniques)),
                    implode(';', $narratives),
                ];
            }
        }

        $csv = $this->arrayToCsv($rows);
        $filename = $this->getExportFilename('analysis_results') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\""
        ]);
    }

    public function getExportFilename(string $type, ?string $experimentName = null): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        if ($experimentName) {
            $sanitizedName = preg_replace('/[^A-Za-z0-9_-]/', '_', $experimentName);
            return "experiment_{$sanitizedName}_{$type}_{$timestamp}";
        }
        
        return "propaganda_analysis_{$type}_{$timestamp}";
    }

    /**
     * Convert dashboard data to CSV format.
     */
    public function dashboardDataToCSV(array $data): string
    {
        // Create CSV headers for dashboard export
        $headers = [
            'job_id',
            'name',
            'text_count',
            'model_count',
            'created_at',
            'f1_score',
            'precision',
            'recall'
        ];
        
        $rows = [$headers];
        
        // Convert recent analyses to CSV rows
        foreach ($data['recent_analyses'] as $analysis) {
            $rows[] = [
                $analysis['job_id'],
                $analysis['name'] ?? '',
                $analysis['total_texts'] ?? 0,
                count($analysis['models_used'] ?? []),
                $analysis['created_at'],
                round($analysis['avg_f1_score'] ?? 0, 3),
                round($analysis['avg_precision'] ?? 0, 3),
                round($analysis['avg_recall'] ?? 0, 3),
            ];
        }
        
        return $this->arrayToCsv($rows);
    }

    /**
     * Convert dashboard data to Excel format.
     */
    public function dashboardDataToExcel(array $data): string
    {
        // For now, return CSV format as Excel is more complex to implement
        // In production, this would use a library like PhpSpreadsheet
        return $this->dashboardDataToCSV($data);
    }
}