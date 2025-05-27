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
        // Add actual model name to comparison_metrics table
        Schema::table('comparison_metrics', function (Blueprint $table) {
            $table->string('actual_model_name')->nullable()->after('model_name')
                  ->comment('The actual model name (e.g., claude-sonnet-4-20250514)');
        });

        // Add actual model names to text_analysis table
        Schema::table('text_analysis', function (Blueprint $table) {
            $table->string('claude_actual_model')->nullable()->after('claude_annotations')
                  ->comment('Actual Claude model used (e.g., claude-sonnet-4-20250514)');
            $table->string('gemini_actual_model')->nullable()->after('gemini_annotations')
                  ->comment('Actual Gemini model used (e.g., gemini-2.5-pro-preview-05-06)');
            $table->string('gpt_actual_model')->nullable()->after('gpt_annotations')
                  ->comment('Actual GPT model used (e.g., gpt-4o)');
        });

        // Add actual model name to experiment_results table
        Schema::table('experiment_results', function (Blueprint $table) {
            $table->string('actual_model_name')->nullable()->after('llm_model')
                  ->comment('The actual model name used in the experiment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comparison_metrics', function (Blueprint $table) {
            $table->dropColumn('actual_model_name');
        });

        Schema::table('text_analysis', function (Blueprint $table) {
            $table->dropColumn(['claude_actual_model', 'gemini_actual_model', 'gpt_actual_model']);
        });

        Schema::table('experiment_results', function (Blueprint $table) {
            $table->dropColumn('actual_model_name');
        });
    }
};
