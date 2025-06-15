<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Models\ModelResult;
use App\Observers\DashboardCacheObserver;

class DashboardCacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register observers for cache invalidation
        AnalysisJob::observe(DashboardCacheObserver::class);
        TextAnalysis::observe(DashboardCacheObserver::class);
        ComparisonMetric::observe(DashboardCacheObserver::class);
        ModelResult::observe(DashboardCacheObserver::class);
    }
}