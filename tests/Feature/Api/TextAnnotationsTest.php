<?php

namespace Tests\Feature\Api;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextAnnotationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_annotations_endpoint_returns_correct_structure()
    {
        $job = AnalysisJob::factory()->completed()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test_text_1',
            'content' => 'Test propaganda content',
            'claude_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 5,
                        'end' => 15,
                        'text' => 'propaganda',
                        'labels' => ['causalOversimplification']
                    ]
                ]
            ]
        ]);

        $response = $this->getJson("/api/text-annotations/{$textAnalysis->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'content' => 'Test propaganda content',
                    'view_type' => 'ai'
                ])
                ->assertJsonStructure([
                    'success',
                    'content',
                    'text',
                    'annotations',
                    'legend',
                    'view_type'
                ]);
    }

    public function test_text_annotations_endpoint_with_view_parameter()
    {
        $job = AnalysisJob::factory()->completed()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'claude_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 10,
                        'text' => 'test',
                        'labels' => ['emotionalAppeal']
                    ]
                ]
            ]
        ]);

        $response = $this->getJson("/api/text-annotations/{$textAnalysis->id}?view=ai");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'content',
                    'text',
                    'annotations',
                    'legend',
                    'view_type'
                ])
                ->assertJson([
                    'view_type' => 'ai'
                ]);
    }

    public function test_text_annotations_endpoint_with_invalid_id()
    {
        $response = $this->getJson('/api/text-annotations/999999');

        $response->assertStatus(404);
    }

    public function test_text_annotations_endpoint_with_expert_view()
    {
        $job = AnalysisJob::factory()->completed()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'expert_annotations' => [
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 0,
                            'end' => 4,
                            'text' => 'Test',
                            'labels' => ['emotionalAppeal']
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->getJson("/api/text-annotations/{$textAnalysis->id}?view=expert");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'view_type' => 'expert'
                ]);
    }
}