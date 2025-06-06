# Troubleshooting Guide

## 🚨 Common Issues & Solutions

### UTF-8 Character Positioning Issues (Fixed 2025-06-06)

#### Problem: AI Model Annotations Don't Match Text Boundaries
```
Highlighted text appears truncated or misaligned
Claude response shows different text than what's highlighted
Position coordinates don't match displayed content
```

#### Root Cause: 
- AI models provide Unicode character positions (0-280)
- PHP `substr()` uses byte positions in UTF-8 encoding  
- Lithuanian characters (ą,č,ę,ė,į,š,ų,ū,ž) use 2-3 bytes but count as 1 character
- Text length: 8229 bytes vs 7703 characters for Lithuanian content

#### Solution Implemented:
1. **Code Fix**: Replaced `substr()` with `mb_substr()` in `AnalysisController.php`
2. **Prompt Enhancement**: Added explicit UTF-8 character positioning instructions
3. **Trust Provided Text**: System now prioritizes AI-provided text over coordinate extraction
4. **Metrics Verification**: Confirmed MetricsService uses correct positioning

#### Files Modified:
- `app/Http/Controllers/AnalysisController.php` (lines 1117-1123, 1142-1148, 1204-1210)
- `app/Services/PromptService.php` (prompt instructions with UTF-8 guidance)
- `docs/METRICS-GUIDE.md` (documentation update)

#### Technical Details:
```php
// Before (incorrect for UTF-8)
$text = substr($originalText, $start, $end - $start);

// After (correct for UTF-8)  
$finalText = !empty($providedText) ? $providedText : mb_substr($originalText, $start, $end - $start, 'UTF-8');
```

#### Prompt Improvements (Enhanced 2025-06-06):
```
**TEKSTO IŠGAVIMO TAISYKLĖS (KRITIŠKAI SVARBU)**:
Kai išgauni teksto fragmentą, VISADA:
- Skaičiuok "simbolius" kaip Unicode kodo taškus (kaip Python len() funkcija)
- Start pozicija įskaitoma, end pozicija neįskaitoma (Python stiliaus text[start:end])
- Grąžink: fragmentą, start ir end indeksus, simbolių skaičių (naudodamas savo skaičiavimo metodą)
- PAVYZDYS: tekstui="AąBčD", text[0:3] turėtų būti "AąB" (3 simboliai)
- Patikrink rezultatą išvedant ilgį naudodamas savo skaičiavimo metodą
- Lietuviški simboliai ą,č,ę,ė,į,š,ų,ū,ž = po 1 Unicode simbolį, ne 2-3 baitus
- NIEKADA nenaudok baitų pozicijų - tik Unicode simbolių pozicijas!

**UNICODE SIMBOLIŲ POZICIJŲ VALIDACIJA**:
Prieš grąžindamas JSON atsakymą, PRIVALAI patikrinti:
- Ar tavo skaičiuojamas teksto ilgis sutampa su Unicode simbolių skaičiumi
- Ar start/end pozicijos tiksliai atitinka pateiktą text fragmentą
- Ar lietuviški simboliai (ą,č,ę,ė,į,š,ų,ū,ž) skaičiuojami kaip po 1 simbolį
- PAVYZDYS tikrinimo: jei text="Aąžė", tai end-start turėtų būti 4, ne 7
```

#### How to Verify Fix Works:
```bash
# Test UTF-8 positioning
php artisan tinker --execute="
\$text = 'Visų pirma nusiimkim spalvotus vaikiškus akinėlius...';
echo 'Bytes: ' . strlen(\$text) . PHP_EOL;
echo 'Characters: ' . mb_strlen(\$text, 'UTF-8') . PHP_EOL;
echo 'Position 280 (bytes): ' . substr(\$text, 0, 280) . PHP_EOL;
echo 'Position 280 (chars): ' . mb_substr(\$text, 0, 280, 'UTF-8') . PHP_EOL;
"
```

#### Additional Fixes Implemented (2025-06-06):

**Multiple Techniques per Annotation:**
- Updated tooltip display to show all techniques when AI provides multiple labels
- Fixed metrics calculation to correctly handle multiple techniques (uses `array_intersect`)
- Added proper color selection for primary technique

**JavaScript Improvements (Enhanced 2025-06-06):**
- Fixed "trust provided text" approach in JavaScript display functions
- **CRITICAL FIX**: Eliminated text duplication when AI provides full text that doesn't match coordinates
- **ENHANCED DUPLICATION PREVENTION**: Added advanced overlap detection with console warnings
- **INTELLIGENT TEXT SEARCH**: Progressive search strategy (current position → near coordinates → fallback)
- **OVERLAP PROTECTION**: Automatic skipping of annotations that would create duplication
- Enhanced tooltip content for multiple techniques with HTML formatting  
- Improved text positioning logic to prevent overlapping annotations

**Example of Multiple Techniques:**
```json
{
  "type": "labels", 
  "value": {
    "start": 0,
    "end": 280,
    "text": "Complete AI-provided text...",
    "labels": ["simplification", "emotionalExpression"]
  }
}
```

**Metrics Calculation:**
- System correctly identifies True Positive if ANY expert technique matches ANY AI technique
- Multiple techniques increase accuracy rather than penalizing over-classification
- Position accuracy calculations maintain UTF-8 character precision

### 1. Redis Connection Issues

#### Problem: "Connection refused" Error
```
Redis connection refused at 127.0.0.1:6379
Queue jobs not processing
Sessions not working
```

#### Solutions:

**Check Redis Status:**
```bash
# Check if Redis is running
sudo systemctl status redis

# Start Redis if stopped
sudo systemctl start redis

# Test Redis connection
redis-cli ping
# Expected output: PONG
```

**Verify Configuration:**
```bash
# Check Redis configuration in .env
cat .env | grep REDIS

# Should show:
# REDIS_HOST=127.0.0.1
# REDIS_PORT=6379
# CACHE_DRIVER=redis
# QUEUE_CONNECTION=redis
# SESSION_DRIVER=redis
```

**Laravel Cache Clear:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan queue:restart
```

### 2. LLM API Issues

#### Problem: Claude API 404 Error
```
Claude API neprieinamas po 3 bandymų: 404 Not Found
```

#### Solutions:

**Check API Configuration:**
```bash
# Verify Claude configuration
php artisan tinker
>>> config('llm.models.claude-opus-4')
```

**Expected Output:**
```php
[
    "api_key" => "sk-ant-api03-...",
    "base_url" => "https://api.anthropic.com/v1/",
    "model" => "claude-sonnet-4-20250514",
    "max_tokens" => 4096,
    "temperature" => 0.1,
    "rate_limit" => "50"
]
```

**Fix Common Issues:**
```bash
# Clear config cache
php artisan config:clear

# Check API key validity
curl -H "x-api-key: $CLAUDE_API_KEY" \
     -H "anthropic-version: 2023-06-01" \
     https://api.anthropic.com/v1/messages

# Update model name if needed
# Edit config/llm.php - 'model' => 'claude-sonnet-4-20250514'
```

#### Problem: Gemini API 404 Error
```
Gemini API neprieinamas po 3 bandymų: 404 Not Found
```

#### Solutions:

**Verify Model Name:**
```bash
# Check current Gemini configuration
php artisan tinker
>>> config('llm.models.gemini-2.5-pro-experimental')
```

**Update Model:**
```php
// In config/llm.php
'gemini-2.5-pro-experimental' => [
    'model' => 'gemini-2.5-pro-experimental', // Ensure correct model
    'base_url' => 'https://generativelanguage.googleapis.com/',
    // ...
]
```

**Test API Key:**
```bash
curl "https://generativelanguage.googleapis.com/v1beta/models?key=$GEMINI_API_KEY"
```

#### Problem: OpenAI API Errors
```
OpenAI API neprieinamas: 401 Unauthorized
```

#### Solutions:

**Check API Key:**
```bash
# Test OpenAI API key
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer $OPENAI_API_KEY"
```

**Verify Model Access:**
```bash
# Check if you have access to GPT-4.1
curl https://api.openai.com/v1/models/gpt-4.1 \
  -H "Authorization: Bearer $OPENAI_API_KEY"
```

#### Problem: API Quota and Billing Errors

The system now uses **intelligent error handling** based on HTTP status codes and API-specific error types rather than string matching. This provides more reliable error detection and handling.

#### Supported Error Types by Provider:

**OpenAI API Errors:**
- `429` + `insufficient_quota` - Quota exceeded
- `402` - Payment required  
- `401` - Authentication error
- `429` + `rate_limit_exceeded` - Rate limit (retryable)

**Claude/Anthropic API Errors:**
- `429` + `rate_limit_error` - Rate limit exceeded
- `401` + `authentication_error` - Invalid API key
- `529` + `overloaded_error` - Service overloaded (retryable)

**Gemini API Errors:**
- `400` + `FAILED_PRECONDITION` - Billing required
- `429` + `RESOURCE_EXHAUSTED` - Rate limit exceeded  
- `403` + `PERMISSION_DENIED` - API key permissions

#### Intelligent Error Handling Behavior:

**✅ Quota/Billing Errors (Continue Processing):**
```
Status: 429 - You exceeded your current quota
Status: 400 - Gemini API free tier billing required  
Status: 429 - Rate limit exceeded
```
- Analysis **continues** with other available models
- Failed model shows specific error message in results
- Batch processing **does not stop**
- Job **completes successfully** with partial results

**⚠️ Authentication Errors (Stop Processing):**
```
Status: 401 - Invalid API key provided
Status: 403 - API key lacks permissions
```
- **Stops entire batch** (configuration issue)
- Requires manual intervention
- All models for this job will fail

**🔄 Server Errors (Retryable):**
```
Status: 500/502/503/504 - Server errors
Status: 529 - Service overloaded
```
- Automatically retried up to 3 times
- Uses exponential backoff delay
- If all retries fail, continues with other models

#### Solutions:

**Check Quota Status:**
```bash
# OpenAI usage
curl https://api.openai.com/v1/usage \
  -H "Authorization: Bearer $OPENAI_API_KEY"

# Gemini models list (requires billing)
curl "https://generativelanguage.googleapis.com/v1beta/models?key=$GEMINI_API_KEY"

# Claude API test
curl -H "x-api-key: $CLAUDE_API_KEY" \
     -H "anthropic-version: 2023-06-01" \
     https://api.anthropic.com/v1/messages
```

**Monitor System Logs:**
```bash
# Check detailed error information
tail -f storage/logs/laravel.log | grep -E "(quota|billing|rate_limit)"

# Look for error classifications
grep "status_code\|error_type\|is_quota_related" storage/logs/laravel.log
```

**Handle Different Error Types:**

1. **Quota Exceeded (OpenAI):**
   - Upgrade plan at https://platform.openai.com/account/billing
   - Results will show successful analysis from other models

2. **Billing Required (Gemini):**  
   - Enable billing in Google AI Studio
   - Claude and OpenAI will continue working

3. **Rate Limits:**
   - Temporary - system will retry automatically
   - Reduce concurrent requests if persistent

4. **Authentication Errors:**
   - Check API key validity and permissions
   - Update keys in `.env` file
   - Run `php artisan config:clear`

### 3. Queue Processing Issues

#### Problem: Jobs Stuck in Queue
```
Jobs remain in "pending" status
Queue worker not processing
```

#### Solutions:

**Check Queue Worker:**
```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# Start queue worker manually
php artisan queue:work redis --verbose

# For production, use Supervisor
sudo supervisorctl status
```

**Restart Queue:**
```bash
# Restart all queue workers
php artisan queue:restart

# Clear failed jobs
php artisan queue:flush

# Check queue status
php artisan queue:monitor
```

**Check Failed Jobs:**
```bash
# List failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:forget all
```

### 4. Database Issues

#### Problem: Migration Errors
```
SQLSTATE[42S01]: Base table or view already exists
```

#### Solutions:

**Fresh Migration:**
```bash
# Drop all tables and re-migrate (CAUTION: Data loss)
php artisan migrate:fresh

# Or rollback and re-migrate
php artisan migrate:rollback
php artisan migrate
```

**Check Migration Status:**
```bash
# See migration status
php artisan migrate:status

# Run specific migration
php artisan migrate --path=/database/migrations/2024_01_01_000000_create_analysis_jobs_table.php
```

#### Problem: Foreign Key Constraints
```
SQLSTATE[23000]: Integrity constraint violation
```

#### Solutions:

**Check Relationship Data:**
```bash
php artisan tinker
>>> \App\Models\AnalysisJob::whereDoesntHave('textAnalyses')->count()
>>> \App\Models\TextAnalysis::whereNull('job_id')->count()
```

**Fix Data Integrity:**
```bash
# Remove orphaned records
php artisan tinker
>>> \App\Models\ComparisonMetric::whereNotIn('job_id', \App\Models\AnalysisJob::pluck('job_id'))->delete()
```

### 5. View/UI Issues

#### Problem: Empty Analysis Results Table
```
Analysis results page shows empty table
No model names displayed
```

#### Solutions:

**Check Data Structure:**
```bash
php artisan tinker
>>> $analysis = \App\Models\TextAnalysis::with('comparisonMetrics')->first()
>>> $analysis->comparisonMetrics->count()
>>> $analysis->claude_annotations
```

**Verify Analysis Completion:**
```bash
# Check if analysis actually completed
>>> $job = \App\Models\AnalysisJob::where('status', 'completed')->first()
>>> $job->textAnalyses->count()
>>> $job->textAnalyses->first()->comparisonMetrics->count()
```

**Re-run Analysis:**
```bash
# If data is missing, re-run analysis
php artisan queue:work redis --verbose
# Upload file again through UI
```

#### Problem: Blade Template Errors
```
Undefined variable: statistics
Undefined array key "overall_accuracy"
```

#### Solutions:

**Check Controller:**
```php
// In AnalysisController::show()
$statistics = $this->metricsService->calculateJobStatistics($analysis);
// Ensure this returns expected structure
```

**Add Default Values:**
```php
// In blade template
{{ $statistics['overall_metrics']['accuracy'] ?? 0 }}
```

### 6. Large Dataset Processing Issues

#### Problem: API Size Limit Errors
```
Claude API error: too many total text bytes: 18688637 > 9000000
OpenAI API error: string too long. Expected a string with maximum length 10485760
Claude API error: prompt is too long: 211369 tokens > 200000 maximum
```

#### Solutions:

**Automatic Dynamic Chunking (Default Behavior):**
The system automatically handles these errors through intelligent chunking:

- **Claude**: Files >8MB split into optimal-sized chunks (typically 10-30 texts based on content)
- **OpenAI**: Files >9MB split into optimal-sized chunks (typically 20-40 texts based on content)
- **Gemini**: Uses individual text processing for long content
- **Smart Sizing**: Chunk size automatically calculated based on text length and token limits

**Monitor Chunking Logs:**
```bash
# Check if chunking is active
tail -f storage/logs/laravel.log | grep -E "(chunking|chunk)"

# Look for chunking indicators
grep -E "File too large|Processing in chunks|Chunk [0-9]+ completed" storage/logs/laravel.log
```

**Expected Chunking Log Output:**
```
[INFO] File too large for single Claude API call, using chunking
[INFO] Calculated optimal chunk size: provider=anthropic, max_tokens=180000, avg_tokens_per_text=4283, calculated_chunk_size=15
[INFO] Processing in chunks: 1000 texts → 67 chunks (15 texts each)
[INFO] Processing chunk 1/67: 15 texts, 180KB
[INFO] Chunk 1 completed: 15 successful, 0 failed
[INFO] All chunks completed: 950 successful, 50 failed
```

#### Problem: Chunking Performance Issues
```
Chunked processing slower than expected
Memory issues with large files
```

#### Solutions:

**Optimize Chunk Size:**
```php
// In ModelAnalysisJob.php, adjust chunk size if needed
$chunkSize = 25; // Reduce from 50 for very large texts
$chunks = array_chunk($jsonData, $chunkSize);
```

**Monitor Memory Usage:**
```bash
# Check memory during chunking
ps aux | grep "queue:work" | awk '{print $6/1024 " MB"}'

# Increase memory limit if needed
php artisan queue:work redis --memory=1024
```

**Verify Partial Results:**
```bash
# Check if partial results are being stored
php artisan tinker
>>> $job = \App\Models\AnalysisJob::where('job_id', 'your-job-id')->first()
>>> $results = \App\Models\ModelResult::where('job_id', $job->job_id)->count()
>>> echo "Results stored: $results"
```

#### Problem: Failed Chunks Stopping Analysis
```
Some chunks fail and entire analysis stops
Partial results not saved
```

#### Solutions:

**Verify Error Isolation:**
```bash
# Check if failed chunks are isolated
grep -E "Chunk [0-9]+ failed.*continuing" storage/logs/laravel.log

# Verify failed results are marked appropriately
php artisan tinker
>>> $failedResults = \App\Models\ModelResult::where('status', 'failed')->count()
>>> $successfulResults = \App\Models\ModelResult::where('status', 'completed')->count()
>>> echo "Failed: $failedResults, Successful: $successfulResults"
```

**Check Error Handling:**
```bash
# Look for proper error handling in logs
grep -E "Chunk processing failed|error_isolation|graceful_degradation" storage/logs/laravel.log
```

### 7. Performance Issues

#### Problem: Slow Analysis Processing
```
Analysis takes too long
API timeouts
```

#### Solutions:

**Check Queue Workers:**
```bash
# Increase queue workers
php artisan queue:work redis --sleep=3 --tries=3 --memory=512

# Run multiple workers in parallel
php artisan queue:work redis --queue=default &
php artisan queue:work redis --queue=default &
php artisan queue:work redis --queue=default &
```

**Optimize Database:**
```bash
# Add indexes for frequent queries
php artisan tinker
>>> \DB::statement('CREATE INDEX idx_job_status ON analysis_jobs(status)')
>>> \DB::statement('CREATE INDEX idx_job_id ON text_analyses(job_id)')
```

**Monitor Memory:**
```bash
# Check memory usage
free -h

# Check Laravel memory usage
php artisan queue:work redis --memory=256
```

### 7. Configuration Issues

#### Problem: Environment Variables Not Loading
```
API keys showing as null
Configuration not updating
```

#### Solutions:

**Clear Configuration Cache:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

**Check .env File:**
```bash
# Verify .env exists and has correct values
cat .env | grep -E "(CLAUDE|GEMINI|OPENAI)_API_KEY"

# No spaces around = sign
# CLAUDE_API_KEY=sk-ant-api03-...
# NOT: CLAUDE_API_KEY = sk-ant-api03-...
```

**Test Configuration Loading:**
```bash
php artisan tinker
>>> env('CLAUDE_API_KEY')
>>> config('llm.models.claude-opus-4.api_key')
```

## 🔧 Debug Mode

### Enable Debug Information

**Environment:**
```env
APP_DEBUG=true
APP_ENV=local
LOG_LEVEL=debug
```

**Queue Debugging:**
```bash
# Run queue with verbose output
php artisan queue:work redis --verbose --timeout=300

# Monitor queue in real-time
tail -f storage/logs/laravel.log
```

**Database Debugging:**
```env
DB_LOG_QUERIES=true
```

## 📊 System Health Checks

### Automated Health Check Script

```bash
#!/bin/bash
# health_check.sh

echo "=== System Health Check ==="

# Check Redis
echo -n "Redis: "
redis-cli ping > /dev/null 2>&1 && echo "✓ OK" || echo "✗ FAILED"

# Check Database
echo -n "Database: "
php artisan tinker --execute="try { \DB::connection()->getPdo(); echo 'OK'; } catch(Exception \$e) { echo 'FAILED'; }" 2>/dev/null

# Check Queue
echo -n "Queue Worker: "
pgrep -f "queue:work" > /dev/null && echo "✓ OK" || echo "✗ FAILED"

# Check API Keys
echo -n "Claude API: "
[ -n "$CLAUDE_API_KEY" ] && echo "✓ Configured" || echo "✗ Missing"

echo -n "Gemini API: "
[ -n "$GEMINI_API_KEY" ] && echo "✓ Configured" || echo "✗ Missing"

echo -n "OpenAI API: "
[ -n "$OPENAI_API_KEY" ] && echo "✓ Configured" || echo "✗ Missing"

# Check Disk Space
echo -n "Disk Space: "
df -h / | awk 'NR==2 {if ($5+0 < 90) print "✓ OK ("$5" used)"; else print "⚠ WARNING ("$5" used)"}'

echo "=========================="
```

Make executable and run:
```bash
chmod +x health_check.sh
./health_check.sh
```

## 🆘 Emergency Recovery

### Complete System Reset

**⚠️ WARNING: This will delete all data**

```bash
# 1. Stop all services
php artisan queue:restart
sudo systemctl stop redis

# 2. Clear all caches and data
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 3. Reset database
php artisan migrate:fresh

# 4. Restart services
sudo systemctl start redis
php artisan queue:work redis --daemon

# 5. Test basic functionality
php artisan queue:work redis --verbose
```

### Partial Recovery (Keep Data)

```bash
# 1. Clear caches only
php artisan config:clear
php artisan cache:clear

# 2. Restart queue workers
php artisan queue:restart

# 3. Re-run failed jobs
php artisan queue:retry all

# 4. Test API endpoints
curl -X GET http://localhost:8000/api/health
```

## 📞 Getting Help

### Log Files to Check

1. **Laravel Logs:** `storage/logs/laravel.log`
2. **Queue Logs:** Check queue worker output
3. **Web Server Logs:** `/var/log/nginx/error.log`
4. **System Logs:** `journalctl -u redis`

### Information to Collect for Bug Reports

```bash
# System info
php --version
redis-cli --version
mysql --version

# Laravel info
php artisan --version
php artisan env
php artisan config:show llm

# Error details
tail -50 storage/logs/laravel.log

# Queue status
php artisan queue:monitor
```

### Contact Information

- **Project Author:** Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)
- **Supervisor:** Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)
- **ATSPARA Project:** https://www.atspara.mif.vu.lt/

---

If none of these solutions work, check the GitHub issues or contact the project maintainer with detailed error information and system logs.