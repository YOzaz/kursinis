<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\IndividualTextAnalysisJob;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ModelResult;
use App\Models\ComparisonMetric;
use App\Services\MetricsService;
use App\Services\ClaudeService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;
use Mockery;

class IndividualTextAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration
        config([
            'llm.models' => [
                'claude-3-sonnet' => [
                    'provider' => 'anthropic',
                    'model' => 'claude-3-sonnet-20240229',
                    'api_key' => 'test-key',
                    'base_url' => 'https://api.anthropic.com/v1/',
                    'max_tokens' => 4000,
                    'temperature' => 0.1,
                ],
                'gpt-4o' => [
                    'provider' => 'openai',
                    'model' => 'gpt-4o',
                    'api_key' => 'test-key',
                    'base_url' => 'https://api.openai.com/v1',
                    'max_tokens' => 4000,
                    'temperature' => 0.1,
                ],
                'gemini-1.5-pro' => [
                    'provider' => 'google',
                    'model' => 'gemini-1.5-pro-latest',
                    'api_key' => 'test-key',
                    'max_tokens' => 4000,
                    'temperature' => 0.1,
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_creation_and_properties()
    {
        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-1';
        $content = 'Test content for analysis';
        $expertAnnotations = [['type' => 'propaganda', 'start' => 0, 'end' => 10]];
        $modelKey = 'claude-3-sonnet';
        $customPrompt = 'Custom analysis prompt';

        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey,
            $customPrompt
        );

        $this->assertEquals($jobId, $job->jobId);
        $this->assertEquals($textId, $job->textId);
        $this->assertEquals($content, $job->content);
        $this->assertEquals($expertAnnotations, $job->expertAnnotations);
        $this->assertEquals($modelKey, $job->modelKey);
        $this->assertEquals($customPrompt, $job->customPrompt);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_individual_text_analysis_with_claude()
    {
        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-1';
        $content = 'This is a test text for propaganda analysis.';
        $expertAnnotations = [];
        $modelKey = 'claude-3-sonnet';

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'requested_models' => [$modelKey],
            'custom_prompt' => null
        ]);

        // Mock Claude service
        $mockClaudeService = Mockery::mock(ClaudeService::class);
        $mockClaudeService->shouldReceive('setModel')
            ->with($modelKey)
            ->once()
            ->andReturn(true);
        
        $mockClaudeService->shouldReceive('analyzeText')
            ->with($content, null)
            ->once()
            ->andReturn([
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [
                    ['type' => 'propaganda', 'start' => 10, 'end' => 20, 'text' => 'test text']
                ],
                'desinformationTechnique' => ['choices' => ['bandwagon']]
            ]);

        $this->app->instance(ClaudeService::class, $mockClaudeService);

        // Create and run the job
        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey
        );

        $job->handle();

        // Assert TextAnalysis was created
        $textAnalysis = TextAnalysis::where('job_id', $jobId)
            ->where('text_id', $textId)
            ->first();

        $this->assertNotNull($textAnalysis);
        $this->assertEquals($content, $textAnalysis->content);
        $this->assertEquals($expertAnnotations, $textAnalysis->expert_annotations);

        // Assert ModelResult was created
        $modelResult = ModelResult::where('job_id', $jobId)
            ->where('text_id', $textId)
            ->where('model_key', $modelKey)
            ->first();

        $this->assertNotNull($modelResult);
        $this->assertEquals('completed', $modelResult->status);
        $this->assertArrayHasKey('primaryChoice', $modelResult->result);
        $this->assertArrayHasKey('annotations', $modelResult->result);

        // Assert legacy fields are populated
        $this->assertNotNull($textAnalysis->claude_annotations);
        $this->assertEquals('claude-3-sonnet-20240229', $textAnalysis->claude_model_name);
    }

    public function test_individual_text_analysis_with_openai()
    {
        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-2';
        $content = 'Another test text for analysis.';
        $expertAnnotations = [];
        $modelKey = 'gpt-4o';

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'requested_models' => [$modelKey],
            'custom_prompt' => null
        ]);

        // Mock OpenAI service
        $mockOpenAIService = Mockery::mock(OpenAIService::class);
        $mockOpenAIService->shouldReceive('setModel')
            ->with($modelKey)
            ->once()
            ->andReturn(true);
        
        $mockOpenAIService->shouldReceive('analyzeText')
            ->with($content, null)
            ->once()
            ->andReturn([
                'primaryChoice' => ['choices' => ['no']],
                'annotations' => [],
                'desinformationTechnique' => ['choices' => []]
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAIService);

        // Create and run the job
        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey
        );

        $job->handle();

        // Assert results
        $textAnalysis = TextAnalysis::where('job_id', $jobId)
            ->where('text_id', $textId)
            ->first();

        $this->assertNotNull($textAnalysis);
        $this->assertNotNull($textAnalysis->gpt_annotations);
        $this->assertEquals('gpt-4o', $textAnalysis->gpt_model_name);
    }

    public function test_individual_text_analysis_with_gemini()
    {
        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-3';
        $content = 'Gemini test text for analysis.';
        $expertAnnotations = [];
        $modelKey = 'gemini-1.5-pro';

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'requested_models' => [$modelKey],
            'custom_prompt' => null
        ]);

        // Mock Gemini service
        $mockGeminiService = Mockery::mock(GeminiService::class);
        $mockGeminiService->shouldReceive('setModel')
            ->with($modelKey)
            ->once()
            ->andReturn(true);
        
        $mockGeminiService->shouldReceive('analyzeText')
            ->with($content, null)
            ->once()
            ->andReturn([
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [
                    ['type' => 'bias', 'start' => 0, 'end' => 6, 'text' => 'Gemini']
                ],
                'desinformationTechnique' => ['choices' => ['loaded_language']]
            ]);

        $this->app->instance(GeminiService::class, $mockGeminiService);

        // Create and run the job
        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey
        );

        $job->handle();

        // Assert results
        $textAnalysis = TextAnalysis::where('job_id', $jobId)
            ->where('text_id', $textId)
            ->first();

        $this->assertNotNull($textAnalysis);
        $this->assertNotNull($textAnalysis->gemini_annotations);
        $this->assertEquals('gemini-1.5-pro-latest', $textAnalysis->gemini_model_name);
    }

    public function test_error_handling_for_failed_analysis()
    {
        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-error';
        $content = 'Error test text.';
        $expertAnnotations = [];
        $modelKey = 'claude-3-sonnet';

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'requested_models' => [$modelKey],
            'custom_prompt' => null
        ]);

        // Mock Claude service to throw exception
        $mockClaudeService = Mockery::mock(ClaudeService::class);
        $mockClaudeService->shouldReceive('setModel')
            ->with($modelKey)
            ->once()
            ->andReturn(true);
        
        $mockClaudeService->shouldReceive('analyzeText')
            ->with($content, null)
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        $this->app->instance(ClaudeService::class, $mockClaudeService);

        // Create the job
        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey
        );

        // Expect exception to be thrown
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API connection failed');

        $job->handle();
    }

    public function test_custom_prompt_handling()
    {
        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-custom';
        $content = 'Custom prompt test text.';
        $expertAnnotations = [];
        $modelKey = 'claude-3-sonnet';
        $customPrompt = 'Analyze this text with special attention to emotional language';

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'requested_models' => [$modelKey],
            'custom_prompt' => $customPrompt
        ]);

        // Mock Claude service
        $mockClaudeService = Mockery::mock(ClaudeService::class);
        $mockClaudeService->shouldReceive('setModel')
            ->with($modelKey)
            ->once()
            ->andReturn(true);
        
        $mockClaudeService->shouldReceive('analyzeText')
            ->with($content, $customPrompt)
            ->once()
            ->andReturn([
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [],
                'desinformationTechnique' => ['choices' => ['emotional_appeal']]
            ]);

        $this->app->instance(ClaudeService::class, $mockClaudeService);

        // Create and run the job
        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey,
            $customPrompt
        );

        $job->handle();

        // Assert the job completed successfully
        $textAnalysis = TextAnalysis::where('job_id', $jobId)
            ->where('text_id', $textId)
            ->first();

        $this->assertNotNull($textAnalysis);
        $this->assertNotNull($textAnalysis->claude_annotations);
    }

    public function test_comparison_metrics_creation_with_expert_annotations()
    {
        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-metrics';
        $content = 'Text with expert annotations for metrics.';
        $expertAnnotations = [
            ['type' => 'propaganda', 'start' => 0, 'end' => 10, 'text' => 'Text with']
        ];
        $modelKey = 'claude-3-sonnet';

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'requested_models' => [$modelKey],
            'custom_prompt' => null
        ]);

        // Mock Claude service
        $mockClaudeService = Mockery::mock(ClaudeService::class);
        $mockClaudeService->shouldReceive('setModel')
            ->with($modelKey)
            ->once()
            ->andReturn(true);
        
        $mockClaudeService->shouldReceive('analyzeText')
            ->with($content, null)
            ->once()
            ->andReturn([
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [
                    ['type' => 'propaganda', 'start' => 0, 'end' => 10, 'text' => 'Text with']
                ],
                'desinformationTechnique' => ['choices' => ['bandwagon']]
            ]);

        $this->app->instance(ClaudeService::class, $mockClaudeService);

        // Mock MetricsService
        $mockMetricsService = Mockery::mock(MetricsService::class);
        $mockMetricsService->shouldReceive('calculateMetricsForText')
            ->once()
            ->andReturn(true);

        $this->app->instance(MetricsService::class, $mockMetricsService);

        // Create and run the job
        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey
        );

        $job->handle();

        // Assert TextAnalysis was created with expert annotations
        $textAnalysis = TextAnalysis::where('job_id', $jobId)
            ->where('text_id', $textId)
            ->first();

        $this->assertNotNull($textAnalysis);
        $this->assertNotEmpty($textAnalysis->expert_annotations);
    }

    public function test_job_progress_update()
    {
        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-progress';
        $content = 'Progress test text.';
        $expertAnnotations = [];
        $modelKey = 'claude-3-sonnet';

        // Create analysis job with multiple models to test progress calculation
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'requested_models' => [$modelKey, 'gpt-4o'],
            'custom_prompt' => null
        ]);

        // Create another text analysis to simulate multiple texts
        TextAnalysis::create([
            'job_id' => $jobId,
            'text_id' => 'text-other',
            'content' => 'Other text content',
            'expert_annotations' => []
        ]);

        // Mock Claude service
        $mockClaudeService = Mockery::mock(ClaudeService::class);
        $mockClaudeService->shouldReceive('setModel')
            ->with($modelKey)
            ->once()
            ->andReturn(true);
        
        $mockClaudeService->shouldReceive('analyzeText')
            ->with($content, null)
            ->once()
            ->andReturn([
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [],
                'desinformationTechnique' => ['choices' => []]
            ]);

        $this->app->instance(ClaudeService::class, $mockClaudeService);

        // Create and run the job
        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey
        );

        $job->handle();

        // Check that job progress was updated
        $analysisJob->refresh();
        $this->assertGreaterThan(0, $analysisJob->processed_texts);
        $this->assertGreaterThan(0, $analysisJob->total_texts);
    }

    public function test_unsupported_provider_throws_exception()
    {
        // Add unsupported provider to config
        config([
            'llm.models.unsupported-model' => [
                'provider' => 'unsupported',
                'model' => 'test-model',
                'api_key' => 'test-key'
            ]
        ]);

        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-unsupported';
        $content = 'Unsupported provider test.';
        $expertAnnotations = [];
        $modelKey = 'unsupported-model';

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'requested_models' => [$modelKey],
            'custom_prompt' => null
        ]);

        // Create the job
        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey
        );

        // Expect exception for unsupported provider
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported provider: unsupported');

        $job->handle();
    }
}