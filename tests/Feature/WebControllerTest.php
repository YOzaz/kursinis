<?php

namespace Tests\Feature\Feature;

use App\Models\AnalysisJob;
use App\Jobs\BatchAnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
    }

    public function test_homepage_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertViewIs('index')
                ->assertSee('Propagandos analizė')
                ->assertSee('Įkelti JSON failą');
    }

    public function test_upload_json_file_successfully(): void
    {
        $jsonData = [
            [
                'id' => 1,
                'data' => ['content' => 'Test propaganda text for analysis'],
                'annotations' => [
                    ['result' => [
                        ['type' => 'labels', 'value' => [
                            'start' => 0, 'end' => 4, 'text' => 'Test',
                            'labels' => ['simplification']
                        ]]
                    ]]
                ]
            ]
        ];

        $file = UploadedFile::fake()->createWithContent(
            'test_data.json',
            json_encode($jsonData)
        );

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4', 'gemini-2.5-pro']
        ]);

        $response->assertRedirect()
                ->assertSessionHas('success');

        Queue::assertPushed(BatchAnalysisJob::class);
    }

    public function test_upload_validation_requires_file(): void
    {
        $response = $this->post('/upload', [
            'models' => ['claude-opus-4']
        ]);

        $response->assertSessionHasErrors(['json_file']);
    }

    public function test_upload_validation_requires_json_file(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);

        $response->assertSessionHasErrors(['json_file']);
    }

    public function test_upload_validation_requires_models(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'test.json',
            json_encode([['id' => 1, 'data' => ['content' => 'test']]])
        );

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => []
        ]);

        $response->assertSessionHasErrors(['models']);
    }

    public function test_upload_validation_invalid_models(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'test.json',
            json_encode([['id' => 1, 'data' => ['content' => 'test']]])
        );

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['invalid-model']
        ]);

        $response->assertSessionHasErrors(['models.0']);
    }

    public function test_upload_handles_invalid_json(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'invalid.json',
            'invalid json content'
        );

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);

        $response->assertSessionHasErrors()
                ->assertSessionHas('error');
    }

    public function test_progress_page_displays_job_status(): void
    {
        $job = AnalysisJob::factory()->processing()->create([
            'total_texts' => 100,
            'processed_texts' => 25
        ]);

        $response = $this->get("/progress/{$job->job_id}");

        $response->assertStatus(200)
                ->assertViewIs('progress')
                ->assertViewHas('job')
                ->assertSee('25%') // Progress percentage
                ->assertSee('Apdorojama');
                
        // Verify the job data is correct
        $viewJob = $response->viewData('job');
        $this->assertEquals($job->job_id, $viewJob->job_id);
        $this->assertEquals(25, $viewJob->processed_texts);
        $this->assertEquals(100, $viewJob->total_texts);
    }

    public function test_progress_page_job_not_found(): void
    {
        $response = $this->get('/progress/non-existent-job');

        $response->assertStatus(404);
    }

    public function test_progress_page_completed_job(): void
    {
        $job = AnalysisJob::factory()->completed()->create([
            'total_texts' => 50,
            'processed_texts' => 50
        ]);

        $response = $this->get("/progress/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('100%')
                ->assertSee('completed')
                ->assertSee('Atsisiųsti rezultatus');
    }

    public function test_progress_page_failed_job(): void
    {
        $job = AnalysisJob::factory()->failed()->create([
            'error_message' => 'API connection failed'
        ]);

        $response = $this->get("/progress/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('failed')
                ->assertSee('API connection failed');
    }

    public function test_upload_large_json_file(): void
    {
        $largeData = [];
        for ($i = 1; $i <= 100; $i++) {
            $largeData[] = [
                'id' => $i,
                'data' => ['content' => "Large test text number {$i} for analysis"],
                'annotations' => [
                    ['result' => [
                        ['type' => 'labels', 'value' => [
                            'start' => 0, 'end' => 5, 'text' => 'Large',
                            'labels' => ['simplification']
                        ]]
                    ]]
                ]
            ];
        }

        $file = UploadedFile::fake()->createWithContent(
            'large_test.json',
            json_encode($largeData)
        );

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);

        $response->assertRedirect()
                ->assertSessionHas('success');
    }

    public function test_upload_with_all_models(): void
    {
        $jsonData = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text'],
                'annotations' => [['result' => []]]
            ]
        ];

        $file = UploadedFile::fake()->createWithContent(
            'test.json',
            json_encode($jsonData)
        );

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4', 'gemini-2.5-pro', 'gpt-4.1']
        ]);

        $response->assertRedirect()
                ->assertSessionHas('success');

        Queue::assertPushed(BatchAnalysisJob::class, function ($job) {
            return count($job->models) === 3;
        });
    }

    public function test_homepage_shows_recent_jobs(): void
    {
        $recentJob = AnalysisJob::factory()->completed()->create([
            'created_at' => now()->subMinutes(5)
        ]);

        $oldJob = AnalysisJob::factory()->completed()->create([
            'created_at' => now()->subDays(5)
        ]);

        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertViewHas('recentJobs');

        $recentJobs = $response->viewData('recentJobs');
        $this->assertCount(2, $recentJobs);
        $this->assertEquals($recentJob->job_id, $recentJobs->first()->job_id);
    }

    public function test_upload_preserves_file_structure(): void
    {
        $complexData = [
            [
                'id' => 1,
                'data' => [
                    'content' => 'Complex test text',
                    'metadata' => ['source' => 'test', 'date' => '2025-01-01']
                ],
                'annotations' => [
                    [
                        'result' => [
                            [
                                'type' => 'labels',
                                'value' => [
                                    'start' => 0,
                                    'end' => 7,
                                    'text' => 'Complex',
                                    'labels' => ['simplification', 'emotionalExpression']
                                ]
                            ]
                        ],
                        'desinformationTechnique' => [
                            'choices' => ['distrustOfLithuanianInstitutions']
                        ]
                    ]
                ]
            ]
        ];

        $file = UploadedFile::fake()->createWithContent(
            'complex_test.json',
            json_encode($complexData)
        );

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);

        $response->assertRedirect()
                ->assertSessionHas('success');

        Queue::assertPushed(BatchAnalysisJob::class, function ($job) use ($complexData) {
            return $job->data === $complexData;
        });
    }
}