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

# Asinchroninis apdorojimas (rekomenduojama)
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

**Produktyvai aplinkai:**
```bash
# Supervisor arba systemd su Redis queue
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --memory=512
```

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

Sukurti `/etc/supervisor/conf.d/propaganda-worker.conf`:

```ini
[program:propaganda-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --memory=512
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log
stopwaitsecs=3600
```

Paleisti supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start propaganda-worker:*
```

### 3. Redis monitoring

```bash
# Stebėti Redis aktyvumą
redis-cli monitor

# Patikrinti Redis atminties naudojimą  
redis-cli info memory

# Patikrinti queue status
php artisan queue:monitor
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

### 2. API testavimas

```bash
# Vieno teksto analizė (su sync queue)
curl -X POST http://propaganda.local/api/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "text_id": "test_1",
    "content": "Testas propagandos technikai",
    "models": ["gpt-4.1"]
  }'

# Batch analizės testavimas
curl -X POST http://propaganda.local/api/batch-analyze \
  -H "Content-Type: application/json" \
  -d @test_data.json

# Statuso tikrinimas
curl http://propaganda.local/api/status/{job_id}
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

## Sistemos architektūra

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
                       │ (3 proc.)   │
                       └─────────────┘
                               │
                    ┌──────────┼──────────┐
                    │          │          │
              ┌──────▼───┐ ┌───▼────┐ ┌───▼──────┐
              │ Claude   │ │ Gemini │ │ OpenAI   │
              │ API      │ │ API    │ │ API      │
              └──────────┘ └────────┘ └──────────┘
```

## Darbo eigā

1. **Web UI**: Vartotojas įkelia JSON failą
2. **Laravel**: Sukuria `AnalysisJob` ir `TextAnalysis` įrašus
3. **Redis Queue**: Pasiskirsto `AnalyzeTextJob` darbai
4. **Workers**: Apdoroja tekstus su LLM API
5. **Metrics**: Skaičiuoja precision/recall/F1/Kappa
6. **Export**: Generuoja CSV su rezultatais

## Baigiamosios pastabos

✅ **Redis yra BŪTINAS** - be jo sistema neveiks efektyviai
✅ **Queue workers** turi būti paleisti asinchroniniam apdorojimui  
✅ **API raktai** turi būti galiojantys testavimui
✅ **Supervisor** rekomenduojama production aplinkoje