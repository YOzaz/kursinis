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
        Schema::table('text_analysis', function (Blueprint $table) {
            // Add error columns for each AI provider
            $table->text('claude_error')->nullable()->after('claude_execution_time_ms');
            $table->text('gpt_error')->nullable()->after('gpt_execution_time_ms');
            $table->text('gemini_error')->nullable()->after('gemini_execution_time_ms');
            
            // Add model name columns for BatchAnalysisJobV3 compatibility
            $table->string('claude_model_name')->nullable()->after('claude_error');
            $table->string('gpt_model_name')->nullable()->after('gpt_error');
            $table->string('gemini_model_name')->nullable()->after('gemini_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('text_analysis', function (Blueprint $table) {
            $table->dropColumn([
                'claude_error',
                'gpt_error', 
                'gemini_error',
                'claude_model_name',
                'gpt_model_name',
                'gemini_model_name'
            ]);
        });
    }
};
