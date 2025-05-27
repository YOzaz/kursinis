# Project Requirements & Overview

## 📋 Project Summary

**Project Name:** Propaganda and Disinformation Analysis System for Lithuanian Text  
**Author:** Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)  
**Institution:** Vilnius University, Faculty of Mathematics and Informatics  
**Supervisor:** Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)  
**Type:** Bachelor's Thesis Project  

## 🎯 Project Purpose & Goals

### Primary Objective
Create an automated system for detecting propaganda techniques and disinformation narratives in Lithuanian text using Large Language Models (LLMs) and compare their performance against expert annotations from the ATSPARA project.

### Core Goals
1. **Research Mode**: Compare LLM performance against expert annotations with statistical metrics
2. **Practical Mode**: Analyze new Lithuanian texts for propaganda detection
3. **Experimental Mode**: Test and optimize custom prompts using RISEN methodology
4. **Academic Value**: Provide measurable insights into LLM effectiveness for Lithuanian propaganda detection

## 🏗️ System Architecture

### Technology Stack
- **Backend**: Laravel 12.15.0 (PHP 8.4.7)
- **Database**: MySQL 8.0+ or SQLite 3.8+
- **Cache/Queue**: Redis 6.0+ (REQUIRED)
- **Frontend**: Blade templates with Bootstrap 5
- **APIs**: Claude 4, Gemini 2.5 Pro, GPT-4o

### Data Sources
- **ATSPARA Project**: Expert annotations and propaganda classification methodology
- **Text Corpus**: Lithuanian news articles, social media posts, academic texts
- **Annotation Format**: Label Studio JSON format with propaganda technique labels

## 🔬 Scientific Methodology

### ATSPARA Classification System
The system implements the ATSPARA (Automatic Detection of Propaganda and Disinformation) project methodology:

**21 Propaganda Techniques:**
1. `emotionalAppeal` - Appeals to emotions
2. `appealToFear` - Fear-mongering tactics
3. `loadedLanguage` - Emotionally charged vocabulary
4. `nameCalling` - Negative labeling of opponents
5. `exaggeration` - Hyperbole or minimization
6. `glitteringGeneralities` - Vague positive terms
7. `whataboutism` - Deflection through counter-accusations
8. `redHerring` - Irrelevant information to distract
9. `strawMan` - Misrepresenting opponent's position
10. `causalOversimplification` - Oversimplified explanations
11. `blackAndWhite` - False dichotomy
12. `thoughtTerminatingCliche` - Clichés to stop thought
13. `slogans` - Memorable phrases or catchwords
14. `obfuscation` - Intentionally vague language
15. `appealToAuthority` - Celebrity endorsements
16. `flagWaving` - Patriotism-based arguments
17. `bandwagon` - Appeal to popular opinion
18. `doubt` - Questioning credibility/reliability
19. `smears` - Character assassination
20. `reductioAdHitlerum` - Comparisons to despised groups
21. `repetition` - Repeating the same message

**2 Disinformation Narratives:**
1. `distrustOfLithuanianInstitutions` - Undermining trust in Lithuanian institutions
2. `natoDistrust` - Reducing trust in NATO

### Statistical Metrics
- **Precision**: Ratio of correct AI predictions to total AI predictions
- **Recall**: Ratio of detected expert annotations to total expert annotations
- **F1 Score**: Harmonic mean of precision and recall
- **Cohen's Kappa**: Agreement coefficient between AI and experts
- **Position Accuracy**: Text position matching accuracy

## 🔄 System Workflows

### 1. Standard Analysis Workflow
```
Text Input → Model Selection → Queue Processing → LLM Analysis → 
Comparison with Expert Annotations → Metrics Calculation → Results Display
```

### 2. Experiment Workflow
```
Custom Prompt Design (RISEN) → Preview → Text Selection → 
Multiple Model Testing → Performance Comparison → Optimization
```

### 3. Batch Analysis Workflow
```
JSON File Upload → Validation → Queue Distribution → 
Parallel Processing → Results Aggregation → CSV Export
```

## 📊 Data Models & Relationships

### Core Tables
1. **analysis_jobs** - Main analysis job tracking
   - `job_id` (primary key, UUID)
   - `status` (pending/processing/completed/failed)
   - `experiment_id` (nullable, for experiment analyses)

2. **text_analyses** - Individual text analysis results
   - `job_id` (foreign key)
   - `text_id` (text identifier)
   - `content` (full text content)
   - `expert_annotations` (JSON, expert labels)
   - `claude_annotations` (JSON, Claude 4 results)
   - `gemini_annotations` (JSON, Gemini results)
   - `gpt_annotations` (JSON, GPT-4o results)

3. **comparison_metrics** - Statistical comparison data
   - `job_id` (foreign key)
   - `model_name` (LLM model used)
   - `precision`, `recall`, `f1_score` (DECIMAL)
   - `true_positives`, `false_positives`, `false_negatives` (INT)

4. **experiments** - Custom prompt experiments
   - `name`, `description`
   - `risen_prompt` (JSON with Role, Instructions, Situation, Execution, Needle)

5. **experiment_results** - Experiment performance data
   - `experiment_id` (foreign key)
   - `llm_model` (model name)
   - `metrics` (JSON, performance data)
   - `execution_time` (seconds)

## 🌐 API Endpoints

### Analysis Endpoints
- `POST /api/analyze` - Single text analysis
- `POST /api/batch-analyze` - Batch text analysis
- `GET /api/status/{job_id}` - Check analysis status
- `GET /api/results/{job_id}` - Get analysis results
- `GET /api/results/{job_id}/export` - Export results as CSV

### Experiment Endpoints
- `POST /api/experiments` - Create new experiment
- `GET /api/experiments/{id}/run` - Run experiment with texts
- `GET /api/experiments/{id}/results` - Get experiment results

### Status Endpoints
- `GET /api/health` - System health check
- `GET /api/models` - Available LLM models status

## 🔧 Configuration Requirements

### Environment Variables
```env
# Database
DB_CONNECTION=mysql  # or sqlite
DB_DATABASE=propaganda_analysis
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Redis (REQUIRED)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# LLM API Keys
CLAUDE_API_KEY=your_claude_api_key
GEMINI_API_KEY=your_gemini_api_key
OPENAI_API_KEY=your_openai_api_key

# Rate Limiting
CLAUDE_RATE_LIMIT=50
GEMINI_RATE_LIMIT=50
OPENAI_RATE_LIMIT=50
```

### LLM Model Configuration
```php
// config/llm.php
'models' => [
    'claude-4' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'base_url' => 'https://api.anthropic.com/v1/',
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 4096,
        'temperature' => 0.1,
    ],
    'gemini-2.5-pro' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/',
        'model' => 'gemini-2.5-pro-preview-05-06',
        'max_tokens' => 4096,
        'temperature' => 0.1,
    ],
    'gpt-4.1' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-4o',
        'max_tokens' => 4096,
        'temperature' => 0.1,
    ],
]
```

## 🎮 User Interface Components

### 1. Main Analysis Page (`/`)
- File upload for JSON with expert annotations
- Model selection (Claude 4, Gemini 2.5 Pro, GPT-4o)
- Progress tracking
- Results preview

### 2. Analyses List (`/analyses`)
- All completed/failed analyses
- Status indicators (Completed/Failed/Processing)
- Type indicators (Standard/Experiment)
- Quick access to results

### 3. Analysis Details (`/analyses/{jobId}`)
- **Text Analysis Table** with columns:
  - Text content preview
  - Model names used
  - AI propaganda decision vs Expert decision
  - Confidence scores (Precision/Recall/F1)
  - Propaganda techniques found (AI vs Expert)
  - Expert comparison metrics
  - Detailed view modal
- **Statistics Panel**:
  - Overall accuracy percentage
  - Precision, Recall, F1 averages
  - Model performance breakdown

### 4. Experiments (`/experiments`)
- **RISEN Prompt Builder**:
  - Role definition
  - Instructions specification
  - Situation context
  - Execution steps
  - Needle (core objective)
- Real-time prompt preview
- Model comparison results
- Performance optimization tracking

### 5. Dashboard (`/dashboard`)
- **Global Statistics**:
  - Total experiments and analyses
  - Model usage distribution
  - Recent activity feed
- **Performance Charts**:
  - Model comparison radar chart
  - Execution time comparison
  - Accuracy trends over time

## ⚠️ Known Issues & Solutions

### 1. API Rate Limiting
**Problem**: LLM APIs have rate limits  
**Solution**: Implemented retry logic with exponential backoff in services

### 2. Large Text Processing
**Problem**: Some texts exceed token limits  
**Solution**: Text chunking and aggregation in PromptService

### 3. Cache Configuration
**Problem**: Failed analyses due to missing Redis  
**Solution**: All sessions, cache, and queues require Redis

### 4. Model Availability
**Problem**: API endpoints change or models get deprecated  
**Solution**: Configurable model endpoints in config/llm.php

## 🔄 Queue Processing

### Job Types
1. **AnalyzeTextJob** - Single text analysis
2. **BatchAnalysisJob** - Multiple text processing
3. **ExperimentJob** - Custom prompt testing

### Queue Monitoring
```bash
# Start queue worker
php artisan queue:work redis --verbose

# Monitor queue status
php artisan queue:monitor

# Restart all workers
php artisan queue:restart
```

## 📈 Performance Expectations

### Processing Times
- **Single Text**: 5-15 seconds per model
- **Batch Analysis** (100 texts): 15-45 minutes depending on models
- **Experiment**: 1-5 minutes per prompt variation

### Accuracy Benchmarks
Based on ATSPARA validation data:
- **Claude 4**: ~85% precision, ~78% recall
- **Gemini 2.5 Pro**: ~82% precision, ~75% recall  
- **GPT-4o**: ~80% precision, ~73% recall

## 🎓 Academic Context

### Research Questions
1. How effectively can LLMs detect propaganda in Lithuanian text?
2. Which LLM performs best for specific propaganda techniques?
3. How does custom prompt engineering affect detection accuracy?
4. What are the limitations of automated propaganda detection?

### Expected Outcomes
- Quantitative comparison of LLM performance for Lithuanian propaganda detection
- Optimization strategies for prompt engineering
- Guidelines for practical deployment of automated propaganda detection
- Academic publication on LLM effectiveness for Baltic language propaganda analysis

## 🔍 Testing Strategy

### Unit Tests
- LLM service implementations
- Metrics calculation accuracy
- Model configuration validation

### Feature Tests
- Analysis workflow end-to-end
- API endpoint functionality
- Experiment creation and execution

### Integration Tests
- LLM API connectivity
- Database relationship integrity
- Queue processing reliability

## 📝 Data Privacy & Ethics

### Data Handling
- Text content stored temporarily for analysis
- No personal data collection
- API keys encrypted in environment
- Results anonymized for research

### Ethical Considerations
- Tool designed for research and media literacy
- Not intended for censorship or content blocking
- Results require human interpretation
- Bias awareness in LLM outputs

## 🚀 Deployment Requirements

### Production Environment
- **PHP**: 8.4.7+
- **MySQL**: 8.0+ (or SQLite for development)
- **Redis**: 6.0+ (REQUIRED)
- **Nginx**: Latest stable
- **Supervisor**: For queue worker management

### Development Environment
- Same as production but SQLite acceptable
- Redis still required for queue functionality
- Debug mode enabled in .env

## 📞 Support & Maintenance

### Primary Contact
**Marijus Plančiūnas**: marijus.planciunas@mif.stud.vu.lt

### Academic Supervisor
**Prof. Dr. Darius Plikynas**: darius.plikynas@mif.vu.lt

### Data Source Contact
**ATSPARA Project**: https://www.atspara.mif.vu.lt/

---

This system represents a significant contribution to automated propaganda detection research for Lithuanian language, combining cutting-edge LLM technology with rigorous academic methodology and practical implementation considerations.