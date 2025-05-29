# API Documentation

## 📋 Overview

The Propaganda Analysis API provides programmatic access to Lithuanian text analysis using multiple Large Language Models (LLMs). The API supports both single text analysis and batch processing with expert annotation comparisons. Custom prompts can be provided directly in analysis requests to optimize results.

**Base URL**: `http://propaganda.local/api`  
**Authentication**: API keys configured in environment  
**Content-Type**: `application/json`  
**Rate Limiting**: Per-model limits (configurable)

## 🔐 Authentication

The system includes a simple authentication layer for production environments:

### Web Interface Authentication
- **Login required** for accessing the web interface in production
- **Default credentials**: admin/propaganda2025 (configurable via environment)
- **Session-based** authentication with automatic logout functionality

### API Authentication
API keys are configured per LLM provider in environment variables:

```env
CLAUDE_API_KEY=your_claude_api_key
GEMINI_API_KEY=your_gemini_api_key
OPENAI_API_KEY=your_openai_api_key

# Web authentication (optional for production)
ADMIN_PASSWORD=your_secure_password
```

### Interactive API Documentation
**Swagger UI** is available at `/api/documentation` for interactive API exploration and testing.

## 📊 Available Models

| Model Key | Provider | Actual Model | Description |
|-----------|----------|--------------|-------------|
| `claude-opus-4` | Anthropic | `claude-opus-4-20250514` | Anthropic's most advanced coding model (Premium) |
| `claude-sonnet-4` | Anthropic | `claude-sonnet-4-20250514` | Evolution of Claude 3.5 Sonnet, excelling in coding |
| `gpt-4.1` | OpenAI | `gpt-4.1` | OpenAI's latest flagship model with improved coding (Premium) |
| `gpt-4o-latest` | OpenAI | `gpt-4o` | OpenAI's multimodal flagship model with audio, vision, and text |
| `gemini-2.5-pro` | Google | `gemini-2.5-pro-experimental` | Google's most advanced model for complex reasoning tasks (Premium) |
| `gemini-2.5-flash` | Google | `gemini-2.5-flash-preview-04-17` | Google's best price-performance model with thinking capabilities |

## 📝 Request/Response Format

### Standard Response Structure

```json
{
  "success": true,
  "data": { ... },
  "message": "Success message",
  "timestamp": "2025-05-27T19:00:00Z"
}
```

### Error Response Structure

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable error message",
    "details": "Additional technical details"
  },
  "timestamp": "2025-05-27T19:00:00Z"
}
```

## 🚀 Endpoints

### 1. Single Text Analysis

**Endpoint**: `POST /api/analyze`

Analyze a single text with selected LLM models.

#### Request

```json
{
  "text_id": "37735",
  "content": "Tekstas analizei su propaganda technikų pavyzdžiais...",
  "models": ["claude-opus-4", "gemini-2.5-pro"],
  "expert_annotations": [
    {
      "type": "labels",
      "value": {
        "start": 0,
        "end": 100,
        "text": "tekstas",
        "labels": ["simplification", "emotionalExpression"]
      }
    }
  ],
  "custom_prompt": "Specialus prompt'as šiai analizei",
  "name": "Analizės pavadinimas",
  "description": "Analizės aprašymas"
}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `text_id` | string | Yes | Unique identifier for the text |
| `content` | string | Yes | Full text content to analyze |
| `models` | array | Yes | List of model keys to use |
| `expert_annotations` | array | No | Expert annotations for comparison |
| `custom_prompt` | string | No | Custom prompt for analysis |
| `reference_analysis_id` | string | No | Reference to previous analysis for text reuse |
| `name` | string | No | Human-readable name for the analysis |
| `description` | string | No | Description of the analysis purpose |

#### Response

```json
{
  "success": true,
  "data": {
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "processing",
    "text_id": "37735",
    "models": ["claude-opus-4", "gemini-2.5-pro"],
    "created_at": "2025-05-27T19:00:00Z"
  }
}
```

#### Example cURL

```bash
curl -X POST https://your-domain.com/api/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "text_id": "test-123",
    "content": "Lietuva yra priklausoma valstybė...",
    "models": ["claude-opus-4"]
  }'
```

### 2. Batch Analysis

**Endpoint**: `POST /api/batch-analyze`

Process multiple texts with expert annotations in ATSPARA format.

#### Request

```json
{
  "file_content": [
    {
      "id": 37735,
      "annotations": [{
        "result": [{
          "type": "labels",
          "value": {
            "start": 0,
            "end": 360,
            "text": "tekstas su propaganda",
            "labels": ["simplification"]
          }
        }],
        "desinformationTechnique": {
          "choices": ["distrustOfLithuanianInstitutions"]
        }
      }],
      "data": {
        "content": "Pilnas tekstas analizei..."
      }
    }
  ],
  "models": ["claude-opus-4", "gemini-2.5-pro", "gpt-4.1"],
  "custom_prompt": "Custom prompt for this batch analysis",
  "name": "Batch Analysis Name",
  "description": "Description of the batch analysis"
}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `file_content` | array | Yes | Array of texts with ATSPARA annotations |
| `models` | array | Yes | List of model keys to use |
| `custom_prompt` | string | No | Custom prompt for all texts in batch |
| `reference_analysis_id` | string | No | Reference to previous analysis for text reuse |
| `name` | string | No | Human-readable name for the batch |
| `description` | string | No | Description of the batch purpose |

#### Response

```json
{
  "success": true,
  "data": {
    "job_id": "550e8400-e29b-41d4-a716-446655440001",
    "status": "processing",
    "total_texts": 100,
    "models": ["claude-opus-4", "gemini-2.5-pro", "gpt-4.1"],
    "estimated_completion": "2025-05-27T19:30:00Z"
  }
}
```

### 3. Job Status

**Endpoint**: `GET /api/status/{job_id}`

Check the status of an analysis job.

#### Response

```json
{
  "success": true,
  "data": {
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "completed",
    "total_texts": 1,
    "processed_texts": 1,
    "progress_percentage": 100,
    "started_at": "2025-05-27T19:00:00Z",
    "completed_at": "2025-05-27T19:05:00Z",
    "error_message": null
  }
}
```

#### Status Values

| Status | Description |
|--------|-------------|
| `pending` | Job queued, waiting to start |
| `processing` | Job currently running |
| `completed` | Job finished successfully |
| `failed` | Job failed with error |

### 4. Analysis Results

**Endpoint**: `GET /api/results/{job_id}`

Retrieve detailed analysis results.

#### Response

```json
{
  "success": true,
  "data": {
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "completed",
    "summary": {
      "total_texts": 1,
      "models_used": ["claude-opus-4"],
      "processing_time": "00:05:23"
    },
    "results": [
      {
        "text_id": "37735",
        "models": {
          "claude-opus-4": {
            "primaryChoice": {
              "choices": ["yes"]
            },
            "annotations": [
              {
                "type": "labels",
                "value": {
                  "start": 0,
                  "end": 196,
                  "text": "Visų pirma nusiimkim spalvotus vaikiškus akinėlius...",
                  "labels": ["loadedLanguage", "causalOversimplification"]
                }
              }
            ],
            "desinformationTechnique": {
              "choices": ["distrustOfLithuanianInstitutions"]
            }
          }
        },
        "comparison_metrics": {
          "claude-opus-4": {
            "precision": 0.85,
            "recall": 0.78,
            "f1_score": 0.81,
            "position_accuracy": 0.92,
            "true_positives": 4,
            "false_positives": 1,
            "false_negatives": 1
          }
        }
      }
    ]
  }
}
```

### 5. Export Results

**Endpoint**: `GET /api/results/{job_id}/export`

Export analysis results as CSV.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `format` | string | `csv` | Export format (csv, json) |
| `include_text` | boolean | `false` | Include full text content |

#### Response (CSV)

```csv
text_id,technique,expert_start,expert_end,expert_text,model,model_start,model_end,model_text,match,position_accuracy,precision,recall,f1_score
37735,simplification,0,360,"Visų pirma nusiimkim...",claude-opus-4,0,196,"Visų pirma nusiimkim...",true,0.92,0.85,0.78,0.81
```

### 6. Text Annotations

**Endpoint**: `GET /api/text-annotations/{text_analysis_id}`

Get annotations for text highlighting, supporting both AI model and expert annotations.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `view` | string | `ai` | Annotation source: `ai` or `expert` |
| `model` | string | `all` | Specific AI model (when view=ai): model name or `all` |
| `enabled` | boolean | `true` | Whether annotations are enabled |

#### Response

```json
{
  "success": true,
  "content": "Analizuojamas tekstas su propagandos technikomis...",
  "annotations": [
    {
      "start": 15,
      "end": 35,
      "technique": "emotionalAppeal",
      "text": "labai pavojingas",
      "models": ["claude-opus-4", "gemini-2.5-pro"]
    }
  ],
  "legend": [
    {
      "technique": "emotionalAppeal",
      "color": "#ff6b6b",
      "number": 1
    }
  ],
  "view_type": "ai"
}
```

#### Error Response

```json
{
  "success": false,
  "message": "Šiam tekstui nėra ekspertų anotacijų"
}
```

### 7. Repeat Analysis

**Endpoint**: `POST /api/repeat-analysis`

Repeat a previous analysis with new parameters (such as different custom prompt or models) while reusing the same text data. This is useful for testing different prompt strategies on the same dataset.

#### Request

```json
{
  "reference_analysis_id": "550e8400-e29b-41d4-a716-446655440000",
  "models": ["claude-opus-4", "gemini-2.5-pro"],
  "custom_prompt": "Naujasis custom prompt'as pakartotinei analizei",
  "name": "Pakartotinė analizė su nauju prompt'u",
  "description": "Testuojame skirtingus prompt'us"
}
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `reference_analysis_id` | string | Yes | ID of completed analysis to repeat |
| `models` | array | Yes | List of model keys to use |
| `custom_prompt` | string | No | New custom prompt for analysis |
| `name` | string | Yes | Human-readable name for the repeated analysis |
| `description` | string | No | Description of the repeated analysis purpose |

#### Response

```json
{
  "success": true,
  "data": {
    "job_id": "550e8400-e29b-41d4-a716-446655440002",
    "status": "processing",
    "reference_analysis_id": "550e8400-e29b-41d4-a716-446655440000",
    "total_texts": 100
  }
}
```

### 7. System Health

**Endpoint**: `GET /api/health`

Check system status and model availability.

#### Response

```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": "2025-05-27T19:00:00Z",
    "services": {
      "database": "connected",
      "redis": "connected",
      "queue": "operational"
    },
    "models": {
      "claude-opus-4": {
        "status": "available",
        "configured": true,
        "rate_limit": 50
      },
      "gemini-2.5-pro": {
        "status": "available",
        "configured": true,
        "rate_limit": 50
      },
      "gpt-4o-latest": {
        "status": "available",
        "configured": true,
        "rate_limit": 50
      }
    },
    "queue_status": {
      "pending_jobs": 5,
      "processing_jobs": 2,
      "failed_jobs": 0
    }
  }
}
```

### 8. Available Models

**Endpoint**: `GET /api/models`

Get list of available LLM models and their configuration.

#### Response

```json
{
  "success": true,
  "data": {
    "models": [
      {
        "key": "claude-opus-4",
        "name": "Claude Opus 4",
        "provider": "Anthropic",
        "model": "claude-opus-4-20250514",
        "configured": true,
        "available": true,
        "rate_limit": 50,
        "max_tokens": 4096
      },
      {
        "key": "gemini-2.5-pro",
        "name": "Gemini 2.5 Pro Preview",
        "provider": "Google",
        "model": "gemini-2.5-pro-experimental",
        "configured": true,
        "available": true,
        "rate_limit": 50,
        "max_tokens": 4096
      },
      {
        "key": "gpt-4.1",
        "name": "GPT-4.1",
        "provider": "OpenAI",
        "model": "gpt-4.1",
        "configured": true,
        "available": true,
        "rate_limit": 50,
        "max_tokens": 4096
      }
    ]
  }
}
```

### 9. Text Annotations for Highlighting

**Endpoint**: `GET /text-annotations/{textAnalysisId}`  
**Description**: Retrieve text annotations with highlighting information for visualization

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `textAnalysisId` | integer | Yes | ID of the text analysis |
| `view` | string | No | View type: `ai` (default) or `expert` |

#### Response

```json
{
  "success": true,
  "text": "Original text content here...",
  "annotations": [
    {
      "start": 25,
      "end": 45,
      "technique": "Emocinė raiška",
      "text": "highlighted fragment"
    }
  ],
  "legend": [
    {
      "technique": "Emocinė raiška",
      "color": "#ff6b6b",
      "number": 1
    }
  ],
  "view_type": "ai"
}
```

#### Example cURL

```bash
curl -X GET "http://propaganda.local/api/text-annotations/123?view=ai" \
  -H "Accept: application/json"
```

#### Error Responses

- `404`: Text analysis not found
- `200` with `success: false`: No expert annotations available (for expert view)

## 📊 Data Formats

### ATSPARA Input Format

The system accepts ATSPARA-formatted JSON with expert annotations:

```json
{
  "id": 37735,
  "annotations": [{
    "result": [
      {
        "type": "choices",
        "value": {
          "choices": ["yes"]
        },
        "from_name": "primaryChoice",
        "to_name": "content"
      },
      {
        "type": "labels",
        "value": {
          "start": 0,
          "end": 360,
          "text": "Tekstas su propaganda technika",
          "labels": ["simplification"]
        },
        "from_name": "label",
        "to_name": "content"
      }
    ],
    "desinformationTechnique": {
      "choices": ["distrustOfLithuanianInstitutions"]
    }
  }],
  "data": {
    "content": "Pilnas tekstas analizei..."
  }
}
```

### LLM Output Format

All LLM services return standardized output:

```json
{
  "primaryChoice": {
    "choices": ["yes"]  // or ["no"]
  },
  "annotations": [
    {
      "type": "labels",
      "value": {
        "start": 0,
        "end": 196,
        "text": "teksto fragmentas",
        "labels": ["emotionalAppeal", "loadedLanguage"]
      }
    }
  ],
  "desinformationTechnique": {
    "choices": ["distrustOfLithuanianInstitutions"]
  }
}
```

## ⚠️ Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `INVALID_REQUEST` | 400 | Request validation failed |
| `MODEL_NOT_FOUND` | 400 | Specified model not available |
| `TEXT_TOO_LONG` | 400 | Text exceeds maximum length |
| `JOB_NOT_FOUND` | 404 | Analysis job not found |
| `MODEL_UNAVAILABLE` | 503 | LLM service temporarily unavailable |
| `RATE_LIMIT_EXCEEDED` | 429 | API rate limit exceeded |
| `PROCESSING_ERROR` | 500 | Internal processing error |

## 🚀 Rate Limiting

Rate limits are applied per model:

| Model | Requests/Minute | Burst Limit |
|-------|----------------|-------------|
| Claude Opus 4 | 50 | 10 |
| Claude Sonnet 4 | 50 | 10 |
| Gemini 2.5 Pro | 50 | 10 |
| Gemini 2.5 Flash | 50 | 10 |
| GPT-4.1 | 50 | 10 |
| GPT-4o | 50 | 10 |

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 50
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1640995200
```

## 📚 Usage Examples

### Python Example

```python
import requests
import json

# Single text analysis
payload = {
    "text_id": "example-001",
    "content": "Lietuva yra nepriklausoma valstybė.",
    "models": ["claude-opus-4", "gemini-2.5-pro"]
}

response = requests.post(
    "https://your-domain.com/api/analyze",
    json=payload,
    headers={"Content-Type": "application/json"}
)

if response.status_code == 200:
    result = response.json()
    job_id = result["data"]["job_id"]
    
    # Check status
    status_response = requests.get(f"https://your-domain.com/api/status/{job_id}")
    print(status_response.json())
else:
    print(f"Error: {response.status_code}")
    print(response.json())
```

### JavaScript Example

```javascript
// Batch analysis
const batchData = {
  file_content: [
    {
      id: 37735,
      data: {
        content: "Tekstas analizei..."
      },
      annotations: [/* expert annotations */]
    }
  ],
  models: ["claude-opus-4"]
};

fetch("https://your-domain.com/api/batch-analyze", {
  method: "POST",
  headers: {
    "Content-Type": "application/json"
  },
  body: JSON.stringify(batchData)
})
.then(response => response.json())
.then(data => {
  console.log("Job started:", data.data.job_id);
  
  // Poll for results
  const jobId = data.data.job_id;
  const pollInterval = setInterval(async () => {
    const statusRes = await fetch(`https://your-domain.com/api/status/${jobId}`);
    const status = await statusRes.json();
    
    if (status.data.status === "completed") {
      clearInterval(pollInterval);
      
      // Get results
      const resultsRes = await fetch(`https://your-domain.com/api/results/${jobId}`);
      const results = await resultsRes.json();
      console.log("Analysis complete:", results);
    }
  }, 5000);
})
.catch(error => console.error("Error:", error));
```

### PHP Example

```php
<?php
// Single text analysis with expert annotations
$data = [
    'text_id' => 'php-example-001',
    'content' => 'Tekstas propagandos analizei...',
    'models' => ['claude-opus-4'],
    'expert_annotations' => [
        [
            'type' => 'labels',
            'value' => [
                'start' => 0,
                'end' => 50,
                'text' => 'tekstas',
                'labels' => ['simplification']
            ]
        ]
    ]
];

$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data)
    ]
];

$context = stream_context_create($options);
$response = file_get_contents('https://your-domain.com/api/analyze', false, $context);
$result = json_decode($response, true);

if ($result['success']) {
    echo "Job ID: " . $result['data']['job_id'] . "\n";
} else {
    echo "Error: " . $result['error']['message'] . "\n";
}
?>
```

## 🔧 Configuration

### Environment Variables

```env
# API Configuration
APP_URL=https://your-domain.com
API_RATE_LIMIT=100

# LLM Models
CLAUDE_API_KEY=your_claude_key
CLAUDE_RATE_LIMIT=50
GEMINI_API_KEY=your_gemini_key
GEMINI_RATE_LIMIT=50
OPENAI_API_KEY=your_openai_key
OPENAI_RATE_LIMIT=50

# Processing Limits
MAX_TEXT_LENGTH=10000
MAX_CONCURRENT_REQUESTS=10
REQUEST_TIMEOUT=60
RETRY_ATTEMPTS=3
```

### 9. Text Annotations and Highlighting

**Endpoint**: `GET /api/text-annotations/{textAnalysisId}`

Get processed annotations for text highlighting visualization with propaganda techniques color-coded and positioned.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `textAnalysisId` | string | Yes | The ID of the text analysis |
| `view` | string | No | View type: `ai` (default) or `expert` |

#### Response

```json
{
  "success": true,
  "content": "Analizuojamas tekstas su propaganda",
  "text": "Analizuojamas tekstas su propaganda",
  "annotations": [
    {
      "start": 25,
      "end": 35,
      "technique": "emotionalAppeal",
      "text": "propaganda"
    }
  ],
  "legend": [
    {
      "technique": "emotionalAppeal",
      "color": "#ff6b6b",
      "description": "Apeliavimas į jausmus"
    }
  ],
  "view_type": "ai"
}
```

#### Usage Example

```javascript
// Fetch annotations for text highlighting
fetch('/api/text-annotations/123?view=ai')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      displayHighlightedText(data.content, data.annotations, data.legend);
    }
  });
```

### 10. Dashboard Export

**Endpoint**: `GET /api/dashboard/export`

Export dashboard statistics in various formats for analysis and reporting.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `format` | string | No | Export format: `json` (default), `csv`, or `excel` |

#### Response

Returns formatted data file as download with appropriate content-type headers.

---

## 🎨 Web Interface Features

### Text Highlighting Visualization

The system provides an interactive text highlighting interface that:

- **Color-codes propaganda techniques** using distinct colors for each ATSPARA methodology technique
- **Toggles between AI and Expert views** to compare automated detection with human annotations  
- **Interactive legend** showing technique descriptions and color mappings
- **Real-time loading** of annotations via AJAX API calls
- **Responsive design** that works across devices

### Dashboard Analytics

Enhanced dashboard with:

- **Interactive Charts** powered by Chart.js showing model performance comparisons
- **Techniques Distribution** visualization with doughnut charts
- **Model Performance Metrics** with bar charts for precision, recall, and F1 scores
- **Export Functionality** for statistics in multiple formats

### Search and Filter System

Advanced analysis management with:

- **Real-time search** across analysis names and IDs
- **Status filtering** (completed, processing, failed, pending)
- **Type filtering** (standard, custom prompt, repeat analysis)
- **Client-side filtering** for fast user experience
- **No results messaging** with clear user guidance

## 🔄 New Workflow Features

### Text Analysis Workflow

1. **Upload Analysis** - JSON file with Lithuanian text corpus
2. **Select Models** - Choose from Claude, Gemini, or GPT models
3. **Configure Prompt** - Use standard ATSPARA or custom prompts
4. **Monitor Progress** - Real-time progress tracking
5. **View Results** - Interactive text highlighting with technique visualization
6. **Export Data** - Download results in CSV/JSON formats
7. **Repeat Analysis** - Re-run with different models or prompts

### Analysis Comparison

- **Side-by-side comparison** of AI vs Expert annotations
- **Model performance metrics** with precision, recall, F1 scores
- **Technique distribution analysis** across different models
- **Statistical visualization** with charts and graphs

---

This API provides comprehensive access to Lithuanian propaganda detection capabilities while maintaining performance, reliability, and ease of use for research applications.