# Production Setup Instructions

## 🚀 Manual Production Deployment

Jei automatinis `deploy-production.sh` script'as neveikia, atlikite šiuos žingsnius rankiniu būdu:

### 1. Instaliuoti Supervisor

```bash
sudo apt update
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

### 2. Sukonfigūruoti Supervisor Workers

```bash
# Nukopijuoti worker konfigūraciją
sudo cp supervisor-config.conf /etc/supervisor/conf.d/propaganda-workers.conf

# Perkrauti supervisor konfigūraciją
sudo supervisorctl reread
sudo supervisorctl update

# Paleisti workers
sudo supervisorctl start propaganda-worker:*
sudo supervisorctl start propaganda-worker-batch:*
```

### 3. Patikrinti Worker Statusą

```bash
# Žiūrėti visų worker statusą
sudo supervisorctl status

# Turėtumėte matyti:
# propaganda-worker:propaganda-worker_00    RUNNING   pid 1234, uptime 0:00:01
# propaganda-worker:propaganda-worker_01    RUNNING   pid 1235, uptime 0:00:01
# propaganda-worker:propaganda-worker_02    RUNNING   pid 1236, uptime 0:00:01
# propaganda-worker-batch:propaganda-worker-batch_00  RUNNING   pid 1237, uptime 0:00:01
# propaganda-worker-batch:propaganda-worker-batch_01  RUNNING   pid 1238, uptime 0:00:01
```

### 4. Monitorinti Workers

```bash
# Žiūrėti worker logs
tail -f storage/logs/worker.log
tail -f storage/logs/worker-batch.log

# Žiūrėti Laravel logs
tail -f storage/logs/laravel.log

# Restart worker'ius jei reikia
sudo supervisorctl restart propaganda-worker:*
```

### 5. Production Optimizacijos (Jau Atliktos)

✅ APP_ENV=production
✅ APP_DEBUG=false
✅ LOG_LEVEL=info
✅ QUEUE_CONNECTION=redis
✅ Config cached
✅ Routes cached
✅ Views cached
✅ Autoloader optimized

## 🎯 Queue Worker Konfigūracija

### Regular Workers (3 procesai):
- **Queue**: `default`
- **Command**: `php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --memory=512`
- **Log**: `storage/logs/worker.log`

### Batch Workers (2 procesai):
- **Queue**: `batch`
- **Command**: `php artisan queue:work redis --queue=batch --sleep=3 --tries=3 --max-time=3600 --memory=512`
- **Log**: `storage/logs/worker-batch.log`

## 🔧 Troubleshooting

### Worker'iai nepaleidžiasi:

```bash
# Patikrinti supervisor statusą
sudo systemctl status supervisor

# Patikrinti konfigūracijos klaidas
sudo supervisorctl tail propaganda-worker:propaganda-worker_00

# Restart supervisor
sudo systemctl restart supervisor
```

### Redis problemos:

```bash
# Patikrinti Redis
redis-cli ping  # Turi grąžinti: PONG

# Patikrinti Redis atminties naudojimą
redis-cli info memory

# Išvalyti Redis queue jei reikia
redis-cli flushdb
```

### Laravel problemos:

```bash
# Išvalyti cache
php artisan cache:clear
php artisan config:clear

# Perkurti cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 📊 Performance Monitoring

### Queue Monitoring:

```bash
# Žiūrėti queue dydį
redis-cli llen propaganda_analysis_database_default

# Monitorinti queue aktyvumą
redis-cli monitor | grep propaganda
```

### System Resources:

```bash
# CPU ir RAM naudojimas
top -p $(pgrep -f "queue:work" | tr '\n' ',' | sed 's/,$//')

# Disk naudojimas
df -h
du -sh storage/logs/
```

## 🎯 Production Ready Checklist

✅ **Environment**: Production mode enabled
✅ **Debug**: Disabled
✅ **Cache**: All Laravel caches optimized  
✅ **Queue**: Redis with 5 worker processes
✅ **Logs**: Proper log levels and rotation
✅ **Security**: Sensitive data protected
✅ **Monitoring**: Supervisor + log monitoring
✅ **Performance**: Autoloader optimized

## 🚨 Important Notes

1. **Never restart workers during active analysis** - use `supervisorctl` to gracefully stop/start
2. **Monitor memory usage** - workers auto-restart after 512MB usage
3. **Log rotation** - setup logrotate for production logs
4. **Backup strategy** - backup database and Redis data regularly

## 🔄 Maintenance Commands

```bash
# Gracefully restart all workers
sudo supervisorctl restart propaganda-worker:* propaganda-worker-batch:*

# Stop all workers (for maintenance)
sudo supervisorctl stop propaganda-worker:* propaganda-worker-batch:*

# Clear failed jobs
php artisan queue:flush

# Monitor active jobs
php artisan queue:monitor
```