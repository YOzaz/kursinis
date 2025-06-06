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
        // Drop the unique constraint first, then recreate the column properly
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });
        
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->string('uuid')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->string('uuid')->nullable(false)->default(null)->change();
        });
    }
};
