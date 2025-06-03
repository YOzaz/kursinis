<?php

namespace Tests\Unit\Models;

use App\Models\TextAnalysis;
use App\Models\AnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_analysis_can_be_created(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $analysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-123',
            'content' => 'Test propaganda text for analysis',
        ]);

        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $job->job_id,
            'text_id' => 'test-123',
            'content' => 'Test propaganda text for analysis',
        ]);
    }

    public function test_text_analysis_has_required_fields(): void
    {
        $job = AnalysisJob::factory()->create();
        $analysis = TextAnalysis::factory()->forJob($job)->create();

        $this->assertNotNull($analysis->job_id);
        $this->assertNotNull($analysis->text_id);
        $this->assertNotNull($analysis->content);
        $this->assertNotNull($analysis->expert_annotations);
        $this->assertNotNull($analysis->claude_annotations);
        $this->assertNotNull($analysis->gemini_annotations);
        $this->assertNotNull($analysis->gpt_annotations);
    }

    public function test_text_analysis_casts_annotations_to_array(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $expertAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 10,
                    'text' => 'Test text',
                    'labels' => ['simplification']
                ]
            ]
        ];
        
        $analysis = TextAnalysis::factory()->forJob($job)->create([
            'expert_annotations' => $expertAnnotations,
        ]);

        $this->assertIsArray($analysis->expert_annotations);
        $this->assertEquals($expertAnnotations, $analysis->expert_annotations);
        $this->assertIsArray($analysis->claude_annotations);
        $this->assertIsArray($analysis->gemini_annotations);
        $this->assertIsArray($analysis->gpt_annotations);
    }

    public function test_text_analysis_has_analysis_job_relationship(): void
    {
        $job = AnalysisJob::factory()->create();
        $analysis = TextAnalysis::factory()->forJob($job)->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $analysis->analysisJob());
        $this->assertEquals($job->job_id, $analysis->analysisJob->job_id);
    }

    public function test_text_analysis_has_comparison_metrics_relationship(): void
    {
        $job = AnalysisJob::factory()->create();
        $analysis = TextAnalysis::factory()->forJob($job)->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $analysis->comparisonMetrics());
    }

    public function test_factory_with_propaganda_state(): void
    {
        $job = AnalysisJob::factory()->create();
        $analysis = TextAnalysis::factory()->withPropaganda()->forJob($job)->create();

        $this->assertStringContainsString('propaganda', $analysis->content);
        $this->assertNotEmpty($analysis->expert_annotations);
        
        // Should have at least one expert annotation
        $this->assertArrayHasKey(0, $analysis->expert_annotations);
        $this->assertArrayHasKey('type', $analysis->expert_annotations[0]);
        $this->assertEquals('labels', $analysis->expert_annotations[0]['type']);
    }

    public function test_factory_without_propaganda_state(): void
    {
        $job = AnalysisJob::factory()->create();
        $analysis = TextAnalysis::factory()->withoutPropaganda()->forJob($job)->create();

        $this->assertStringContainsString('neutral', $analysis->content);
        $this->assertEmpty($analysis->expert_annotations);
    }

    public function test_factory_with_complex_annotations_state(): void
    {
        $job = AnalysisJob::factory()->create();
        $analysis = TextAnalysis::factory()->withComplexAnnotations()->forJob($job)->create();

        $this->assertStringContainsString('Complex', $analysis->content);
        $this->assertGreaterThanOrEqual(3, count($analysis->expert_annotations));
        
        // Check that annotations have proper structure
        foreach ($analysis->expert_annotations as $annotation) {
            $this->assertArrayHasKey('type', $annotation);
            $this->assertArrayHasKey('value', $annotation);
            $this->assertArrayHasKey('labels', $annotation['value']);
            $this->assertIsArray($annotation['value']['labels']);
        }
    }

    public function test_text_analysis_can_store_llm_annotations(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $claudeAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 5,
                    'end' => 15,
                    'text' => 'Claude text',
                    'labels' => ['emotionalExpression']
                ]
            ]
        ];
        
        $geminiAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 10,
                    'end' => 20,
                    'text' => 'Gemini text',
                    'labels' => ['doubt']
                ]
            ]
        ];
        
        $gptAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 15,
                    'end' => 25,
                    'text' => 'GPT text',
                    'labels' => ['simplification']
                ]
            ]
        ];

        $analysis = TextAnalysis::factory()->forJob($job)->create([
            'claude_annotations' => $claudeAnnotations,
            'gemini_annotations' => $geminiAnnotations,
            'gpt_annotations' => $gptAnnotations,
        ]);

        $this->assertEquals($claudeAnnotations, $analysis->claude_annotations);
        $this->assertEquals($geminiAnnotations, $analysis->gemini_annotations);
        $this->assertEquals($gptAnnotations, $analysis->gpt_annotations);
    }

    public function test_text_analysis_can_handle_empty_annotations(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $analysis = TextAnalysis::factory()->forJob($job)->create([
            'expert_annotations' => [],
            'claude_annotations' => [],
            'gemini_annotations' => [],
            'gpt_annotations' => [],
        ]);

        $this->assertIsArray($analysis->expert_annotations);
        $this->assertEmpty($analysis->expert_annotations);
        $this->assertIsArray($analysis->claude_annotations);
        $this->assertEmpty($analysis->claude_annotations);
        $this->assertIsArray($analysis->gemini_annotations);
        $this->assertEmpty($analysis->gemini_annotations);
        $this->assertIsArray($analysis->gpt_annotations);
        $this->assertEmpty($analysis->gpt_annotations);
    }

    public function test_text_analysis_belongs_to_specific_text_id(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $analysis1 = TextAnalysis::factory()->withTextId('text-001')->forJob($job)->create();
        $analysis2 = TextAnalysis::factory()->withTextId('text-002')->forJob($job)->create();

        $this->assertEquals('text-001', $analysis1->text_id);
        $this->assertEquals('text-002', $analysis2->text_id);
        $this->assertNotEquals($analysis1->text_id, $analysis2->text_id);
    }

    public function test_multiple_analyses_can_belong_to_same_job(): void
    {
        $job = AnalysisJob::factory()->create();
        
        TextAnalysis::factory()->count(5)->forJob($job)->create();

        $this->assertDatabaseCount('text_analysis', 5);
        
        $analyses = TextAnalysis::where('job_id', $job->job_id)->get();
        $this->assertCount(5, $analyses);
        
        // All should belong to the same job
        foreach ($analyses as $analysis) {
            $this->assertEquals($job->job_id, $analysis->job_id);
        }
    }

    public function test_text_analysis_validates_annotation_structure(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $validAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 10,
                    'text' => 'Valid text',
                    'labels' => ['simplification', 'emotionalExpression']
                ]
            ]
        ];

        $analysis = TextAnalysis::factory()->forJob($job)->create([
            'expert_annotations' => $validAnnotations,
        ]);

        // Check that annotation structure is maintained
        $annotation = $analysis->expert_annotations[0];
        $this->assertEquals('labels', $annotation['type']);
        $this->assertArrayHasKey('value', $annotation);
        $this->assertArrayHasKey('start', $annotation['value']);
        $this->assertArrayHasKey('end', $annotation['value']);
        $this->assertArrayHasKey('text', $annotation['value']);
        $this->assertArrayHasKey('labels', $annotation['value']);
        $this->assertIsArray($annotation['value']['labels']);
    }

    public function test_supports_all_propaganda_techniques(): void
    {
        $job = AnalysisJob::factory()->create();
        $techniques = ['simplification', 'emotionalExpression', 'uncertainty', 'doubt', 'wavingTheFlag', 'reductioAdHitlerum', 'repetition'];
        
        $annotations = [];
        foreach ($techniques as $technique) {
            $annotations[] = [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 10,
                    'text' => 'Test text',
                    'labels' => [$technique]
                ]
            ];
        }

        $analysis = TextAnalysis::factory()->forJob($job)->create([
            'expert_annotations' => $annotations,
        ]);

        $this->assertCount(7, $analysis->expert_annotations);
        
        // Verify all techniques are present
        $foundTechniques = [];
        foreach ($analysis->expert_annotations as $annotation) {
            $foundTechniques = array_merge($foundTechniques, $annotation['value']['labels']);
        }
        
        foreach ($techniques as $technique) {
            $this->assertContains($technique, $foundTechniques);
        }
    }

    public function test_get_all_attempted_models_with_successful_and_failed(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $analysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-123',
            'claude_annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => []
            ],
            'claude_actual_model' => 'claude-sonnet-4-20250514',
            'gpt_annotations' => [
                'error' => 'API quota exceeded',
                'model' => 'gpt-4o-latest'
            ],
            'gemini_annotations' => [
                'error' => 'Invalid response format',
                'model' => 'gemini-2.5-flash'
            ]
        ]);

        $attemptedModels = $analysis->getAllAttemptedModels();

        // Should have 3 models: 1 successful, 2 failed
        $this->assertCount(3, $attemptedModels);
        
        // Check successful model
        $this->assertArrayHasKey('claude-sonnet-4-20250514', $attemptedModels);
        $this->assertEquals('success', $attemptedModels['claude-sonnet-4-20250514']['status']);
        $this->assertNotNull($attemptedModels['claude-sonnet-4-20250514']['annotations']);
        
        // Check failed models
        $this->assertArrayHasKey('gpt-4o-latest', $attemptedModels);
        $this->assertEquals('failed', $attemptedModels['gpt-4o-latest']['status']);
        $this->assertEquals('API quota exceeded', $attemptedModels['gpt-4o-latest']['error']);
        
        $this->assertArrayHasKey('gemini-2.5-flash', $attemptedModels);
        $this->assertEquals('failed', $attemptedModels['gemini-2.5-flash']['status']);
        $this->assertEquals('Invalid response format', $attemptedModels['gemini-2.5-flash']['error']);
    }
}