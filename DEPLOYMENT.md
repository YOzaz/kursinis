# Propagandos analizės sistemos diegimo instrukcijos

## Sistemos reikalavimai

- PHP >= 8.2
- MySQL >= 8.0
- Redis >= 6.0
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

### 3. Redis konfigūracija

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### 4. LLM API raktų nustatymas

```env
# Anthropic Claude
CLAUDE_API_KEY=your_claude_api_key_here

# Google Gemini
GEMINI_API_KEY=your_gemini_api_key_here

# OpenAI
OPENAI_API_KEY=your_openai_api_key_here
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

### 7. Queue worker paleisti

```bash
# Produktyviai aplinkai - naudoti supervisor arba systemd
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600

# Development aplinkai
php artisan queue:listen redis
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
    server_name your_domain.com;
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

### 2. Queue worker su Supervisor

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

### 3. Logging

Sukurti log failus:
```bash
touch storage/logs/laravel.log
chmod 775 storage/logs/laravel.log
chown www-data:www-data storage/logs/laravel.log
```

## Testavimas

### 1. Sistemos tikrinimas

```bash
# Patikrinti duomenų bazės ryšį
php artisan migrate:status

# Patikrinti Redis ryšį
php artisan redis:ping

# Patikrinti queue configuraciją
php artisan queue:monitor
```

### 2. API testavimas

```bash
# Vieno teksto analizė
curl -X POST http://your-domain.com/api/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "text_id": "test_1",
    "content": "Testas propagandos technikai",
    "models": ["claude-4"]
  }'

# Statuso tikrinimas
curl http://your-domain.com/api/status/{job_id}
```

## Klaidų sprendimas

### Dažnos problemos

1. **"Class not found" klaidos**
   ```bash
   composer dump-autoload
   php artisan clear-compiled
   php artisan cache:clear
   ```

2. **Permission denied klaidos**
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R 775 storage bootstrap/cache
   ```

3. **Queue jobs neprasideda**
   ```bash
   # Patikrinti Redis būseną
   redis-cli ping
   
   # Restart queue worker
   php artisan queue:restart
   ```

4. **Memory limit klaidos**
   - Padidinti PHP memory_limit
   - Optimizuoti batch dydžius konfigūracijoje

### Log failai

- **Laravel logs**: `storage/logs/laravel.log`
- **Web server logs**: `/var/log/nginx/error.log` arba `/var/log/apache2/error.log`
- **Queue worker logs**: `storage/logs/worker.log`

## Saugumas

1. Nustatyti `APP_ENV=production` ir `APP_DEBUG=false`
2. Naudoti HTTPS
3. Slėpti `.env` failus
4. Reguliariai atnaujinti priklausomybes
5. Monitorizuoti API raktų naudojimą

## Monitoringas

### Horizon (opcionalu)

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

### Log monitoring

```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/worker.log
```