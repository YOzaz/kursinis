<?php

namespace App\Services;

use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Models\AnalysisJob;
use Illuminate\Support\Facades\Log;

/**
 * Metrikų skaičiavimo servisas propagandos analizei.
 * 
 * Skaičiuoja precision, recall, F1, Cohen's Kappa ir kitas metrikas
 * lietuviško teksto analizei.
 * 
 * Kursinio darbo autorius: Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)
 * Dėstytojas: Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)
 *
 * Duomenų šaltiniai ir metodologija:
 * - ATSPARA korpuso duomenys ir anotavimo metodologija: https://www.atspara.mif.vu.lt/
 */
class MetricsService
{
    /**
     * Pozicijos tikslumo tolerancija simboliais.
     */
    const POSITION_TOLERANCE = 10;

    /**
     * Apskaičiuoti metrikas vienam tekstui ir modeliui.
     */
    public function calculateMetricsForText(
        TextAnalysis $textAnalysis, 
        string $modelName, 
        string $jobId,
        ?string $actualModelName = null
    ): ComparisonMetric {
        $expertAnnotations = $textAnalysis->expert_annotations;
        $modelAnnotations = $textAnalysis->getModelAnnotations($modelName);

        if (empty($expertAnnotations) || empty($modelAnnotations)) {
            Log::warning('Trūksta anotacijų metrikoms skaičiuoti', [
                'text_id' => $textAnalysis->text_id,
                'model' => $modelName,
                'has_expert' => !empty($expertAnnotations),
                'has_model' => !empty($modelAnnotations)
            ]);
            
            return $this->createEmptyMetric($jobId, $textAnalysis->text_id, $modelName);
        }

        // Išgauti anotacijas iš struktūros
        $expertLabels = $this->extractLabelsFromAnnotations($expertAnnotations);
        $modelLabels = $this->extractLabelsFromAnnotations($modelAnnotations['annotations'] ?? []);

        // Apskaičiuoti sutapimo statistiką
        $stats = $this->calculateOverlapStatistics($expertLabels, $modelLabels);

        // Apskaičiuoti pozicijos tikslumą
        $positionAccuracy = $this->calculatePositionAccuracy($expertLabels, $modelLabels);

        // Gauti vykdymo laiką iš tekstų analizės
        $executionTimeMs = $textAnalysis->getModelExecutionTime($modelName);
        
        // Sukurti metrikų įrašą
        $metric = ComparisonMetric::create([
            'job_id' => $jobId,
            'text_id' => $textAnalysis->text_id,
            'model_name' => $modelName,
            'actual_model_name' => $actualModelName,
            'true_positives' => $stats['true_positives'],
            'false_positives' => $stats['false_positives'],
            'false_negatives' => $stats['false_negatives'],
            'position_accuracy' => $positionAccuracy,
            'analysis_execution_time_ms' => $executionTimeMs,
        ]);

        // Apskaičiuoti ir išsaugoti metrikas
        $metric->updateCalculatedMetrics();
        $metric->save();

        Log::info('MetrikOs apskaičiuotos tekstui', [
            'text_id' => $textAnalysis->text_id,
            'model' => $modelName,
            'precision' => $metric->precision,
            'recall' => $metric->recall,
            'f1_score' => $metric->f1_score
        ]);

        return $metric;
    }

    /**
     * Apskaičiuoti agregatas metrikas visam darbui.
     */
    public function calculateAggregatedMetrics(string $jobId): array
    {
        // Get all unique model names that have metrics for this job
        $models = ComparisonMetric::where('job_id', $jobId)
            ->distinct('model_name')
            ->pluck('model_name')
            ->toArray();
        
        $results = [];

        foreach ($models as $model) {
            $metrics = ComparisonMetric::where('job_id', $jobId)
                ->where('model_name', $model)
                ->get();

            if ($metrics->isEmpty()) {
                continue;
            }

            $results[$model] = [
                'precision' => round($metrics->avg('precision'), 4),
                'recall' => round($metrics->avg('recall'), 4),
                'f1_score' => round($metrics->avg('f1_score'), 4),
                'position_accuracy' => round($metrics->avg('position_accuracy'), 4),
                'cohen_kappa' => $this->calculateCohenKappa($metrics),
                'total_texts' => $metrics->count(),
            ];
        }

        return $results;
    }

    /**
     * Išgauti etiketes iš anotacijų struktūros.
     */
    private function extractLabelsFromAnnotations(array $annotations): array
    {
        $labels = [];

        foreach ($annotations as $annotation) {
            if (isset($annotation['result'])) {
                // Ekspertų anotacijų formatas
                foreach ($annotation['result'] as $result) {
                    if (isset($result['value']) && $result['type'] === 'labels') {
                        // Only process label annotations, skip choices and other types
                        $value = $result['value'];
                        
                        // Skip empty or invalid annotations
                        if (empty($value['text']) || empty($value['labels']) || 
                            ($value['start'] === 0 && $value['end'] === 0)) {
                            continue;
                        }
                        
                        $labels[] = [
                            'start' => $value['start'] ?? 0,
                            'end' => $value['end'] ?? 0,
                            'text' => $value['text'] ?? '',
                            'labels' => $value['labels'] ?? [],
                        ];
                    }
                }
            } elseif (isset($annotation['value']) && $annotation['type'] === 'labels') {
                // LLM anotacijų formatas
                $value = $annotation['value'];
                
                // Skip empty or invalid annotations
                if (empty($value['text']) || empty($value['labels'])) {
                    continue;
                }
                
                $labels[] = [
                    'start' => $value['start'] ?? 0,
                    'end' => $value['end'] ?? 0,
                    'text' => $value['text'] ?? '',
                    'labels' => $value['labels'] ?? [],
                ];
            }
        }

        return $labels;
    }

    /**
     * Apskaičiuoti sutapimo statistiką tarp ekspertų ir modelio anotacijų.
     */
    private function calculateOverlapStatistics(array $expertLabels, array $modelLabels): array
    {
        $truePositives = 0;
        $falsePositives = 0;
        $falseNegatives = 0;
        $matchedExpertIndices = [];

        // Ieškoti true positives ir false positives
        foreach ($modelLabels as $modelLabel) {
            $matched = false;
            
            foreach ($expertLabels as $index => $expertLabel) {
                if (in_array($index, $matchedExpertIndices)) {
                    continue; // Jau suporuotas
                }

                if ($this->labelsMatch($expertLabel, $modelLabel)) {
                    $truePositives++;
                    $matchedExpertIndices[] = $index;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $falsePositives++;
            }
        }

        // False negatives = nesuporuotos ekspertų anotacijos
        $falseNegatives = count($expertLabels) - count($matchedExpertIndices);

        return [
            'true_positives' => $truePositives,
            'false_positives' => $falsePositives,
            'false_negatives' => $falseNegatives,
        ];
    }

    /**
     * Patikrinti ar dvi etiketės sutampa.
     */
    private function labelsMatch(array $expertLabel, array $modelLabel): bool
    {
        // Patikrinti pozicijos sutapimą su tolerancija
        $positionMatch = $this->positionsOverlap(
            $expertLabel['start'], 
            $expertLabel['end'],
            $modelLabel['start'], 
            $modelLabel['end']
        );

        if (!$positionMatch) {
            return false;
        }

        // Patikrinti etikečių sutapimą su kategorijų žemėlapiu
        $expertLabelSet = array_map('strtolower', $expertLabel['labels'] ?? []);
        $modelLabelSet = array_map('strtolower', $modelLabel['labels'] ?? []);

        // Direct match first
        if (!empty(array_intersect($expertLabelSet, $modelLabelSet))) {
            return true;
        }

        // Try category mapping
        foreach ($expertLabelSet as $expertCategory) {
            $mappedCategories = $this->getMappedCategories($expertCategory);
            if (!empty(array_intersect($mappedCategories, $modelLabelSet))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gauti AI kategorijų atitikimus ekspertų kategorijoms.
     * 
     * Ekspertai naudoja supaprastintas kategorijas, o AI naudoja ATSPARA metodologijos kategorijas.
     * Šis žemėlapis susieja skirtingas klasifikacijas.
     */
    private function getMappedCategories(string $expertCategory): array
    {
        $categoryMapping = [
            // Expert category => AI categories (ATSPARA)
            'simplification' => ['causaloversimplification', 'blackandwhite', 'thoughtterminatingcliche', 'slogans'],
            'emotionalexpression' => ['emotionalappeal', 'loadedlanguage', 'appealtofear', 'exaggeration', 'namecalling'],
            'uncertainty' => ['doubt', 'obfuscation', 'appealtofear'],
            'doubt' => ['doubt', 'smears', 'uncertainty'],
            'repetition' => ['repetition', 'bandwagon'],
            'reductionadhitlerum' => ['reductionoadhitlerum', 'namecalling'],
            'wavingtheflag' => ['flagwaving', 'appealtofear'],
            'namecalling' => ['namecalling', 'loadedlanguage', 'smears'],
            
            // Additional mappings for other expert categories observed
            'whataboutism' => ['whataboutism', 'redherring'],
            'strawman' => ['strawman', 'redherring'],
            'bandwagon' => ['bandwagon', 'repetition'],
            'authority' => ['appealtoauthority'],
            'false' => ['doubt', 'smears'],
            'fear' => ['appealtofear', 'loadedlanguage'],
        ];

        return $categoryMapping[strtolower($expertCategory)] ?? [];
    }

    /**
     * Patikrinti ar pozicijos persidengia.
     */
    private function positionsOverlap(int $start1, int $end1, int $start2, int $end2): bool
    {
        // Apskaičiuoti persidenginų pozicijų santykį
        $overlapStart = max($start1, $start2);
        $overlapEnd = min($end1, $end2);
        
        if ($overlapStart >= $overlapEnd) {
            return false; // Nėra persidengimo
        }

        $overlapLength = $overlapEnd - $overlapStart;
        $totalLength = max($end1 - $start1, $end2 - $start2);

        // Reikia bent 50% persidengimo
        return ($overlapLength / $totalLength) >= 0.5;
    }

    /**
     * Apskaičiuoti pozicijos tikslumą.
     */
    private function calculatePositionAccuracy(array $expertLabels, array $modelLabels): float
    {
        if (empty($modelLabels)) {
            return 0.0;
        }

        $accuratePositions = 0;

        foreach ($modelLabels as $modelLabel) {
            foreach ($expertLabels as $expertLabel) {
                $startDiff = abs($modelLabel['start'] - $expertLabel['start']);
                $endDiff = abs($modelLabel['end'] - $expertLabel['end']);

                if ($startDiff <= self::POSITION_TOLERANCE && $endDiff <= self::POSITION_TOLERANCE) {
                    $accuratePositions++;
                    break; // Rasta atitikmuo, pereiti prie kito modelio label
                }
            }
        }

        return round($accuratePositions / count($modelLabels), 4);
    }

    /**
     * Apskaičiuoti Cohen's Kappa koeficientą.
     */
    private function calculateCohenKappa($metrics): float
    {
        $totalTP = $metrics->sum('true_positives');
        $totalFP = $metrics->sum('false_positives');
        $totalFN = $metrics->sum('false_negatives');
        $totalTN = $totalTP; // Supaprastintas skaičiavimas

        $total = $totalTP + $totalFP + $totalFN + $totalTN;
        
        if ($total === 0) {
            return 0.0;
        }

        // Stebimas sutarimas
        $observedAgreement = ($totalTP + $totalTN) / $total;

        // Tikėtinas sutarimas
        $expertPositive = ($totalTP + $totalFN) / $total;
        $modelPositive = ($totalTP + $totalFP) / $total;
        $expertNegative = ($totalTN + $totalFP) / $total;
        $modelNegative = ($totalTN + $totalFN) / $total;

        $expectedAgreement = ($expertPositive * $modelPositive) + 
                           ($expertNegative * $modelNegative);

        if ($expectedAgreement >= 1.0) {
            return 0.0;
        }

        $kappa = ($observedAgreement - $expectedAgreement) / (1 - $expectedAgreement);
        
        return round($kappa, 4);
    }

    /**
     * Skaičiuoti darbo statistikas.
     */
    public function calculateJobStatistics(AnalysisJob $job): array
    {
        $textAnalyses = $job->textAnalyses;
        
        if ($textAnalyses->isEmpty()) {
            return [
                'total_texts' => 0,
                'models_used' => [],
                'overall_metrics' => [],
                'per_model_metrics' => [],
                'execution_time' => 0,
            ];
        }

        // Gauti visas metrikas tiesiogiai iš job
        $allMetrics = $job->comparisonMetrics;

        $modelMetrics = $allMetrics->groupBy('model_name');
        $modelsUsed = $modelMetrics->keys()->toArray();

        $perModelMetrics = [];
        foreach ($modelMetrics as $model => $metrics) {
            $perModelMetrics[$model] = [
                'count' => $metrics->count(),
                'avg_precision' => round($metrics->avg('precision') ?? 0, 3),
                'avg_recall' => round($metrics->avg('recall') ?? 0, 3),
                'avg_f1' => round($metrics->avg('f1_score') ?? 0, 3),
                'total_tp' => $metrics->sum('true_positives'),
                'total_fp' => $metrics->sum('false_positives'),
                'total_fn' => $metrics->sum('false_negatives'),
            ];
        }

        $overallMetrics = [
            'avg_precision' => round($allMetrics->avg('precision') ?? 0, 3),
            'avg_recall' => round($allMetrics->avg('recall') ?? 0, 3),
            'avg_f1' => round($allMetrics->avg('f1_score') ?? 0, 3),
            'total_comparisons' => $allMetrics->count(),
        ];

        $executionTime = 0;
        if ($job->started_at && $job->completed_at) {
            $executionTime = $job->started_at->diffInSeconds($job->completed_at);
        }

        return [
            'total_texts' => $textAnalyses->count(),
            'models_used' => $modelsUsed,
            'overall_metrics' => $overallMetrics,
            'per_model_metrics' => $perModelMetrics,
            'execution_time' => $executionTime,
        ];
    }

    /**
     * Sukurti tuščią metriką, kai trūksta duomenų.
     */
    private function createEmptyMetric(string $jobId, string $textId, string $modelName): ComparisonMetric
    {
        return ComparisonMetric::create([
            'job_id' => $jobId,
            'text_id' => $textId,
            'model_name' => $modelName,
            'true_positives' => 0,
            'false_positives' => 0,
            'false_negatives' => 0,
            'position_accuracy' => 0.0000,
            'precision' => 0.0000,
            'recall' => 0.0000,
            'f1_score' => 0.0000,
        ]);
    }
}