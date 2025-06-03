<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tekstų analizės modelis.
 * 
 * Ši klasė representuoja tekstų analizės duomenis su ekspertų ir LLM anotacijomis.
 */
class TextAnalysis extends Model
{
    use HasFactory;
    
    /**
     * Lentelės pavadinimas.
     */
    protected $table = 'text_analysis';

    /**
     * Masyvai leidžiami užpildymui.
     */
    protected $fillable = [
        'job_id',
        'text_id',
        'content',
        'expert_annotations',
        'claude_annotations',
        'claude_actual_model',
        'claude_execution_time_ms',
        'gemini_annotations',
        'gemini_actual_model',
        'gemini_execution_time_ms',
        'gpt_annotations',
        'gpt_actual_model',
        'gpt_execution_time_ms',
    ];

    /**
     * Datos tipų konvertavimas.
     */
    protected $casts = [
        'expert_annotations' => 'array',
        'claude_annotations' => 'array',
        'gemini_annotations' => 'array',
        'gpt_annotations' => 'array',
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
     * Ryšys su palyginimo metrikomis (filtruojama pagal job_id ir text_id).
     */
    public function comparisonMetrics(): HasMany
    {
        return $this->hasMany(ComparisonMetric::class, 'text_id', 'text_id')
                    ->where('job_id', $this->job_id);
    }

    /**
     * Gauti visų modelių anotacijas.
     */
    public function getAllModelAnnotations(): array
    {
        $annotations = [];
        
        // Check which providers have successful annotations and get actual model names
        if (!empty($this->claude_annotations) && !isset($this->claude_annotations['error'])) {
            $actualModel = $this->claude_actual_model ?? 'claude-opus-4';
            $annotations[$actualModel] = $this->claude_annotations;
        }
        
        if (!empty($this->gpt_annotations) && !isset($this->gpt_annotations['error'])) {
            $actualModel = $this->gpt_actual_model ?? 'gpt-4.1';
            $annotations[$actualModel] = $this->gpt_annotations;
        }
        
        if (!empty($this->gemini_annotations) && !isset($this->gemini_annotations['error'])) {
            $actualModel = $this->gemini_actual_model ?? 'gemini-2.5-pro';
            $annotations[$actualModel] = $this->gemini_annotations;
        }
        
        return $annotations;
    }

    /**
     * Gauti visų bandytų modelių sąrašą (ir sėkmingų, ir nesėkmingų).
     */
    public function getAllAttemptedModels(): array
    {
        $models = [];
        
        // Get successful models
        $successfulModels = $this->getAllModelAnnotations();
        foreach ($successfulModels as $modelName => $annotations) {
            $models[$modelName] = [
                'status' => 'success',
                'annotations' => $annotations,
                'actual_model' => $modelName
            ];
        }
        
        // Get failed models from stored annotations with errors
        if (!empty($this->claude_annotations) && isset($this->claude_annotations['error'])) {
            $modelName = $this->claude_annotations['model'] ?? 'claude-unknown';
            $models[$modelName] = [
                'status' => 'failed',
                'annotations' => null,
                'error' => $this->claude_annotations['error'],
                'actual_model' => $modelName
            ];
        }
        
        if (!empty($this->gpt_annotations) && isset($this->gpt_annotations['error'])) {
            $modelName = $this->gpt_annotations['model'] ?? 'gpt-unknown';
            $models[$modelName] = [
                'status' => 'failed',
                'annotations' => null,
                'error' => $this->gpt_annotations['error'],
                'actual_model' => $modelName
            ];
        }
        
        if (!empty($this->gemini_annotations) && isset($this->gemini_annotations['error'])) {
            $modelName = $this->gemini_annotations['model'] ?? 'gemini-unknown';
            $models[$modelName] = [
                'status' => 'failed',
                'annotations' => null,
                'error' => $this->gemini_annotations['error'],
                'actual_model' => $modelName
            ];
        }
        
        // Add models from comparison metrics that aren't already listed
        foreach ($this->comparisonMetrics as $metric) {
            if (!isset($models[$metric->model_name])) {
                $models[$metric->model_name] = [
                    'status' => 'failed',
                    'annotations' => null,
                    'actual_model' => $metric->actual_model_name ?? $metric->model_name,
                    'has_metrics' => true
                ];
            }
        }
        
        return $models;
    }

    /**
     * Nustatyti modelio anotacijas.
     */
    public function setModelAnnotations(string $modelName, array $annotations, ?string $actualModelName = null, ?int $executionTimeMs = null): void
    {
        $field = $this->getAnnotationField($modelName);
        if ($field) {
            $this->$field = $annotations;
            
            // Store actual model name if provided
            if ($actualModelName) {
                $actualField = $this->getActualModelField($modelName);
                if ($actualField) {
                    $this->$actualField = $actualModelName;
                }
            }
            
            // Store execution time if provided
            if ($executionTimeMs !== null) {
                $timeField = $this->getExecutionTimeField($modelName);
                if ($timeField) {
                    $this->$timeField = $executionTimeMs;
                }
            }
        }
    }

    /**
     * Gauti modelio anotacijas.
     */
    public function getModelAnnotations(string $modelName): ?array
    {
        $field = $this->getAnnotationField($modelName);
        return $field ? $this->$field : null;
    }

    /**
     * Gauti anotacijų lauko pavadinimą pagal modelio pavadinimą.
     */
    private function getAnnotationField(string $modelName): ?string
    {
        if (str_starts_with($modelName, 'claude')) {
            return 'claude_annotations';
        } elseif (str_starts_with($modelName, 'gemini')) {
            return 'gemini_annotations';
        } elseif (str_starts_with($modelName, 'gpt')) {
            return 'gpt_annotations';
        }
        
        return null;
    }

    /**
     * Gauti tikrojo modelio lauko pavadinimą pagal modelio pavadinimą.
     */
    private function getActualModelField(string $modelName): ?string
    {
        if (str_starts_with($modelName, 'claude')) {
            return 'claude_actual_model';
        } elseif (str_starts_with($modelName, 'gemini')) {
            return 'gemini_actual_model';
        } elseif (str_starts_with($modelName, 'gpt')) {
            return 'gpt_actual_model';
        }
        
        return null;
    }

    /**
     * Gauti vykdymo laiko lauko pavadinimą pagal modelio pavadinimą.
     */
    private function getExecutionTimeField(string $modelName): ?string
    {
        if (str_starts_with($modelName, 'claude')) {
            return 'claude_execution_time_ms';
        } elseif (str_starts_with($modelName, 'gemini')) {
            return 'gemini_execution_time_ms';
        } elseif (str_starts_with($modelName, 'gpt')) {
            return 'gpt_execution_time_ms';
        }
        
        return null;
    }

    /**
     * Gauti modelio vykdymo laiką milisekundėmis.
     */
    public function getModelExecutionTime(string $modelName): ?int
    {
        $field = $this->getExecutionTimeField($modelName);
        return $field ? $this->$field : null;
    }

    /**
     * Patikrinti ar visi pasirinkti modeliai baigė analizę.
     */
    public function isAnalysisComplete(array $selectedModels): bool
    {
        foreach ($selectedModels as $model) {
            if (empty($this->getModelAnnotations($model))) {
                return false;
            }
        }
        return true;
    }
}