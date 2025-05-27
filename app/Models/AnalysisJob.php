<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Analizės darbo modelis.
 * 
 * Ši klasė representuoja analizės darbo informaciją duomenų bazėje.
 */
class AnalysisJob extends Model
{
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
    ];

    /**
     * Datos tipų konvertavimas.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'total_texts' => 'integer',
        'processed_texts' => 'integer',
    ];

    /**
     * Galimi darbo statusai.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

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
     * Gauti progreso procentą.
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_texts === 0) {
            return 0.0;
        }
        
        return ($this->processed_texts / $this->total_texts) * 100;
    }
}