# Status Monitoring System

## Overview

The system provides comprehensive real-time monitoring capabilities for AI analysis jobs, featuring both standard progress tracking and an advanced "Mission Control" view with technical details.

## Status Views

### 1. Standard Progress View
**URL**: `/progress/{jobId}`
- Basic progress tracking
- Job status indicators  
- Auto-refresh functionality
- Link to Mission Control view

### 2. Mission Control View 🤖
**URL**: `/status-view/{jobId}`
- Movie-style technical interface
- Real-time updates every 5 seconds
- Matrix rain background effect
- Comprehensive technical details

### 3. Status API Endpoint
**URL**: `/status/{jobId}`
- JSON API for real-time data
- Used by Mission Control view
- Detailed analytics and metrics

## Mission Control Features

### Visual Design
- **Matrix Theme**: Green terminal-style interface with rain effect
- **Real-time Indicators**: Pulsing status badges and progress bars
- **Responsive Layout**: Works on desktop and mobile
- **Auto-refresh**: Updates every 5 seconds with live indicator

### Data Sections

#### 1. Mission Details
```json
{
  "job": {
    "id": "uuid",
    "status": "processing|pending|completed|failed",
    "duration": "2 minutes ago",
    "progress_percentage": 45.5,
    "processed_texts": 455,
    "total_texts": 1000
  }
}
```

#### 2. Data Analysis
```json
{
  "texts": {
    "total_records": 6000,
    "unique_texts": 1000,
    "avg_text_length": 2500,
    "total_characters": 2500000
  }
}
```

#### 3. AI Model Status
For each AI model (Claude, GPT, Gemini):
```json
{
  "claude-opus-4": {
    "name": "claude-opus-4-20250514",
    "provider": "anthropic",
    "status": "processing|pending|completed|partial_failure",
    "completed": 450,
    "errors": 5,
    "success_rate": 98.9,
    "estimated_chunks": 334,
    "api_calls_made": 334
  }
}
```

#### 4. System Logs
```json
{
  "logs": [
    {
      "timestamp": "2025-06-04T19:15:30Z",
      "level": "INFO|WARNING|ERROR",
      "message": "Processing model claude-opus-4 with smart chunking",
      "context": {"model": "claude-opus-4", "chunk": "15/125"}
    }
  ]
}
```

#### 5. Queue Status
```json
{
  "queue": {
    "batch_workers_active": true,
    "jobs_in_queue": 0,
    "failed_jobs": 0,
    "last_queue_activity": "2025-06-04T19:15:00Z"
  }
}
```

## Status Indicators

### Job Status
- **🟡 Pending**: Job created, waiting to start
- **🟠 Processing**: Currently analyzing texts  
- **🟢 Completed**: All analyses finished successfully
- **🔴 Failed**: Job encountered fatal error

### Model Status
- **⚪ Pending**: Model not started yet
- **🟠 Processing**: Model currently analyzing (pulsing animation)
- **🟢 Completed**: Model finished all texts
- **🟡 Partial Failure**: Some texts failed, some succeeded
- **🔴 Failed**: Model completely failed

## Technical Implementation

### Backend Controller
```php
public function detailedStatus(string $jobId)
{
    // Comprehensive data collection
    $job = AnalysisJob::where('job_id', $jobId)->first();
    $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
    
    // Calculate detailed statistics
    $stats = [
        'job' => [...],
        'texts' => [...], 
        'models' => [...]
    ];
    
    return response()->json([
        'stats' => $stats,
        'logs' => $this->getRecentLogs($jobId),
        'queue' => $this->getQueueStatus(),
        'timestamp' => now(),
        'refresh_interval' => 5
    ]);
}
```

### Frontend JavaScript
```javascript
// Auto-refresh mechanism
function updateStatus() {
    fetch(`/status/${jobId}`)
        .then(response => response.json())
        .then(data => renderStatus(data))
        .catch(error => console.error('Status update failed:', error));
}

setInterval(updateStatus, 5000); // Every 5 seconds
```

### Progress Calculation
```php
// Job progress (model completion based)
$progress = ($job->processed_texts / $job->total_texts) * 100;

// Model progress (individual text completion)
$modelProgress = ($completed_texts / $total_texts) * 100;

// Success rate calculation
$successRate = $total_texts > 0 ? ($completed / $total_texts) * 100 : 0;
```

## Database Schema Requirements

### Required Columns
The system requires these columns in `text_analysis` table:
```sql
-- Error tracking columns
claude_error TEXT NULL
gpt_error TEXT NULL  
gemini_error TEXT NULL

-- Model name tracking
claude_model_name VARCHAR(255) NULL
gpt_model_name VARCHAR(255) NULL
gemini_model_name VARCHAR(255) NULL
```

### Migration
```bash
php artisan make:migration add_missing_columns_to_text_analysis_table
php artisan migrate
```

## Performance Considerations

### Optimization
- **Efficient Queries**: Uses collection methods for statistics
- **Cached Calculations**: Model stats calculated once per request
- **Minimal Database Hits**: Single query for all TextAnalysis records
- **Client-side Rendering**: Heavy UI work done in JavaScript

### Scalability
- **Large Datasets**: Handles 1000+ texts efficiently
- **Real-time Updates**: 5-second refresh interval balances accuracy vs performance
- **Memory Efficient**: Streams data to frontend, doesn't cache server-side

## Error Handling

### Job Not Found
```json
{
  "error": "Job not found"
}
```
**HTTP Status**: 404

### API Failures
- Frontend shows "ERROR" status in refresh indicator
- Continues attempting to reconnect
- Previous data remains visible

### Database Errors
- Graceful degradation with default values
- Error logging for debugging
- User sees partial information rather than complete failure

## Security Considerations

### Access Control
- No authentication required (matches existing system)
- Job IDs are UUIDs (hard to guess)
- No sensitive data exposed in status

### Data Sanitization
- All output JSON-encoded
- No raw database data exposed
- XSS protection through proper escaping

## Troubleshooting

### Common Issues

#### Status Shows "Pending" Forever
```bash
# Check if job was dispatched
php artisan queue:work --once --queue=batch

# Check supervisor workers
sudo supervisorctl status

# Check database schema
php artisan tinker --execute="Schema::hasColumn('text_analysis', 'claude_error')"
```

#### Mission Control View Not Loading
1. Check route is registered: `php artisan route:list | grep status`
2. Verify JavaScript console for errors
3. Test API endpoint directly: `curl /status/{jobId}`

#### Inaccurate Statistics
1. Verify migration ran: `php artisan migrate:status`
2. Check TextAnalysis records exist
3. Validate model configuration in `config/llm.php`

## Usage Examples

### Access Mission Control
1. Go to regular progress page: `/progress/{jobId}`
2. Click "🤖 Mission Control View" button
3. New tab opens with real-time technical view

### Monitor Large Jobs
```javascript
// Check if job is actually processing
fetch('/status/your-job-id')
  .then(r => r.json())
  .then(data => {
    console.log('Models status:', data.stats.models);
    console.log('Queue status:', data.queue);
    console.log('Recent logs:', data.logs);
  });
```

### Custom Refresh Intervals
The default 5-second refresh can be modified in the controller:
```php
return response()->json([
    // ...
    'refresh_interval' => 10 // 10 seconds
]);
```

## Future Enhancements

### Planned Features
1. **Live Log Streaming**: WebSocket-based real-time log feed
2. **Performance Metrics**: API response times, throughput graphs
3. **Alerting System**: Email/Slack notifications for failures
4. **Historical Tracking**: Trend analysis over time
5. **Resource Monitoring**: CPU, memory, disk usage
6. **Custom Dashboards**: User-configurable status panels

### API Extensions
```php
// Planned endpoints
GET /status/{jobId}/metrics     // Performance metrics
GET /status/{jobId}/logs/live   // WebSocket log stream  
GET /status/{jobId}/export      // Status data export
POST /status/{jobId}/actions    // Job control (pause/resume)
```