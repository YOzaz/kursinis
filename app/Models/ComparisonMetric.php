<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Palyginimo metrikų modelis.
 * 
 * Ši klasė representuoja statistines metrikas tarp ekspertų ir LLM anotacijų.
 */
class ComparisonMetric extends Model
{
    /**
     * Lentelės pavadinimas.
     */
    protected $table = 'comparison_metrics';

    /**
     * Masyvai leidžiami užpildymui.
     */
    protected $fillable = [
        'job_id',
        'text_id',
        'model_name',
        'true_positives',
        'false_positives',
        'false_negatives',
        'position_accuracy',
        'precision',
        'recall',
        'f1_score',
    ];

    /**
     * Datos tipų konvertavimas.
     */
    protected $casts = [
        'true_positives' => 'integer',
        'false_positives' => 'integer',
        'false_negatives' => 'integer',
        'position_accuracy' => 'decimal:4',
        'precision' => 'decimal:4',
        'recall' => 'decimal:4',
        'f1_score' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Ryšys su analizės darbu.
     */
    public function analysisJob(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'job_id', 'job_id');
    }

    /**
     * Ryšys su tekstų analize.
     */
    public function textAnalysis(): BelongsTo
    {
        return $this->belongsTo(TextAnalysis::class, 'text_id', 'text_id');
    }

    /**
     * Apskaičiuoti precision metriką.
     */
    public function calculatePrecision(): float
    {
        $totalPredicted = $this->true_positives + $this->false_positives;
        return $totalPredicted > 0 ? $this->true_positives / $totalPredicted : 0.0;
    }

    /**
     * Apskaičiuoti recall metriką.
     */
    public function calculateRecall(): float
    {
        $totalActual = $this->true_positives + $this->false_negatives;
        return $totalActual > 0 ? $this->true_positives / $totalActual : 0.0;
    }

    /**
     * Apskaičiuoti F1 score.
     */
    public function calculateF1Score(): float
    {
        $precision = $this->calculatePrecision();
        $recall = $this->calculateRecall();
        
        return ($precision + $recall) > 0 ? 
            (2 * $precision * $recall) / ($precision + $recall) : 0.0;
    }

    /**
     * Atnaujinti apskaičiuotas metrikas.
     */
    public function updateCalculatedMetrics(): void
    {
        $this->precision = $this->calculatePrecision();
        $this->recall = $this->calculateRecall();
        $this->f1_score = $this->calculateF1Score();
    }
}