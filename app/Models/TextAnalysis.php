<?php

namespace App\Models;

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
        'gemini_annotations',
        'gpt_annotations',
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
     * Ryšys su palyginimo metrikomis.
     */
    public function comparisonMetrics(): HasMany
    {
        return $this->hasMany(ComparisonMetric::class, 'text_id', 'text_id');
    }

    /**
     * Gauti visų modelių anotacijas.
     */
    public function getAllModelAnnotations(): array
    {
        return [
            'claude-4' => $this->claude_annotations,
            'gemini-2.5-pro' => $this->gemini_annotations,
            'gpt-4.1' => $this->gpt_annotations,
        ];
    }

    /**
     * Nustatyti modelio anotacijas.
     */
    public function setModelAnnotations(string $modelName, array $annotations): void
    {
        $field = $this->getAnnotationField($modelName);
        if ($field) {
            $this->$field = $annotations;
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
        return match ($modelName) {
            'claude-4' => 'claude_annotations',
            'gemini-2.5-pro' => 'gemini_annotations',
            'gpt-4.1' => 'gpt_annotations',
            default => null,
        };
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