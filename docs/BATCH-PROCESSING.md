# Batch Processing Optimization

## Overview

The system now supports optimized batch processing for large datasets, significantly reducing API calls and improving performance when analyzing many texts.

## Performance Improvement

### Before (Individual Processing)
- **6000 texts × 3 models = 18,000 API requests**
- Processing time: ~2-3 hours
- Higher API costs
- Higher rate limit risk

### After (Batch Processing)
- **6000 texts ÷ 50 batch size × 3 models = 360 API requests**
- Processing time: ~15-30 minutes
- **98% reduction in API calls**
- Lower API costs
- Reduced rate limit risk

## Model Context Windows & Batch Sizes

### Claude Models
- **Context Window**: 200,000 tokens
- **Batch Size**: 50 texts per request
- **Estimated Throughput**: ~120 texts/minute

### GPT-4.1 Models  
- **Context Window**: 1,000,000 tokens
- **Batch Size**: 100 texts per request
- **Estimated Throughput**: ~200 texts/minute

### Gemini Models
- **Context Window**: 2,000,000 tokens (2.5-pro), 1,000,000 tokens (2.5-flash)
- **Batch Size**: 200 texts (pro), 100 texts (flash) per request
- **Estimated Throughput**: ~300 texts/minute (pro), ~200 texts/minute (flash)

## How It Works

### Automatic Selection
The system automatically chooses the optimal processing method:

```php
// For datasets with 100+ texts
if (count($texts) > 100) {
    BatchAnalysisJobV2::dispatch($jobId, $jsonData, $models); // Optimized
} else {
    BatchAnalysisJob::dispatch($jobId, $jsonData, $models);   // Standard
}
```

### Batch Request Structure
Instead of individual requests:
```
Request 1: Analyze text_1
Request 2: Analyze text_2  
Request 3: Analyze text_3
...
```

The system sends:
```
Request 1: Analyze [text_1, text_2, ..., text_50]
Request 2: Analyze [text_51, text_52, ..., text_100]
...
```

### Response Processing
Each batch request returns a JSON array:
```json
[
  {
    "text_id": "139414",
    "primaryChoice": {"choices": ["yes"]},
    "annotations": [...],
    "desinformationTechnique": {"choices": [...]}
  },
  {
    "text_id": "139415", 
    "primaryChoice": {"choices": ["no"]},
    "annotations": [...],
    "desinformationTechnique": {"choices": [...]}
  }
]
```

## Error Handling & Fallbacks

### Robust Error Recovery
1. **Batch Failure**: If a batch request fails, the system automatically falls back to individual processing for that batch
2. **JSON Parsing**: Improved JSON extraction with multiple parsing strategies
3. **Partial Success**: Individual texts in a batch can succeed/fail independently

### Improved JSON Parsing
The `extractJsonFromResponse` method now includes:
- Multiple extraction strategies (code blocks, brace matching, arrays)
- Automatic JSON cleaning and repair
- Better error logging for debugging
- Fallback mechanisms for malformed responses

## Configuration

### Model Configuration (`config/llm.php`)
```php
'claude-opus-4' => [
    'context_window' => 200000,  // Input token limit
    'batch_size' => 50,          // Texts per batch request
    // ...
],
'gpt-4.1' => [
    'context_window' => 1000000,
    'batch_size' => 100,
    // ...
],
'gemini-2.5-pro' => [
    'context_window' => 2000000,
    'batch_size' => 200,
    // ...
]
```

### Dynamic Batch Size Calculation
The system can calculate optimal batch sizes based on:
- Text length distribution
- Model context window
- Available tokens for prompts/responses

```php
$batchService = app(BatchAnalysisService::class);
$optimalSize = $batchService->calculateOptimalBatchSize($texts, $modelKey);
```

## Monitoring & Logging

### Performance Metrics
```php
Log::info('Starting optimized batch analysis', [
    'model' => $modelKey,
    'total_texts' => count($texts),
    'batch_size' => $batchSize,
    'total_batches' => count($batches),
    'estimated_time' => $estimatedMinutes . ' minutes'
]);
```

### Progress Tracking
- Real-time progress updates per batch
- Model-by-model completion tracking
- Detailed error reporting for failed batches

## API Compatibility

### Existing API Endpoints
All existing API endpoints continue to work unchanged:
- `/api/analyze` (single text)
- `/api/analyze-batch` (multiple texts)
- `/api/results/{jobId}`
- `/api/status/{jobId}`

### New Optimization
The optimization is transparent to API users - they simply experience faster processing times.

## Best Practices

### For Large Datasets (1000+ texts)
1. **Use JSON files up to 100MB** (system supports this)
2. **Select 2-3 models maximum** for reasonable processing time
3. **Monitor progress** via `/progress/{jobId}` endpoint
4. **Consider custom prompts** to reduce token usage

### For Maximum Performance
1. **Gemini 2.5-Pro**: Best for very large datasets (highest batch size)
2. **GPT-4.1**: Good balance of speed and context window
3. **Claude**: Most reliable for complex Lithuanian text analysis

### Token Estimation
Average Lithuanian text tokens:
- **Short news article**: ~500-1000 tokens
- **Medium article**: ~1500-3000 tokens  
- **Long analysis**: ~3000-5000 tokens

## Troubleshooting

### Common Issues

#### Batch Processing Fails
```
[ERROR] Model batch processing failed: {...}
```
**Solution**: System automatically falls back to individual processing

#### JSON Parsing Errors
```  
[ERROR] JSON parsing failed: Syntax error
```
**Solution**: Improved parser with multiple strategies and auto-repair

#### Rate Limits
```
[ERROR] API rate limit exceeded
```
**Solution**: Batch processing significantly reduces API calls

### Debug Mode
Enable detailed logging:
```php
Log::debug('Claude raw response', [
    'content_length' => strlen($content),
    'first_100_chars' => substr($content, 0, 100)
]);
```

## Migration Guide

### Existing Installations
No migration needed - the optimization is automatic and backward compatible.

### Testing the Optimization
1. Upload a dataset with 200+ texts
2. Select multiple AI models
3. Monitor the logs for "Using optimized batch processing"
4. Compare processing time with previous runs

### Rollback if Needed
To disable batch processing temporarily:
```php
// In WebController.php, change:
$useOptimizedBatch = false; // Force disable
```

## Future Improvements

### Planned Features
1. **Dynamic batch sizing** based on real-time performance metrics
2. **Cross-model batch optimization** (processing multiple models in single request where supported)
3. **Predictive loading** for large queue processing
4. **Advanced caching** for repeated analysis patterns

### API Rate Limit Monitoring
Future versions will include:
- Real-time rate limit tracking
- Automatic batch size adjustment
- Intelligent request spacing
- Multi-region failover support