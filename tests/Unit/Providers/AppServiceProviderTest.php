<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    private AppServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new AppServiceProvider($this->app);
    }

    public function test_register_method_exists()
    {
        $this->assertTrue(method_exists($this->provider, 'register'));
        
        // Call register method (should not throw exceptions)
        $this->provider->register();
        
        // Since register method is empty, we just verify it doesn't crash
        $this->assertTrue(true);
    }

    public function test_boot_method_configures_pagination()
    {
        // Reset paginator configuration before test
        Paginator::useBootstrap(false);
        
        // Call boot method
        $this->provider->boot();
        
        // Test that pagination views are set correctly
        $this->assertEquals('pagination::bootstrap-5', Paginator::$defaultView);
        $this->assertEquals('pagination::simple-bootstrap-5', Paginator::$defaultSimpleView);
    }

    public function test_provider_can_be_instantiated()
    {
        $this->assertInstanceOf(AppServiceProvider::class, $this->provider);
    }

    public function test_provider_extends_service_provider()
    {
        $this->assertInstanceOf(\Illuminate\Support\ServiceProvider::class, $this->provider);
    }

    public function test_boot_method_uses_bootstrap_pagination()
    {
        // Store original state
        $originalUseBootstrap = Paginator::$useBootstrap ?? false;
        
        // Ensure bootstrap is not used initially
        Paginator::useBootstrap(false);
        
        // Call boot method
        $this->provider->boot();
        
        // Verify bootstrap pagination is enabled
        $this->assertTrue(Paginator::$useBootstrap);
        
        // Restore original state
        Paginator::useBootstrap($originalUseBootstrap);
    }
}