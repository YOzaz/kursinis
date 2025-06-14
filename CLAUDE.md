# Lithuanian Propaganda Detection System

## Project Overview

This is a Laravel-based system for detecting propaganda techniques in Lithuanian text using multiple AI models (Claude, GPT, Gemini). The system compares AI model results against expert annotations to measure performance accuracy.

## Key Features

- **Multi-model Analysis**: Supports Claude Opus/Sonnet 4, GPT-4.1/4o, Gemini 2.5 Pro/Flash
- **User Management**: Multi-user system with role-based access (superadmin, admin, user)
- **Personal API Keys**: Users can configure their own API keys for AI providers
- **Expert Annotation Comparison**: Validates AI results against human expert annotations
- **Advanced Metrics**: Precision, Recall, F1-score, Cohen's Kappa, Position Accuracy (IAA formula)
- **Audit Logging**: Complete audit trail of user actions and system changes
- **Confusion Matrix Analysis**: Text-level propaganda detection statistics (TP, FP, TN, FN)
- **Batch Processing**: Queue-based analysis of multiple texts
- **Real-time Monitoring**: Mission Control interface for system status
- **Export Capabilities**: JSON and CSV export of results

## Architecture

- **Backend**: Laravel 11 with MySQL database
- **Authentication**: User-based authentication with role-based access control
- **API Key Management**: Per-user API key storage with usage tracking
- **Queue System**: Laravel Queues for background processing
- **AI Integration**: Service-based architecture for multiple LLM providers
- **Audit System**: Complete logging of user actions and data changes
- **Frontend**: Blade templates with Bootstrap and Chart.js
- **Testing**: Comprehensive PHPUnit test suite

## Development Commands

- `php artisan test` - Run test suite
- `php artisan queue:work` - Start queue worker
- `php artisan tinker` - Laravel REPL for debugging
- `php artisan serve` - Start development server

## User Management Commands

- `php artisan user:create-superadmin` - Create or upgrade superadmin user
- `php artisan migrate` - Run database migrations for user management
- `php artisan metrics:recalculate-position-accuracy` - Recalculate position accuracy using IAA formula

## Position Accuracy Formula

The system now uses the Inter-Annotator Agreement (IAA) formula for position accuracy:

```
Agreement = |A ∩ B| / min(|A|, |B|)
```

Where:
- `A ∩ B` = Number of overlapping characters between expert and AI annotations
- `min(|A|, |B|)` = The smaller annotation set (minimum total characters annotated)

This formula provides a more accurate measure of annotation agreement compared to the previous method.

## Documentation

- See `docs/` directory for detailed documentation
- `docs/METRICS-GUIDE.md` - Comprehensive metrics explanation
- `docs/ARCHITECTURE.md` - System architecture details
- `README.md` - Setup and installation instructions