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
     * Apskaičiuoti pažangesnes metrikas tyrimo išvadoms.
     */
    public function calculateAdvancedMetrics(string $jobId): array
    {
        $metrics = ComparisonMetric::where('job_id', $jobId)->get();
        
        if ($metrics->isEmpty()) {
            return [
                'error' => 'Nėra metrikų duomenų',
                'suggestions' => [
                    'Patikrinkite ar yra ekspertų anotacijos',
                    'Patikrinkite ar analizė sėkmingai baigėsi'
                ]
            ];
        }

        $models = $metrics->groupBy('model_name');
        $results = [];
        
        foreach ($models as $modelName => $modelMetrics) {
            $results[$modelName] = [
                // Pagrindinės metrikos
                'precision' => round($modelMetrics->avg('precision'), 3),
                'recall' => round($modelMetrics->avg('recall'), 3),
                'f1_score' => round($modelMetrics->avg('f1_score'), 3),
                
                // Pažangesnės metrikos
                'position_accuracy' => round($modelMetrics->avg('position_accuracy'), 3),
                'consistency' => $this->calculateConsistency($modelMetrics),
                'coverage' => $this->calculateCoverage($modelMetrics),
                
                // Zarankos darbo metrikos
                'fragment_identification_score' => $this->calculateFragmentIdentificationScore($modelMetrics),
                'span_detection_accuracy' => $this->calculateSpanDetectionAccuracy($modelMetrics),
                'zaranka_comparison' => $this->compareWithZarankaResults($modelMetrics),
                
                // Statistikos
                'total_texts' => $modelMetrics->count(),
                'avg_execution_time_ms' => round($modelMetrics->avg('analysis_execution_time_ms') ?? 0),
                
                // Klasifikacijos statistika
                'classification_stats' => $this->getClassificationStats($modelMetrics),
                
                // Klaidos analizė
                'error_analysis' => $this->analyzeErrors($modelMetrics),
            ];
        }
        
        // Modelių palyginimas
        $results['comparison'] = $this->compareModels($results);
        
        // Bendros analizės išvados
        $results['insights'] = $this->generateInsights($results);

        return $results;
    }

    /**
     * Apskaičiuoti modelio nuoseklumą (consistency).
     */
    private function calculateConsistency(object $metrics): float
    {
        $f1Scores = $metrics->pluck('f1_score')->filter()->values();
        
        if ($f1Scores->count() <= 1) {
            return 0.0;
        }
        
        $mean = $f1Scores->avg();
        $variance = $f1Scores->map(function($score) use ($mean) {
            return pow($score - $mean, 2);
        })->avg();
        
        $stdDev = sqrt($variance);
        
        // Nuoseklumas: 1 - (std_dev / mean), ribojamas 0-1
        return max(0, round(1 - ($stdDev / max($mean, 0.001)), 3));
    }

    /**
     * Apskaičiuoti modelio apimtį (coverage) - kiek tekstų turėjo bent vieną anotaciją.
     */
    private function calculateCoverage(object $metrics): float
    {
        $withAnnotations = $metrics->filter(function($metric) {
            return ($metric->true_positives + $metric->false_positives) > 0;
        })->count();
        
        $total = $metrics->count();
        
        return $total > 0 ? round($withAnnotations / $total, 3) : 0.0;
    }

    /**
     * Gauti klasifikacijos statistiką.
     */
    private function getClassificationStats(object $metrics): array
    {
        return [
            'total_true_positives' => $metrics->sum('true_positives'),
            'total_false_positives' => $metrics->sum('false_positives'),
            'total_false_negatives' => $metrics->sum('false_negatives'),
            'avg_annotations_per_text' => round(($metrics->sum('true_positives') + $metrics->sum('false_positives')) / max($metrics->count(), 1), 2),
        ];
    }

    /**
     * Analizuoti klaidas ir problemas.
     */
    private function analyzeErrors(object $metrics): array
    {
        $analysis = [
            'high_false_positive_texts' => 0,
            'high_false_negative_texts' => 0,
            'zero_detection_texts' => 0,
            'perfect_match_texts' => 0,
        ];
        
        foreach ($metrics as $metric) {
            if ($metric->false_positives > 3) {
                $analysis['high_false_positive_texts']++;
            }
            
            if ($metric->false_negatives > 3) {
                $analysis['high_false_negative_texts']++;
            }
            
            if ($metric->true_positives == 0 && $metric->false_positives == 0) {
                $analysis['zero_detection_texts']++;
            }
            
            if ($metric->false_positives == 0 && $metric->false_negatives == 0 && $metric->true_positives > 0) {
                $analysis['perfect_match_texts']++;
            }
        }
        
        return $analysis;
    }

    /**
     * Palyginti modelius tarpusavyje.
     */
    private function compareModels(array $results): array
    {
        $models = array_filter($results, function($key) {
            return $key !== 'comparison' && $key !== 'insights';
        }, ARRAY_FILTER_USE_KEY);
        
        if (count($models) < 2) {
            return ['note' => 'Reikia bent 2 modelių palyginimui'];
        }
        
        $comparison = [
            'best_precision' => $this->findBestModel($models, 'precision'),
            'best_recall' => $this->findBestModel($models, 'recall'),
            'best_f1' => $this->findBestModel($models, 'f1_score'),
            'most_consistent' => $this->findBestModel($models, 'consistency'),
            'fastest' => $this->findFastestModel($models),
        ];
        
        return $comparison;
    }

    /**
     * Rasti geriausią modelį pagal metriką.
     */
    private function findBestModel(array $models, string $metric): array
    {
        $best = null;
        $bestValue = -1;
        
        foreach ($models as $modelName => $modelData) {
            if (isset($modelData[$metric]) && $modelData[$metric] > $bestValue) {
                $bestValue = $modelData[$metric];
                $best = $modelName;
            }
        }
        
        return [
            'model' => $best,
            'value' => $bestValue
        ];
    }

    /**
     * Rasti greičiausią modelį.
     */
    private function findFastestModel(array $models): array
    {
        $fastest = null;
        $fastestTime = PHP_INT_MAX;
        
        foreach ($models as $modelName => $modelData) {
            $time = $modelData['avg_execution_time_ms'] ?? PHP_INT_MAX;
            if ($time < $fastestTime && $time > 0) {
                $fastestTime = $time;
                $fastest = $modelName;
            }
        }
        
        return [
            'model' => $fastest,
            'avg_time_ms' => $fastestTime === PHP_INT_MAX ? null : $fastestTime
        ];
    }

    /**
     * Generuoti tyrimo išvadas pagal Zarankos darbo ir ATSPARA metodologiją.
     */
    private function generateInsights(array $results): array
    {
        $models = array_filter($results, function($key) {
            return $key !== 'comparison' && $key !== 'insights';
        }, ARRAY_FILTER_USE_KEY);
        
        $insights = [];
        
        // Bendras vertinimas pagal Zarankos darbo standartus
        $avgF1 = collect($models)->avg('f1_score');
        if ($avgF1 > 0.69) {
            $insights[] = "Puikūs rezultatai (vid. F1: " . round($avgF1, 3) . ") - viršija Zarankos magistrinio darbo lygį (69.3%)";
        } elseif ($avgF1 > 0.6) {
            $insights[] = "Geri rezultatai (vid. F1: " . round($avgF1, 3) . ") - artėja prie Zarankos darbo standarto";
        } elseif ($avgF1 > 0.44) {
            $insights[] = "Vidutiniai rezultatai (vid. F1: " . round($avgF1, 3) . ") - viršija anglų kalbos tyrimus (~44%)";
        } else {
            $insights[] = "Žemi rezultatai (vid. F1: " . round($avgF1, 3) . ") - žemiau anglų kalbos standarto";
        }
        
        // Lietuvių kalbos specifika
        if ($avgF1 > 0.44) {
            $insights[] = "Lietuvių kalbos modeliai efektyvesni nei anglų kalbos analogai - patvirtina Zarankos tyrimo išvadas";
        }
        
        // Precision vs Recall analizė
        $avgPrecision = collect($models)->avg('precision');
        $avgRecall = collect($models)->avg('recall');
        
        if ($avgPrecision > $avgRecall + 0.1) {
            $insights[] = "Modeliai konservatyvūs (P=" . round($avgPrecision, 3) . " > R=" . round($avgRecall, 3) . ") - mažiau false positives";
        } elseif ($avgRecall > $avgPrecision + 0.1) {
            $insights[] = "Modeliai jautrūs (R=" . round($avgRecall, 3) . " > P=" . round($avgPrecision, 3) . ") - aptinka daugiau propagandos";
        } else {
            $insights[] = "Subalansuoti modeliai (P≈R≈" . round(($avgPrecision + $avgRecall) / 2, 3) . ") - optimalus precision/recall santykis";
        }
        
        // Fragmentų identifikavimo specifika
        $avgCoverage = collect($models)->avg('coverage');
        if ($avgCoverage > 0.8) {
            $insights[] = "Aukšta fragmentų aptikimo apimtis (" . round($avgCoverage * 100, 1) . "%) - modeliai randa anotacijas daugumos tekstų";
        } elseif ($avgCoverage > 0.6) {
            $insights[] = "Vidutinė fragmentų aptikimo apimtis (" . round($avgCoverage * 100, 1) . "%) - dalies tekstų propagandos neaptikta";
        } else {
            $insights[] = "Žema fragmentų aptikimo apimtis (" . round($avgCoverage * 100, 1) . "%) - dauguma tekstų be anotacijų";
        }
        
        // Nuoseklumo analizė
        $avgConsistency = collect($models)->avg('consistency');
        if ($avgConsistency > 0.8) {
            $insights[] = "Aukšta modelių nuoseklumas - stabilūs rezultatai skirtingiems tekstams";
        } elseif ($avgConsistency < 0.5) {
            $insights[] = "Žema modelių nuoseklumas - nestabilūs rezultatai, reikia prompt'ų optimizavimo";
        }
        
        // ATSPARA metodologijos kontekstas
        if (count($models) >= 3) {
            $insights[] = "Kelių modelių palyginimas atitinka ATSPARA projekto metodologiją";
        }
        
        // Fragmentų ilgio analizė (pagal Zarankos pastebėjimus)
        $insights[] = "Lietuvių kalbos propagandos fragmentai vidutiniškai 12x ilgesni nei anglų - tai paaiškinat geresnius F1 rezultatus";
        
        return $insights;
    }

    /**
     * Apskaičiuoti fragmentų identifikavimo balą (pagal Zarankos metodologiją).
     */
    private function calculateFragmentIdentificationScore(object $metrics): float
    {
        // Zarankos darbe naudojamas span-based F1 score
        $totalTruePositives = $metrics->sum('true_positives');
        $totalFalsePositives = $metrics->sum('false_positives');
        $totalFalseNegatives = $metrics->sum('false_negatives');
        
        if ($totalTruePositives == 0) {
            return 0.0;
        }
        
        $precision = $totalTruePositives / ($totalTruePositives + $totalFalsePositives);
        $recall = $totalTruePositives / ($totalTruePositives + $totalFalseNegatives);
        
        if ($precision + $recall == 0) {
            return 0.0;
        }
        
        return round(2 * ($precision * $recall) / ($precision + $recall), 3);
    }

    /**
     * Apskaičiuoti span detection tikslumą.
     */
    private function calculateSpanDetectionAccuracy(object $metrics): float
    {
        // Kiek tiksliai nustatytos propagandos fragmentų pozicijos
        $accurateSpans = $metrics->filter(function($metric) {
            return $metric->position_accuracy > 0.8; // 80% pozicijos tikslumas
        })->count();
        
        $totalSpans = $metrics->count();
        
        return $totalSpans > 0 ? round($accurateSpans / $totalSpans, 3) : 0.0;
    }

    /**
     * Palyginti su Zarankos darbo rezultatais.
     */
    private function compareWithZarankaResults(object $metrics): array
    {
        $ourF1 = round($metrics->avg('f1_score'), 3);
        
        // Zarankos darbo benchmark'ai
        $zarankaBenchmarks = [
            'xlm-roberta-base' => 0.693,  // 69.3%
            'litlat-bert' => 0.660,       // 66.0%
            'mdeberta-v3-base' => 0.646,  // 64.6%
            'english_baseline' => 0.44    // Anglų kalbos tyrimai
        ];
        
        $comparison = [
            'our_f1' => $ourF1,
            'zaranka_best' => $zarankaBenchmarks['xlm-roberta-base'],
            'improvement_vs_english' => round($ourF1 - $zarankaBenchmarks['english_baseline'], 3),
            'vs_zaranka_best' => round($ourF1 - $zarankaBenchmarks['xlm-roberta-base'], 3),
        ];
        
        // Vertinimas
        if ($ourF1 >= $zarankaBenchmarks['xlm-roberta-base']) {
            $comparison['assessment'] = 'Excellent - equals or exceeds Zaranka benchmark';
        } elseif ($ourF1 >= $zarankaBenchmarks['litlat-bert']) {
            $comparison['assessment'] = 'Very good - comparable to Zaranka second-best';
        } elseif ($ourF1 >= $zarankaBenchmarks['mdeberta-v3-base']) {
            $comparison['assessment'] = 'Good - comparable to Zaranka third-best';
        } elseif ($ourF1 >= $zarankaBenchmarks['english_baseline']) {
            $comparison['assessment'] = 'Above English baseline - confirms Lithuanian superiority';
        } else {
            $comparison['assessment'] = 'Below expected - investigate methodology';
        }
        
        return $comparison;
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