<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Http\Controllers\AnalysisController;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Services\MetricsService;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Config;
use Mockery;

class AnalysisControllerComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private AnalysisController $controller;
    private $mockMetricsService;
    private $mockExportService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockMetricsService = Mockery::mock(MetricsService::class);
        $this->mockExportService = Mockery::mock(ExportService::class);
        $this->controller = new AnalysisController($this->mockMetricsService, $this->mockExportService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_method_returns_paginated_analyses()
    {
        AnalysisJob::factory()->count(20)->create();

        $response = $this->controller->index();

        $this->assertEquals('analyses.index', $response->getName());
        $this->assertArrayHasKey('analyses', $response->getData());
    }

    public function test_analyze_single_validates_required_fields()
    {
        $request = Request::create('/api/analyze', 'POST', []);
        
        $response = $this->controller->analyzeSingle($request);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_analyze_single_validates_text_length()
    {
        $request = Request::create('/api/analyze', 'POST', [
            'text' => str_repeat('a', 50001), // Exceeds 50,000 character limit
            'models' => ['claude-opus-4']
        ]);
        
        $response = $this->controller->analyzeSingle($request);
        
        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('50000', $data['message'] ?? '');
    }

    public function test_analyze_single_validates_models_array()
    {
        $request = Request::create('/api/analyze', 'POST', [
            'text' => 'Valid text content',
            'models' => 'invalid-string-not-array'
        ]);
        
        $response = $this->controller->analyzeSingle($request);
        
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_analyze_single_creates_analysis_job()
    {
        Queue::fake();
        
        $request = Request::create('/api/analyze', 'POST', [
            'text' => 'Test propaganda text for analysis',
            'models' => ['claude-opus-4'],
            'name' => 'Test Analysis'
        ]);
        
        $response = $this->controller->analyzeSingle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('job_id', $data);
        
        $this->assertDatabaseHas('analysis_jobs', [
            'job_id' => $data['job_id'],
            'name' => 'Test Analysis'
        ]);
    }

    public function test_analyze_batch_validates_file_structure()
    {
        $request = Request::create('/api/batch-analyze', 'POST', [
            'texts' => [
                ['invalid' => 'structure']
            ],
            'models' => ['claude-opus-4']
        ]);
        
        $response = $this->controller->analyzeBatch($request);
        
        $this->assertEquals(422, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_analyze_batch_processes_valid_request()
    {
        Queue::fake();
        
        $request = Request::create('/api/batch-analyze', 'POST', [
            'texts' => [
                [
                    'text_id' => 'test-1',
                    'content' => 'First test text'
                ],
                [
                    'text_id' => 'test-2', 
                    'content' => 'Second test text'
                ]
            ],
            'models' => ['claude-opus-4'],
            'name' => 'Batch Test'
        ]);
        
        $response = $this->controller->analyzeBatch($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('job_id', $data);
    }

    public function test_get_results_returns_404_for_nonexistent_job()
    {
        $response = $this->controller->getResults('nonexistent-job-id');
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_get_results_returns_job_data()
    {
        $job = AnalysisJob::factory()->create();
        
        $this->mockMetricsService
            ->shouldReceive('calculateJobStatistics')
            ->once()
            ->andReturn(['total_texts' => 1]);
        
        $response = $this->controller->getResults($job->job_id);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($job->job_id, $data['job_id']);
    }

    public function test_export_results_returns_404_for_nonexistent_job()
    {
        $response = $this->controller->exportResults('nonexistent-job-id');
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_export_results_delegates_to_export_service()
    {
        $job = AnalysisJob::factory()->create();
        
        $this->mockExportService
            ->shouldReceive('exportAnalysisResults')
            ->once()
            ->with($job->job_id, 'json')
            ->andReturn(response()->json(['exported' => true]));
        
        $response = $this->controller->exportResults($job->job_id);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_get_status_returns_job_status()
    {
        $job = AnalysisJob::factory()->create(['status' => 'processing']);
        
        $response = $this->controller->getStatus($job->job_id);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('processing', $data['status']);
    }

    public function test_repeat_analysis_validates_job_exists()
    {
        $request = Request::create('/api/repeat-analysis', 'POST', [
            'job_id' => 'nonexistent-job',
            'models' => ['claude-opus-4']
        ]);
        
        $response = $this->controller->repeatAnalysis($request);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_repeat_analysis_creates_new_job()
    {
        Queue::fake();
        
        $originalJob = AnalysisJob::factory()->create();
        TextAnalysis::factory()->create(['job_id' => $originalJob->job_id]);
        
        $request = Request::create('/api/repeat-analysis', 'POST', [
            'job_id' => $originalJob->job_id,
            'models' => ['claude-sonnet-4']
        ]);
        
        $response = $this->controller->repeatAnalysis($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNotEquals($originalJob->job_id, $data['new_job_id']);
    }

    public function test_health_returns_system_status()
    {
        $response = $this->controller->health();
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('Propaganda Analysis API', $data['service']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function test_models_returns_available_models()
    {
        Config::set('llm.models', [
            'claude-opus-4' => [
                'provider' => 'anthropic',
                'description' => 'Claude Opus 4',
                'api_key' => 'test-key'
            ],
            'gpt-4.1' => [
                'provider' => 'openai',
                'description' => 'GPT-4.1',
                'api_key' => 'test-key'
            ]
        ]);
        
        $response = $this->controller->models();
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('models', $data);
        $this->assertCount(2, $data['models']);
        
        $modelNames = array_column($data['models'], 'name');
        $this->assertContains('claude-opus-4', $modelNames);
        $this->assertContains('gpt-4.1', $modelNames);
    }

    public function test_repeat_web_validates_job_exists()
    {
        $request = Request::create('/analysis/repeat', 'POST', [
            'job_id' => 'nonexistent-job',
            'models' => ['claude-opus-4']
        ]);
        
        $response = $this->controller->repeat($request);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('error', $response->getTargetUrl());
    }

    public function test_get_text_annotations_validates_text_exists()
    {
        $request = Request::create('/api/text-annotations/999', 'GET');
        
        $response = $this->controller->getTextAnnotations($request, 999);
        
        $this->assertEquals(404, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Tekstas nerastas', $data['message']);
    }

    public function test_get_text_annotations_returns_ai_view_by_default()
    {
        $textAnalysis = TextAnalysis::factory()->create([
            'claude_annotations' => [
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 0,
                            'end' => 10,
                            'text' => 'test text',
                            'labels' => ['propaganda']
                        ]
                    ]
                ]
            ]
        ]);
        
        $request = Request::create("/api/text-annotations/{$textAnalysis->id}", 'GET');
        
        $response = $this->controller->getTextAnnotations($request, $textAnalysis->id);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('ai', $data['view_type']);
        $this->assertArrayHasKey('annotations', $data);
    }

    public function test_get_text_annotations_returns_expert_view()
    {
        $textAnalysis = TextAnalysis::factory()->create([
            'expert_annotations' => [
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 0,
                            'end' => 10,
                            'text' => 'test text',
                            'labels' => ['propaganda']
                        ]
                    ]
                ]
            ]
        ]);
        
        $request = Request::create("/api/text-annotations/{$textAnalysis->id}?view=expert", 'GET');
        
        $response = $this->controller->getTextAnnotations($request, $textAnalysis->id);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('expert', $data['view_type']);
    }

    public function test_controller_constructor_accepts_services()
    {
        $metricsService = new MetricsService();
        $exportService = new ExportService();
        
        $controller = new AnalysisController($metricsService, $exportService);
        
        $this->assertInstanceOf(AnalysisController::class, $controller);
    }

    public function test_controller_extends_base_controller()
    {
        $this->assertInstanceOf(Controller::class, $this->controller);
    }
}