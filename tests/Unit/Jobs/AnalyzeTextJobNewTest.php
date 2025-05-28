<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\AnalyzeTextJobNew;
use App\Models\TextAnalysis;
use App\Models\AnalysisJob;
use App\Services\ClaudeServiceNew;
use App\Services\GeminiServiceNew;
use App\Services\OpenAIServiceNew;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;

class AnalyzeTextJobNewTest extends TestCase
{
    use RefreshDatabase;

    private TextAnalysis $textAnalysis;
    private AnalysisJob $analysisJob;
    private ClaudeServiceNew $claudeService;
    private GeminiServiceNew $geminiService;
    private OpenAIServiceNew $openaiService;
    private MetricsService $metricsService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-id',
            'status' => 'processing',
            'total_texts' => 1,
            'processed_texts' => 0
        ]);

        $this->textAnalysis = TextAnalysis::factory()->create([
            'job_id' => 'test-job-id',
            'text_id' => 'test-text-1',
            'content' => 'Test content for analysis'
        ]);

        // Mock services
        $this->claudeService = Mockery::mock(ClaudeServiceNew::class);
        $this->geminiService = Mockery::mock(GeminiServiceNew::class);
        $this->openaiService = Mockery::mock(OpenAIServiceNew::class);
        $this->metricsService = Mockery::mock(MetricsService::class);

        $this->app->instance(ClaudeServiceNew::class, $this->claudeService);
        $this->app->instance(GeminiServiceNew::class, $this->geminiService);
        $this->app->instance(OpenAIServiceNew::class, $this->openaiService);
        $this->app->instance(MetricsService::class, $this->metricsService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_handles_single_model_successfully()
    {
        // Arrange
        $this->claudeService
            ->shouldReceive('analyze')
            ->with($this->textAnalysis->content, null)
            ->once()
            ->andReturn('Claude analysis result');

        $this->metricsService
            ->shouldReceive('calculateComparisonMetrics')
            ->once();

        Log::shouldReceive('info')->twice();

        // Act
        $job = new AnalyzeTextJobNew(
            $this->textAnalysis->id,
            ['claude-opus-4'],
            'test-job-id'
        );
        $job->handle();

        // Assert
        $this->textAnalysis->refresh();
        $this->assertEquals('Claude analysis result', $this->textAnalysis->claude_annotations);
        
        $this->analysisJob->refresh();
        $this->assertEquals(1, $this->analysisJob->processed_texts);
    }

    public function test_job_handles_multiple_models_successfully()
    {
        // Arrange
        $this->claudeService
            ->shouldReceive('analyze')
            ->with($this->textAnalysis->content, null)
            ->once()
            ->andReturn('Claude analysis result');

        $this->geminiService
            ->shouldReceive('analyze')
            ->with($this->textAnalysis->content, null)
            ->once()
            ->andReturn('Gemini analysis result');

        $this->metricsService
            ->shouldReceive('calculateComparisonMetrics')
            ->once();

        Log::shouldReceive('info')->times(3);

        // Act
        $job = new AnalyzeTextJobNew(
            $this->textAnalysis->id,
            ['claude-opus-4', 'gemini-pro'],
            'test-job-id'
        );
        $job->handle();

        // Assert
        $this->textAnalysis->refresh();
        $this->assertEquals('Claude analysis result', $this->textAnalysis->claude_annotations);
        $this->assertEquals('Gemini analysis result', $this->textAnalysis->gemini_annotations);
        
        $this->analysisJob->refresh();
        $this->assertEquals(1, $this->analysisJob->processed_texts);
    }

    public function test_job_continues_when_one_model_fails()
    {
        // Arrange
        $this->claudeService
            ->shouldReceive('analyze')
            ->once()
            ->andThrow(new \Exception('Claude service error'));

        $this->geminiService
            ->shouldReceive('analyze')
            ->with($this->textAnalysis->content, null)
            ->once()
            ->andReturn('Gemini analysis result');

        $this->metricsService
            ->shouldReceive('calculateComparisonMetrics')
            ->once();

        Log::shouldReceive('error')->once();
        Log::shouldReceive('info')->twice();

        // Act
        $job = new AnalyzeTextJobNew(
            $this->textAnalysis->id,
            ['claude-opus-4', 'gemini-pro'],
            'test-job-id'
        );
        $job->handle();

        // Assert
        $this->textAnalysis->refresh();
        $this->assertNull($this->textAnalysis->claude_annotations);
        $this->assertEquals('Gemini analysis result', $this->textAnalysis->gemini_annotations);
        
        $this->analysisJob->refresh();
        $this->assertEquals(1, $this->analysisJob->processed_texts);
    }

    public function test_job_handles_custom_prompt()
    {
        // Arrange
        $customPrompt = 'Custom analysis prompt';
        
        $this->claudeService
            ->shouldReceive('analyze')
            ->with($this->textAnalysis->content, $customPrompt)
            ->once()
            ->andReturn('Claude analysis with custom prompt');

        $this->metricsService
            ->shouldReceive('calculateComparisonMetrics')
            ->once();

        Log::shouldReceive('info')->twice();

        // Act
        $job = new AnalyzeTextJobNew(
            $this->textAnalysis->id,
            ['claude-opus-4'],
            'test-job-id',
            $customPrompt
        );
        $job->handle();

        // Assert
        $this->textAnalysis->refresh();
        $this->assertEquals('Claude analysis with custom prompt', $this->textAnalysis->claude_annotations);
    }

    public function test_job_updates_analysis_job_completion_status()
    {
        // Arrange
        $this->claudeService
            ->shouldReceive('analyze')
            ->once()
            ->andReturn('Result');

        $this->metricsService
            ->shouldReceive('calculateComparisonMetrics')
            ->once();

        Log::shouldReceive('info')->twice();

        // Update the job to show this is the last text
        $this->analysisJob->update(['processed_texts' => 0, 'total_texts' => 1]);

        // Act
        $job = new AnalyzeTextJobNew(
            $this->textAnalysis->id,
            ['claude-opus-4'],
            'test-job-id'
        );
        $job->handle();

        // Assert
        $this->analysisJob->refresh();
        $this->assertEquals('completed', $this->analysisJob->status);
        $this->assertEquals(1, $this->analysisJob->processed_texts);
    }

    public function test_job_handles_unknown_model_gracefully()
    {
        // Arrange
        $this->metricsService
            ->shouldReceive('calculateComparisonMetrics')
            ->once();

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        // Act
        $job = new AnalyzeTextJobNew(
            $this->textAnalysis->id,
            ['unknown-model'],
            'test-job-id'
        );
        $job->handle();

        // Assert
        $this->textAnalysis->refresh();
        // No annotations should be set for unknown model
        $this->assertNull($this->textAnalysis->claude_annotations);
        $this->assertNull($this->textAnalysis->gemini_annotations);
        $this->assertNull($this->textAnalysis->gpt_annotations);
    }

    public function test_job_handles_model_mapping_correctly()
    {
        // Test that different model names map to correct services
        $this->claudeService
            ->shouldReceive('analyze')
            ->once()
            ->andReturn('Claude result');

        $this->openaiService
            ->shouldReceive('analyze')
            ->once()
            ->andReturn('OpenAI result');

        $this->metricsService
            ->shouldReceive('calculateComparisonMetrics')
            ->once();

        Log::shouldReceive('info')->times(3);

        // Act
        $job = new AnalyzeTextJobNew(
            $this->textAnalysis->id,
            ['claude-opus-4', 'gpt-4'],
            'test-job-id'
        );
        $job->handle();

        // Assert
        $this->textAnalysis->refresh();
        $this->assertEquals('Claude result', $this->textAnalysis->claude_annotations);
        $this->assertEquals('OpenAI result', $this->textAnalysis->gpt_annotations);
    }

    public function test_job_fails_when_text_analysis_not_found()
    {
        // Arrange
        Log::shouldReceive('error')->once();

        // Act & Assert
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $job = new AnalyzeTextJobNew(
            99999, // Non-existent ID
            ['claude-opus-4'],
            'test-job-id'
        );
        $job->handle();
    }

    public function test_job_constructor_sets_properties_correctly()
    {
        // Act
        $job = new AnalyzeTextJobNew(
            $this->textAnalysis->id,
            ['claude-opus-4', 'gemini-pro'],
            'test-job-id',
            'custom prompt'
        );

        // Assert
        $this->assertEquals($this->textAnalysis->id, $job->textAnalysisId);
        $this->assertEquals(['claude-opus-4', 'gemini-pro'], $job->modelNames);
        $this->assertEquals('test-job-id', $job->jobId);
        $this->assertEquals('custom prompt', $job->customPrompt);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_failed_method_logs_error_and_updates_job_status()
    {
        // Arrange
        $exception = new \Exception('Test failure');
        
        Log::shouldReceive('error')->once()->with(
            'Tekstų analizės darbo klaida',
            Mockery::type('array')
        );

        // Act
        $job = new AnalyzeTextJobNew(
            $this->textAnalysis->id,
            ['claude-opus-4'],
            'test-job-id'
        );
        $job->failed($exception);

        // Assert
        $this->analysisJob->refresh();
        $this->assertEquals('failed', $this->analysisJob->status);
        $this->assertStringContains('Test failure', $this->analysisJob->error_message);
    }
}