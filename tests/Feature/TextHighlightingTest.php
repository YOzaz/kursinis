<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TextHighlightingTest extends TestCase
{
    use RefreshDatabase;

    private AnalysisJob $analysisJob;
    private TextAnalysis $textAnalysis;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a sample analysis job
        $this->analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        // Create a sample text analysis with annotations
        $this->textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $this->analysisJob->job_id,
            'content' => 'Tai yra pavyzdinis tekstas su propaganda. Čia naudojama emocinė raiška ir whataboutism metodai.',
            'claude_annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 25,
                            'end' => 51,
                            'text' => 'tekstas su propaganda',
                            'labels' => ['Emocinė raiška']
                        ]
                    ],
                    [
                        'type' => 'labels', 
                        'value' => [
                            'start' => 67,
                            'end' => 82,
                            'text' => 'emocinė raiška',
                            'labels' => ['Emocinė raiška', 'Whataboutism']
                        ]
                    ]
                ]
            ],
            'expert_annotations' => [
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 25,
                            'end' => 51,
                            'text' => 'tekstas su propaganda',
                            'labels' => ['Emocinė raiška']
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function test_can_get_ai_annotations()
    {
        $response = $this->getJson("/api/text-annotations/{$this->textAnalysis->id}?view=ai");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'view_type' => 'ai'
            ])
            ->assertJsonStructure([
                'success',
                'text',
                'annotations' => [
                    '*' => [
                        'start',
                        'end', 
                        'technique',
                        'text'
                    ]
                ],
                'legend' => [
                    '*' => [
                        'technique',
                        'color',
                        'number'
                    ]
                ],
                'view_type'
            ]);

        $data = $response->json();
        $this->assertEquals($this->textAnalysis->content, $data['text']);
        $this->assertGreaterThanOrEqual(2, count($data['annotations'])); // At least two annotation fragments
        $this->assertNotEmpty($data['legend']);
    }

    public function test_can_get_expert_annotations()
    {
        $response = $this->getJson("/api/text-annotations/{$this->textAnalysis->id}?view=expert");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'view_type' => 'expert'
            ]);

        $data = $response->json();
        $this->assertEquals($this->textAnalysis->content, $data['text']);
        $this->assertCount(1, $data['annotations']); // One expert annotation
        $this->assertNotEmpty($data['legend']);
    }

    public function test_expert_view_fails_when_no_expert_annotations()
    {
        // Create text analysis without expert annotations
        $textWithoutExpertAnnotations = TextAnalysis::factory()->create([
            'job_id' => $this->analysisJob->job_id,
            'content' => 'Tekstas be ekspertų anotacijų',
            'expert_annotations' => []
        ]);

        $response = $this->getJson("/api/text-annotations/{$textWithoutExpertAnnotations->id}?view=expert");

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Šiam tekstui nėra ekspertų anotacijų'
            ]);
    }

    public function test_defaults_to_ai_view_when_no_view_specified()
    {
        $response = $this->getJson("/api/text-annotations/{$this->textAnalysis->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'view_type' => 'ai'
            ]);
    }

    public function test_returns_404_for_nonexistent_text_analysis()
    {
        $response = $this->getJson("/api/text-annotations/99999");

        $response->assertStatus(404);
    }

    public function test_legend_has_consistent_colors_and_numbering()
    {
        $response = $this->getJson("/api/text-annotations/{$this->textAnalysis->id}?view=ai");
        
        $data = $response->json();
        $legend = $data['legend'];
        
        // Check that each legend item has required fields
        foreach ($legend as $item) {
            $this->assertArrayHasKey('technique', $item);
            $this->assertArrayHasKey('color', $item);
            $this->assertArrayHasKey('number', $item);
            $this->assertIsString($item['technique']);
            $this->assertIsString($item['color']);
            $this->assertIsInt($item['number']);
            $this->assertStringStartsWith('#', $item['color']); // Color should be hex
        }
        
        // Check numbering sequence
        $numbers = array_column($legend, 'number');
        $this->assertEquals(range(1, count($legend)), $numbers);
    }

    public function test_annotations_have_valid_positions()
    {
        $response = $this->getJson("/api/text-annotations/{$this->textAnalysis->id}?view=ai");
        
        $data = $response->json();
        $annotations = $data['annotations'];
        $text = $data['text'];
        $textLength = strlen($text);
        
        foreach ($annotations as $annotation) {
            $this->assertArrayHasKey('start', $annotation);
            $this->assertArrayHasKey('end', $annotation);
            $this->assertIsInt($annotation['start']);
            $this->assertIsInt($annotation['end']);
            $this->assertGreaterThanOrEqual(0, $annotation['start']);
            $this->assertLessThanOrEqual($textLength, $annotation['end']);
            $this->assertGreaterThan($annotation['start'], $annotation['end']); // end should be after start
        }
    }

    public function test_ai_annotations_merge_multiple_models()
    {
        // Add GPT annotations to test merging
        $this->textAnalysis->update([
            'gpt_annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 67,
                            'end' => 82,
                            'text' => 'emocinė raiška',
                            'labels' => ['Emocinė raiška']
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->getJson("/api/text-annotations/{$this->textAnalysis->id}?view=ai");
        
        $data = $response->json();
        
        // Should still have reasonable number of annotations (not duplicated)
        $this->assertLessThanOrEqual(3, count($data['annotations']));
        $this->assertNotEmpty($data['legend']);
    }
}