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
        'claude_error',
        'claude_model_name',
        'gemini_annotations',
        'gemini_actual_model',
        'gemini_execution_time_ms',
        'gemini_error',
        'gemini_model_name',
        'gpt_annotations',
        'gpt_actual_model',
        'gpt_execution_time_ms',
        'gpt_error',
        'gpt_model_name',
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
     * Gauti palyginimo metrikos šiam teksto analizės įrašui.
     */
    public function getComparisonMetricsAttribute()
    {
        return ComparisonMetric::where('job_id', $this->job_id)
                              ->where('text_id', $this->text_id)
                              ->get();
    }
    
    /**
     * Ryšys su palyginimo metrikomis - legacy method (for compatibility).
     */
    public function comparisonMetrics()
    {
        return $this->getComparisonMetricsAttribute();
    }

    /**
     * Gauti visų modelių anotacijas.
     */
    public function getAllModelAnnotations(): array
    {
        $annotations = [];
        
        // Return annotations with config keys, not actual model names
        if (!empty($this->claude_annotations)) {
            // Determine which Claude model was used based on the actual model name
            $configKey = 'claude-opus-4'; // default
            if ($this->claude_actual_model) {
                if (str_contains($this->claude_actual_model, 'sonnet')) {
                    $configKey = 'claude-sonnet-4';
                } elseif (str_contains($this->claude_actual_model, 'opus')) {
                    $configKey = 'claude-opus-4';
                }
            }
            $annotations[$configKey] = $this->claude_annotations;
        }
        
        if (!empty($this->gpt_annotations)) {
            // Determine which GPT model was used based on the actual model name
            $configKey = 'gpt-4.1'; // default
            if ($this->gpt_actual_model) {
                if (str_contains($this->gpt_actual_model, 'gpt-4o')) {
                    $configKey = 'gpt-4o-latest';
                } elseif (str_contains($this->gpt_actual_model, 'gpt-4.1')) {
                    $configKey = 'gpt-4.1';
                }
            }
            $annotations[$configKey] = $this->gpt_annotations;
        }
        
        if (!empty($this->gemini_annotations)) {
            // Determine which Gemini model was used based on the actual model name
            $configKey = 'gemini-2.5-pro'; // default
            if ($this->gemini_actual_model) {
                if (str_contains($this->gemini_actual_model, 'flash')) {
                    $configKey = 'gemini-2.5-flash';
                } elseif (str_contains($this->gemini_actual_model, 'pro')) {
                    $configKey = 'gemini-2.5-pro';
                }
            }
            $annotations[$configKey] = $this->gemini_annotations;
        }
        
        return $annotations;
    }

    /**
     * Gauti visų bandytų modelių sąrašą (ir sėkmingų, ir nesėkmingų).
     */
    public function getAllAttemptedModels(): array
    {
        $models = [];
        
        // Check each provider's fields for annotations and errors
        
        // Claude models
        if (!empty($this->claude_annotations) || !empty($this->claude_error) || !empty($this->claude_model_name)) {
            $configKey = 'claude-opus-4'; // default
            if ($this->claude_actual_model) {
                if (str_contains($this->claude_actual_model, 'sonnet')) {
                    $configKey = 'claude-sonnet-4';
                } elseif (str_contains($this->claude_actual_model, 'opus')) {
                    $configKey = 'claude-opus-4';
                }
            } elseif ($this->claude_model_name) {
                if (str_contains($this->claude_model_name, 'sonnet')) {
                    $configKey = 'claude-sonnet-4';
                } elseif (str_contains($this->claude_model_name, 'opus')) {
                    $configKey = 'claude-opus-4';
                }
            }
            
            $models[$configKey] = [
                'status' => !empty($this->claude_annotations) && !$this->claude_error ? 'success' : 'failed',
                'annotations' => $this->claude_annotations,
                'error' => $this->claude_error,
                'actual_model' => $this->claude_actual_model ?: $this->claude_model_name ?: $configKey
            ];
        }
        
        // GPT models
        if (!empty($this->gpt_annotations) || !empty($this->gpt_error) || !empty($this->gpt_model_name)) {
            $configKey = 'gpt-4.1'; // default
            if ($this->gpt_actual_model) {
                if (str_contains($this->gpt_actual_model, 'gpt-4o')) {
                    $configKey = 'gpt-4o-latest';
                } elseif (str_contains($this->gpt_actual_model, 'gpt-4.1')) {
                    $configKey = 'gpt-4.1';
                }
            } elseif ($this->gpt_model_name) {
                if (str_contains($this->gpt_model_name, 'gpt-4o')) {
                    $configKey = 'gpt-4o-latest';
                } elseif (str_contains($this->gpt_model_name, 'gpt-4.1')) {
                    $configKey = 'gpt-4.1';
                }
            }
            
            $models[$configKey] = [
                'status' => !empty($this->gpt_annotations) && !$this->gpt_error ? 'success' : 'failed',
                'annotations' => $this->gpt_annotations,
                'error' => $this->gpt_error,
                'actual_model' => $this->gpt_actual_model ?: $this->gpt_model_name ?: $configKey
            ];
        }
        
        // Gemini models
        if (!empty($this->gemini_annotations) || !empty($this->gemini_error) || !empty($this->gemini_model_name)) {
            $configKey = 'gemini-2.5-pro'; // default
            if ($this->gemini_actual_model) {
                if (str_contains($this->gemini_actual_model, 'flash')) {
                    $configKey = 'gemini-2.5-flash';
                } elseif (str_contains($this->gemini_actual_model, 'pro')) {
                    $configKey = 'gemini-2.5-pro';
                }
            } elseif ($this->gemini_model_name) {
                if (str_contains($this->gemini_model_name, 'flash')) {
                    $configKey = 'gemini-2.5-flash';
                } elseif (str_contains($this->gemini_model_name, 'pro')) {
                    $configKey = 'gemini-2.5-pro';
                }
            }
            
            $models[$configKey] = [
                'status' => !empty($this->gemini_annotations) && !$this->gemini_error ? 'success' : 'failed',
                'annotations' => $this->gemini_annotations,
                'error' => $this->gemini_error,
                'actual_model' => $this->gemini_actual_model ?: $this->gemini_model_name ?: $configKey
            ];
        }
        
        // Add models from comparison metrics that might not be in the annotation fields
        foreach ($this->comparisonMetrics as $metric) {
            if (!isset($models[$metric->model_name])) {
                $models[$metric->model_name] = [
                    'status' => 'success', // If it has metrics, it was processed successfully
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