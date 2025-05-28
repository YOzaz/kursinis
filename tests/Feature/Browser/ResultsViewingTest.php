<?php

namespace Tests\Feature\Browser;

use App\Models\AnalysisJob;
use App\Models\ComparisonMetric;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Browser tests for viewing analysis results
 */
class ResultsViewingTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyses_list_page_displays_jobs()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        AnalysisJob::factory()->create([
            'name' => 'Test Analysis 1',
            'status' => AnalysisJob::STATUS_COMPLETED
        ]);
        
        AnalysisJob::factory()->create([
            'name' => 'Test Analysis 2',
            'status' => AnalysisJob::STATUS_PROCESSING
        ]);

        $response = $this->get('/analyses');

        $response->assertStatus(200)
                ->assertSee('Test Analysis 1')
                ->assertSee('Test Analysis 2')
                ->assertSee('completed')
                ->assertSee('processing');
    }

    public function test_analysis_details_page_shows_results()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create([
            'name' => 'Detailed Analysis'
        ]);
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1',
            'content' => 'Test propaganda content',
            'model_name' => 'claude-opus-4',
            'ai_annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 0,
                            'end' => 4,
                            'text' => 'Test',
                            'labels' => ['emotional_appeal']
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('Test propaganda content')
                ->assertSee('claude-opus-4')
                ->assertSee('emotional_appeal');
    }

    public function test_text_highlighting_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1',
            'content' => 'Test propaganda text with emotional appeals',
            'ai_annotations' => [
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 0,
                            'end' => 4,
                            'text' => 'Test',
                            'labels' => ['emotional_appeal']
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('highlighted-text', false)
                ->assertSee('data-labels', false);
    }

    public function test_model_comparison_view()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        // Create analyses for different models
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1',
            'model_name' => 'claude-opus-4'
        ]);
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1',
            'model_name' => 'gpt-4.1'
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('claude-opus-4')
                ->assertSee('gpt-4.1')
                ->assertSee('model-comparison', false);
    }

    public function test_export_functionality_links()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('export', false)
                ->assertSee('CSV')
                ->assertSee('JSON');
    }

    public function test_analysis_repeat_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('repeat-analysis', false)
                ->assertSee('Pakartoti analizę');
    }

    public function test_statistics_display()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1',
            'model_name' => 'claude-opus-4',
            'precision' => 0.85,
            'recall' => 0.90,
            'f1_score' => 0.87
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('0.85')
                ->assertSee('0.90')
                ->assertSee('0.87');
    }

    public function test_ai_vs_expert_view_toggle()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1',
            'ai_annotations' => ['annotations' => []],
            'expert_annotations' => ['annotations' => []]
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('ai-view', false)
                ->assertSee('expert-view', false)
                ->assertSee('view-toggle', false);
    }

    public function test_pagination_for_large_result_sets()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        // Create many text analyses
        for ($i = 1; $i <= 25; $i++) {
            TextAnalysis::factory()->create([
                'job_id' => $job->job_id,
                'text_id' => (string)$i
            ]);
        }

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200);
        
        // Check if pagination or DataTables is present
        $this->assertTrue(
            str_contains($response->getContent(), 'pagination') ||
            str_contains($response->getContent(), 'DataTables')
        );
    }

    public function test_analysis_details_modal()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('modal', false)
                ->assertSee('text-details', false);
    }

    public function test_legend_display_for_annotations()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1',
            'ai_annotations' => [
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 0,
                            'end' => 4,
                            'text' => 'Test',
                            'labels' => ['emotional_appeal', 'simplification']
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('legend', false)
                ->assertSee('emotional_appeal')
                ->assertSee('simplification');
    }

    public function test_search_and_filter_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/analyses');

        $response->assertStatus(200)
                ->assertSee('search', false)
                ->assertSee('filter', false);
    }

    public function test_responsive_layout_elements()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('col-md-', false)
                ->assertSee('col-lg-', false)
                ->assertSee('responsive', false);
    }
}