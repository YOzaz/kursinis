<?php

namespace App\Console\Commands;

use App\Models\TextAnalysis;
use App\Models\ModelResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fix incorrect annotation positions in the database.
 * 
 * This command analyzes propaganda annotations and fixes any incorrect
 * start/end positions by recalculating them based on the annotation text
 * and the original content.
 */
class FixAnnotationPositionsCommand extends Command
{
    protected $signature = 'annotations:fix-positions 
                           {--dry-run : Show what would be fixed without making changes}
                           {--model= : Fix only specific model (e.g., claude-opus-4)}
                           {--text-id= : Fix only specific text ID}';

    protected $description = 'Fix incorrect annotation positions in propaganda analysis data';

    private int $totalTexts = 0;
    private int $fixedTexts = 0;
    private int $totalAnnotations = 0;
    private int $fixedAnnotations = 0;
    private array $errors = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $specificModel = $this->option('model');
        $specificTextId = $this->option('text-id');

        $this->info('🔍 Analyzing annotation positions...');
        
        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
        }

        // Query text analyses
        $query = TextAnalysis::query();
        
        if ($specificTextId) {
            $query->where('text_id', $specificTextId);
        }

        $textAnalyses = $query->get();
        $this->totalTexts = $textAnalyses->count();

        if ($this->totalTexts === 0) {
            $this->warn('No text analyses found to process.');
            return 0;
        }

        $this->info("📊 Found {$this->totalTexts} text analyses to process");

        // Process each text analysis
        foreach ($textAnalyses as $textAnalysis) {
            $this->processTextAnalysis($textAnalysis, $dryRun, $specificModel);
        }

        // Show summary
        $this->displaySummary();

        return 0;
    }

    private function processTextAnalysis(TextAnalysis $textAnalysis, bool $dryRun, ?string $specificModel): void
    {
        $originalContent = $textAnalysis->content;
        $textFixed = false;

        // Process expert annotations
        if (!empty($textAnalysis->expert_annotations)) {
            $fixed = $this->fixAnnotationsInArray(
                $textAnalysis->expert_annotations,
                $originalContent,
                "Expert annotations for text {$textAnalysis->text_id}",
                $dryRun
            );

            if ($fixed && !$dryRun) {
                $textAnalysis->expert_annotations = $textAnalysis->expert_annotations;
                $textFixed = true;
            }
        }

        // Process model results (new table)
        $modelResults = $textAnalysis->modelResults();
        if ($specificModel) {
            $modelResults->where('model_key', $specificModel);
        }

        foreach ($modelResults->get() as $modelResult) {
            if (!empty($modelResult->annotations)) {
                $fixed = $this->fixAnnotationsInArray(
                    $modelResult->annotations,
                    $originalContent,
                    "Model {$modelResult->model_key} for text {$textAnalysis->text_id}",
                    $dryRun
                );

                if ($fixed && !$dryRun) {
                    $modelResult->save();
                    $textFixed = true;
                }
            }
        }

        // Process legacy annotation fields
        $legacyFields = ['claude_annotations', 'gpt_annotations', 'gemini_annotations'];
        
        foreach ($legacyFields as $field) {
            if ($specificModel && !str_starts_with($specificModel, str_replace('_annotations', '', $field))) {
                continue;
            }

            if (!empty($textAnalysis->$field)) {
                $fixed = $this->fixAnnotationsInArray(
                    $textAnalysis->$field,
                    $originalContent,
                    "Legacy {$field} for text {$textAnalysis->text_id}",
                    $dryRun
                );

                if ($fixed && !$dryRun) {
                    $textFixed = true;
                }
            }
        }

        if ($textFixed && !$dryRun) {
            $textAnalysis->save();
            $this->fixedTexts++;
        }
    }

    private function fixAnnotationsInArray(array &$annotations, string $originalContent, string $context, bool $dryRun): bool
    {
        $hasChanges = false;

        // Handle different annotation formats
        $annotationsToProcess = $this->extractAnnotationsFromFormat($annotations);

        foreach ($annotationsToProcess as &$annotation) {
            if (!isset($annotation['value'])) {
                continue;
            }

            $value = &$annotation['value'];
            
            if (!isset($value['start'], $value['end'], $value['text'])) {
                continue;
            }

            $this->totalAnnotations++;
            
            $start = (int) $value['start'];
            $end = (int) $value['end'];
            $annotationText = $value['text'];

            // Calculate what the text should be at these positions
            $contentLength = mb_strlen($originalContent, 'UTF-8');
            
            if ($start < 0 || $end > $contentLength || $start >= $end) {
                $this->logError("Invalid positions in {$context}: start={$start}, end={$end}, content_length={$contentLength}");
                continue;
            }

            $actualText = mb_substr($originalContent, $start, $end - $start, 'UTF-8');

            // Check if the positions are correct
            if (trim($actualText) === trim($annotationText)) {
                // Positions are correct
                continue;
            }

            // Try to find the correct position
            $correctPositions = $this->findTextPosition($originalContent, $annotationText);
            
            if ($correctPositions === null) {
                $this->logError("Could not find text '{$annotationText}' in {$context}");
                continue;
            }

            [$correctStart, $correctEnd] = $correctPositions;

            if ($start !== $correctStart || $end !== $correctEnd) {
                $this->info("🔧 [{$context}] Position mismatch:");
                $this->line("   Text: '{$annotationText}'");
                $this->line("   Wrong: start={$start}, end={$end} ('{$actualText}')");
                $this->line("   Fixed: start={$correctStart}, end={$correctEnd}");

                if (!$dryRun) {
                    $value['start'] = $correctStart;
                    $value['end'] = $correctEnd;
                }

                $this->fixedAnnotations++;
                $hasChanges = true;
            }
        }

        return $hasChanges;
    }

    private function extractAnnotationsFromFormat(array $annotations): array
    {
        // Handle Label Studio format
        if (isset($annotations[0]['result'])) {
            return $annotations[0]['result'];
        }

        // Handle direct annotations format
        if (isset($annotations['annotations'])) {
            return $annotations['annotations'];
        }

        // Handle flat array format
        if (isset($annotations[0]['value'])) {
            return $annotations;
        }

        return [];
    }

    private function findTextPosition(string $content, string $searchText): ?array
    {
        $searchText = trim($searchText);
        
        if (empty($searchText)) {
            return null;
        }

        // Try exact match first
        $pos = mb_strpos($content, $searchText, 0, 'UTF-8');
        if ($pos !== false) {
            return [$pos, $pos + mb_strlen($searchText, 'UTF-8')];
        }

        // Try case-insensitive match
        $pos = mb_stripos($content, $searchText, 0, 'UTF-8');
        if ($pos !== false) {
            return [$pos, $pos + mb_strlen($searchText, 'UTF-8')];
        }

        // Try with normalized whitespace
        $normalizedSearch = preg_replace('/\s+/', ' ', $searchText);
        $normalizedContent = preg_replace('/\s+/', ' ', $content);
        
        $pos = mb_strpos($normalizedContent, $normalizedSearch, 0, 'UTF-8');
        if ($pos !== false) {
            // Find the actual position in the original content
            $beforeText = mb_substr($normalizedContent, 0, $pos, 'UTF-8');
            $actualPos = $this->findActualPosition($content, $beforeText);
            if ($actualPos !== null) {
                return [$actualPos, $actualPos + mb_strlen($searchText, 'UTF-8')];
            }
        }

        return null;
    }

    private function findActualPosition(string $originalContent, string $beforeText): ?int
    {
        $pos = 0;
        $normalizedPos = 0;
        $originalLength = mb_strlen($originalContent, 'UTF-8');
        $beforeLength = mb_strlen($beforeText, 'UTF-8');

        while ($pos < $originalLength && $normalizedPos < $beforeLength) {
            $char = mb_substr($originalContent, $pos, 1, 'UTF-8');
            $normalizedChar = preg_replace('/\s+/', ' ', $char);
            
            if (!empty(trim($normalizedChar))) {
                $normalizedPos++;
            }
            $pos++;
        }

        return $pos <= $originalLength ? $pos : null;
    }

    private function logError(string $message): void
    {
        $this->errors[] = $message;
        $this->error("❌ {$message}");
    }

    private function displaySummary(): void
    {
        $this->info('');
        $this->info('📈 Summary:');
        $this->info("   Texts processed: {$this->totalTexts}");
        $this->info("   Texts with fixes: {$this->fixedTexts}");
        $this->info("   Total annotations: {$this->totalAnnotations}");
        $this->info("   Annotations fixed: {$this->fixedAnnotations}");

        if (!empty($this->errors)) {
            $this->info("   Errors: " . count($this->errors));
            
            if (count($this->errors) <= 10) {
                $this->warn('Errors encountered:');
                foreach ($this->errors as $error) {
                    $this->line("   • {$error}");
                }
            } else {
                $this->warn('Too many errors to display (' . count($this->errors) . ' total)');
            }
        }

        if ($this->fixedAnnotations > 0) {
            $this->info('');
            $this->info('✅ Position fixing completed successfully!');
        } else {
            $this->info('');
            $this->info('✨ No position issues found - all annotations are correctly positioned!');
        }
    }
}