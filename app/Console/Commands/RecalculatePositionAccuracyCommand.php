<?php

namespace App\Console\Commands;

use App\Models\ComparisonMetric;
use App\Models\TextAnalysis;
use App\Services\MetricsService;
use Illuminate\Console\Command;

class RecalculatePositionAccuracyCommand extends Command
{
    protected $signature = 'metrics:recalculate-position-accuracy {--job-id=} {--force}';

    protected $description = 'Recalculate position accuracy using the new inter-annotator agreement formula';

    public function handle(MetricsService $metricsService)
    {
        $this->info('Starting position accuracy recalculation...');

        $query = ComparisonMetric::query();
        
        if ($jobId = $this->option('job-id')) {
            $query->where('job_id', $jobId);
            $this->info("Filtering by job ID: {$jobId}");
        }

        $metrics = $query->get();
        
        if ($metrics->isEmpty()) {
            $this->warn('No metrics found to recalculate.');
            return self::SUCCESS;
        }

        $this->info("Found {$metrics->count()} metrics to recalculate.");

        if (!$this->option('force') && !$this->confirm('This will update all position accuracy values. Continue?')) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($metrics->count());
        $updated = 0;
        $errors = 0;

        foreach ($metrics as $metric) {
            try {
                // Get the text analysis for this metric
                $textAnalysis = TextAnalysis::where('text_id', $metric->text_id)
                    ->where('job_id', $metric->job_id)
                    ->first();

                if (!$textAnalysis) {
                    $this->error("Text analysis not found for metric ID: {$metric->id}");
                    $errors++;
                    continue;
                }

                // Get expert and model annotations
                $expertAnnotations = $textAnalysis->expert_annotations;
                $modelAnnotations = $textAnalysis->getModelAnnotations($metric->model_name);

                if (empty($expertAnnotations) || empty($modelAnnotations)) {
                    $this->warn("Missing annotations for metric ID: {$metric->id}");
                    continue;
                }

                // Extract labels using the same method as MetricsService
                $expertLabels = $this->extractLabelsFromAnnotations($expertAnnotations);
                $modelLabels = $this->extractLabelsFromAnnotations($modelAnnotations['annotations'] ?? []);

                // Calculate new position accuracy using the updated formula
                $newPositionAccuracy = $this->calculatePositionAccuracy($expertLabels, $modelLabels);

                // Update the metric
                $oldValue = $metric->position_accuracy;
                $metric->update(['position_accuracy' => $newPositionAccuracy]);

                if ($oldValue != $newPositionAccuracy) {
                    $updated++;
                }

            } catch (\Exception $e) {
                $this->error("Error processing metric ID {$metric->id}: " . $e->getMessage());
                $errors++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Recalculation completed!");
        $this->info("Total metrics processed: {$metrics->count()}");
        $this->info("Values updated: {$updated}");
        
        if ($errors > 0) {
            $this->warn("Errors encountered: {$errors}");
        }

        return self::SUCCESS;
    }

    /**
     * Extract labels from annotations (copied from MetricsService).
     */
    private function extractLabelsFromAnnotations(array $annotations): array
    {
        $labels = [];

        foreach ($annotations as $annotation) {
            if (isset($annotation['result'])) {
                foreach ($annotation['result'] as $result) {
                    if (isset($result['value']) && $result['type'] === 'labels') {
                        $value = $result['value'];
                        
                        if (empty($value['text']) || empty($value['labels']) || 
                            ($value['start'] === $value['end'])) {
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
                $value = $annotation['value'];
                
                if (empty($value['text']) || empty($value['labels']) || 
                    ($value['start'] === $value['end'])) {
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
     * Calculate position accuracy using new formula (copied from MetricsService).
     */
    private function calculatePositionAccuracy(array $expertLabels, array $modelLabels): float
    {
        if (empty($expertLabels) && empty($modelLabels)) {
            return 1.0;
        }

        if (empty($expertLabels) || empty($modelLabels)) {
            return 0.0;
        }

        $expertTotalLength = 0;
        foreach ($expertLabels as $label) {
            $expertTotalLength += ($label['end'] - $label['start']);
        }

        $modelTotalLength = 0;
        foreach ($modelLabels as $label) {
            $modelTotalLength += ($label['end'] - $label['start']);
        }

        $intersectionLength = $this->calculateIntersectionLength($expertLabels, $modelLabels);

        $minLength = min($expertTotalLength, $modelTotalLength);
        
        if ($minLength === 0) {
            return 0.0;
        }

        $agreement = $intersectionLength / $minLength;

        return round(min(1.0, $agreement), 4);
    }

    /**
     * Calculate intersection length (copied from MetricsService).
     */
    private function calculateIntersectionLength(array $expertLabels, array $modelLabels): int
    {
        $intersectionLength = 0;

        foreach ($expertLabels as $expertLabel) {
            foreach ($modelLabels as $modelLabel) {
                $overlapStart = max($expertLabel['start'], $modelLabel['start']);
                $overlapEnd = min($expertLabel['end'], $modelLabel['end']);

                if ($overlapStart < $overlapEnd) {
                    $intersectionLength += ($overlapEnd - $overlapStart);
                }
            }
        }

        return $intersectionLength;
    }
}
