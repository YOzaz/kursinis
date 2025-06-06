<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Analizės darbo modelis.
 * 
 * Ši klasė representuoja analizės darbo informaciją duomenų bazėje.
 */
class AnalysisJob extends Model
{
    use HasFactory;
    
    /**
     * Pirminė lentelės kolona.
     */
    protected $primaryKey = 'job_id';

    /**
     * Pirminės kolenos tipas.
     */
    protected $keyType = 'string';

    /**
     * Ar automatiškai generuoti ID.
     */
    public $incrementing = false;

    /**
     * Lentelės pavadinimas.
     */
    protected $table = 'analysis_jobs';

    /**
     * Masyvai leidžiami užpildymui.
     */
    protected $fillable = [
        'job_id',
        'status',
        'total_texts',
        'processed_texts',
        'error_message',
        'custom_prompt',
        'reference_analysis_id',
        'name',
        'description',
        'total_execution_time_seconds',
        'started_at',
        'completed_at',
        'requested_models',
    ];

    /**
     * Datos tipų konvertavimas.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_texts' => 'integer',
        'processed_texts' => 'integer',
        'total_execution_time_seconds' => 'integer',
        'requested_models' => 'array',
    ];

    /**
     * Galimi darbo statusai.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Gauti visas tekstų analizės.
     */
    public function textAnalyses(): HasMany
    {
        return $this->hasMany(TextAnalysis::class, 'job_id', 'job_id');
    }

    /**
     * Gauti visas palyginimo metrikas.
     */
    public function comparisonMetrics(): HasMany
    {
        return $this->hasMany(ComparisonMetric::class, 'job_id', 'job_id');
    }

    /**
     * Get all model results for this analysis job.
     */
    public function modelResults(): HasMany
    {
        return $this->hasMany(ModelResult::class, 'job_id', 'job_id');
    }

    /**
     * Patikrinti ar darbas užbaigtas.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Patikrinti ar darbas nepavyko.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Patikrinti ar darbas buvo atšauktas.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Gauti progreso procentą.
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_texts === 0) {
            return 0.0;
        }
        
        return ($this->processed_texts / $this->total_texts) * 100;
    }

    /**
     * Gauti nuorodos analizę (jei tai pakartojimas).
     */
    public function referenceAnalysis(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'reference_analysis_id', 'job_id');
    }

    /**
     * Gauti pakartojamas analizės.
     */
    public function repeatedAnalyses(): HasMany
    {
        return $this->hasMany(AnalysisJob::class, 'reference_analysis_id', 'job_id');
    }

    /**
     * Patikrinti ar analizė naudoja custom prompt'ą.
     */
    public function usesCustomPrompt(): bool
    {
        return !empty($this->custom_prompt);
    }
}