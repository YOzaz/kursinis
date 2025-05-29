# Troubleshooting Guide

## 🚨 Common Issues & Solutions

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

### 6. Performance Issues

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