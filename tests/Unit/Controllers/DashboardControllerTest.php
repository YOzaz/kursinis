<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\DashboardController;
use App\Models\AnalysisJob;
use App\Services\StatisticsService;
use Illuminate\View\View;
use Tests\TestCase;
use Mockery;

class DashboardControllerTest extends TestCase
{
    private DashboardController $controller;
    private $mockStatisticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStatisticsService = Mockery::mock(StatisticsService::class);
        $this->controller = new DashboardController($this->mockStatisticsService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_controller_can_be_instantiated()
    {
        $this->assertInstanceOf(DashboardController::class, $this->controller);
    }

    public function test_controller_extends_base_controller()
    {
        $this->assertInstanceOf(\App\Http\Controllers\Controller::class, $this->controller);
    }

    public function test_index_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'index'));
    }

    public function test_index_method_return_type()
    {
        $reflection = new \ReflectionMethod($this->controller, 'index');
        $returnType = $reflection->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertEquals('Illuminate\View\View', $returnType->getName());
    }

    public function test_constructor_accepts_statistics_service()
    {
        $reflection = new \ReflectionClass(DashboardController::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertEquals('statisticsService', $parameters[0]->getName());
        $this->assertEquals(StatisticsService::class, $parameters[0]->getType()->getName());
    }

    public function test_constructor_has_private_property_promotion()
    {
        $reflection = new \ReflectionClass(DashboardController::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();
        
        $this->assertTrue($parameters[0]->isPromoted());
    }
}