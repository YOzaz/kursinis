<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Models\ModelResult;

class CleanAnalysisData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analysis:clean 
                           {--force : Skip confirmation prompt}
                           {--keep-expert : Keep expert annotations, only remove LLM results}
                           {--jobs-only : Only clean queue jobs, keep analysis data}
                           {--older-than= : Only clean data older than X days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean analysis database for fresh start';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Analysis Database Cleaning Tool');
        $this->info('===================================');

        // Check what will be cleaned
        $this->showCurrentStats();

        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to proceed with cleaning?')) {
                $this->info('Cleaning cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info('Starting database cleanup...');

        try {
            DB::beginTransaction();

            if ($this->option('jobs-only')) {
                $this->cleanQueueJobs();
            } else {
                $this->cleanAnalysisData();
                $this->cleanQueueJobs();
            }

            DB::commit();
            
            $this->info('✅ Database cleanup completed successfully!');
            $this->showCurrentStats();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error during cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Show current database statistics.
     */
    private function showCurrentStats()
    {
        $this->info('📊 Current Database Status:');
        
        $analysisJobs = AnalysisJob::count();
        $textAnalyses = TextAnalysis::count();
        $comparisonMetrics = ComparisonMetric::count();
        $modelResults = ModelResult::count();
        $queueJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $this->table(['Table', 'Records'], [
            ['Analysis Jobs', $analysisJobs],
            ['Text Analyses', $textAnalyses],
            ['Comparison Metrics', $comparisonMetrics],
            ['Model Results', $modelResults],
            ['Queue Jobs', $queueJobs],
            ['Failed Jobs', $failedJobs],
        ]);

        // Show disk usage of database
        if (file_exists(database_path('database.sqlite'))) {
            $size = filesize(database_path('database.sqlite'));
            $this->info("💾 Database file size: " . $this->formatBytes($size));
        }
    }

    /**
     * Clean analysis data from database.
     */
    private function cleanAnalysisData()
    {
        $olderThan = $this->option('older-than');
        $keepExpert = $this->option('keep-expert');

        if ($olderThan) {
            $date = now()->subDays((int)$olderThan);
            $this->info("🗓️  Cleaning data older than {$olderThan} days (before {$date->format('Y-m-d H:i:s')})");
            
            if ($keepExpert) {
                $this->info("🔒 Keeping expert annotations, only removing LLM results");
                $this->cleanOldLLMResults($date);
            } else {
                $this->cleanOldAnalysisData($date);
            }
        } else {
            if ($keepExpert) {
                $this->info("🔒 Keeping expert annotations, only removing LLM results");
                $this->cleanAllLLMResults();
            } else {
                $this->info("🧹 Cleaning ALL analysis data");
                $this->cleanAllAnalysisData();
            }
        }
    }

    /**
     * Clean all analysis data.
     */
    private function cleanAllAnalysisData()
    {
        $this->info('Cleaning Model Results...');
        $deleted = ModelResult::query()->delete();
        $this->line("✓ Model Results cleaned ({$deleted} records)");

        $this->info('Cleaning Comparison Metrics...');
        $deleted = ComparisonMetric::query()->delete();
        $this->line("✓ Comparison Metrics cleaned ({$deleted} records)");

        $this->info('Cleaning Text Analyses...');
        $deleted = TextAnalysis::query()->delete();
        $this->line("✓ Text Analyses cleaned ({$deleted} records)");

        $this->info('Cleaning Analysis Jobs...');
        $deleted = AnalysisJob::query()->delete();
        $this->line("✓ Analysis Jobs cleaned ({$deleted} records)");
    }

    /**
     * Clean only LLM results, keep expert annotations.
     */
    private function cleanAllLLMResults()
    {
        $this->info('Cleaning Model Results...');
        $deletedResults = ModelResult::query()->delete();
        $this->line("✓ Model Results cleaned ({$deletedResults} records)");

        $this->info('Cleaning Comparison Metrics...');
        $deletedMetrics = ComparisonMetric::query()->delete();
        $this->line("✓ Comparison Metrics cleaned ({$deletedMetrics} records)");

        $this->info('Resetting LLM annotations in Text Analyses...');
        TextAnalysis::query()->update([
            'claude_annotations' => null,
            'claude_actual_model' => null,
            'claude_execution_time_ms' => null,
            'claude_error' => null,
            'claude_model_name' => null,
            'gemini_annotations' => null,
            'gemini_actual_model' => null,
            'gemini_execution_time_ms' => null,
            'gemini_error' => null,
            'gemini_model_name' => null,
            'gpt_annotations' => null,
            'gpt_actual_model' => null,
            'gpt_execution_time_ms' => null,
            'gpt_error' => null,
            'gpt_model_name' => null,
        ]);
        $this->line("✓ LLM annotations reset, expert annotations preserved");

        $this->info('Resetting Analysis Jobs status...');
        AnalysisJob::query()->update([
            'status' => 'pending',
            'processed_texts' => 0,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'total_execution_time_seconds' => null,
            'failed_models' => null,
            'retry_count' => 0,
            'last_retry_at' => null,
            'model_status' => null,
        ]);
        $this->line("✓ Analysis Jobs reset to pending status");
    }

    /**
     * Clean analysis data older than specified date.
     */
    private function cleanOldAnalysisData($date)
    {
        $deletedJobs = AnalysisJob::where('created_at', '<', $date)->delete();
        $this->line("✓ Deleted {$deletedJobs} old analysis jobs");
        
        // Related data will be cascade deleted due to foreign key constraints
        $this->line("✓ Related text analyses, metrics, and model results automatically cleaned");
    }

    /**
     * Clean LLM results older than specified date.
     */
    private function cleanOldLLMResults($date)
    {
        $deletedResults = ModelResult::whereHas('analysisJob', function($query) use ($date) {
            $query->where('created_at', '<', $date);
        })->delete();
        
        $deletedMetrics = ComparisonMetric::whereHas('analysisJob', function($query) use ($date) {
            $query->where('created_at', '<', $date);
        })->delete();

        $this->line("✓ Deleted {$deletedResults} old model results");
        $this->line("✓ Deleted {$deletedMetrics} old comparison metrics");

        // Reset LLM annotations for old text analyses
        $updated = TextAnalysis::whereHas('analysisJob', function($query) use ($date) {
            $query->where('created_at', '<', $date);
        })->update([
            'claude_annotations' => null,
            'claude_actual_model' => null,
            'claude_execution_time_ms' => null,
            'claude_error' => null,
            'claude_model_name' => null,
            'gemini_annotations' => null,
            'gemini_actual_model' => null,
            'gemini_execution_time_ms' => null,
            'gemini_error' => null,
            'gemini_model_name' => null,
            'gpt_annotations' => null,
            'gpt_actual_model' => null,
            'gpt_execution_time_ms' => null,
            'gpt_error' => null,
            'gpt_model_name' => null,
        ]);
        $this->line("✓ Reset LLM annotations for {$updated} old text analyses");
    }

    /**
     * Clean queue jobs.
     */
    private function cleanQueueJobs()
    {
        $this->info('Cleaning Queue Jobs...');
        
        $deletedJobs = DB::table('jobs')->delete();
        $this->line("✓ Deleted {$deletedJobs} pending queue jobs");

        $deletedFailedJobs = DB::table('failed_jobs')->delete();
        $this->line("✓ Deleted {$deletedFailedJobs} failed queue jobs");
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}