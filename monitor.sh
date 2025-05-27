#!/bin/bash

echo "🎯 Propaganda Analysis System - Production Status"
echo "================================================="
echo ""

# System status
echo "🌐 Website Status:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://propaganda.local)
if [ "$HTTP_CODE" = "200" ]; then
    echo "   ✅ http://propaganda.local - ONLINE ($HTTP_CODE)"
else
    echo "   ❌ http://propaganda.local - OFFLINE ($HTTP_CODE)"
fi
echo ""

# Redis status
echo "🔧 Redis Status:"
if redis-cli ping > /dev/null 2>&1; then
    echo "   ✅ Redis - CONNECTED"
    QUEUE_SIZE=$(redis-cli llen propaganda_analysis_database_default 2>/dev/null || echo "0")
    echo "   📊 Queue size: $QUEUE_SIZE jobs"
else
    echo "   ❌ Redis - DISCONNECTED"
fi
echo ""

# Workers status
echo "👷 Queue Workers:"
WORKER_COUNT=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
if [ "$WORKER_COUNT" -gt 0 ]; then
    echo "   ✅ $WORKER_COUNT workers running"
    ps aux | grep "queue:work" | grep -v grep | awk '{print "   🔸 Worker PID " $2 " - " $11 " " $12 " " $13}'
else
    echo "   ❌ No workers running"
fi
echo ""

# Database status
echo "💾 Database Status:"
if mysql -u propaganda -pYLlLk69bL8OK propaganda -e "SELECT 1" > /dev/null 2>&1; then
    echo "   ✅ MySQL - CONNECTED"
    JOB_COUNT=$(mysql -u propaganda -pYLlLk69bL8OK propaganda -se "SELECT COUNT(*) FROM analysis_jobs" 2>/dev/null || echo "0")
    ANALYSIS_COUNT=$(mysql -u propaganda -pYLlLk69bL8OK propaganda -se "SELECT COUNT(*) FROM text_analysis" 2>/dev/null || echo "0")
    echo "   📊 Total jobs: $JOB_COUNT"
    echo "   📊 Total analyses: $ANALYSIS_COUNT"
else
    echo "   ❌ MySQL - DISCONNECTED"
fi
echo ""

# Recent logs
echo "📝 Recent Activity (last 5 lines):"
if [ -f "storage/logs/laravel.log" ]; then
    tail -5 storage/logs/laravel.log | sed 's/^/   /'
else
    echo "   No log file found"
fi
echo ""

echo "🔄 To monitor in real-time:"
echo "   Worker logs: tail -f storage/logs/worker.log"
echo "   App logs:    tail -f storage/logs/laravel.log"
echo "   Redis monitor: redis-cli monitor | grep propaganda"