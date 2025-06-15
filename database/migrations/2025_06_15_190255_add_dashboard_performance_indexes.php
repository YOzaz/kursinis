<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for dashboard performance optimization
        
        // Index for model_results table - optimize dashboard queries
        Schema::table('model_results', function (Blueprint $table) {
            // Index for grouping by model_key with status filter
            $table->index(['model_key', 'status', 'execution_time_ms'], 'idx_model_key_status_execution');
            
            // Index for JSON queries on annotations
            $table->index(['status', 'job_id'], 'idx_status_annotations');
        });
        
        // Index for comparison_metrics table - optimize aggregation queries
        Schema::table('comparison_metrics', function (Blueprint $table) {
            // Composite index for model performance queries
            $table->index(['model_name', 'f1_score', 'precision', 'recall'], 'idx_model_metrics');
            
            // Index for execution time queries
            $table->index(['model_name', 'analysis_execution_time_ms'], 'idx_execution_time');
        });
        
        // Index for analysis_jobs table - optimize time series queries
        Schema::table('analysis_jobs', function (Blueprint $table) {
            // Add index for recent jobs query
            $table->index('created_at', 'idx_created_at_desc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_results', function (Blueprint $table) {
            $table->dropIndex('idx_model_key_status_execution');
            $table->dropIndex('idx_status_annotations');
        });
        
        Schema::table('comparison_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_model_metrics');
            $table->dropIndex('idx_execution_time');
        });
        
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_created_at_desc');
        });
    }
};
