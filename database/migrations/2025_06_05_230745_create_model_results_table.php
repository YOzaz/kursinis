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
        Schema::create('model_results', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 36)->index();
            $table->string('text_id')->index();
            $table->string('model_key'); // e.g., 'claude-opus-4', 'claude-sonnet-4'
            $table->string('provider'); // 'anthropic', 'openai', 'google'
            $table->string('model_name')->nullable(); // Requested model name
            $table->string('actual_model_name')->nullable(); // Actual model name returned by API
            $table->longText('annotations')->nullable(); // JSON annotations
            $table->text('error_message')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['job_id', 'text_id']);
            $table->index(['job_id', 'model_key']);
            $table->index(['provider', 'status']);
            
            // Unique constraint to prevent duplicate results
            $table->unique(['job_id', 'text_id', 'model_key']);
            
            // Foreign key constraints
            $table->foreign('job_id')->references('job_id')->on('analysis_jobs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_results');
    }
};