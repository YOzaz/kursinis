<?php

namespace Tests\Feature\Browser;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Simple browser-style tests for text highlighting functionality
 * Tests the rendered HTML and JavaScript functionality without requiring Dusk
 */
class SimpleTextHighlightingTest extends TestCase
{
    use RefreshDatabase;

    private AnalysisJob $analysisJob;
    private TextAnalysis $textAnalysis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed',
            'name' => 'Text Highlighting Test'
        ]);

        $this->textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $this->analysisJob->job_id,
            'content' => 'Tai yra propagandos tekstas su emocinėmis technikomis.',
            'claude_annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 12,
                            'end' => 31,
                            'text' => 'propagandos tekstas',
                            'labels' => ['Emocinė raiška']
                        ]
                    ]
                ]
            ],
            'expert_annotations' => [
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 12,
                            'end' => 31,
                            'text' => 'propagandos tekstas',
                            'labels' => ['Emocinė raiška']
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function test_analysis_page_contains_highlighting_interface()
    {
        // Handle authentication if required
        $this->withoutMiddleware();
        
        $response = $this->get("/analyses/{$this->analysisJob->job_id}");

        $response->assertStatus(200)
                ->assertSee('Analizės detalės')
                ->assertSee($this->analysisJob->job_id)
                
                // Check for modal structure
                ->assertSee('analysisModal' . $this->textAnalysis->id)
                
                // Check for highlighting interface elements
                ->assertSee('annotation-view-' . $this->textAnalysis->id)
                ->assertSee('AI analizė')
                ->assertSee('Ekspertų vertinimas')
                ->assertSee('legend-' . $this->textAnalysis->id)
                ->assertSee('highlighted-text-' . $this->textAnalysis->id)
                
                // Check for JavaScript functions
                ->assertSee('loadTextAnnotations')
                ->assertSee('toggleTextSize')
                ->assertSee('displayHighlightedText');
    }

    public function test_modal_has_correct_structure_for_highlighting()
    {
        $this->withoutMiddleware();
        $response = $this->get("/analyses/{$this->analysisJob->job_id}");

        $content = $response->getContent();
        
        // Verify modal has correct size class
        $this->assertStringContainsString('modal-xl', $content);
        
        // Verify radio button structure
        $this->assertStringContainsString('btn-check', $content);
        $this->assertStringContainsString('name="annotation-view-' . $this->textAnalysis->id . '"', $content);
        
        // Verify legend container
        $this->assertStringContainsString('id="legend-' . $this->textAnalysis->id . '"', $content);
        
        // Verify text container
        $this->assertStringContainsString('id="highlighted-text-' . $this->textAnalysis->id . '"', $content);
        
        // Verify loading indicator
        $this->assertStringContainsString('fa-spinner fa-spin', $content);
    }

    public function test_javascript_functions_are_included()
    {
        $this->withoutMiddleware();
        $response = $this->get("/analyses/{$this->analysisJob->job_id}");

        $content = $response->getContent();
        
        // Check for key JavaScript functions
        $this->assertStringContainsString('function toggleTextSize(', $content);
        $this->assertStringContainsString('function loadTextAnnotations(', $content);
        $this->assertStringContainsString('function displayHighlightedText(', $content);
        $this->assertStringContainsString('function displayLegend(', $content);
        
        // Check for event listeners setup
        $this->assertStringContainsString('addEventListener(\'change\'', $content);
        $this->assertStringContainsString('addEventListener(\'shown.bs.modal\'', $content);
    }

    public function test_api_annotations_endpoint_works()
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
                    'annotations',
                    'legend',
                    'view_type'
                ]);
    }

    public function test_expert_annotations_endpoint_works()
    {
        $response = $this->getJson("/api/text-annotations/{$this->textAnalysis->id}?view=expert");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'view_type' => 'expert'
                ]);
    }

    public function test_modal_includes_proper_accessibility_attributes()
    {
        $this->withoutMiddleware();
        $response = $this->get("/analyses/{$this->analysisJob->job_id}");

        $content = $response->getContent();
        
        // Check for accessibility attributes
        $this->assertStringContainsString('role="group"', $content);
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $content);
        $this->assertStringContainsString('data-bs-placement="top"', $content);
        
        // Check for proper labeling
        $this->assertStringContainsString('for="ai-view-' . $this->textAnalysis->id . '"', $content);
        $this->assertStringContainsString('for="expert-view-' . $this->textAnalysis->id . '"', $content);
    }

    public function test_highlighting_interface_responsive_classes()
    {
        $this->withoutMiddleware();
        $response = $this->get("/analyses/{$this->analysisJob->job_id}");

        $content = $response->getContent();
        
        // Check for Bootstrap responsive classes
        $this->assertStringContainsString('d-flex', $content);
        $this->assertStringContainsString('flex-wrap', $content);
        $this->assertStringContainsString('gap-2', $content);
        $this->assertStringContainsString('btn-group-sm', $content);
        
        // Check for proper spacing classes
        $this->assertStringContainsString('mb-2', $content);
        $this->assertStringContainsString('mb-3', $content);
        $this->assertStringContainsString('p-2', $content);
    }

    public function test_page_includes_necessary_css_classes()
    {
        $this->withoutMiddleware();
        $response = $this->get("/analyses/{$this->analysisJob->job_id}");

        $content = $response->getContent();
        
        // Check for highlighting-specific CSS classes
        $this->assertStringContainsString('propaganda-highlight', $content);
        $this->assertStringContainsString('legend-item', $content);
        $this->assertStringContainsString('legend-color', $content);
        $this->assertStringContainsString('technique-number', $content);
    }

    public function test_modal_has_proper_bootstrap_structure()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        $response = $this->get("/analyses/{$this->analysisJob->job_id}");

        $content = $response->getContent();
        
        // Verify Bootstrap modal structure
        $this->assertStringContainsString('class="modal fade"', $content);
        $this->assertStringContainsString('class="modal-dialog modal-xl"', $content);
        $this->assertStringContainsString('class="modal-content"', $content);
        $this->assertStringContainsString('class="modal-header"', $content);
        $this->assertStringContainsString('class="modal-body"', $content);
        $this->assertStringContainsString('class="btn-close"', $content);
    }

    public function test_error_handling_displays_properly()
    {
        // Test with non-existent text analysis
        $response = $this->getJson("/api/text-annotations/99999?view=ai");

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Tekstas nerastas'
                ]);
    }
}