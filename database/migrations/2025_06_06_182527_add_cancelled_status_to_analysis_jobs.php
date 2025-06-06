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
            // Modify the status enum to include 'cancelled'
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_jobs', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->change();
        });
    }
};
