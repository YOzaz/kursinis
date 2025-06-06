<?php

namespace App\Services;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;

class StatisticsService
{
    public function getGlobalStatistics(): array
    {
        $totalAnalyses = AnalysisJob::count();
        $totalTexts = TextAnalysis::count();
        $totalMetrics = ComparisonMetric::count();
        
        // Get model performance statistics
        $modelPerformance = $this->getModelPerformanceStats();
        
        // Get execution time statistics
        $avgExecutionTimes = $this->getAverageExecutionTimes();
        
        // Get propaganda techniques statistics
        $topTechniques = $this->getTopTechniques();
        
        // Get time series data
        $timeSeriesData = $this->getTimeSeriesData();
        
        return [
            'total_analyses' => $totalAnalyses,
            'total_texts' => $totalTexts,
            'total_metrics' => $totalMetrics,
            'model_performance' => $modelPerformance,
            'avg_execution_times' => $avgExecutionTimes,
            'top_techniques' => $topTechniques,
            'time_series_data' => $timeSeriesData,
        ];
    }
    
    private function getModelPerformanceStats(): array
    {
        $metrics = ComparisonMetric::with('textAnalysis')
            ->get()
            ->groupBy('model_name');
            
        $performance = [];
        foreach ($metrics as $model => $modelMetrics) {
            // Filter to only include propaganda texts for metrics calculation
            $propagandaMetrics = $modelMetrics->filter(function($metric) {
                $textAnalysis = $metric->textAnalysis;
                if (!$textAnalysis || !$textAnalysis->expert_annotations) {
                    return false;
                }
                return $this->expertFoundPropaganda($textAnalysis->expert_annotations);
            });
            
            // Calculate confusion matrix for propaganda detection
            $confusionMatrix = $this->calculatePropagandaConfusionMatrix($modelMetrics);
            
            // Calculate accuracy for propaganda detection (all texts)
            $accuracy = $this->calculatePropagandaDetectionAccuracy($modelMetrics);
            
            $f1Score = $propagandaMetrics->avg('f1_score') ?? 0;
            $precision = $propagandaMetrics->avg('precision') ?? 0;
            $recall = $propagandaMetrics->avg('recall') ?? 0;
            
            // Calculate overall score (weighted average: F1 more important)
            $overallScore = ($f1Score * 0.5) + ($precision * 0.25) + ($recall * 0.25);
            
            $performance[$model] = [
                'total_analyses' => $modelMetrics->count(),
                'total_propaganda_texts' => $propagandaMetrics->count(),
                'avg_precision' => round($precision, 4),
                'avg_recall' => round($recall, 4),
                'avg_f1_score' => round($f1Score, 4),
                'overall_score' => round($overallScore, 2),
                'propaganda_detection_accuracy' => round($accuracy, 2),
                // Confusion matrix for propaganda detection
                'propaganda_tp' => $confusionMatrix['tp'], // Correctly identified as propaganda
                'propaganda_fp' => $confusionMatrix['fp'], // Incorrectly identified as propaganda 
                'propaganda_tn' => $confusionMatrix['tn'], // Correctly identified as non-propaganda
                'propaganda_fn' => $confusionMatrix['fn'], // Incorrectly identified as non-propaganda
            ];
        }
        
        return $performance;
    }
    
    /**
     * Calculate confusion matrix for propaganda detection at text level.
     * Returns TP, FP, TN, FN for propaganda presence/absence detection.
     * Excludes texts where the model failed to analyze (timeout/error).
     */
    private function calculatePropagandaConfusionMatrix($modelMetrics): array
    {
        $tp = 0; // Expert has propaganda, Model found propaganda
        $fp = 0; // Expert has no propaganda, Model found propaganda  
        $tn = 0; // Expert has no propaganda, Model found no propaganda
        $fn = 0; // Expert has propaganda, Model found no propaganda
        
        foreach ($modelMetrics as $metric) {
            $textAnalysis = $metric->textAnalysis;
            if (!$textAnalysis || !$textAnalysis->expert_annotations) {
                continue;
            }
            
            // Skip this text if the model failed to analyze it (timeout/error)
            if (!$this->modelSuccessfullyAnalyzed($textAnalysis, $metric->model_name)) {
                continue; // Exclude from confusion matrix calculation
            }
            
            // Check if expert found propaganda
            $expertFoundPropaganda = $this->expertFoundPropaganda($textAnalysis->expert_annotations);
            
            // Check if model found propaganda at document level (primary choice)
            $modelFoundPropaganda = $this->modelFoundPropagandaAtDocumentLevel($textAnalysis, $metric->model_name);
            
            // Classify based on expert vs model predictions
            if ($expertFoundPropaganda && $modelFoundPropaganda) {
                $tp++;
            } elseif (!$expertFoundPropaganda && $modelFoundPropaganda) {
                $fp++;
            } elseif (!$expertFoundPropaganda && !$modelFoundPropaganda) {
                $tn++;
            } elseif ($expertFoundPropaganda && !$modelFoundPropaganda) {
                $fn++;
            }
        }
        
        return [
            'tp' => $tp,
            'fp' => $fp, 
            'tn' => $tn,
            'fn' => $fn,
        ];
    }

    /**
     * Check if a model successfully analyzed a text (no timeout/error).
     * 
     * @param TextAnalysis $textAnalysis
     * @param string $modelName
     * @return bool
     */
    private function modelSuccessfullyAnalyzed(TextAnalysis $textAnalysis, string $modelName): bool
    {
        // First check new ModelResult table (preferred)
        $modelResult = $textAnalysis->modelResults()->where('model_key', $modelName)->first();
        if ($modelResult) {
            return $modelResult->isSuccessful();
        }
        
        // Fallback to legacy TextAnalysis fields
        $annotations = $textAnalysis->getModelAnnotations($modelName);
        $errorField = $this->getErrorField($modelName);
        
        // Consider successful if:
        // 1. Has annotations (even empty array - means model ran)
        // 2. No error message in error field
        if ($errorField && !empty($textAnalysis->$errorField)) {
            return false; // Has error message
        }
        
        // If annotations exist (even empty), consider it a successful run
        return $annotations !== null;
    }
    
    /**
     * Get the error field name for a model.
     */
    private function getErrorField(string $modelName): ?string
    {
        if (str_starts_with($modelName, 'claude')) {
            return 'claude_error';
        } elseif (str_starts_with($modelName, 'gemini')) {
            return 'gemini_error';
        } elseif (str_starts_with($modelName, 'gpt')) {
            return 'gpt_error';
        }
        
        return null;
    }

    /**
     * Calculate accuracy for propaganda detection specifically.
     * Only counts texts where model correctly identified propaganda presence/absence.
     */
    private function calculatePropagandaDetectionAccuracy($modelMetrics): float
    {
        $correctDetections = 0;
        $totalTexts = 0;
        
        foreach ($modelMetrics as $metric) {
            $textAnalysis = $metric->textAnalysis;
            if (!$textAnalysis || !$textAnalysis->expert_annotations) {
                continue;
            }
            
            // Skip this text if the model failed to analyze it (timeout/error)
            if (!$this->modelSuccessfullyAnalyzed($textAnalysis, $metric->model_name)) {
                continue; // Exclude from accuracy calculation
            }
            
            // Check if expert found propaganda
            $expertFoundPropaganda = $this->expertFoundPropaganda($textAnalysis->expert_annotations);
            
            // Check if model found propaganda at document level (primary choice)
            $modelFoundPropaganda = $this->modelFoundPropagandaAtDocumentLevel($textAnalysis, $metric->model_name);
            
            // Count as correct if both agree on propaganda presence/absence
            if ($expertFoundPropaganda === $modelFoundPropaganda) {
                $correctDetections++;
            }
            
            $totalTexts++;
        }
        
        return $totalTexts > 0 ? $correctDetections / $totalTexts : 0;
    }
    
    /**
     * Check if a model found propaganda at the document level (primary choice).
     * Uses the model's primary choice annotation, not fragment-level metrics.
     * 
     * @param TextAnalysis $textAnalysis
     * @param string $modelName
     * @return bool
     */
    private function modelFoundPropagandaAtDocumentLevel(TextAnalysis $textAnalysis, string $modelName): bool
    {
        // First check new ModelResult table (preferred approach)
        $modelResult = $textAnalysis->modelResults()->where('model_key', $modelName)->first();
        if ($modelResult) {
            return $modelResult->detectedPropaganda();
        }
        
        // Fallback to legacy annotation structure
        $annotations = $textAnalysis->getModelAnnotations($modelName);
        if (empty($annotations)) {
            return false;
        }
        
        // Check primary choice in annotations
        if (isset($annotations['primaryChoice']['choices']) && 
            in_array('yes', $annotations['primaryChoice']['choices'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if expert annotations indicate propaganda was found.
     */
    private function expertFoundPropaganda(array $expertAnnotations): bool
    {
        // Check for label annotations (fragments)
        foreach ($expertAnnotations as $annotation) {
            if (isset($annotation['result'])) {
                foreach ($annotation['result'] as $result) {
                    if (isset($result['type']) && $result['type'] === 'labels' && 
                        isset($result['value']['labels']) && !empty($result['value']['labels'])) {
                        return true;
                    }
                }
            }
        }
        
        // Check for primary choice (document level)
        foreach ($expertAnnotations as $annotation) {
            if (isset($annotation['result'])) {
                foreach ($annotation['result'] as $result) {
                    if (isset($result['type']) && $result['type'] === 'choices' && 
                        isset($result['value']['choices']) && 
                        in_array('yes', $result['value']['choices'])) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    public function calculateJobStatistics(AnalysisJob $job): array
    {
        $textAnalyses = $job->textAnalyses()->with('comparisonMetrics')->get();
        
        if ($textAnalyses->isEmpty()) {
            return [
                'precision' => 0,
                'recall' => 0,
                'f1_score' => 0,
                'total_texts' => 0,
                'propaganda_texts' => 0,
                'with_expert_annotations' => 0,
            ];
        }

        $allMetrics = collect();
        $propagandaMetrics = collect();
        $withExpertAnnotations = 0;
        $propagandaTexts = 0;
        
        foreach ($textAnalyses as $textAnalysis) {
            if ($textAnalysis->expert_annotations) {
                $withExpertAnnotations++;
                
                // Check if this is a propaganda text
                $isPropaganda = $this->expertFoundPropaganda($textAnalysis->expert_annotations);
                if ($isPropaganda) {
                    $propagandaTexts++;
                    $propagandaMetrics = $propagandaMetrics->merge($textAnalysis->comparisonMetrics);
                }
            }
            $allMetrics = $allMetrics->merge($textAnalysis->comparisonMetrics);
        }

        if ($propagandaMetrics->isEmpty()) {
            return [
                'precision' => 0,
                'recall' => 0,
                'f1_score' => 0,
                'total_texts' => $textAnalyses->count(),
                'propaganda_texts' => $propagandaTexts,
                'with_expert_annotations' => $withExpertAnnotations,
            ];
        }

        return [
            'precision' => round($propagandaMetrics->avg('precision') ?? 0, 2),
            'recall' => round($propagandaMetrics->avg('recall') ?? 0, 2),
            'f1_score' => round($propagandaMetrics->avg('f1_score') ?? 0, 2),
            'total_texts' => $textAnalyses->count(),
            'propaganda_texts' => $propagandaTexts,
            'with_expert_annotations' => $withExpertAnnotations,
        ];
    }

    /**
     * Get average execution times per model.
     */
    private function getAverageExecutionTimes(): array
    {
        $executionTimes = [];
        
        // Get execution times from TextAnalysis table (primary source)
        $textAnalyses = TextAnalysis::whereNotNull('claude_execution_time_ms')
            ->orWhereNotNull('gpt_execution_time_ms')
            ->orWhereNotNull('gemini_execution_time_ms')
            ->get();
            
        foreach ($textAnalyses as $analysis) {
            // Claude times
            if ($analysis->claude_execution_time_ms > 0) {
                $executionTimes['claude-opus-4'] = ($executionTimes['claude-opus-4'] ?? []);
                $executionTimes['claude-opus-4'][] = $analysis->claude_execution_time_ms;
                
                // Also count for claude-sonnet-4 if it was used
                $executionTimes['claude-sonnet-4'] = ($executionTimes['claude-sonnet-4'] ?? []);
                $executionTimes['claude-sonnet-4'][] = $analysis->claude_execution_time_ms;
            }
            
            // GPT times
            if ($analysis->gpt_execution_time_ms > 0) {
                $executionTimes['gpt-4.1'] = ($executionTimes['gpt-4.1'] ?? []);
                $executionTimes['gpt-4.1'][] = $analysis->gpt_execution_time_ms;
                
                $executionTimes['gpt-4o-latest'] = ($executionTimes['gpt-4o-latest'] ?? []);
                $executionTimes['gpt-4o-latest'][] = $analysis->gpt_execution_time_ms;
            }
            
            // Gemini times
            if ($analysis->gemini_execution_time_ms > 0) {
                $executionTimes['gemini-2.5-pro'] = ($executionTimes['gemini-2.5-pro'] ?? []);
                $executionTimes['gemini-2.5-pro'][] = $analysis->gemini_execution_time_ms;
                
                $executionTimes['gemini-2.5-flash'] = ($executionTimes['gemini-2.5-flash'] ?? []);
                $executionTimes['gemini-2.5-flash'][] = $analysis->gemini_execution_time_ms;
            }
        }
        
        // Calculate averages
        foreach ($executionTimes as $model => $times) {
            if (!empty($times)) {
                $executionTimes[$model] = round(array_sum($times) / count($times), 0);
            }
        }
        
        // Try ModelResult table as backup
        if (empty($executionTimes)) {
            $modelResults = \App\Models\ModelResult::whereNotNull('execution_time_ms')
                ->where('status', 'completed')
                ->get()
                ->groupBy('model_key');
                
            foreach ($modelResults as $modelKey => $results) {
                $avgTime = $results->avg('execution_time_ms');
                if ($avgTime > 0) {
                    $executionTimes[$modelKey] = round($avgTime, 0);
                }
            }
        }
        
        // Also try comparison metrics as backup
        if (empty($executionTimes)) {
            $metrics = ComparisonMetric::whereNotNull('analysis_execution_time_ms')
                ->get()
                ->groupBy('model_name');
                
            foreach ($metrics as $model => $modelMetrics) {
                $avgTime = $modelMetrics->avg('analysis_execution_time_ms');
                if ($avgTime > 0) {
                    $executionTimes[$model] = round($avgTime, 0);
                }
            }
        }
        
        return $executionTimes;
    }

    /**
     * Get top propaganda techniques from all analyses.
     */
    private function getTopTechniques(): array
    {
        $techniques = [];
        
        // Get all text analyses with annotations
        $textAnalyses = TextAnalysis::whereNotNull('expert_annotations')
            ->orWhereNotNull('claude_annotations')
            ->orWhereNotNull('gemini_annotations')
            ->orWhereNotNull('gpt_annotations')
            ->get();
            
        foreach ($textAnalyses as $analysis) {
            // Extract techniques from expert annotations (ground truth)
            if (!empty($analysis->expert_annotations)) {
                $this->extractTechniquesFromAnnotations($analysis->expert_annotations, $techniques);
            }
            
            // Extract techniques from AI model annotations
            foreach (['claude_annotations', 'gemini_annotations', 'gpt_annotations'] as $field) {
                if (!empty($analysis->$field)) {
                    $this->extractTechniquesFromAnnotations($analysis->$field, $techniques);
                }
            }
        }
        
        // Sort by frequency and return top 10
        arsort($techniques);
        return array_slice($techniques, 0, 10, true);
    }
    
    /**
     * Extract propaganda techniques from annotation array.
     */
    private function extractTechniquesFromAnnotations(array $annotations, array &$techniques): void
    {
        // Handle AI model annotation structure (Claude, Gemini, GPT)
        if (isset($annotations['annotations']) && is_array($annotations['annotations'])) {
            foreach ($annotations['annotations'] as $annotation) {
                if (isset($annotation['type']) && $annotation['type'] === 'labels' && 
                    isset($annotation['value']['labels']) && is_array($annotation['value']['labels'])) {
                    foreach ($annotation['value']['labels'] as $label) {
                        $techniques[$label] = ($techniques[$label] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Handle expert annotation structure (Label Studio format)
        if (isset($annotations[0]['result']) && is_array($annotations[0]['result'])) {
            foreach ($annotations[0]['result'] as $result) {
                if (isset($result['type']) && $result['type'] === 'labels' && 
                    isset($result['value']['labels']) && is_array($result['value']['labels'])) {
                    foreach ($result['value']['labels'] as $label) {
                        $techniques[$label] = ($techniques[$label] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Legacy format support - direct array of annotations
        foreach ($annotations as $annotation) {
            if (is_array($annotation)) {
                // Handle different annotation formats
                if (isset($annotation['technique'])) {
                    $technique = $annotation['technique'];
                    $techniques[$technique] = ($techniques[$technique] ?? 0) + 1;
                } elseif (isset($annotation['type'])) {
                    $technique = $annotation['type'];
                    $techniques[$technique] = ($techniques[$technique] ?? 0) + 1;
                } elseif (isset($annotation['label'])) {
                    $technique = $annotation['label'];
                    $techniques[$technique] = ($techniques[$technique] ?? 0) + 1;
                }
            } elseif (is_string($annotation)) {
                // Simple string technique
                $techniques[$annotation] = ($techniques[$annotation] ?? 0) + 1;
            }
        }
    }
    
    /**
     * Get time series data for analyses creation over time.
     */
    private function getTimeSeriesData(): array
    {
        $timeSeriesData = [];
        
        // Get analyses from the last 30 days
        $analyses = AnalysisJob::where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at')
            ->get();
            
        // Group by date
        $dailyData = $analyses->groupBy(function ($analysis) {
            return $analysis->created_at->format('Y-m-d');
        });
        
        // Fill in missing dates and format for chart
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = isset($dailyData[$date]) ? $dailyData[$date]->count() : 0;
            
            $timeSeriesData[] = [
                'date' => $date,
                'count' => $count,
                'label' => now()->subDays($i)->format('M j')
            ];
        }
        
        return $timeSeriesData;
    }

    /**
     * Get dashboard export data.
     */
    public function getDashboardExportData(): array
    {
        $globalStats = $this->getGlobalStatistics();
        $recentAnalyses = AnalysisJob::with(['textAnalyses', 'comparisonMetrics'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
            
        return [
            'global_statistics' => $globalStats,
            'model_performance' => $globalStats['model_performance'],
            'execution_times' => $globalStats['avg_execution_times'],
            'recent_analyses' => $recentAnalyses->map(function ($analysis) {
                return [
                    'job_id' => $analysis->job_id,
                    'name' => $analysis->name,
                    'status' => $analysis->status,
                    'total_texts' => $analysis->textAnalyses->count(),
                    'models_used' => $analysis->comparisonMetrics->pluck('model_name')->unique()->values(),
                    'created_at' => $analysis->created_at->format('Y-m-d H:i:s'),
                    'avg_f1_score' => $analysis->comparisonMetrics->avg('f1_score'),
                    'avg_precision' => $analysis->comparisonMetrics->avg('precision'),
                    'avg_recall' => $analysis->comparisonMetrics->avg('recall'),
                ];
            }),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}