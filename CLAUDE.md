# Lithuanian Propaganda Detection System

## Project Overview

This is a Laravel-based system for detecting propaganda techniques in Lithuanian text using multiple AI models (Claude, GPT, Gemini). The system compares AI model results against expert annotations to measure performance accuracy.

## Key Features

- **Multi-model Analysis**: Supports Claude Opus/Sonnet 4, GPT-4.1/4o, Gemini 2.5 Pro/Flash
- **Expert Annotation Comparison**: Validates AI results against human expert annotations
- **Advanced Metrics**: Precision, Recall, F1-score, Cohen's Kappa, Position Accuracy
- **Confusion Matrix Analysis**: Text-level propaganda detection statistics (TP, FP, TN, FN)
- **Batch Processing**: Queue-based analysis of multiple texts
- **Real-time Monitoring**: Mission Control interface for system status
- **Export Capabilities**: JSON and CSV export of results

## Architecture

- **Backend**: Laravel 11 with MySQL database
- **Queue System**: Laravel Queues for background processing
- **AI Integration**: Service-based architecture for multiple LLM providers
- **Frontend**: Blade templates with Bootstrap and Chart.js
- **Testing**: Comprehensive PHPUnit test suite

## Development Commands

- `php artisan test` - Run test suite
- `php artisan queue:work` - Start queue worker
- `php artisan tinker` - Laravel REPL for debugging
- `php artisan serve` - Start development server

## Documentation

- See `docs/` directory for detailed documentation
- `docs/METRICS-GUIDE.md` - Comprehensive metrics explanation
- `docs/ARCHITECTURE.md` - System architecture details
- `README.md` - Setup and installation instructions