<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * API integration tests for core endpoints.
 * Tests essential API functionality without duplicating unit test coverage.
 */
class ApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_health_endpoint_responds()
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'timestamp',
                    'services',
                    'models'
                ]);
    }

    public function test_api_models_endpoint_responds()
    {
        $response = $this->get('/api/models');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'models' => [
                        '*' => [
                            'name',
                            'provider', 
                            'configured'
                        ]
                    ]
                ]);
    }

    public function test_api_default_prompt_endpoint_responds()
    {
        $response = $this->get('/api/default-prompt');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'prompt'
                ]);
    }

    public function test_api_analyze_endpoint_validates_input()
    {
        $response = $this->postJson('/api/analyze', []);

        $response->assertStatus(422);
    }

    public function test_api_batch_analyze_endpoint_exists()
    {
        $response = $this->postJson('/api/batch-analyze', []);

        $response->assertStatus(422);
    }

    public function test_api_dashboard_export_endpoint()
    {
        $response = $this->get('/api/dashboard/export?format=json');

        $response->assertStatus(200);
    }

    public function test_api_dashboard_export_handles_invalid_format()
    {
        $response = $this->get('/api/dashboard/export?format=invalid');

        $response->assertStatus(400)
                ->assertJsonStructure(['error']);
    }
}