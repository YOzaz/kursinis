# Database Cleaning Tool

This documentation describes the database cleaning tools available for the Propaganda Analysis System.

## Overview

The system provides comprehensive database cleaning capabilities through both an Artisan command and a convenient shell script wrapper. These tools help you manage analysis data, reset the system for fresh starts, and maintain database cleanliness.

## Available Tools

### 1. Artisan Command: `analysis:clean`

Laravel Artisan command with full control over cleaning operations.

```bash
php artisan analysis:clean [options]
```

**Options:**
- `--force` - Skip confirmation prompt
- `--keep-expert` - Keep expert annotations, only remove LLM results
- `--jobs-only` - Only clean queue jobs, keep analysis data
- `--older-than=X` - Only clean data older than X days

### 2. Shell Script: `clean-analysis.sh`

User-friendly wrapper with preset configurations.

```bash
./clean-analysis.sh [command]
```

## Usage Examples

### Quick Commands (Shell Script)

```bash
# Show current database status
./clean-analysis.sh status

# Clean only queue jobs (safest option)
./clean-analysis.sh queue

# Full clean - remove LLM results, keep expert annotations
./clean-analysis.sh full

# Complete wipe - remove everything
./clean-analysis.sh wipe

# Clean data older than 30 days
./clean-analysis.sh old 30

# Force operations without confirmation
./clean-analysis.sh force-queue
./clean-analysis.sh force-full
./clean-analysis.sh force-wipe
```

### Advanced Commands (Artisan)

```bash
# Show help
php artisan analysis:clean --help

# Interactive full clean with confirmation
php artisan analysis:clean

# Keep expert annotations, remove only LLM results
php artisan analysis:clean --keep-expert

# Clean old data without confirmation
php artisan analysis:clean --older-than=7 --force

# Clean only failed/pending queue jobs
php artisan analysis:clean --jobs-only --force
```

## What Gets Cleaned

### Database Tables Affected

1. **analysis_jobs** - Analysis job metadata
2. **text_analysis** - Text content and annotations
3. **comparison_metrics** - Performance metrics
4. **model_results** - Individual model outputs
5. **jobs** - Laravel queue jobs
6. **failed_jobs** - Failed queue jobs

### Cleaning Modes

#### 1. Full Clean (`--keep-expert`)
- ✅ Keeps: Expert annotations, text content, job structure
- ❌ Removes: All LLM annotations, model results, metrics
- 🔄 Resets: Job status to 'pending'

**Use when:** You want to re-run LLM analysis without losing expert data

#### 2. Complete Wipe (default)
- ❌ Removes: Everything including expert annotations
- 🧹 Result: Clean slate for new analysis

**Use when:** Starting completely fresh or changing data structure

#### 3. Queue Only (`--jobs-only`)
- ✅ Keeps: All analysis data
- ❌ Removes: Only pending/failed queue jobs

**Use when:** Clearing stuck jobs without affecting analysis results

#### 4. Time-based (`--older-than=X`)
- 🗓️ Targets: Only data older than X days
- 📊 Preserves: Recent analysis work

**Use when:** Regular maintenance or clearing old test data

## Safety Features

### Confirmation Prompts
All operations show current database status and require confirmation unless `--force` is used.

### Transaction Safety
All cleaning operations run within database transactions - if anything fails, changes are rolled back.

### Cascade Deletes
Foreign key relationships ensure related data is properly cleaned when parent records are deleted.

## Monitoring and Feedback

### Before/After Statistics
The tool shows database statistics before and after cleaning:

```
📊 Current Database Status:
+--------------------+---------+
| Table              | Records |
+--------------------+---------+
| Analysis Jobs      | 7       |
| Text Analyses      | 7       |
| Comparison Metrics | 13      |
| Model Results      | 40      |
| Queue Jobs         | 0       |
| Failed Jobs        | 8       |
+--------------------+---------+
💾 Database file size: 1.2 MB
```

### Progress Tracking
Real-time feedback on what's being cleaned:

```
🧹 Cleaning ALL analysis data
Cleaning Model Results...
✓ Model Results cleaned
Cleaning Comparison Metrics...
✓ Comparison Metrics cleaned
```

## Common Use Cases

### 1. Development Reset
```bash
# Quick reset for development
./clean-analysis.sh force-full
```

### 2. Re-run Failed Analysis
```bash
# Clear failed jobs and reset LLM results
./clean-analysis.sh queue
php artisan analysis:clean --keep-expert --force
```

### 3. Production Maintenance
```bash
# Clean data older than 90 days
./clean-analysis.sh old 90
```

### 4. Debugging Queue Issues
```bash
# Clear only stuck jobs
./clean-analysis.sh force-queue
```

### 5. Complete System Reset
```bash
# Nuclear option - start fresh
./clean-analysis.sh force-wipe
```

## Error Handling

### Transaction Rollback
If any error occurs during cleaning, all changes are rolled back:

```
❌ Error during cleanup: SQLSTATE[23000]: Integrity constraint violation
Database cleanup rolled back - no changes made
```

### Environment Validation
The shell script validates the environment before running:

```
❌ Error: Not in Laravel project directory
Please run this script from the project root directory.
```

## Performance Considerations

### Large Datasets
For databases with millions of records, consider:
- Running during low-usage hours
- Using time-based cleaning (`--older-than`) for gradual cleanup
- Monitoring disk space during operations

### SQLite vs MySQL
- **SQLite**: Uses `TRUNCATE` equivalent for faster cleaning
- **MySQL**: Supports true `TRUNCATE` operations for optimal performance

## Best Practices

1. **Always backup** before major cleaning operations
2. **Use `--keep-expert`** when re-running analysis to preserve valuable annotations
3. **Regular maintenance** with `--older-than` to prevent database bloat
4. **Test with `status`** command first to see what will be affected
5. **Use `--force`** only in scripts, interactive mode for manual operations

## Troubleshooting

### Command Not Found
```bash
# Ensure you're in the project directory
cd /path/to/propaganda-analysis
./clean-analysis.sh help
```

### Permission Denied
```bash
# Make script executable
chmod +x clean-analysis.sh
```

### Database Lock Errors
```bash
# Stop queue workers before cleaning
sudo supervisorctl stop propaganda_worker:*
./clean-analysis.sh full
sudo supervisorctl start propaganda_worker:*
```

### Foreign Key Constraints
The tool handles foreign key relationships automatically, but if you encounter issues:

```bash
# Check for orphaned records
php artisan tinker
# Then investigate relationships manually
```

## Integration with Workflows

### CI/CD Pipeline
```yaml
# Example GitHub Actions step
- name: Clean test database
  run: ./clean-analysis.sh force-wipe
```

### Cron Jobs
```bash
# Weekly cleanup of old data
0 2 * * 0 cd /path/to/project && ./clean-analysis.sh force-old 30
```

### Deployment Scripts
```bash
#!/bin/bash
# deployment.sh
git pull
composer install --no-dev
./clean-analysis.sh force-queue  # Clear any stuck jobs
php artisan migrate --force
sudo supervisorctl restart propaganda_worker:*
```

---

**Author:** Marijus Plančiūnas  
**Course:** Kursinis darbas, VU MIF  
**Last Updated:** 2025-06-06