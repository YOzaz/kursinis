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
        // Drop experiment_id column from analysis_jobs table
        Schema::table('analysis_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('analysis_jobs', 'experiment_id')) {
                $table->dropColumn('experiment_id');
            }
        });

        // Drop experiment-related tables
        Schema::dropIfExists('experiment_results');
        Schema::dropIfExists('experiments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate experiments table
        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('risen_config');
            $table->text('custom_prompt');
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        // Recreate experiment_results table
        Schema::create('experiment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->onDelete('cascade');
            $table->string('job_id');
            $table->text('analysis_summary')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
            
            $table->foreign('job_id')->references('job_id')->on('analysis_jobs')->onDelete('cascade');
        });

        // Add experiment_id column back to analysis_jobs table
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->foreignId('experiment_id')->nullable()->constrained()->onDelete('set null');
        });
    }
};
