<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sukurti tekstų analizės lentelę.
     * 
     * Ši lentelė saugo ekspertų ir LLM anotacijas kiekvienam tekstui.
     */
    public function up(): void
    {
        Schema::create('text_analysis', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_id');
            $table->string('text_id');
            $table->longText('content');
            $table->json('expert_annotations');
            $table->json('claude_annotations')->nullable();
            $table->json('gemini_annotations')->nullable();
            $table->json('gpt_annotations')->nullable();
            $table->timestamps();
            
            $table->foreign('job_id')->references('job_id')->on('analysis_jobs')->onDelete('cascade');
            $table->index(['job_id', 'text_id']);
        });
    }

    /**
     * Atšaukti migracijos pakeitimus.
     */
    public function down(): void
    {
        Schema::dropIfExists('text_analysis');
    }
};