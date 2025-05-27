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
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->integer('total_execution_time_seconds')->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('total_execution_time_seconds');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        Schema::table('text_analysis', function (Blueprint $table) {
            $table->integer('claude_execution_time_ms')->nullable()->after('claude_actual_model');
            $table->integer('gemini_execution_time_ms')->nullable()->after('gemini_actual_model');
            $table->integer('gpt_execution_time_ms')->nullable()->after('gpt_actual_model');
        });

        Schema::table('comparison_metrics', function (Blueprint $table) {
            $table->integer('analysis_execution_time_ms')->nullable()->after('position_accuracy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->dropColumn(['total_execution_time_seconds', 'started_at', 'completed_at']);
        });

        Schema::table('text_analysis', function (Blueprint $table) {
            $table->dropColumn(['claude_execution_time_ms', 'gemini_execution_time_ms', 'gpt_execution_time_ms']);
        });

        Schema::table('comparison_metrics', function (Blueprint $table) {
            $table->dropColumn('analysis_execution_time_ms');
        });
    }
};