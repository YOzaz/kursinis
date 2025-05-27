<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sukurti palyginimo metrikų lentelę.
     * 
     * Ši lentelė saugo detalizuotas metrikas kiekvienam tekstui ir modeliui.
     */
    public function up(): void
    {
        Schema::create('comparison_metrics', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_id');
            $table->string('text_id');
            $table->enum('model_name', ['claude-4', 'gemini-2.5-pro', 'gpt-4.1']);
            $table->integer('true_positives')->default(0);
            $table->integer('false_positives')->default(0);
            $table->integer('false_negatives')->default(0);
            $table->decimal('position_accuracy', 5, 4)->default(0.0000);
            $table->decimal('precision', 5, 4)->default(0.0000);
            $table->decimal('recall', 5, 4)->default(0.0000);
            $table->decimal('f1_score', 5, 4)->default(0.0000);
            $table->timestamps();
            
            $table->foreign('job_id')->references('job_id')->on('analysis_jobs')->onDelete('cascade');
            $table->index(['job_id', 'model_name']);
            $table->index(['text_id', 'model_name']);
        });
    }

    /**
     * Atšaukti migracijos pakeitimus.
     */
    public function down(): void
    {
        Schema::dropIfExists('comparison_metrics');
    }
};