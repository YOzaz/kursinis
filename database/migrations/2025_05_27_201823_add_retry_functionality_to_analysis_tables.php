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
        // Add retry and error tracking to analysis_jobs table
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->json('failed_models')->nullable()->after('error_message')
                  ->comment('JSON array of models that failed during analysis');
            $table->integer('retry_count')->default(0)->after('failed_models')
                  ->comment('Number of times this job has been retried');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count')
                  ->comment('Timestamp of last retry attempt');
            $table->json('model_status')->nullable()->after('last_retry_at')
                  ->comment('Status of each model (success/failed/pending)');
        });

        // Add retry tracking to text_analysis table
        Schema::table('text_analysis', function (Blueprint $table) {
            $table->json('analysis_attempts')->nullable()->after('gpt_actual_model')
                  ->comment('Track analysis attempts per model');
            $table->timestamp('last_updated_at')->nullable()->after('analysis_attempts')
                  ->comment('Last time any model was updated');
        });

        // Create model_analysis_logs table for detailed tracking
        Schema::create('model_analysis_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->index();
            $table->string('text_id')->index();
            $table->string('model_name');
            $table->string('actual_model_name')->nullable();
            $table->enum('status', ['pending', 'processing', 'success', 'failed']);
            $table->text('error_message')->nullable();
            $table->integer('attempt_number')->default(1);
            $table->decimal('processing_time', 8, 3)->nullable()->comment('Processing time in seconds');
            $table->json('response_metadata')->nullable()->comment('Additional response info');
            $table->timestamps();

            $table->index(['job_id', 'model_name']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->dropColumn(['failed_models', 'retry_count', 'last_retry_at', 'model_status']);
        });

        Schema::table('text_analysis', function (Blueprint $table) {
            $table->dropColumn(['analysis_attempts', 'last_updated_at']);
        });

        Schema::dropIfExists('model_analysis_logs');
    }
};