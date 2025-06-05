# Multi-Model Architecture

## Overview

The system has been restructured to support **multiple models per provider** (e.g., multiple Claude models, multiple GPT models, etc.). This document describes the new architecture and migration from the legacy system.

## Architecture Changes

### Before (Legacy Architecture)
- **One model per provider**: Could only store one Claude, one GPT, and one Gemini result per text
- **Provider-based columns**: `claude_annotations`, `gpt_annotations`, `gemini_annotations`
- **Overwriting issue**: Multiple models from the same provider would overwrite each other

### After (New Architecture)
- **Multiple models per provider**: Can store unlimited models from any provider
- **Model-specific storage**: Each model gets its own record in `model_results` table
- **Backward compatibility**: Legacy columns still supported for existing data

## Database Schema

### New Table: `model_results`

```sql
CREATE TABLE model_results (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    job_id VARCHAR(36) NOT NULL,
    text_id VARCHAR(255) NOT NULL,
    model_key VARCHAR(255) NOT NULL,           -- e.g., 'claude-opus-4', 'claude-sonnet-4'
    provider VARCHAR(255) NOT NULL,            -- 'anthropic', 'openai', 'google'
    model_name VARCHAR(255) NULL,              -- Requested model name
    actual_model_name VARCHAR(255) NULL,       -- Actual model name from API
    annotations LONGTEXT NULL,                 -- JSON annotations
    error_message TEXT NULL,                   -- Error if failed
    execution_time_ms INT NULL,                -- Processing time
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY unique_result (job_id, text_id, model_key),
    INDEX idx_job_text (job_id, text_id),
    INDEX idx_job_model (job_id, model_key),
    INDEX idx_provider_status (provider, status),
    
    FOREIGN KEY (job_id) REFERENCES analysis_jobs(job_id) ON DELETE CASCADE
);
```

### Enhanced Table: `analysis_jobs`

Added field:
- `requested_models JSON NULL` - Array of model keys requested for this analysis

## Model Changes

### ModelResult Model

New model representing individual model results:

```php
class ModelResult extends Model
{
    protected $fillable = [
        'job_id', 'text_id', 'model_key', 'provider', 
        'model_name', 'actual_model_name', 'annotations', 
        'error_message', 'execution_time_ms', 'status'
    ];

    protected $casts = [
        'annotations' => 'array',
        'execution_time_ms' => 'integer'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Helper methods
    public function isSuccessful(): bool
    public function isFailed(): bool  
    public function detectedPropaganda(): bool
    public function getDetectedTechniques(): array
}
```

### Updated TextAnalysis Model

#### New Methods

```php
// Store results in new architecture
public function storeModelResult(
    string $modelKey, 
    array $annotations, 
    ?string $actualModelName = null, 
    ?int $executionTimeMs = null, 
    ?string $errorMessage = null
): ModelResult

// Get model results relationship
public function modelResults(): HasMany
```

#### Updated Methods (Backward Compatible)

```php
// Now uses new architecture when available, falls back to legacy
public function getAllAttemptedModels(): array
public function getAllModelAnnotations(): array

// Enhanced to use both new and legacy storage
public function setModelAnnotations(string $modelName, array $annotations, ...): void
```

### Updated AnalysisJob Model

#### New Relationships

```php
public function modelResults(): HasMany
```

#### New Field

```php
protected $fillable = [..., 'requested_models'];
protected $casts = ['requested_models' => 'array'];
```

## Usage Examples

### Storing Model Results

```php
// New method (preferred)
$textAnalysis->storeModelResult(
    'claude-opus-4',
    $annotations,
    'claude-3-opus-20240229',
    15000,  // 15 seconds
    null    // no error
);

// Legacy method (backward compatible)
$textAnalysis->setModelAnnotations('claude-opus-4', $annotations, 'claude-3-opus-20240229');
```

### Retrieving Model Results

```php
// Get all attempted models (new + legacy)
$models = $textAnalysis->getAllAttemptedModels();
// Returns: ['claude-opus-4' => [...], 'claude-sonnet-4' => [...]]

// Get successful annotations only
$annotations = $textAnalysis->getAllModelAnnotations();
// Returns: ['claude-opus-4' => [...], 'claude-sonnet-4' => [...]]

// Access new model results directly
$modelResults = $textAnalysis->modelResults;
foreach ($modelResults as $result) {
    echo $result->model_key . ': ' . $result->status;
}
```

### Querying Model Results

```php
// Find all results for a specific model across jobs
$claudeOpusResults = ModelResult::where('model_key', 'claude-opus-4')
    ->where('status', 'completed')
    ->get();

// Get model results for specific job
$jobResults = ModelResult::where('job_id', $jobId)
    ->with(['textAnalysis', 'analysisJob'])
    ->get();

// Count successful models per job
$successfulModels = ModelResult::where('job_id', $jobId)
    ->where('status', 'completed')
    ->whereNotNull('annotations')
    ->distinct('model_key')
    ->count('model_key');
```

## Migration Strategy

### Data Migration (Automatic)

The system automatically detects which architecture to use:

1. **New analyses**: Use `model_results` table for storage
2. **Legacy analyses**: Continue using existing columns, gradual migration
3. **Hybrid support**: Methods check new table first, fall back to legacy

### No Data Loss

- Existing legacy data remains accessible
- Views and APIs work with both architectures
- Gradual migration as new analyses are created

## Benefits

### Multi-Model Support
- ✅ Multiple Claude models (Opus, Sonnet, Haiku)
- ✅ Multiple GPT models (GPT-4, GPT-4o, GPT-4-turbo)
- ✅ Multiple Gemini models (Pro, Flash)
- ✅ Future model additions without schema changes

### Enhanced Analysis
- ✅ Compare different models from same provider
- ✅ Model-specific performance metrics
- ✅ Detailed execution time tracking
- ✅ Individual error handling per model

### Better UI/UX
- ✅ "Visi modeliai" dropdown shows all models
- ✅ Correct model counts in analysis lists
- ✅ All model responses visible in detail view
- ✅ Accurate progress reporting

## Performance Considerations

### Database Design
- Efficient indexing for common queries
- Partitioning by job_id for large datasets
- Cascade deletes for cleanup

### Memory Usage
- Lazy loading of model results
- Chunked processing for large analyses
- Optional pagination for model results

## Testing

### Integration Tests
- Multi-model analysis workflows
- Backward compatibility verification
- Error handling scenarios

### Performance Tests
- Large-scale multi-model processing
- Database query performance
- Memory usage monitoring

## Future Enhancements

### Planned Features
- Model result caching
- Async model processing
- Model result aggregation
- Advanced analytics per model

### Schema Evolution
- Additional metadata fields
- Model version tracking
- Processing pipeline metadata