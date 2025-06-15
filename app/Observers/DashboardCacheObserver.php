<?php

namespace App\Observers;

use App\Services\CachedStatisticsService;

class DashboardCacheObserver
{
    /**
     * Handle any model event that should invalidate the cache.
     */
    private function invalidateCache(): void
    {
        CachedStatisticsService::invalidateCache();
    }
    
    // Handle all model events with generic methods
    public function created($model): void
    {
        $this->invalidateCache();
    }
    
    public function updated($model): void
    {
        $this->invalidateCache();
    }
    
    public function deleted($model): void
    {
        $this->invalidateCache();
    }
    
    public function saved($model): void
    {
        $this->invalidateCache();
    }
    
    public function deleting($model): void
    {
        $this->invalidateCache();
    }
}