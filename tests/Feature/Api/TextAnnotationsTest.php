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
            'expert_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 4,
                        'text' => 'Test',
                        'labels' => ['emotionalAppeal']
                    ]
                ]
            ],
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
                    'text_id' => 'test_text_1',
                    'content' => 'Test propaganda content',
                    'expert_annotations' => [
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
                ])
                ->assertJsonStructure([
                    'text_id',
                    'content',
                    'expert_annotations',
                    'claude_annotations',
                    'gemini_annotations', 
                    'gpt_annotations'
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
                    'text_id',
                    'content',
                    'claude_annotations'
                ]);
    }

    public function test_text_annotations_endpoint_with_invalid_id()
    {
        $response = $this->getJson('/api/text-annotations/999999');

        $response->assertStatus(404);
    }

    public function test_text_annotations_endpoint_with_invalid_view()
    {
        $job = AnalysisJob::factory()->completed()->create();
        $textAnalysis = TextAnalysis::factory()->create(['job_id' => $job->job_id]);

        $response = $this->getJson("/api/text-annotations/{$textAnalysis->id}?view=invalid");

        $response->assertStatus(400);
    }
}