<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Services\ModelStatusService;

/**
 * Aplikacijos paslaugų teikėjas.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registruoti aplikacijos paslaugas.
     */
    public function register(): void
    {
        $this->app->singleton(ModelStatusService::class);
    }

    /**
     * Paleisti aplikacijos paslaugas.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 pagination views
        Paginator::useBootstrap();
        Paginator::defaultView('pagination::bootstrap-5');
        Paginator::defaultSimpleView('pagination::simple-bootstrap-5');
    }
}