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
        Schema::table('comparison_metrics', function (Blueprint $table) {
            // Change model_name from enum to string for flexibility with dynamic models
            $table->string('model_name')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comparison_metrics', function (Blueprint $table) {
            // Revert back to enum (though this might cause data loss)
            $table->enum('model_name', ['claude-4', 'gemini-2.5-pro', 'gpt-4.1'])->change();
        });
    }
};
