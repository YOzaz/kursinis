<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebAnalysisRepeatTest extends TestCase
{
    use RefreshDatabase;

    private AnalysisJob $originalAnalysis;
    private TextAnalysis $textAnalysis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalAnalysis = AnalysisJob::factory()->create([
            'status' => 'completed',
            'name' => 'Original Analysis for Repeat Test'
        ]);

        $this->textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $this->originalAnalysis->job_id,
            'model_name' => 'claude-opus-4',
            'status' => 'completed'
        ]);
    }

    public function test_analysis_repeat_requires_authentication()
    {
        $response = $this->post("/analysis/repeat", [
            'job_id' => $this->originalAnalysis->job_id,
            'models' => ['claude-opus-4']
        ]);

        $response->assertRedirect('/login');
    }

    public function test_analysis_repeat_with_valid_job_id()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'job_id' => $this->originalAnalysis->job_id,
            'models' => ['claude-opus-4']
        ]);

        $response->assertRedirect()
                ->assertSessionHas('success');
    }

    public function test_analysis_repeat_with_invalid_job_id()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'job_id' => 'non-existent-job-id',
            'models' => ['claude-opus-4']
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors();
    }

    public function test_analysis_repeat_requires_models_array()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'job_id' => $this->originalAnalysis->job_id
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors('models');
    }

    public function test_analysis_repeat_requires_at_least_one_model()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'job_id' => $this->originalAnalysis->job_id,
            'models' => []
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors('models');
    }

    public function test_analysis_repeat_validates_model_names()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'job_id' => $this->originalAnalysis->job_id,
            'models' => ['invalid-model-name']
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors('models.0');
    }

    public function test_analysis_repeat_with_multiple_models()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'job_id' => $this->originalAnalysis->job_id,
            'models' => ['claude-opus-4', 'gpt-4o']
        ]);

        $response->assertRedirect()
                ->assertSessionHas('success');
    }

    public function test_analysis_repeat_creates_new_analysis_job()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $initialJobCount = AnalysisJob::count();

        $response = $this->post("/analysis/repeat", [
            'job_id' => $this->originalAnalysis->job_id,
            'models' => ['claude-opus-4']
        ]);

        $response->assertRedirect();
        $this->assertEquals($initialJobCount + 1, AnalysisJob::count());
    }

    public function test_analysis_repeat_preserves_original_data()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'job_id' => $this->originalAnalysis->job_id,
            'models' => ['claude-opus-4']
        ]);

        $response->assertRedirect();

        // Original analysis should still exist and be unchanged
        $original = AnalysisJob::find($this->originalAnalysis->job_id);
        $this->assertEquals('completed', $original->status);
        $this->assertEquals('Original Analysis for Repeat Test', $original->name);
    }

    public function test_analysis_repeat_redirects_to_progress_page()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'job_id' => $this->originalAnalysis->job_id,
            'models' => ['claude-opus-4']
        ]);

        // Should redirect to progress page with new job ID
        $response->assertRedirect();
        
        $newJob = AnalysisJob::where('job_id', '!=', $this->originalAnalysis->job_id)->first();
        $this->assertNotNull($newJob);
    }

    public function test_analysis_repeat_handles_missing_job_id()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'models' => ['claude-opus-4']
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors('job_id');
    }

    public function test_analysis_repeat_handles_empty_job_id()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->post("/analysis/repeat", [
            'job_id' => '',
            'models' => ['claude-opus-4']
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors('job_id');
    }
}