# System Architecture Documentation

**Autorius:** Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)  
**Institucija:** VU MIF Informatikos 3 kursas  
**Dėstytojas:** Prof. Dr. Darius Plikynas

> Architektūros projektavimas ir optimizavimas atliktas su [Claude Code](https://claude.ai/code) pagalba.

## 🏗️ High-Level Architecture

### System Overview
The Propaganda and Disinformation Analysis System is a Laravel-based web application that processes Lithuanian text through multiple Large Language Models (LLMs) to detect propaganda techniques and disinformation narratives. The system supports custom prompts for optimizing analysis results and allows repetition of analysis with different parameters.

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Browser   │    │   API Clients   │    │  Queue Workers  │
└─────────┬───────┘    └─────────┬───────┘    └─────────┬───────┘
          │                      │                      │
          ▼                      ▼                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Laravel Application                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐ │
│  │ Web Routes   │  │ API Routes   │  │   Queue Jobs         │ │
│  │ Controllers  │  │ Controllers  │  │   - AnalyzeTextJob   │ │
│  │ Blade Views  │  │ JSON APIs    │  │   - BatchAnalysisJob │ │
│  └──────────────┘  └──────────────┘  └──────────────────────┘ │
└─────────────────────────┬───────────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
┌─────────────────┐ ┌─────────────┐ ┌─────────────┐
│     Redis       │ │   MySQL     │ │ LLM APIs    │
│  - Cache        │ │ - Main DB   │ │ - Claude    │
│  - Sessions     │ │ - Analysis  │ │   Opus/Sonnet│
│  - Queues       │ │   Results   │ │ - Gemini 2.5│
│                 │ │             │ │ - GPT-4.1   │
└─────────────────┘ └─────────────┘ └─────────────┘
```

## 📂 Directory Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AnalysisController.php      # Main analysis workflow with enhanced metrics
│   │   ├── DashboardController.php     # Statistics and overview
│   │   └── WebController.php           # Main web interface
│   └── Middleware/                     # Custom middleware
├── Services/
│   ├── LLM Services/
│   │   ├── ClaudeService.php          # Anthropic Claude 4 API
│   │   ├── GeminiService.php          # Google Gemini API
│   │   ├── OpenAIService.php          # OpenAI GPT-4o API
│   │   └── LLMServiceInterface.php    # Common interface
│   ├── Exceptions/                    # Error handling system
│   │   ├── LLMException.php           # Classified LLM errors
│   │   ├── LLMErrorHandlerInterface.php # Error handler contract
│   │   ├── OpenAIErrorHandler.php     # OpenAI-specific error classification
│   │   ├── ClaudeErrorHandler.php     # Claude-specific error classification
│   │   └── GeminiErrorHandler.php     # Gemini-specific error classification
│   ├── Core Services/
│   │   ├── PromptService.php          # Standard and custom prompt generation
│   │   ├── MetricsService.php         # Advanced statistical calculations with category mapping
│   │   ├── StatisticsService.php      # Global performance stats
│   │   └── ExportService.php          # Dynamic CSV/JSON export with model detection
├── Jobs/
│   ├── AnalyzeTextJob.php             # Single text analysis with custom prompts
│   ├── BatchAnalysisJob.php           # Multiple text processing (legacy)
│   ├── BatchAnalysisJobV4.php         # Individual text processing orchestrator
│   ├── IndividualTextAnalysisJob.php  # Individual text-model analysis processing
│   └── ModelAnalysisJob.php           # Individual model processing with automatic chunking (deprecated)
├── Models/
│   ├── AnalysisJob.php                # Main job tracking with custom prompts and references
│   ├── TextAnalysis.php               # Individual text results
│   └── ComparisonMetric.php           # Statistical comparisons
└── Console/Commands/                   # Artisan commands

config/
├── llm.php                            # LLM model configurations
├── queue.php                          # Redis queue settings
└── cache.php                          # Redis cache settings

database/
├── migrations/                        # Database schema
└── factories/                         # Test data generation

resources/
├── views/
│   ├── analyses/                      # Analysis results UI with custom prompt support
│   ├── dashboard/                     # Statistics dashboard
│   └── layout.blade.php               # Main layout
└── css/                               # Frontend styles

routes/
├── web.php                            # Web interface routes
└── api.php                            # API endpoints

tests/
├── Feature/                           # End-to-end tests
├── Unit/                              # Component tests
└── Integration/                       # API integration tests
```

## 🔄 Data Flow Architecture

### 1. Standard Analysis Flow

```
Text Input
    ↓
┌─────────────────┐
│ AnalysisController │
│ - Validate input  │
│ - Create job      │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ AnalyzeTextJob  │
│ - Queue job     │
│ - Select models │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ LLM Services    │
│ - Claude Opus 4 │
│ - Claude Sonnet 4│
│ - Gemini 2.5    │
│ - GPT-4.1       │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ MetricsService  │
│ - Compare AI vs │
│   Expert labels │
│ - Calculate P/R/F1 │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ Database Storage│
│ - TextAnalysis  │
│ - ComparisonMetric │
└─────────────────┘
```

### 2. Analysis Repetition Flow

```
Reference Analysis ID
    ↓
┌─────────────────┐
│ AnalysisController │
│ - Get reference data │
│ - Apply new prompt   │
│ - Create new job     │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ RepeatAnalysis  │
│ - Copy text data │
│ - Use custom prompt │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ AnalyzeTextJob  │
│ - Build prompt  │
│ - Apply custom  │
│   modifications │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ LLM Services    │
│ - Process with  │
│ - Custom prompt │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ Results Analysis│
│ - Performance   │
│ - Comparison    │
└─────────────────┘
```

## 🗄️ Database Architecture

### Entity Relationship Diagram

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   AnalysisJob   │    │  TextAnalysis   │    │ ComparisonMetric│
│─────────────────│    │─────────────────│    │─────────────────│
│ job_id (PK)     │───▶│ job_id (FK)     │───▶│ job_id (FK)     │
│ status          │    │ text_id         │    │ text_id         │
│ total_texts     │    │ content         │    │ model_name      │
│ processed_texts │    │ expert_annotations │ │ precision       │
│ custom_prompt   │    │ claude_annotations │ │ recall          │
│ reference_id    │    │ gemini_annotations │ │ f1_score        │
│ created_at      │    │ gpt_annotations │    │ true_positives  │
│ completed_at    │    │ created_at      │    │ false_positives │
│ error_message   │    └─────────────────┘    │ false_negatives │
└─────────────────┘                           └─────────────────┘
```

### Table Descriptions

#### `analysis_jobs`
- **Purpose**: Track analysis job lifecycle
- **Key Fields**: 
  - `job_id`: UUID primary key
  - `status`: pending/processing/completed/failed
  - `custom_prompt`: Optional custom prompt for analysis
  - `reference_id`: Links to previous analysis for repetition

#### `text_analyses`
- **Purpose**: Store individual text analysis results
- **Key Fields**:
  - `content`: Full text content
  - `expert_annotations`: JSON with ATSPARA expert labels
  - `claude_annotations`, `gemini_annotations`, `gpt_annotations`: JSON with AI results

#### `comparison_metrics`
- **Purpose**: Statistical comparison between AI and expert annotations with intelligent category mapping
- **Key Fields**:
  - `model_name`: Dynamically detected AI model name
  - `precision`, `recall`, `f1_score`: Performance metrics (calculated from real data)
  - `true_positives`, `false_positives`, `false_negatives`: Confusion matrix data
  - `position_accuracy`: Spatial overlap accuracy
  - `cohen_kappa`: Inter-annotator agreement coefficient

**Recent Enhancement**: Supports category mapping between expert annotations (simplified names like 'simplification', 'emotionalExpression') and AI annotations (ATSPARA methodology names like 'causalOversimplification', 'loadedLanguage')

## 🚀 Individual Text Processing Architecture

### Individual Text Processing System

The system processes each text individually instead of using chunking for better reliability and simpler error handling:

#### Architecture Overview

```
Multiple Texts from File Upload
    ↓
┌─────────────────┐
│ BatchAnalysisJobV4 │
│ - Create TextAnalysis records │
│ - Dispatch individual jobs │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ Individual Text │
│ Analysis Jobs   │
│ - One job per text-model │
│ - Parallel processing │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ LLM Services    │
│ - Direct API calls │
│ - No chunking needed │
│ - Better error isolation │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ Result Storage  │
│ - ModelResult records │
│ - Legacy compatibility │
│ - Progress tracking │
└─────────────────┘
```

#### Individual Processing Benefits

| Benefit | Description | Previous Approach |
|---------|-------------|-------------------|
| **Error Isolation** | Single text failure doesn't affect others | Chunk failure affected multiple texts |
| **Simpler Logic** | No complex chunking algorithms | Complex size calculations and splitting |
| **Better Monitoring** | Per-text progress tracking | Per-chunk progress only |
| **Easier Debugging** | Direct text-to-result mapping | Complex chunk-to-text mapping |
| **Parallel Processing** | True parallelism across queue workers | Sequential chunk processing |

#### Processing Implementation

**IndividualTextAnalysisJob** processes one text with one model:

```php
// Direct service call - no chunking needed
switch ($provider) {
    case 'anthropic':
        $service = app(ClaudeService::class);
        $service->setModel($modelKey);
        return $service->analyzeText($content, $customPrompt);
        
    case 'openai':
        $service = app(OpenAIService::class);
        $service->setModel($modelKey);
        return $service->analyzeText($content, $customPrompt);
        
    case 'google':
        $service = app(GeminiService::class);
        $service->setModel($modelKey);
        return $service->analyzeText($content, $customPrompt);
}
```

#### Queue Architecture

Each text-model combination gets its own queue job:

- **2 texts × 3 models = 6 individual jobs**
- **Jobs processed in parallel by queue workers**
- **Failed jobs don't affect successful ones**
- **Granular retry capability per text-model combination**

#### Performance Benefits

- **True Parallelism**: Multiple texts processed simultaneously
- **Simpler Error Handling**: Individual failures are isolated
- **Better Resource Utilization**: Queue workers can process different texts
- **Improved Reliability**: No complex chunking logic to fail
- **Easier Maintenance**: Simple, straightforward processing flow

## 🆕 Recent System Enhancements (2025)

### Individual Text Processing Refactoring (June 2025)

**Major architectural change**: Replaced chunking-based file processing with individual text processing for improved reliability and maintainability.

#### Key Changes:
- **New Job**: `IndividualTextAnalysisJob` processes one text with one model
- **Refactored**: `BatchAnalysisJobV4` now orchestrates individual job dispatching
- **Removed**: Complex chunking logic and file attachment strategies
- **Enhanced**: True parallel processing across queue workers

#### Benefits:
- **Error Isolation**: Single text failures don't affect batch processing
- **Simplified Logic**: No complex size calculations or chunk management
- **Better Monitoring**: Granular progress tracking per text-model combination
- **Improved Reliability**: Elimination of chunking failure scenarios
- **Easier Debugging**: Direct text-to-result mapping

#### Migration Impact:
- **Tests Updated**: All batch processing tests migrated to individual processing
- **Documentation Updated**: Architecture guides reflect new processing flow
- **Backward Compatibility**: Legacy fields maintained for existing data
- **Queue Configuration**: New 'individual' queue for text processing jobs

### Text Highlighting and Visualization System
- **Interactive Text Highlighting**: Real-time visualization of AI and expert annotations
- **Dual-View Interface**: Switch between AI annotations and expert annotations
- **Color-Coded Techniques**: Visual legend for different propaganda techniques
- **Text Size Toggle**: Accessibility feature for better readability
- **Modal-Based Details**: Full-screen analysis view with Bootstrap modals

### Enhanced Model Management
- **Updated Model Support**: Latest Claude Opus 4, Sonnet 4, GPT-4.1, and Gemini 2.5 models
- **Execution Time Tracking**: Monitors and stores processing time for each analysis
- **Retry Functionality**: Automatic retry capability for failed analyses
- **Model Name Detection**: Dynamic actual model name detection and storage

### Custom Prompt and Reference Analysis
- **Custom Prompt Support**: Ability to provide custom prompts for specialized analysis
- **Reference Analysis**: Link analyses to previous ones for comparison studies
- **Prompt Template System**: Standardized prompt templates with custom override capability

### Intelligent Error Handling System

**Purpose**: Provides robust, API-specific error handling that allows batch processing to continue when individual models fail.

#### Error Classification Architecture

```
LLMException (Base)
├── statusCode: HTTP status code
├── errorType: API-specific error type
├── provider: 'openai' | 'claude' | 'gemini'
├── isRetryable: boolean
├── isQuotaRelated: boolean
└── shouldFailBatch(): boolean
```

#### Provider-Specific Error Handlers

**OpenAIErrorHandler:**
- Handles `429` quota exceeded, `402` payment required
- Detects `insufficient_quota` vs `rate_limit_exceeded`
- Classifies authentication and permission errors
- Supports JSON error response parsing

**ClaudeErrorHandler:**
- Handles `429` rate limits, `529` overloaded errors
- Supports Anthropic's error format specifications
- Classifies `authentication_error`, `permission_error`

**GeminiErrorHandler:**
- Handles `400` billing requirements, `429` resource exhausted
- Supports Google's `FAILED_PRECONDITION`, `RESOURCE_EXHAUSTED` format
- Classifies `PERMISSION_DENIED`, `UNAUTHENTICATED`

#### Error Handling Behavior

**Continue Processing (Graceful Failures):**
- Quota exceeded (OpenAI 429 + insufficient_quota)
- Billing required (Gemini 400 + FAILED_PRECONDITION)
- Rate limits (all providers 429 + rate_limit)
- Server errors (5xx - retryable)

**Stop Processing (Critical Failures):**
- Authentication errors (401)
- Permission errors (403)
- Configuration errors (400 - non-billing)

#### Implementation Flow

1. **LLM Service** throws generic Exception
2. **Provider-specific ErrorHandler** classifies the exception
3. **LLMException** created with proper metadata
4. **AnalyzeTextJob** uses `shouldFailBatch()` to determine handling
5. **Batch continues** for graceful failures, stops for critical ones

### Performance Monitoring
- **Execution Time Metrics**: Track processing times across models and analyses
- **Success Rate Monitoring**: Real-time tracking of analysis success/failure rates
- **Model Performance Analytics**: Compare performance across different AI models

## 🔧 Service Layer Architecture

### LLM Service Pattern

All LLM services implement `LLMServiceInterface`:

```php
interface LLMServiceInterface
{
    public function analyzeText(string $text, ?string $customPrompt = null): array;
    public function isConfigured(): bool;
    public function getModelName(): string;
    public function getRateLimit(): int;
}
```

### Service Dependencies

```
Controllers
    ↓
┌─────────────────┐
│   Core Services │
│ - PromptService │
│ - MetricsService│
│ - StatisticsService │
└─────────┬───────┘
          ↓
┌─────────────────┐
│  LLM Services   │
│ - ClaudeService │
│ - GeminiService │
│ - OpenAIService │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ External APIs   │
│ - Claude API    │
│ - Gemini API    │
│ - OpenAI API    │
└─────────────────┘
```

## 🚀 Queue Architecture

### Redis Queue Configuration

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 300,
        'block_for' => null,
    ],
]
```

### Job Processing Flow

```
HTTP Request
    ↓
┌─────────────────┐
│   Controller    │
│ - Validate      │
│ - Dispatch Job  │
└─────────┬───────┘
          ↓
┌─────────────────┐
│   Redis Queue   │
│ - Store job     │
│ - Priority      │
└─────────┬───────┘
          ↓
┌─────────────────┐
│  Queue Worker   │
│ - Process job   │
│ - Handle errors │
│ - Retry logic   │
└─────────┬───────┘
          ↓
┌─────────────────┐
│   Job Result    │
│ - Update status │
│ - Store results │
└─────────────────┘
```

### Job Types and Priorities

1. **AnalyzeTextJob** - Priority: Normal
   - Single text analysis with optional custom prompts
   - Quick turnaround expected

2. **BatchAnalysisJobV4** - Priority: Normal
   - Orchestrates individual text processing
   - Fast job coordination, dispatches individual jobs

3. **IndividualTextAnalysisJob** - Priority: Normal
   - Processes one text with one model
   - Parallel execution across queue workers
   - Isolated error handling

4. **BatchAnalysisJob** - Priority: Low (Legacy)
   - Multiple text processing (deprecated)
   - Replaced by individual processing approach

## 🔐 Security Architecture

### API Security

```
Request
    ↓
┌─────────────────┐
│   Rate Limiting │
│ - Per model     │
│ - Per user      │
└─────────┬───────┘
          ↓
┌─────────────────┐
│  Input Validation │
│ - Text length   │
│ - JSON format   │
│ - Model selection │
└─────────┬───────┘
          ↓
┌─────────────────┐
│ API Key Management │
│ - Environment vars │
│ - Encrypted storage │
└─────────┬───────┘
          ↓
┌─────────────────┐
│  LLM API Call   │
│ - HTTPS only    │
│ - Retry logic   │
└─────────────────┘
```

### Data Security

- **API Keys**: Stored in environment variables, never in code
- **Text Content**: Temporarily stored, not logged
- **Results**: Anonymized for research purposes
- **Database**: Credentials in environment, encrypted connections

## 🔄 Recent System Enhancements (2025)

### Major UI/UX Improvements

#### Enhanced Analysis Results Display
- **Model-Specific Results**: Results table redesigned to show each model in separate rows with clear performance metrics
- **Interactive Help System**: 14+ context-sensitive help tooltips using Bootstrap popovers
- **Smart Text Handling**: Modal text automatically truncates long content (>500 chars) with async expansion
- **Direct Downloads**: JSON/CSV export buttons directly accessible from analysis results

#### Improved Statistics Visualization
- **Real Metrics Display**: Fixed statistics showing actual calculated values instead of zeros
- **Dynamic Model Detection**: System automatically detects which models were used in analysis
- **Comprehensive Metrics**: Shows Precision, Recall, F1-score, and Cohen's Kappa for each model

### Advanced Metrics System

#### Intelligent Category Mapping
The `MetricsService` now includes sophisticated category mapping between expert and AI annotation systems:

```php
// Expert annotation categories (simplified)
'simplification' => ['causalOversimplification', 'blackandwhite', 'thoughtterminatingcliche']
'emotionalExpression' => ['emotionalappeal', 'loadedlanguage', 'appealtofear']
'distraction' => ['whataboutism', 'strawman', 'redherring']
```

This enables accurate comparison between different annotation methodologies while maintaining compatibility with both expert annotations (using simplified category names) and AI annotations (using full ATSPARA methodology names).

#### Enhanced Data Processing
- **Annotation Filtering**: Automatically filters out invalid or incomplete annotations
- **Type-Safe Processing**: Validates annotation structure with proper `type` field handling
- **Position Tolerance**: Accounts for slight position differences in text span annotations
- **Real-World Format Support**: Handles complex annotation structures from actual research data

### Export System Enhancements
- **Dynamic Model Detection**: Export service automatically discovers which models were used
- **Comprehensive Data Export**: Includes all analysis metadata, performance metrics, and detailed annotations
- **Format Flexibility**: Supports both JSON (for programmatic use) and CSV (for spreadsheet analysis)

## 📊 Performance Architecture

### Caching Strategy

```
┌─────────────────┐
│   Application   │
└─────────┬───────┘
          ↓
┌─────────────────┐
│   Redis Cache   │
│ - Config cache  │
│ - Route cache   │
│ - View cache    │
│ - Session cache │
└─────────┬───────┘
          ↓
┌─────────────────┐
│   Database      │
│ - Query cache   │
│ - Connection    │
│   pooling       │
└─────────────────┘
```

### Optimization Techniques

1. **Lazy Loading**: Models loaded with relationships only when needed
2. **Queue Processing**: Heavy operations moved to background
3. **Database Indexing**: Optimized indexes on frequently queried fields
4. **API Rate Limiting**: Prevents overwhelming external services

## 🔄 Error Handling Architecture

### Multi-Level Error Handling

```
┌─────────────────┐
│  User Interface │
│ - Friendly msgs │
│ - Status updates│
└─────────┬───────┘
          ↓
┌─────────────────┐
│   Controllers   │
│ - Validation    │
│ - Exception     │
│   handling      │
└─────────┬───────┘
          ↓
┌─────────────────┐
│   Services      │
│ - API errors    │
│ - Retry logic   │
│ - Fallbacks     │
└─────────┬───────┘
          ↓
┌─────────────────┐
│   Queue Jobs    │
│ - Failed jobs   │
│ - Dead letter   │
│   queue         │
└─────────┬───────┘
          ↓
┌─────────────────┐
│   Logging       │
│ - Error details │
│ - Performance   │
│   metrics       │
└─────────────────┘
```

### Error Recovery Strategies

1. **API Failures**: Exponential backoff retry
2. **Queue Failures**: Dead letter queue for manual review
3. **Database Errors**: Transaction rollbacks
4. **Validation Errors**: User-friendly feedback

## 🧪 Testing Architecture

### Test Structure

```
tests/
├── Unit/
│   ├── Services/           # Service layer tests
│   ├── Models/             # Model validation tests
│   └── Jobs/               # Queue job tests
├── Feature/
│   ├── Analysis/           # End-to-end analysis tests
│   ├── API/                # API endpoint tests
│   └── CustomPrompts/      # Custom prompt functionality tests
└── Integration/
    ├── LLM/                # LLM service integration
    ├── Database/           # DB relationship tests
    └── Queue/              # Queue processing tests
```

### Test Data Strategy

- **Factories**: Generate realistic test data
- **Fixtures**: Static test cases with known outcomes
- **Mocking**: LLM API responses for consistent testing
- **Seeding**: Development database with sample data

### Enhanced Testing for Individual Processing

#### Individual Processing Test Coverage

- **Individual Job Dispatching**: Tests that each text-model combination gets its own job
- **Parallel Processing**: Verifies jobs can run concurrently without interference
- **Error Isolation**: Confirms failed individual jobs don't affect others
- **Progress Tracking**: Tests accurate progress calculation based on completed jobs

#### Test Examples

```php
// Test individual job dispatching
public function test_individual_text_jobs_dispatched_for_each_text_model_combination()
{
    $fileContent = [/* 2 texts */];
    $models = ['claude-3-sonnet', 'gpt-4o'];
    
    // Should dispatch 2 texts × 2 models = 4 individual jobs
    Queue::assertPushed(IndividualTextAnalysisJob::class, 4);
}

// Test error isolation
public function test_individual_text_analysis_with_error_handling()
{
    // Mock service to throw exception for one text
    // Verify other texts continue processing normally
    // Confirm failed job doesn't stop batch processing
}
```

## 🚀 Deployment Architecture

### Production Environment

```
┌─────────────────┐
│     Nginx       │
│ - Reverse proxy │
│ - SSL termination │
│ - Static files  │
└─────────┬───────┘
          ↓
┌─────────────────┐
│   PHP-FPM       │
│ - Laravel app   │
│ - Process pool  │
└─────────┬───────┘
          ↓
┌─────────────────┐    ┌─────────────────┐
│     MySQL       │    │     Redis       │
│ - Main database │    │ - Cache/Queue   │
│ - Replication   │    │ - Sessions      │
└─────────────────┘    └─────────────────┘
          ↓                      ↓
┌─────────────────┐    ┌─────────────────┐
│   Supervisor    │    │   Queue Workers │
│ - Process mgmt  │    │ - Background    │
│ - Auto-restart  │    │   processing    │
└─────────────────┘    └─────────────────┘
```

### Scalability Considerations

1. **Horizontal Scaling**: Multiple queue workers
2. **Database Scaling**: Read replicas for heavy queries
3. **Cache Scaling**: Redis clustering for high load
4. **API Scaling**: Rate limiting and request queuing

---

This architecture provides a robust, scalable foundation for automated propaganda detection research while maintaining code quality, performance, and reliability standards.