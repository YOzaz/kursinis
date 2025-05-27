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
        
        return [
            'total_analyses' => $totalAnalyses,
            'total_texts' => $totalTexts,
            'total_metrics' => $totalMetrics,
            'model_performance' => $modelPerformance,
            'avg_execution_times' => $avgExecutionTimes,
        ];
    }
    
    private function getModelPerformanceStats(): array
    {
        $metrics = ComparisonMetric::with('textAnalysis')
            ->get()
            ->groupBy('model_name');
            
        $performance = [];
        foreach ($metrics as $model => $modelMetrics) {
            $performance[$model] = [
                'total_analyses' => $modelMetrics->count(),
                'avg_precision' => round($modelMetrics->avg('precision') ?? 0, 2),
                'avg_recall' => round($modelMetrics->avg('recall') ?? 0, 2),
                'avg_f1_score' => round($modelMetrics->avg('f1_score') ?? 0, 2),
            ];
        }
        
        return $performance;
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
                'with_expert_annotations' => 0,
            ];
        }

        $allMetrics = collect();
        $withExpertAnnotations = 0;
        
        foreach ($textAnalyses as $textAnalysis) {
            if ($textAnalysis->expert_annotations) {
                $withExpertAnnotations++;
            }
            $allMetrics = $allMetrics->merge($textAnalysis->comparisonMetrics);
        }

        if ($allMetrics->isEmpty()) {
            return [
                'precision' => 0,
                'recall' => 0,
                'f1_score' => 0,
                'total_texts' => $textAnalyses->count(),
                'with_expert_annotations' => $withExpertAnnotations,
            ];
        }

        return [
            'precision' => round($allMetrics->avg('precision') ?? 0, 2),
            'recall' => round($allMetrics->avg('recall') ?? 0, 2),
            'f1_score' => round($allMetrics->avg('f1_score') ?? 0, 2),
            'total_texts' => $textAnalyses->count(),
            'with_expert_annotations' => $withExpertAnnotations,
        ];
    }

    /**
     * Get average execution times per model.
     */
    private function getAverageExecutionTimes(): array
    {
        $executionTimes = [];
        
        // Get execution times from comparison metrics
        $metrics = ComparisonMetric::whereNotNull('analysis_execution_time_ms')
            ->get()
            ->groupBy('model_name');
            
        foreach ($metrics as $model => $modelMetrics) {
            $avgTime = $modelMetrics->avg('analysis_execution_time_ms');
            $executionTimes[$model] = round($avgTime, 0);
        }
        
        return $executionTimes;
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