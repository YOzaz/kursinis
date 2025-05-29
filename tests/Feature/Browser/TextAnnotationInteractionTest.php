<?php

namespace Tests\Feature\Browser;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test text annotation interaction functionality
 */
class TextAnnotationInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_annotation_toggle_functionality()
    {
        // Create authenticated session
        session(['authenticated' => true]);

        // Create completed analysis with text
        $job = AnalysisJob::factory()->completed()->create([
            'name' => 'Test Analysis for Annotations'
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test_001',
            'content' => 'This is test content for annotation testing',
            'claude_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 4,
                        'text' => 'This',
                        'labels' => ['emotionalExpression']
                    ]
                ]
            ]
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('Analizės detalės')
                ->assertSee('Detalės') // Modal trigger button
                ->assertSee('modal-annotation-toggle') // Annotation toggle checkbox
                ->assertSee('modal-ai-model-select') // Model selector
                ->assertSee('Rodyti anotacijas'); // Toggle label
    }

    public function test_model_selector_shows_available_models()
    {
        // Create authenticated session
        session(['authenticated' => true]);

        // Create completed analysis with multiple models
        $job = AnalysisJob::factory()->completed()->create();

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'claude_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 4,
                        'text' => 'Test',
                        'labels' => ['emotionalExpression']
                    ]
                ]
            ],
            'gemini_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 5,
                        'end' => 11,
                        'text' => 'content',
                        'labels' => ['emotionalExpression']
                    ]
                ]
            ]
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('Visi modeliai'); // "All models" option
    }

    public function test_expert_annotations_toggle_functionality()
    {
        // Create authenticated session
        session(['authenticated' => true]);

        // Create completed analysis with expert annotations
        $job = AnalysisJob::factory()->completed()->create();

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'expert_annotations' => [
                [
                    'id' => 1,
                    'result' => [
                        [
                            'type' => 'labels',
                            'value' => [
                                'start' => 0,
                                'end' => 10,
                                'text' => 'Expert test',
                                'labels' => ['simplification']
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('AI anotacijos')
                ->assertSee('Ekspertų anotacijos');
    }

    public function test_annotation_toggle_controls_visibility()
    {
        // Create authenticated session
        session(['authenticated' => true]);

        // Create completed analysis
        $job = AnalysisJob::factory()->completed()->create();

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'content' => 'Test content for annotation visibility testing'
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200);
        
        // Check that model selector is hidden by default (style="display: none;")
        $this->assertStringContainsString('style="display: none;"', $response->getContent());
    }

    public function test_text_annotations_api_endpoint_with_model_parameter()
    {
        // Create text analysis with multiple models
        $job = AnalysisJob::factory()->completed()->create();
        
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'claude_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 4,
                        'text' => 'Test',
                        'labels' => ['emotionalExpression']
                    ]
                ]
            ]
        ]);

        // Test API with specific model
        $response = $this->getJson("/api/text-annotations/{$textAnalysis->id}?view=ai&model=claude&enabled=true");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'content',
                    'annotations',
                    'legend',
                    'view_type'
                ])
                ->assertJson([
                    'success' => true,
                    'view_type' => 'ai'
                ]);

        // Test API with all models
        $response = $this->getJson("/api/text-annotations/{$textAnalysis->id}?view=ai&model=all&enabled=true");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'view_type' => 'ai'
                ]);

        // Test with annotations disabled
        $response = $this->getJson("/api/text-annotations/{$textAnalysis->id}?enabled=false");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'annotations' => [],
                    'legend' => []
                ]);
    }

    public function test_expert_annotations_api_functionality()
    {
        // Create text analysis with expert annotations in Label Studio format
        $job = AnalysisJob::factory()->completed()->create();
        
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'content' => 'Expert annotated content with propaganda techniques',
            'expert_annotations' => [
                [
                    'id' => 1,
                    'result' => [
                        [
                            'type' => 'labels',
                            'value' => [
                                'start' => 0,
                                'end' => 6,
                                'text' => 'Expert',
                                'labels' => ['simplification']
                            ]
                        ],
                        [
                            'type' => 'labels',
                            'value' => [
                                'start' => 25,
                                'end' => 35,
                                'text' => 'propaganda',
                                'labels' => ['emotionalExpression']
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Test expert annotations API
        $response = $this->getJson("/api/text-annotations/{$textAnalysis->id}?view=expert&enabled=true");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'view_type' => 'expert'
                ])
                ->assertJsonStructure([
                    'success',
                    'content',
                    'annotations' => [
                        '*' => [
                            'start',
                            'end',
                            'technique',
                            'text'
                        ]
                    ],
                    'legend',
                    'view_type'
                ]);

        // Verify that annotations are properly extracted
        $data = $response->json();
        $this->assertCount(2, $data['annotations']);
        $this->assertEquals('simplification', $data['annotations'][0]['technique']);
        $this->assertEquals('emotionalExpression', $data['annotations'][1]['technique']);
    }
}