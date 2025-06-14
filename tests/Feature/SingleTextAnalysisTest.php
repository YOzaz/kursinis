<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class SingleTextAnalysisTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_display_single_text_form()
    {
        // Mock authentication since this might require auth
        session(['username' => 'test_user']);
        
        $response = $this->get(route('single-text'));
        
        $response->assertStatus(200)
                 ->assertViewIs('single-text')
                 ->assertSee(__('messages.single_text_analysis'))
                 ->assertSee(__('messages.text_input'))
                 ->assertSee(__('messages.select_models'));
    }

    /** @test */
    public function it_validates_required_fields()
    {
        session(['username' => 'test_user']);
        
        $response = $this->from(route('single-text'))
                         ->post(route('single-text.upload'), []);
        
        $response->assertRedirect(route('single-text'))
                 ->assertSessionHasErrors(['text_content', 'models']);
    }

    /** @test */
    public function it_validates_text_length()
    {
        session(['username' => 'test_user']);
        
        $response = $this->from(route('single-text'))
                         ->post(route('single-text.upload'), [
            'text_content' => 'short',
            'models' => ['claude-opus-4']
        ]);
        
        $response->assertRedirect(route('single-text'))
                 ->assertSessionHasErrors(['text_content']);
    }

    /** @test */
    public function it_validates_model_selection()
    {
        session(['username' => 'test_user']);
        
        $response = $this->from(route('single-text'))
                         ->post(route('single-text.upload'), [
            'text_content' => str_repeat('Valid text content for analysis. ', 5),
            'models' => []
        ]);
        
        $response->assertRedirect(route('single-text'))
                 ->assertSessionHasErrors(['models']);
    }

    /** @test */
    public function it_creates_analysis_job_for_valid_submission()
    {
        Queue::fake();
        session(['username' => 'test_user']);
        
        $response = $this->post(route('single-text.upload'), [
            'text_content' => str_repeat('Valid text content for propaganda analysis. ', 10),
            'models' => ['claude-opus-4'],
            'name' => 'Test Single Text Analysis',
            'description' => 'Test description'
        ]);
        
        $this->assertDatabaseHas('analysis_jobs', [
            'name' => 'Test Single Text Analysis',
            'description' => 'Test description',
            'total_texts' => 1,
            'status' => AnalysisJob::STATUS_PENDING
        ]);
        
        $job = AnalysisJob::where('name', 'Test Single Text Analysis')->first();
        $response->assertRedirect(route('analyses.show', $job->job_id));
    }

    /** @test */
    public function it_uses_default_name_when_not_provided()
    {
        Queue::fake();
        session(['username' => 'test_user']);
        
        $response = $this->post(route('single-text.upload'), [
            'text_content' => str_repeat('Valid text content for propaganda analysis. ', 10),
            'models' => ['claude-opus-4']
        ]);
        
        $this->assertDatabaseHas('analysis_jobs', [
            'name' => __('messages.single_text_analysis'),
            'total_texts' => 1,
            'status' => AnalysisJob::STATUS_PENDING
        ]);
    }

    /** @test */
    public function it_handles_multiple_models()
    {
        Queue::fake();
        session(['username' => 'test_user']);
        
        $response = $this->post(route('single-text.upload'), [
            'text_content' => str_repeat('Valid text content for propaganda analysis. ', 10),
            'models' => ['claude-opus-4', 'gpt-4.1']
        ]);
        
        $this->assertDatabaseHas('analysis_jobs', [
            'total_texts' => 2, // 1 text * 2 models
            'status' => AnalysisJob::STATUS_PENDING
        ]);
    }
}