<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperimentResult extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'experiment_id',
        'analysis_job_id',
        'llm_model',
        'metrics',
        'raw_results',
        'execution_time',
    ];

    protected $casts = [
        'metrics' => 'array',
        'raw_results' => 'array',
        'execution_time' => 'decimal:3',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    public function analysisJob(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class);
    }
}
