<?php

namespace App\Services;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Eksporto servisas.
 * 
 * Atsakingas už duomenų eksportavimą į CSV formatą.
 */
class ExportService
{
    /**
     * Eksportuoti analizės rezultatus į CSV.
     */
    public function exportToCsv(string $jobId): Response
    {
        try {
            $job = AnalysisJob::with(['textAnalyses', 'comparisonMetrics'])
                ->where('job_id', $jobId)
                ->first();

            if (!$job) {
                throw new \Exception('Analizės darbas nerastas');
            }

            if (!$job->isCompleted()) {
                throw new \Exception('Analizė dar nebaigta');
            }

            Log::info('Pradedamas CSV eksportas', ['job_id' => $jobId]);

            // Generuoti CSV turinį
            $csvContent = $this->generateCsvContent($job);

            $fileName = "propaganda_analysis_{$jobId}_" . date('Y-m-d_H-i-s') . '.csv';

            return response($csvContent, 200)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', "attachment; filename=\"{$fileName}\"")
                ->header('Cache-Control', 'no-cache, must-revalidate');

        } catch (\Exception $e) {
            Log::error('CSV eksporto klaida', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Generuoti CSV turinį.
     */
    private function generateCsvContent(AnalysisJob $job): string
    {
        $csvData = [];
        
        // CSV antraštės pagal specifikaciją
        $csvData[] = [
            'text_id',
            'technique',
            'expert_start',
            'expert_end',
            'expert_text',
            'model',
            'model_start',
            'model_end',
            'model_text',
            'match',
            'position_accuracy',
            'precision',
            'recall',
            'f1_score'
        ];

        // Apdoroti kiekvieną tekstą
        foreach ($job->textAnalyses as $textAnalysis) {
            $this->processTextForCsv($textAnalysis, $csvData);
        }

        Log::info('CSV duomenys sugeneruoti', [
            'job_id' => $job->job_id,
            'rows_count' => count($csvData) - 1 // Atimti antraštės eilutę
        ]);

        return $this->arrayToCsv($csvData);
    }

    /**
     * Apdoroti vieną tekstą CSV eksportui.
     */
    private function processTextForCsv(TextAnalysis $textAnalysis, array &$csvData): void
    {
        $expertAnnotations = $this->extractAnnotationsFromStructure($textAnalysis->expert_annotations);
        $models = ['claude-4', 'gemini-2.5-pro', 'gpt-4.1'];

        foreach ($models as $model) {
            $modelAnnotations = $textAnalysis->getModelAnnotations($model);
            
            if (empty($modelAnnotations)) {
                continue;
            }

            $modelLabels = $this->extractAnnotationsFromStructure($modelAnnotations['annotations'] ?? []);
            $metrics = $this->getMetricsForTextAndModel($textAnalysis->text_id, $model, $textAnalysis->job_id);

            // Sukurti eilutes kiekvienai technikai
            $this->createCsvRowsForTechniques($textAnalysis, $expertAnnotations, $modelLabels, $model, $metrics, $csvData);
        }
    }

    /**
     * Sukurti CSV eilutes kiekvienai technikai.
     */
    private function createCsvRowsForTechniques(
        TextAnalysis $textAnalysis,
        array $expertAnnotations,
        array $modelAnnotations,
        string $model,
        ?ComparisonMetric $metrics,
        array &$csvData
    ): void {
        // Gauti visas technikas iš abiejų šaltinių
        $allTechniques = $this->getAllTechniques($expertAnnotations, $modelAnnotations);

        foreach ($allTechniques as $technique) {
            $expertMatches = $this->findAnnotationsByTechnique($expertAnnotations, $technique);
            $modelMatches = $this->findAnnotationsByTechnique($modelAnnotations, $technique);

            // Jei yra ekspertų anotacijų šiai technikai
            foreach ($expertMatches as $expertMatch) {
                $bestModelMatch = $this->findBestMatch($expertMatch, $modelMatches);
                
                $csvData[] = [
                    $textAnalysis->text_id,
                    $technique,
                    $expertMatch['start'] ?? 0,
                    $expertMatch['end'] ?? 0,
                    $this->cleanText($expertMatch['text'] ?? ''),
                    $model,
                    $bestModelMatch['start'] ?? '',
                    $bestModelMatch['end'] ?? '',
                    $this->cleanText($bestModelMatch['text'] ?? ''),
                    $bestModelMatch ? 'true' : 'false',
                    $this->calculatePositionAccuracyForPair($expertMatch, $bestModelMatch),
                    $metrics->precision ?? 0,
                    $metrics->recall ?? 0,
                    $metrics->f1_score ?? 0
                ];
            }

            // Jei yra modelio anotacijų, kurios neturi ekspertų atitikmens
            foreach ($modelMatches as $modelMatch) {
                if (!$this->hasExpertMatch($modelMatch, $expertMatches)) {
                    $csvData[] = [
                        $textAnalysis->text_id,
                        $technique,
                        '', // Nėra ekspertų anotacijos
                        '',
                        '',
                        $model,
                        $modelMatch['start'] ?? 0,
                        $modelMatch['end'] ?? 0,
                        $this->cleanText($modelMatch['text'] ?? ''),
                        'false', // False positive
                        0.0,
                        $metrics->precision ?? 0,
                        $metrics->recall ?? 0,
                        $metrics->f1_score ?? 0
                    ];
                }
            }
        }
    }

    /**
     * Išgauti anotacijas iš struktūros.
     */
    private function extractAnnotationsFromStructure(array $annotations): array
    {
        $extracted = [];

        foreach ($annotations as $annotation) {
            if (isset($annotation['result'])) {
                // Ekspertų formatas
                foreach ($annotation['result'] as $result) {
                    if (isset($result['value'])) {
                        $extracted[] = $result['value'];
                    }
                }
            } elseif (isset($annotation['value'])) {
                // LLM formatas
                $extracted[] = $annotation['value'];
            }
        }

        return $extracted;
    }

    /**
     * Gauti visas technikas iš anotacijų.
     */
    private function getAllTechniques(array $expertAnnotations, array $modelAnnotations): array
    {
        $techniques = [];

        foreach ($expertAnnotations as $annotation) {
            foreach ($annotation['labels'] ?? [] as $label) {
                $techniques[] = $label;
            }
        }

        foreach ($modelAnnotations as $annotation) {
            foreach ($annotation['labels'] ?? [] as $label) {
                $techniques[] = $label;
            }
        }

        return array_unique($techniques);
    }

    /**
     * Rasti anotacijas pagal techniką.
     */
    private function findAnnotationsByTechnique(array $annotations, string $technique): array
    {
        $matches = [];

        foreach ($annotations as $annotation) {
            if (in_array($technique, $annotation['labels'] ?? [])) {
                $matches[] = $annotation;
            }
        }

        return $matches;
    }

    /**
     * Rasti geriausią atitikmenį.
     */
    private function findBestMatch(array $expertAnnotation, array $modelAnnotations): ?array
    {
        $bestMatch = null;
        $bestOverlap = 0;

        foreach ($modelAnnotations as $modelAnnotation) {
            $overlap = $this->calculateOverlap($expertAnnotation, $modelAnnotation);
            
            if ($overlap > $bestOverlap) {
                $bestOverlap = $overlap;
                $bestMatch = $modelAnnotation;
            }
        }

        return $bestMatch;
    }

    /**
     * Apskaičiuoti persidengimą tarp dviejų anotacijų.
     */
    private function calculateOverlap(array $annotation1, array $annotation2): float
    {
        $start1 = $annotation1['start'] ?? 0;
        $end1 = $annotation1['end'] ?? 0;
        $start2 = $annotation2['start'] ?? 0;
        $end2 = $annotation2['end'] ?? 0;

        $overlapStart = max($start1, $start2);
        $overlapEnd = min($end1, $end2);

        if ($overlapStart >= $overlapEnd) {
            return 0.0;
        }

        $overlapLength = $overlapEnd - $overlapStart;
        $totalLength = max($end1 - $start1, $end2 - $start2);

        return $totalLength > 0 ? $overlapLength / $totalLength : 0.0;
    }

    /**
     * Patikrinti ar modelio anotacija turi ekspertų atitikmenį.
     */
    private function hasExpertMatch(array $modelAnnotation, array $expertAnnotations): bool
    {
        foreach ($expertAnnotations as $expertAnnotation) {
            if ($this->calculateOverlap($modelAnnotation, $expertAnnotation) > 0.5) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apskaičiuoti pozicijos tikslumą porai anotacijų.
     */
    private function calculatePositionAccuracyForPair(?array $expertAnnotation, ?array $modelAnnotation): float
    {
        if (!$expertAnnotation || !$modelAnnotation) {
            return 0.0;
        }

        $startDiff = abs(($modelAnnotation['start'] ?? 0) - ($expertAnnotation['start'] ?? 0));
        $endDiff = abs(($modelAnnotation['end'] ?? 0) - ($expertAnnotation['end'] ?? 0));

        // Jei skirtumas mažiau nei 10 simbolių, laikyti tikslia
        $tolerance = 10;
        
        if ($startDiff <= $tolerance && $endDiff <= $tolerance) {
            return 1.0;
        }

        // Apskaičiuoti tikslumą pagal atstumą
        $maxLength = max(
            ($expertAnnotation['end'] ?? 0) - ($expertAnnotation['start'] ?? 0),
            ($modelAnnotation['end'] ?? 0) - ($modelAnnotation['start'] ?? 0)
        );

        if ($maxLength === 0) {
            return 0.0;
        }

        $avgDiff = ($startDiff + $endDiff) / 2;
        $accuracy = max(0, 1 - ($avgDiff / $maxLength));

        return round($accuracy, 4);
    }

    /**
     * Gauti metrikas tekstui ir modeliui.
     */
    private function getMetricsForTextAndModel(string $textId, string $model, string $jobId): ?ComparisonMetric
    {
        return ComparisonMetric::where('text_id', $textId)
            ->where('model_name', $model)
            ->where('job_id', $jobId)
            ->first();
    }

    /**
     * Išvalyti tekstą CSV formatui.
     */
    private function cleanText(string $text): string
    {
        // Pašalinti naujas eilutes ir tabuliacijas
        $text = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $text);
        
        // Sutraukti kelis tarpus į vieną
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Apriboti teksto ilgį
        if (strlen($text) > 200) {
            $text = substr($text, 0, 197) . '...';
        }

        return trim($text);
    }

    /**
     * Konvertuoti masyvą į CSV stringą.
     */
    private function arrayToCsv(array $data): string
    {
        $output = '';
        
        foreach ($data as $row) {
            $escapedRow = array_map(function ($field) {
                // Escape commas, quotes, and newlines
                if (is_string($field) && (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false)) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }
                return $field;
            }, $row);
            
            $output .= implode(',', $escapedRow) . "\n";
        }

        return $output;
    }
}