# 2025 Multi-Model Update

> Sistema plėtota su [Claude Code](https://claude.ai/code) pagalba - Anthropic AI kodo plėtojimo įrankyje, kuris neįkainojamai padėjo optimizuoti sistemą ir atnaujinti ją naujausiais AI modeliais.

## 🚀 What's New

### Multiple Models Per Provider
The system now supports multiple models from each provider:

#### Anthropic Claude
- **Claude Opus 4** (Premium) - `claude-opus-4-20250514`
  - World's best coding model with 72.5% on SWE-bench
  - $15/$75 per million tokens (input/output)
- **Claude Sonnet 4** (Standard) - `claude-sonnet-4-20250514`
  - Evolution of Claude 3.5 Sonnet with 72.7% on SWE-bench
  - $3/$15 per million tokens (input/output)

#### OpenAI GPT
- **GPT-4.1** (Premium) - `gpt-4.1`
  - Latest flagship model with 54.6% on SWE-bench
  - $2/$8 per million tokens, 1M context window
- **GPT-4o Latest** (Standard) - `gpt-4o`
  - Multimodal model with audio, vision, and text
  - $2.50/$10 per million tokens

#### Google Gemini
- **Gemini 2.5 Pro** (Premium) - `gemini-2.5-pro-experimental`
  - Most advanced model for complex reasoning
  - State-of-the-art performance
- **Gemini 2.5 Flash** (Standard) - `gemini-2.5-flash-preview-04-17`
  - Best price-performance with thinking capabilities
  - Supports thinking budget (0-24576 tokens)

### Graceful Error Handling
- **Continue on Failure**: Analysis continues with other models if one fails
- **Retry Logic**: Failed models can be retried with exponential backoff
- **Configurable Timeouts**: Per-model timeout and retry settings
- **Detailed Logging**: Track which models succeed/fail and why

### Enhanced UI
- **Provider Grouping**: Models organized by provider (Anthropic, OpenAI, Google)
- **Tier Indicators**: Premium/Standard badges for each model
- **Dynamic Loading**: Models loaded from configuration
- **Default Selection**: Automatically selects default models (Opus 4, GPT-4.1, Gemini Pro)

### Database Enhancements
- **Retry Tracking**: Track retry attempts and timing
- **Model Status**: Per-model success/failure status
- **Analysis Logs**: Detailed logging table for all model attempts
- **Actual Model Names**: Store both system keys and actual API model names

## 🔧 Configuration

### LLM Configuration (`config/llm.php`)
```php
'models' => [
    'claude-opus-4' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => 'claude-opus-4-20250514',
        'provider' => 'anthropic',
        'tier' => 'premium',
        'is_default' => true,
    ],
    // ... more models
],

'error_handling' => [
    'continue_on_failure' => true,
    'max_retries_per_model' => 3,
    'retry_delay_seconds' => 2,
    'exponential_backoff' => true,
    'timeout_seconds' => 120,
],
```

### Environment Variables
```env
# API Keys (same as before)
CLAUDE_API_KEY=your_claude_api_key
GEMINI_API_KEY=your_gemini_api_key
OPENAI_API_KEY=your_openai_api_key

# Optional: Override default error handling
MAX_RETRIES_PER_MODEL=3
RETRY_DELAY_SECONDS=2
TIMEOUT_SECONDS=120
```

## 📊 Usage

### API Request Example
```json
{
  "text_id": "example-001",
  "content": "Lithuanian text for analysis...",
  "models": [
    "claude-opus-4",
    "claude-sonnet-4", 
    "gpt-4.1",
    "gemini-2.5-pro"
  ]
}
```

### Response with Multiple Models
```json
{
  "success": true,
  "data": {
    "job_id": "uuid-here",
    "models": ["claude-opus-4", "gpt-4.1", "gemini-2.5-pro"],
    "failed_models": ["claude-sonnet-4"],
    "status": "completed"
  }
}
```

### Retry Failed Models
```bash
curl -X POST /api/retry/{job_id} \
  -H "Content-Type: application/json" \
  -d '{"models": ["claude-sonnet-4"]}'
```

## 🔍 Error Handling Behavior

### When One Model Fails
1. Log the error with details
2. Continue with remaining models
3. Mark analysis as partial success if any models succeed
4. Store failed model information for retry

### Retry Logic
1. Exponential backoff: 2s, 4s, 8s delays
2. Maximum 3 attempts per model
3. Different error handling per provider
4. Timeout protection (120s default)

## 🧪 Testing

### Test New Features
```bash
# Test multi-model configuration
php test_new_features.php

# Verify API model names
php verify_model_names.php

# Test specific model switching
php artisan tinker
>>> $service = app(\App\Services\ClaudeServiceNew::class);
>>> $service->setModel('claude-opus-4');
>>> $service->getActualModelName();
```

### API Testing
```bash
# Test with multiple models
curl -X POST /api/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "text_id": "test-multi",
    "content": "Test text",
    "models": ["claude-opus-4", "gpt-4.1", "gemini-2.5-pro"]
  }'
```

## 📈 Performance Improvements

### Parallel Processing
- Models are processed concurrently where possible
- Queue jobs handle multiple models efficiently
- Failed models don't block successful ones

### Smart Defaults
- Premium models selected by default for best quality
- Can fallback to standard models if premium fails
- Configurable model priorities

### Monitoring
- Track processing time per model
- Monitor success/failure rates
- Analyze which models perform best

## 🚨 Important Notes

### Model Availability
- Model names are based on 2025 research
- Some models may be in preview/experimental status
- Check provider documentation for latest availability
- API keys need appropriate access levels

### Cost Considerations
- Premium models (Opus 4, GPT-4.1) are more expensive
- Consider using standard models for development
- Monitor usage with multiple models selected

### Rate Limiting
- Each provider has different rate limits
- System respects individual provider limits
- Failed requests due to rate limits are retried

## 🔄 Migration Notes

### From Old System
1. Old model keys still work (backward compatibility)
2. Single-model analyses continue to work
3. UI automatically shows new model options
4. Database migrations add new tracking fields

### Recommended Migration Steps
1. Update API calls to use new model keys
2. Test with new multi-model selection
3. Monitor error rates and adjust retry settings
4. Update monitoring for new success metrics

---

This update provides a robust foundation for multi-model propaganda analysis with enterprise-grade error handling and monitoring capabilities.