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
        
        return [
            'total_analyses' => $totalAnalyses,
            'total_texts' => $totalTexts,
            'total_metrics' => $totalMetrics,
            'model_performance' => $modelPerformance,
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
}