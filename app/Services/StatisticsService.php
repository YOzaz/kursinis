<?php

namespace App\Services;

use App\Models\Experiment;
use App\Models\ExperimentResult;
use App\Models\ComparisonMetric;
use Illuminate\Support\Collection;

class StatisticsService
{
    public function getExperimentStatistics(Experiment $experiment): array
    {
        $results = $experiment->results()->with('analysisJob')->get();
        
        if ($results->isEmpty()) {
            return [
                'models' => [],
                'metrics' => [],
                'comparison' => [],
                'charts' => [],
            ];
        }

        return [
            'models' => $this->getModelStatistics($results),
            'metrics' => $this->getMetricsComparison($results),
            'comparison' => $this->getModelComparison($results),
            'charts' => $this->getChartsData($results),
        ];
    }

    private function getModelStatistics(Collection $results): array
    {
        $modelStats = [];
        
        foreach ($results->groupBy('llm_model') as $model => $modelResults) {
            $metrics = $modelResults->pluck('metrics')->flatten(1);
            
            $modelStats[$model] = [
                'total_analyses' => $modelResults->count(),
                'avg_execution_time' => $modelResults->avg('execution_time'),
                'avg_precision' => $this->calculateAverageMetric($metrics, 'precision'),
                'avg_recall' => $this->calculateAverageMetric($metrics, 'recall'),
                'avg_f1' => $this->calculateAverageMetric($metrics, 'f1_score'),
                'avg_kappa' => $this->calculateAverageMetric($metrics, 'cohens_kappa'),
            ];
        }

        return $modelStats;
    }

    private function getMetricsComparison(Collection $results): array
    {
        $comparison = [];
        
        foreach ($results->groupBy('llm_model') as $model => $modelResults) {
            $metrics = $modelResults->pluck('metrics')->flatten(1);
            
            $comparison[$model] = [
                'precision' => [
                    'values' => $metrics->pluck('precision')->filter()->values(),
                    'avg' => $this->calculateAverageMetric($metrics, 'precision'),
                    'std' => $this->calculateStdDeviation($metrics->pluck('precision')->filter()),
                ],
                'recall' => [
                    'values' => $metrics->pluck('recall')->filter()->values(),
                    'avg' => $this->calculateAverageMetric($metrics, 'recall'),
                    'std' => $this->calculateStdDeviation($metrics->pluck('recall')->filter()),
                ],
                'f1_score' => [
                    'values' => $metrics->pluck('f1_score')->filter()->values(),
                    'avg' => $this->calculateAverageMetric($metrics, 'f1_score'),
                    'std' => $this->calculateStdDeviation($metrics->pluck('f1_score')->filter()),
                ],
                'cohens_kappa' => [
                    'values' => $metrics->pluck('cohens_kappa')->filter()->values(),
                    'avg' => $this->calculateAverageMetric($metrics, 'cohens_kappa'),
                    'std' => $this->calculateStdDeviation($metrics->pluck('cohens_kappa')->filter()),
                ],
            ];
        }

        return $comparison;
    }

    private function getModelComparison(Collection $results): array
    {
        $models = $results->pluck('llm_model')->unique()->values();
        $comparison = [];

        foreach ($models as $model1) {
            foreach ($models as $model2) {
                if ($model1 !== $model2) {
                    $key = "{$model1}_vs_{$model2}";
                    $comparison[$key] = $this->compareModels($results, $model1, $model2);
                }
            }
        }

        return $comparison;
    }

    private function compareModels(Collection $results, string $model1, string $model2): array
    {
        $model1Results = $results->where('llm_model', $model1);
        $model2Results = $results->where('llm_model', $model2);

        if ($model1Results->isEmpty() || $model2Results->isEmpty()) {
            return [];
        }

        $model1Metrics = $model1Results->pluck('metrics')->flatten(1);
        $model2Metrics = $model2Results->pluck('metrics')->flatten(1);

        return [
            'precision_diff' => $this->calculateAverageMetric($model1Metrics, 'precision') - 
                               $this->calculateAverageMetric($model2Metrics, 'precision'),
            'recall_diff' => $this->calculateAverageMetric($model1Metrics, 'recall') - 
                            $this->calculateAverageMetric($model2Metrics, 'recall'),
            'f1_diff' => $this->calculateAverageMetric($model1Metrics, 'f1_score') - 
                        $this->calculateAverageMetric($model2Metrics, 'f1_score'),
            'kappa_diff' => $this->calculateAverageMetric($model1Metrics, 'cohens_kappa') - 
                           $this->calculateAverageMetric($model2Metrics, 'cohens_kappa'),
            'time_diff' => $model1Results->avg('execution_time') - $model2Results->avg('execution_time'),
        ];
    }

    private function getChartsData(Collection $results): array
    {
        return [
            'metrics_comparison' => $this->getMetricsComparisonChart($results),
            'execution_time' => $this->getExecutionTimeChart($results),
            'score_distribution' => $this->getScoreDistributionChart($results),
            'model_accuracy' => $this->getModelAccuracyChart($results),
        ];
    }

    private function getMetricsComparisonChart(Collection $results): array
    {
        $data = [];
        $models = $results->pluck('llm_model')->unique();

        foreach ($models as $model) {
            $modelResults = $results->where('llm_model', $model);
            $metrics = $modelResults->pluck('metrics')->flatten(1);

            $data[] = [
                'model' => $model,
                'precision' => round($this->calculateAverageMetric($metrics, 'precision'), 3),
                'recall' => round($this->calculateAverageMetric($metrics, 'recall'), 3),
                'f1_score' => round($this->calculateAverageMetric($metrics, 'f1_score'), 3),
                'cohens_kappa' => round($this->calculateAverageMetric($metrics, 'cohens_kappa'), 3),
            ];
        }

        return $data;
    }

    private function getExecutionTimeChart(Collection $results): array
    {
        return $results->groupBy('llm_model')
            ->map(function ($modelResults, $model) {
                return [
                    'model' => $model,
                    'avg_time' => round($modelResults->avg('execution_time'), 2),
                    'min_time' => round($modelResults->min('execution_time'), 2),
                    'max_time' => round($modelResults->max('execution_time'), 2),
                ];
            })->values()->toArray();
    }

    private function getScoreDistributionChart(Collection $results): array
    {
        $data = [];
        
        foreach ($results->groupBy('llm_model') as $model => $modelResults) {
            $f1Scores = $modelResults->pluck('metrics')
                ->flatten(1)
                ->pluck('f1_score')
                ->filter()
                ->map(function ($score) {
                    return round($score, 1);
                });

            $distribution = $f1Scores->countBy()->sortKeys();

            $data[$model] = $distribution->toArray();
        }

        return $data;
    }

    private function getModelAccuracyChart(Collection $results): array
    {
        return $results->groupBy('llm_model')
            ->map(function ($modelResults, $model) {
                $metrics = $modelResults->pluck('metrics')->flatten(1);
                $totalPredictions = $metrics->count();
                $correctPredictions = $metrics->filter(function ($metric) {
                    return isset($metric['accuracy']) && $metric['accuracy'] > 0.8;
                })->count();

                return [
                    'model' => $model,
                    'accuracy_rate' => $totalPredictions > 0 ? round(($correctPredictions / $totalPredictions) * 100, 1) : 0,
                    'total_predictions' => $totalPredictions,
                ];
            })->values()->toArray();
    }

    private function calculateAverageMetric(Collection $metrics, string $metricName): float
    {
        $values = $metrics->pluck($metricName)->filter();
        return $values->isEmpty() ? 0 : round($values->avg(), 3);
    }

    private function calculateStdDeviation(Collection $values): float
    {
        if ($values->count() < 2) {
            return 0;
        }

        $mean = $values->avg();
        $variance = $values->map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        })->avg();

        return round(sqrt($variance), 3);
    }

    public function getGlobalStatistics(): array
    {
        // Gauti ir eksperimentų rezultatus, ir standartinės analizės duomenis
        $experimentResults = ExperimentResult::with(['experiment', 'analysisJob'])->get();
        $standardAnalyses = \App\Models\TextAnalysis::with(['analysisJob'])->get();
        
        return [
            'total_experiments' => Experiment::count(),
            'total_analyses' => $standardAnalyses->count() + $experimentResults->count(),
            'total_standard_analyses' => $standardAnalyses->count(),
            'total_experiment_analyses' => $experimentResults->count(),
            'model_performance' => $this->getCombinedModelPerformance($experimentResults, $standardAnalyses),
            'recent_activity' => $this->getRecentActivity(),
        ];
    }

    private function getGlobalModelPerformance(Collection $results): array
    {
        $performance = [];
        
        foreach ($results->groupBy('llm_model') as $model => $modelResults) {
            $metrics = $modelResults->pluck('metrics')->flatten(1);
            
            $performance[$model] = [
                'total_analyses' => $modelResults->count(),
                'avg_precision' => $this->calculateAverageMetric($metrics, 'precision'),
                'avg_recall' => $this->calculateAverageMetric($metrics, 'recall'),
                'avg_f1' => $this->calculateAverageMetric($metrics, 'f1_score'),
                'avg_execution_time' => round($modelResults->avg('execution_time'), 2),
                'reliability_score' => $this->calculateReliabilityScore($metrics),
            ];
        }

        return $performance;
    }

    private function getCombinedModelPerformance(Collection $experimentResults, Collection $standardAnalyses): array
    {
        $performance = [];
        
        // Iš eksperimentų rezultatų
        foreach ($experimentResults->groupBy('llm_model') as $model => $modelResults) {
            $metrics = $modelResults->pluck('metrics')->flatten(1);
            
            $performance[$model] = [
                'total_analyses' => $modelResults->count(),
                'experiment_analyses' => $modelResults->count(),
                'standard_analyses' => 0,
                'avg_precision' => $this->calculateAverageMetric($metrics, 'precision'),
                'avg_recall' => $this->calculateAverageMetric($metrics, 'recall'),
                'avg_f1' => $this->calculateAverageMetric($metrics, 'f1_score'),
                'avg_execution_time' => round($modelResults->avg('execution_time'), 2),
                'reliability_score' => $this->calculateReliabilityScore($metrics),
            ];
        }
        
        // Iš standartinių analizių (per ComparisonMetric)
        $jobIds = $standardAnalyses->pluck('analysis_job_id')->unique();
        $comparisonMetrics = ComparisonMetric::whereIn('job_id', $jobIds)
            ->get()
            ->groupBy('model_name');
            
        foreach ($comparisonMetrics as $model => $modelMetrics) {
            if (!isset($performance[$model])) {
                $performance[$model] = [
                    'total_analyses' => 0,
                    'experiment_analyses' => 0,
                    'standard_analyses' => 0,
                    'avg_precision' => 0,
                    'avg_recall' => 0,
                    'avg_f1' => 0,
                    'avg_execution_time' => 0,
                    'reliability_score' => 0,
                ];
            }
            
            $performance[$model]['standard_analyses'] = $modelMetrics->count();
            $performance[$model]['total_analyses'] += $modelMetrics->count();
            
            // Skaičiuoti metrikas iš ComparisonMetric
            if ($modelMetrics->count() > 0) {
                $performance[$model]['avg_precision'] = round($modelMetrics->avg('precision'), 3);
                $performance[$model]['avg_recall'] = round($modelMetrics->avg('recall'), 3);
                $performance[$model]['avg_f1'] = round($modelMetrics->avg('f1_score'), 3);
            }
        }

        return $performance;
    }

    private function calculateReliabilityScore(Collection $metrics): float
    {
        $f1Scores = $metrics->pluck('f1_score')->filter();
        
        if ($f1Scores->isEmpty()) {
            return 0;
        }

        $avgF1 = $f1Scores->avg();
        $stdDev = $this->calculateStdDeviation($f1Scores);
        
        // Reliability = high average with low variance
        $reliability = $avgF1 * (1 - min($stdDev, 1));
        
        return round(max(0, min(1, $reliability)), 3);
    }

    private function getRecentActivity(): array
    {
        return ExperimentResult::with(['experiment', 'analysisJob'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($result) {
                return [
                    'experiment_name' => $result->experiment->name ?? 'Unknown',
                    'model' => $result->llm_model,
                    'created_at' => $result->created_at->format('Y-m-d H:i'),
                    'execution_time' => round($result->execution_time, 2),
                ];
            })->toArray();
    }
}