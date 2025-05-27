#!/bin/bash

echo "🚀 Deploying Propaganda Analysis System to Production..."

# Install Supervisor (if not installed)
echo "📦 Installing Supervisor..."
sudo apt update
sudo apt install -y supervisor

# Copy supervisor configuration
echo "⚙️ Setting up Supervisor configuration..."
sudo cp supervisor-config.conf /etc/supervisor/conf.d/propaganda-workers.conf

# Set proper file permissions
echo "🔐 Setting file permissions..."
chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache

# Optimize Laravel for production
echo "⚡ Optimizing Laravel for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer dump-autoload --optimize --classmap-authoritative

# Create log files for workers
echo "📄 Creating worker log files..."
touch storage/logs/worker.log
touch storage/logs/worker-batch.log
chmod 775 storage/logs/worker.log
chmod 775 storage/logs/worker-batch.log

# Restart and start supervisor services
echo "🔄 Starting Supervisor workers..."
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start propaganda-worker:*
sudo supervisorctl start propaganda-worker-batch:*

# Check worker status
echo "✅ Checking worker status..."
sudo supervisorctl status

# Test Redis connection
echo "🔧 Testing Redis connection..."
redis-cli ping

# Test system
echo "🧪 Testing system..."
curl -s -o /dev/null -w "%{http_code}" http://propaganda.local

echo ""
echo "🎯 Production deployment completed!"
echo ""
echo "📊 Monitor workers with:"
echo "   sudo supervisorctl status"
echo "   tail -f storage/logs/worker.log"
echo ""
echo "🌐 Access system at: http://propaganda.local"
echo "📝 API documentation: http://propaganda.local/api/status/{job_id}"
echo ""