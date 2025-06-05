# Status Monitoring System

## Overview

The system provides comprehensive real-time monitoring capabilities for AI analysis jobs, featuring both standard progress tracking and an advanced system-wide "Mission Control" dashboard with optional job filtering and technical details.

## Status Views

### 1. Standard Progress View
**URL**: `/progress/{jobId}`
- Basic progress tracking for individual jobs
- Job status indicators  
- Auto-refresh functionality
- Links to both filtered and system-wide Mission Control views

### 2. Mission Control Dashboard 🤖
**URL**: `/mission-control`
- **System-wide monitoring**: Overview of all models, jobs, and system status
- **Job filtering**: Optional `?job_id={jobId}` parameter for specific job focus
- Movie-style technical interface with Matrix theme
- Real-time updates every 5 seconds
- Comprehensive technical details and system metrics

### 3. Mission Control API Endpoint
**URL**: `/api/mission-control`
- **System-wide data**: JSON API providing comprehensive system status
- **Job filtering**: Optional `?job_id={jobId}` parameter
- Used by Mission Control dashboard
- Real-time system analytics and metrics

### 4. Legacy Status API Endpoint  
**URL**: `/status/{jobId}` 
- JSON API for individual job data
- Used by detailed status views
- Job-specific analytics and metrics

## Mission Control Features

### Visual Design
- **Matrix Theme**: Green terminal-style interface with rain effect
- **Real-time Indicators**: Pulsing status badges and progress bars
- **Responsive Layout**: Works on desktop and mobile
- **Auto-refresh**: Updates every 5 seconds with live indicator
- **Filtering Interface**: Input field and controls for job filtering
- **System Overview**: Comprehensive dashboard showing all system activity

### Data Sections

#### 1. System Overview Statistics
```json
{
  "overview": {
    "total_jobs": 25,
    "active_jobs": 3,
    "completed_jobs": 20,
    "failed_jobs": 2,
    "unique_texts": 15000,
    "queue": 0
  }
}
```

#### 2. Job Details (when filtered)
When filtering by specific job ID, additional job details are shown:
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

#### 3. AI Model Status (System-wide)
System-wide statistics for each AI model across all jobs:
```json
{
  "claude-opus-4": {
    "name": "claude-opus-4-20250514",
    "provider": "anthropic",
    "status": "processing|pending|completed|partial_failure",
    "total_analyses": 1500,
    "successful": 1450,
    "failed": 45,
    "pending": 5,
    "success_rate": 96.7
  }
}
```

#### 4. System Logs (Filtered or System-wide)
```json
{
  "logs": [
    {
      "timestamp": "2025-06-04T19:15:30Z",
      "level": "INFO|WARNING|ERROR", 
      "message": "📤 Uploading file to claude-opus-4 API...",
      "context": {"model": "claude-opus-4", "job_type": "BatchAnalysisJobV4"},
      "job_id": "abc123..." // Present when filtering, null for system-wide logs
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
public function missionControl(Request $request): JsonResponse
{
    $jobFilter = $request->get('job_id'); // Optional job filter
    return $this->getSystemWideStatus($jobFilter);
}

private function getSystemWideStatus(?string $jobFilter = null): array
{
    // Get jobs (filtered or system-wide)
    $jobsQuery = AnalysisJob::orderBy('created_at', 'desc');
    if ($jobFilter) {
        $jobsQuery->where('job_id', $jobFilter);
    } else {
        $jobsQuery->limit(10); // Show last 10 jobs if no filter
    }
    $jobs = $jobsQuery->get();
    
    // Calculate system-wide model statistics
    $systemStats = [
        'overview' => [...],
        'queue' => [...],
        'models' => [...] // Aggregated across all jobs
    ];
    
    return [
        'system' => $systemStats,
        'job_details' => $jobFilter ? [...] : null,
        'logs' => $this->getSystemLogs($jobFilter),
        'timestamp' => now(),
        'filtered_by_job' => $jobFilter,
        'refresh_interval' => 5
    ];
}
```

### Frontend JavaScript
```javascript
// System-wide monitoring with optional job filtering
let currentJobFilter = null;

function updateStatus() {
    const url = new URL('/api/mission-control', window.location.origin);
    if (currentJobFilter) {
        url.searchParams.append('job_id', currentJobFilter);
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => renderStatus(data))
        .catch(error => console.error('Status update failed:', error));
}

function applyFilter() {
    const filterValue = document.getElementById('jobFilter').value.trim();
    currentJobFilter = filterValue || null;
    updateStatus();
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

#### Mission Control Dashboard Not Loading
1. Check routes are registered: `php artisan route:list | grep mission-control`
2. Verify JavaScript console for errors
3. Test API endpoint directly: `curl /api/mission-control`
4. Test with job filter: `curl "/api/mission-control?job_id=your-job-id"`

#### Inaccurate Statistics
1. Verify migration ran: `php artisan migrate:status`
2. Check TextAnalysis records exist
3. Validate model configuration in `config/llm.php`

## Usage Examples

### Access Mission Control
**System-wide view:**
1. Navigate directly to `/mission-control`
2. Or click "System-Wide View" from any progress page

**Filtered view for specific job:**
1. Go to progress page: `/progress/{jobId}`
2. Click "🤖 Mission Control (Filtered)" button
3. Or go to analysis results page and click Mission Control links
4. New tab opens with filtered real-time view

### Monitor System Status
```javascript
// Check system-wide status
fetch('/api/mission-control')
  .then(r => r.json())
  .then(data => {
    console.log('System overview:', data.system.overview);
    console.log('Models status:', data.system.models);
    console.log('Queue status:', data.system.queue);
    console.log('Recent logs:', data.logs);
  });

// Check specific job status  
fetch('/api/mission-control?job_id=your-job-id')
  .then(r => r.json())
  .then(data => {
    console.log('Job details:', data.job_details);
    console.log('Filtered logs:', data.logs);
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
// Current endpoints
GET /api/mission-control              // System-wide status
GET /api/mission-control?job_id={}    // Filtered by job ID

// Planned endpoints
GET /api/mission-control/metrics      // System performance metrics
GET /api/mission-control/logs/live    // WebSocket log stream  
GET /api/mission-control/export       // System status export
POST /api/mission-control/actions     // System control (pause/resume queues)
GET /status/{jobId}                   // Legacy job-specific endpoint
```