<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experiment extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'description',
        'custom_prompt',
        'risen_config',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'risen_config' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(ExperimentResult::class);
    }

    public function analysisJobs(): HasMany
    {
        return $this->hasMany(AnalysisJob::class);
    }
}
