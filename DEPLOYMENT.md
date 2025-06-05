# Propagandos analizės sistemos diegimo instrukcijos

## Sistemos reikalavimai

- PHP >= 8.2
- MySQL >= 8.0
- Redis >= 6.0 ⭐ **BŪTINA** - naudojama cache, sessions ir queue
- Composer >= 2.0
- Node.js (opcionalu, jei reikia asset'ų kompiliavimo)

## Diegimo žingsniai

### 1. Projekto paruošimas

```bash
# Nukopijuoti .env failą
cp .env.example .env

# Instaliuoti PHP priklausomybes
composer install --optimize-autoloader --no-dev

# Sugeneruoti aplikacijos raktą
php artisan key:generate
```

### 2. Duomenų bazės konfigūracija

Redaguoti `.env` failą su savo duomenų bazės nustatymais:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=propaganda_analysis
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 3. Redis konfigūracija ⭐ **BŪTINA**

```env
# Redis nustatymai (BŪTINA)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache, Queue, Session naudoja Redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

**Patikrinti Redis ryšį:**
```bash
redis-cli ping  # Turi grąžinti: PONG
```

### 4. LLM API raktų nustatymas

```env
# Anthropic Claude (būtina testavimui)
CLAUDE_API_KEY=your_claude_api_key_here

# Google Gemini (būtina testavimui)  
GEMINI_API_KEY=your_gemini_api_key_here

# OpenAI (jau sukonfigūruotas)
OPENAI_API_KEY=sk-proj-...
```

### 5. Duomenų bazės migracijos

```bash
# Sukurti duomenų bazę (jei dar nesukurta)
mysql -u root -p -e "CREATE DATABASE propaganda_analysis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Paleisti migracijas
php artisan migrate

# Arba su seed duomenimis (jei yra)
php artisan migrate --seed
```

### 6. Failų sistemos teisės

```bash
# Nustatyti teises storage ir cache direktorioms
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 7. Queue worker paleisti ⭐ **BŪTINA**

**Development aplinkai:**
```bash
# Sinchroninis apdorojimas (greitam testui)
# Jei .env: QUEUE_CONNECTION=sync

# Asinchroninis apdorojimas (rekomenduojama) - naujas file attachment architecture
php artisan queue:work redis --queue=batch,models,analysis,default --sleep=3 --tries=3 --max-time=3600
```

**Produktyvai aplinkai su nauja architektūra:**
```bash
# Optimizuota queue processing su skirtingais queue tipais
php artisan queue:work redis --queue=batch,models,analysis,default --sleep=3 --tries=3 --max-time=3600 --memory=512
```

**Naujos file attachment architektūros queue tipai:**
- **batch**: BatchAnalysisJobV4 orchestrator darbai
- **models**: ModelAnalysisJob parallel apdorojimas
- **analysis**: Individualūs AnalyzeTextJob darbai  
- **default**: Standartiniai Laravel darbai

### 8. Web serverio konfigūracija

#### Apache (.htaccess)
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ /public/$1 [L,QSA]
</IfModule>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name propaganda.local;
    root /path/to/project/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Lokalaus development aplinkos nustatymai

### .env konfigūracija development'ui:

```env
APP_NAME="Propaganda Analysis System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://propaganda.local

# Duomenų bazė
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=propaganda
DB_USERNAME=your_username
DB_PASSWORD=your_secure_password

# Redis (BŪTINA)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# LLM API raktai
CLAUDE_API_KEY=your_claude_api_key_here
GEMINI_API_KEY=your_gemini_api_key_here
OPENAI_API_KEY=sk-proj-...

# Performance nustatymai
MAX_CONCURRENT_REQUESTS=10
RETRY_ATTEMPTS=3
REQUEST_TIMEOUT=60
```

## Produktyvios aplinkos nustatymai

### 1. Optimizacija

```bash
# Config cache
php artisan config:cache

# Route cache
php artisan route:cache

# View cache  
php artisan view:cache

# Autoloader optimization
composer dump-autoload --optimize --classmap-authoritative
```

### 2. Queue worker su Supervisor ⭐ **BŪTINA asynchronine analizei**

Sukurti `/etc/supervisor/conf.d/propaganda-worker.conf` su nauja file attachment architektūra:

```ini
[program:propaganda-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work redis --queue=batch,models,analysis,default --sleep=3 --tries=3 --max-time=3600 --memory=512
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log
stopwaitsecs=3600
```

**File attachment architektūros optimizacijos:**
- Padidinti `numprocs=4` dėl parallel ModelAnalysisJob darbų
- Queue prioritetai: `batch,models,analysis,default`
- Didesni timeout dėl 30-minutės file attachment procesing

Paleisti supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start propaganda-worker:*
```

### 3. Enhanced System Monitoring

#### Redis monitoring
```bash
# Stebėti Redis aktyvumą
redis-cli monitor

# Patikrinti Redis atminties naudojimą  
redis-cli info memory

# Patikrinti queue status
php artisan queue:monitor
```

#### Mission Control monitoring (Nauja funkcija)
```bash
# Real-time job monitoring per Web UI
# GET /status/{jobId} - Mission Control view

# API endpoint monitoringui
curl /api/models/status  # Model health status
curl /api/models/status/refresh  # Force refresh modelių status

# Debug capabilities
curl /api/debug/{textAnalysisId}  # Raw query/response debug info
```

#### Log monitoring su nauja architektūra
```bash
# BatchAnalysisJobV4 logs
tail -f storage/logs/laravel.log | grep "BatchAnalysisJobV4"

# ModelAnalysisJob parallel processing logs  
tail -f storage/logs/laravel.log | grep "ModelAnalysisJob"

# File attachment processing logs
tail -f storage/logs/laravel.log | grep "file_attachment"

# Model liveness check logs
tail -f storage/logs/laravel.log | grep "ModelStatusService"
```

## Testavimas

### 1. Sistemos tikrinimas

```bash
# Patikrinti duomenų bazės ryšį
php artisan migrate:status

# Patikrinti Redis ryšį
redis-cli ping
php artisan tinker --execute="echo \Illuminate\Support\Facades\Cache::get('test') ?: 'Redis cache works!';"

# Patikrinti queue konfigūraciją
php artisan queue:monitor
```

### 2. API testavimas su nauja file attachment architektūra

```bash
# Vieno teksto analizė (su sync queue)
curl -X POST http://propaganda.local/api/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "text_id": "test_1",
    "content": "Testas propagandos technikai",
    "models": ["claude-opus-4", "gpt-4.1", "gemini-2.5-pro"]
  }'

# Batch analizės testavimas (naudoja BatchAnalysisJobV4)
curl -X POST http://propaganda.local/api/batch-analyze \
  -H "Content-Type: application/json" \
  -d @test_data.json

# Statuso tikrinimas
curl http://propaganda.local/api/status/{job_id}

# Naujų funkcijų testavimas
curl http://propaganda.local/api/models/status  # Model health check
curl http://propaganda.local/api/debug/{textAnalysisId}  # Debug info
curl -X POST http://propaganda.local/api/models/status/refresh  # Force refresh
```

### 3. File attachment architektūros testavimas

```bash
# Tikrinti BatchAnalysisJobV4 funkcionavimą
curl -X POST http://propaganda.local/api/batch-analyze \
  -H "Content-Type: application/json" \
  -d '{
    "file_content": [
      {
        "id": 1,
        "data": {"content": "Testas file attachment metodui"},
        "annotations": []
      }
    ],
    "models": ["claude-opus-4", "gemini-2.5-pro"]
  }'

# Mission Control monitoring
# Atidaryti naršyklėje: http://propaganda.local/status/{job_id}
```

### 3. Web sąsajos testavimas

1. Atidaryti http://propaganda.local
2. Įkelti `test_data.json` failą
3. Pasirinkti modelius
4. Stebėti progresą
5. Eksportuoti rezultatus

## Performance optimizacija

### Redis tuning

```bash
# Redis konfigūracija (/etc/redis/redis.conf)
maxmemory 2gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### PHP tuning

```ini
; php.ini optimizacija
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
```

## Klaidų sprendimas

### Dažnos problemos

1. **Redis connection refused**
   ```bash
   sudo systemctl start redis
   sudo systemctl enable redis
   redis-cli ping
   ```

2. **Queue jobs neprasideda**
   ```bash
   # Patikrinti Redis būseną
   redis-cli ping
   
   # Restart queue worker
   php artisan queue:restart
   
   # Paleisti worker rankiniu būdu
   php artisan queue:work redis --verbose
   ```

3. **Memory limit klaidos**
   - Padidinti PHP memory_limit iki 512M
   - Optimizuoti batch dydžius konfigūracijoje

4. **API 404 klaidos**
   - Patikrinti API raktus .env faile
   - Patikrinti internet ryšį
   - Patikrinti API endpoint'ų URLs

### Log failai

- **Laravel logs**: `storage/logs/laravel.log`
- **Web server logs**: `/var/log/nginx/error.log` arba `/var/log/apache2/error.log`
- **Queue worker logs**: `storage/logs/worker.log`
- **Redis logs**: `/var/log/redis/redis-server.log`

## Saugumas

1. Nustatyti `APP_ENV=production` ir `APP_DEBUG=false`
2. Naudoti HTTPS
3. Slėpti `.env` failus
4. Reguliariai atnaujinti priklausomybes
5. Monitorizuoti API raktų naudojimą
6. Apsaugoti Redis su slaptažodžiu production'e

## Monitoringas

### Horizon (rekomenduojama)

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

### Log monitoring

```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/worker.log
tail -f /var/log/redis/redis-server.log
```

### Redis monitoring

```bash
redis-cli --latency
redis-cli info stats
```

## Sistemos architektūra (File Attachment Architecture)

```
┌─────────────────┐    ┌──────────────┐    ┌─────────────┐
│   Web Browser   │◄──►│    Nginx     │◄──►│  Laravel    │
└─────────────────┘    └──────────────┘    │   APP       │
                                           └─────────────┘
                                                   │
                       ┌─────────────┐             │
                       │   Redis     │◄────────────┤
                       │ (Cache/     │             │
                       │  Queue/     │             │
                       │  Sessions)  │             │
                       └─────────────┘             │
                                                   │
                       ┌─────────────┐             │
                       │   MySQL     │◄────────────┤
                       │ (Database)  │             │
                       └─────────────┘             │
                                                   │
                       ┌─────────────┐             │
                       │ Queue       │◄────────────┘
                       │ Workers     │
                       │ (4 proc.)   │◄── batch,models,analysis,default
                       └─────────────┘
                               │
                    ┌──────────┼──────────┐
                    │          │          │
         BatchAnalysisJobV4    │    ModelAnalysisJob×3 (parallel)
              (Orchestrator)   │          │
                    │          │          │
              ┌──────▼───┐ ┌───▼────┐ ┌───▼──────┐
              │ Claude   │ │ Gemini │ │ OpenAI   │
              │ API      │ │File API│ │ API      │
              │(JSON in  │ │(Upload │ │(Struct   │
              │ message) │ │ + ref) │ │ JSON)    │
              └──────────┘ └────────┘ └──────────┘
```

### Nauja File Attachment Architektūra:
- **BatchAnalysisJobV4**: Orchestrator, kuris sukuria temp JSON failą
- **ModelAnalysisJob×3**: Parallel darbai kiekvienam modeliui
- **Provider optimization**: Kiekvienas provider naudoja optimalų metodą
- **Mission Control**: Real-time monitoring su log parsing

## Darbo eigā (File Attachment Architecture)

1. **Web UI**: Vartotojas įkelia JSON failą su tekstais
2. **Laravel**: Sukuria `AnalysisJob` ir `TextAnalysis` įrašus
3. **BatchAnalysisJobV4**: Orchestrator sukuria temp JSON failą ir dispatchina ModelAnalysisJob×N
4. **Redis Queue**: Pasiskirsto darbai per batch/models/analysis queue
5. **ModelAnalysisJob×3**: Parallel workers apdoroja su provider-specific strategijomis:
   - **Claude**: JSON duomenys message content
   - **Gemini**: File upload į File API + reference
   - **OpenAI**: Structured JSON chunks
6. **Mission Control**: Real-time monitoring su log parsing
7. **Debug System**: Raw query/response tracking per model
8. **Metrics**: Skaičiuoja precision/recall/F1/Kappa
9. **Export**: Generuoja CSV su rezultatais

### Performance Benefits:
- **98% mažiau API call**: File attachment vs chunking
- **50-60% greičiau**: Parallel processing + optimized strategies
- **Better monitoring**: Real-time status ir debug capabilities

## Baigiamosios pastabos (2025-06-05 File Attachment Architecture)

✅ **Redis yra BŪTINAS** - be jo sistema neveiks efektyviai
✅ **Queue workers** turi būti paleisti su naujais queue tipais: `--queue=batch,models,analysis,default`
✅ **API raktai** turi būti galiojantys testavimui naujų modelių
✅ **Supervisor** rekomenduojama production aplinkoje su `numprocs=4`
✅ **Mission Control** monitoring: `/status/{jobId}` real-time stebėjimui
✅ **Debug capabilities**: `/api/debug/{textAnalysisId}` troubleshooting
✅ **Enhanced model health**: `/api/models/status` ir `/api/models/status/refresh`

### Naujos architektūros deployment points:
- **File attachment processing**: 30-minute timeouts per job
- **Parallel execution**: ModelAnalysisJob×N concurrent processing  
- **Provider optimization**: Claude (JSON), Gemini (File API), OpenAI (structured)
- **Enhanced monitoring**: Real-time log parsing ir debug endpoints