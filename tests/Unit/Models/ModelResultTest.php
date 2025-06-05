<?php

namespace Tests\Unit\Models;

use App\Models\ModelResult;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_result_can_be_created(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $modelResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-text-123',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'model_name' => 'claude-opus-4',
            'actual_model_name' => 'claude-3-opus-20240229',
            'annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => []
            ],
            'execution_time_ms' => 15000,
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        $this->assertDatabaseHas('model_results', [
            'job_id' => $job->job_id,
            'text_id' => 'test-text-123',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'status' => 'completed'
        ]);
    }

    public function test_model_result_casts_annotations_to_array(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $annotations = [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 10,
                        'text' => 'Test text',
                        'labels' => ['simplification']
                    ]
                ]
            ]
        ];
        
        $modelResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-text-456',
            'model_key' => 'claude-sonnet-4',
            'provider' => 'anthropic',
            'annotations' => $annotations,
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        $this->assertIsArray($modelResult->annotations);
        $this->assertEquals($annotations, $modelResult->annotations);
    }

    public function test_model_result_status_constants(): void
    {
        $this->assertEquals('pending', ModelResult::STATUS_PENDING);
        $this->assertEquals('processing', ModelResult::STATUS_PROCESSING);
        $this->assertEquals('completed', ModelResult::STATUS_COMPLETED);
        $this->assertEquals('failed', ModelResult::STATUS_FAILED);
    }

    public function test_is_successful_method(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Successful result
        $successfulResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-success',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'annotations' => ['primaryChoice' => ['choices' => ['yes']]],
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        // Failed result
        $failedResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-failed',
            'model_key' => 'gpt-4o-latest',
            'provider' => 'openai',
            'error_message' => 'API error',
            'status' => ModelResult::STATUS_FAILED
        ]);

        // Pending result
        $pendingResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-pending',
            'model_key' => 'gemini-2.5-pro',
            'provider' => 'google',
            'status' => ModelResult::STATUS_PENDING
        ]);

        $this->assertTrue($successfulResult->isSuccessful());
        $this->assertFalse($failedResult->isSuccessful());
        $this->assertFalse($pendingResult->isSuccessful());
    }

    public function test_is_failed_method(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $successfulResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-success',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'annotations' => ['primaryChoice' => ['choices' => ['yes']]],
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        $failedResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-failed',
            'model_key' => 'gpt-4o-latest',
            'provider' => 'openai',
            'error_message' => 'API error',
            'status' => ModelResult::STATUS_FAILED
        ]);

        $this->assertFalse($successfulResult->isFailed());
        $this->assertTrue($failedResult->isFailed());
    }

    public function test_detected_propaganda_method(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Result with propaganda detected
        $propagandaResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-propaganda',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => []
            ],
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        // Result without propaganda
        $noPropagandaResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-no-propaganda',
            'model_key' => 'claude-sonnet-4',
            'provider' => 'anthropic',
            'annotations' => [
                'primaryChoice' => ['choices' => ['no']],
                'annotations' => []
            ],
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        // Result with empty annotations
        $emptyResult = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-empty',
            'model_key' => 'gpt-4o-latest',
            'provider' => 'openai',
            'annotations' => [],
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        $this->assertTrue($propagandaResult->detectedPropaganda());
        $this->assertFalse($noPropagandaResult->detectedPropaganda());
        $this->assertFalse($emptyResult->detectedPropaganda());
    }

    public function test_get_detected_techniques_method(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $techniques = ['simplification', 'emotionalExpression', 'doubt'];
        
        $result = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-techniques',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'desinformationTechnique' => ['choices' => $techniques],
                'annotations' => []
            ],
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        $resultEmpty = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-no-techniques',
            'model_key' => 'claude-sonnet-4',
            'provider' => 'anthropic',
            'annotations' => [
                'primaryChoice' => ['choices' => ['no']],
                'annotations' => []
            ],
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        $this->assertEquals($techniques, $result->getDetectedTechniques());
        $this->assertEquals([], $resultEmpty->getDetectedTechniques());
    }

    public function test_unique_constraint(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Create first result
        ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-unique',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        // Attempt to create duplicate should throw exception
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-unique',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'status' => ModelResult::STATUS_FAILED
        ]);
    }

    public function test_update_or_create_functionality(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Create initial result
        $result1 = ModelResult::updateOrCreate(
            [
                'job_id' => $job->job_id,
                'text_id' => 'test-update',
                'model_key' => 'claude-opus-4'
            ],
            [
                'provider' => 'anthropic',
                'status' => ModelResult::STATUS_PENDING
            ]
        );

        $this->assertEquals(ModelResult::STATUS_PENDING, $result1->status);
        $this->assertDatabaseCount('model_results', 1);

        // Update existing result
        $result2 = ModelResult::updateOrCreate(
            [
                'job_id' => $job->job_id,
                'text_id' => 'test-update',
                'model_key' => 'claude-opus-4'
            ],
            [
                'provider' => 'anthropic',
                'status' => ModelResult::STATUS_COMPLETED,
                'annotations' => ['primaryChoice' => ['choices' => ['yes']]]
            ]
        );

        $this->assertEquals(ModelResult::STATUS_COMPLETED, $result2->status);
        $this->assertNotNull($result2->annotations);
        $this->assertDatabaseCount('model_results', 1);
        $this->assertEquals($result1->id, $result2->id);
    }

    public function test_provider_determination(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $testCases = [
            ['claude-opus-4', 'anthropic'],
            ['claude-sonnet-4', 'anthropic'],
            ['gpt-4o-latest', 'openai'],
            ['gpt-4.1', 'openai'],
            ['gemini-2.5-pro', 'google'],
            ['gemini-2.5-flash', 'google']
        ];

        foreach ($testCases as [$modelKey, $expectedProvider]) {
            $result = ModelResult::create([
                'job_id' => $job->job_id,
                'text_id' => "test-{$modelKey}",
                'model_key' => $modelKey,
                'provider' => $expectedProvider,
                'status' => ModelResult::STATUS_COMPLETED
            ]);

            $this->assertEquals($expectedProvider, $result->provider);
        }
    }

    public function test_execution_time_casting(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $result = ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'test-time',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'execution_time_ms' => '15000',
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        $this->assertIsInt($result->execution_time_ms);
        $this->assertEquals(15000, $result->execution_time_ms);
    }
}