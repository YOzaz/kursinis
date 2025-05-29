<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\AnalysisController;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Services\ExportService;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Mockery;

class AnalysisControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;
    protected $exportService;
    protected $metricsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportService = Mockery::mock(ExportService::class);
        $this->metricsService = Mockery::mock(MetricsService::class);
        $this->app->instance(ExportService::class, $this->exportService);
        $this->app->instance(MetricsService::class, $this->metricsService);
        $this->controller = $this->app->make(AnalysisController::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'index'));
    }

    public function test_show_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'show'));
    }

    public function test_controller_has_required_api_methods()
    {
        $requiredMethods = [
            'analyzeSingle', 'analyzeBatch', 'getResults', 
            'exportResults', 'getStatus', 'repeatAnalysis',
            'health', 'models', 'getTextAnnotations'
        ];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($this->controller, $method),
                "Method {$method} should exist on AnalysisController"
            );
        }
    }

    public function test_controller_uses_correct_dependencies()
    {
        $reflection = new \ReflectionClass(AnalysisController::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('App\Services\MetricsService', $parameters[0]->getType()->getName());
        $this->assertEquals('App\Services\ExportService', $parameters[1]->getType()->getName());
    }

    public function test_controller_instantiation_with_dependencies()
    {
        $this->assertInstanceOf(AnalysisController::class, $this->controller);
    }

    public function test_export_results_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'exportResults'));
        
        $reflection = new \ReflectionMethod($this->controller, 'exportResults');
        $parameters = $reflection->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('jobId', $parameters[0]->getName());
    }

    public function test_analyze_single_method_signature()
    {
        $this->assertTrue(method_exists($this->controller, 'analyzeSingle'));
        
        $reflection = new \ReflectionMethod($this->controller, 'analyzeSingle');
        $parameters = $reflection->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('request', $parameters[0]->getName());
        $this->assertEquals('Illuminate\Http\Request', $parameters[0]->getType()->getName());
    }

    public function test_get_text_annotations_method_signature()
    {
        $this->assertTrue(method_exists($this->controller, 'getTextAnnotations'));
        
        $reflection = new \ReflectionMethod($this->controller, 'getTextAnnotations');
        $parameters = $reflection->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('request', $parameters[0]->getName());
        $this->assertEquals('textAnalysisId', $parameters[1]->getName());
    }

    public function test_health_method_exists_and_returns_json_response()
    {
        $this->assertTrue(method_exists($this->controller, 'health'));
        
        $reflection = new \ReflectionMethod($this->controller, 'health');
        $returnType = $reflection->getReturnType();
        $this->assertEquals('Illuminate\Http\JsonResponse', $returnType->getName());
    }

    public function test_models_method_exists_and_returns_json_response()
    {
        $this->assertTrue(method_exists($this->controller, 'models'));
        
        $reflection = new \ReflectionMethod($this->controller, 'models');
        $returnType = $reflection->getReturnType();
        $this->assertEquals('Illuminate\Http\JsonResponse', $returnType->getName());
    }

    public function test_controller_implements_correct_structure()
    {
        // Test that controller extends the base Controller
        $this->assertInstanceOf(\App\Http\Controllers\Controller::class, $this->controller);
    }
}