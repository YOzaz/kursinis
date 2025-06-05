<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model result represents the output from a single AI model for a specific text.
 * This allows multiple models (including multiple models from the same provider) 
 * to be stored for each text analysis.
 */
class ModelResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'text_id',
        'model_key',
        'provider',
        'model_name',
        'actual_model_name',
        'annotations',
        'error_message',
        'execution_time_ms',
        'status',
    ];

    protected $casts = [
        'annotations' => 'array',
        'execution_time_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Get the analysis job this result belongs to.
     */
    public function analysisJob(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'job_id', 'job_id');
    }

    /**
     * Get the text analysis this result belongs to.
     */
    public function textAnalysis(): BelongsTo
    {
        return $this->belongsTo(TextAnalysis::class, 'text_id', 'text_id')
                    ->where('job_id', $this->job_id);
    }

    /**
     * Check if this result was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_COMPLETED && 
               !empty($this->annotations) && 
               empty($this->error_message);
    }

    /**
     * Check if this result failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED || 
               !empty($this->error_message);
    }

    /**
     * Get the provider from model key if not explicitly set.
     */
    public function getProviderAttribute($value): string
    {
        if (!empty($value)) {
            return $value;
        }

        // Infer provider from model key
        if (str_starts_with($this->model_key, 'claude')) {
            return 'anthropic';
        } elseif (str_starts_with($this->model_key, 'gpt')) {
            return 'openai';
        } elseif (str_starts_with($this->model_key, 'gemini')) {
            return 'google';
        }

        return 'unknown';
    }

    /**
     * Check if this result detected propaganda.
     */
    public function detectedPropaganda(): bool
    {
        if (empty($this->annotations)) {
            return false;
        }

        return isset($this->annotations['primaryChoice']['choices']) && 
               in_array('yes', $this->annotations['primaryChoice']['choices']);
    }

    /**
     * Get detected techniques from annotations.
     */
    public function getDetectedTechniques(): array
    {
        if (empty($this->annotations)) {
            return [];
        }

        $techniques = [];

        // Check desinformationTechnique field first (primary source)
        if (isset($this->annotations['desinformationTechnique']['choices'])) {
            $techniques = array_merge($techniques, $this->annotations['desinformationTechnique']['choices']);
        }

        // Also check detailed annotations
        if (isset($this->annotations['annotations']) && is_array($this->annotations['annotations'])) {
            foreach ($this->annotations['annotations'] as $annotation) {
                if (isset($annotation['value']['labels'])) {
                    $techniques = array_merge($techniques, $annotation['value']['labels']);
                }
            }
        }

        return array_unique($techniques);
    }
}