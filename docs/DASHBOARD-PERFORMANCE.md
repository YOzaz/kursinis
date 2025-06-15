# Dashboard Performance Optimization

## Overview

The dashboard performance has been optimized to handle large datasets efficiently through caching and database indexing.

## Performance Improvements

### 1. Caching Strategy

The dashboard now uses a caching layer (`CachedStatisticsService`) that:

- **Cache Duration**: 5 minutes (300 seconds)
- **Cache Keys**: Separate cache keys for different statistics components
- **Automatic Invalidation**: Cache is automatically cleared when data changes

#### Cached Components:
- Global statistics (total analyses, texts, metrics)
- Model performance metrics
- Average execution times
- Top propaganda techniques
- Time series data

### 2. Database Query Optimization

The original `StatisticsService` loaded all records into memory, causing performance issues with large datasets. The new `CachedStatisticsService` uses:

- **Optimized Queries**: Direct SQL queries with aggregations done at the database level
- **Reduced Memory Usage**: No loading of entire record sets into memory
- **JSON Query Optimization**: Efficient JSON column queries for propaganda detection

### 3. Database Indexes

New indexes have been added to improve query performance:

#### Model Results Table:
- `idx_model_key_status_execution`: Composite index on (model_key, status, execution_time_ms)
- `idx_status_annotations`: Composite index on (status, job_id)

#### Comparison Metrics Table:
- `idx_model_metrics`: Composite index on (model_name, f1_score, precision, recall)
- `idx_execution_time`: Composite index on (model_name, analysis_execution_time_ms)

#### Analysis Jobs Table:
- `idx_created_at_desc`: Index on created_at for recent jobs queries

## Implementation Details

### CachedStatisticsService

Located at: `app/Services/CachedStatisticsService.php`

Key methods:
- `getGlobalStatistics()`: Returns cached global dashboard statistics
- `getOptimizedModelPerformanceStats()`: Calculates model performance using optimized queries
- `getOptimizedConfusionMatrix()`: Calculates confusion matrix using database aggregations
- `clearCache()`: Manually clear all dashboard caches
- `invalidateCache()`: Static method called by observers when data changes

### Cache Observer

Located at: `app/Observers/DashboardCacheObserver.php`

Automatically invalidates cache when:
- New analysis jobs are created
- Analysis results are updated
- Comparison metrics are calculated
- Model results are saved

### Service Provider

Located at: `app/Providers/DashboardCacheServiceProvider.php`

Registers observers for automatic cache invalidation on the following models:
- AnalysisJob
- TextAnalysis
- ComparisonMetric
- ModelResult

## Manual Cache Management

### Clear Cache via Artisan

```bash
php artisan tinker
>>> \App\Services\CachedStatisticsService::invalidateCache()
```

### Clear Cache Programmatically

```php
use App\Services\CachedStatisticsService;

// Clear all dashboard caches
CachedStatisticsService::invalidateCache();
```

## Performance Testing

### Generate Test Data

A seeder is available to generate dummy data for performance testing:

```bash
php artisan db:seed --class=DashboardPerformanceSeeder
```

This creates:
- 100 analysis jobs
- ~3,000 text analyses
- ~12,000 comparison metrics
- ~18,000 model results

### Monitoring Performance

The dashboard load time should be under 500ms even with large datasets, compared to 30+ seconds without optimization.

## Troubleshooting

### Cache Not Updating

If the dashboard shows stale data:

1. Check if cache is enabled in `.env`:
   ```
   CACHE_DRIVER=redis  # or 'file'
   ```

2. Clear the cache manually:
   ```bash
   php artisan cache:clear
   ```

3. Check observer registration in `bootstrap/app.php`

### Slow Queries

If queries are still slow:

1. Check if migrations have been run:
   ```bash
   php artisan migrate:status
   ```

2. Verify indexes exist:
   ```bash
   php artisan tinker
   >>> DB::select("SHOW INDEX FROM model_results")
   ```

3. Analyze query performance:
   ```bash
   php artisan tinker
   >>> DB::enableQueryLog()
   >>> // Run dashboard request
   >>> dd(DB::getQueryLog())
   ```

## Future Improvements

1. **Partial Cache Invalidation**: Only invalidate affected cache segments
2. **Background Processing**: Move heavy calculations to background jobs
3. **Read Replicas**: Use database read replicas for dashboard queries
4. **Materialized Views**: Pre-calculate statistics in database views