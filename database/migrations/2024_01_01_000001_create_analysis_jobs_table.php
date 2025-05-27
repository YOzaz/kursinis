<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sukurti analizės darbų lentelę.
     * 
     * Ši lentelė saugo informaciją apie analizės darbus.
     */
    public function up(): void
    {
        Schema::create('analysis_jobs', function (Blueprint $table) {
            $table->uuid('job_id')->primary();
            $table->timestamp('created_at');
            $table->timestamp('updated_at')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed']);
            $table->integer('total_texts');
            $table->integer('processed_texts')->default(0);
            $table->text('error_message')->nullable();
            
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Atšaukti migracijos pakeitimus.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_jobs');
    }
};