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
        Schema::create('experiment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->onDelete('cascade');
            $table->uuid('analysis_job_id');
            $table->foreign('analysis_job_id')->references('job_id')->on('analysis_jobs')->onDelete('cascade');
            $table->string('llm_model');
            $table->json('metrics');
            $table->json('raw_results');
            $table->decimal('execution_time', 8, 3)->nullable();
            $table->timestamps();
            
            $table->index(['experiment_id', 'llm_model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiment_results');
    }
};
