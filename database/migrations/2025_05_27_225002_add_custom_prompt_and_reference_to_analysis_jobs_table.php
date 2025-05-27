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
            $table->text('custom_prompt')->nullable()->after('error_message');
            $table->string('reference_analysis_id')->nullable()->after('custom_prompt');
            $table->string('name')->nullable()->after('reference_analysis_id');
            $table->text('description')->nullable()->after('name');
            
            $table->foreign('reference_analysis_id')->references('job_id')->on('analysis_jobs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->dropForeign(['reference_analysis_id']);
            $table->dropColumn(['custom_prompt', 'reference_analysis_id', 'name', 'description']);
        });
    }
};
