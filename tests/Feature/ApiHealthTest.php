<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok()
    {
        $response = $this->getJson('/api/health');
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'ok',
                    'service' => 'Propaganda Analysis API'
                ])
                ->assertJsonStructure([
                    'status',
                    'service',
                    'timestamp',
                    'environment'
                ]);
    }

    public function test_health_endpoint_includes_timestamp()
    {
        $response = $this->getJson('/api/health');
        
        $response->assertStatus(200)
                ->assertJsonStructure(['timestamp']);
                
        $data = $response->json();
        $this->assertNotEmpty($data['timestamp']);
        $this->assertTrue(strtotime($data['timestamp']) !== false);
    }

    public function test_health_endpoint_includes_environment()
    {
        $response = $this->getJson('/api/health');
        
        $response->assertStatus(200)
                ->assertJsonStructure(['environment']);
                
        $data = $response->json();
        $this->assertEquals('testing', $data['environment']);
    }

    public function test_models_endpoint_returns_available_models()
    {
        $response = $this->getJson('/api/models');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'models' => [
                        '*' => [
                            'name',
                            'provider',
                            'available',
                            'description'
                        ]
                    ]
                ]);
    }

    public function test_models_endpoint_includes_claude_models()
    {
        $response = $this->getJson('/api/models');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        $modelNames = array_column($data['models'], 'name');
        
        $this->assertContains('claude-opus-4', $modelNames);
        $this->assertContains('claude-sonnet-4', $modelNames);
    }

    public function test_models_endpoint_includes_openai_models()
    {
        $response = $this->getJson('/api/models');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        $modelNames = array_column($data['models'], 'name');
        
        $this->assertContains('gpt-4o', $modelNames);
        $this->assertContains('gpt-4o-mini', $modelNames);
    }

    public function test_models_endpoint_includes_gemini_models()
    {
        $response = $this->getJson('/api/models');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        $modelNames = array_column($data['models'], 'name');
        
        $this->assertContains('gemini-1.5-pro', $modelNames);
        $this->assertContains('gemini-1.5-flash', $modelNames);
    }

    public function test_models_endpoint_shows_availability_status()
    {
        $response = $this->getJson('/api/models');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        
        foreach ($data['models'] as $model) {
            $this->assertArrayHasKey('available', $model);
            $this->assertIsBool($model['available']);
        }
    }

    public function test_models_endpoint_includes_provider_information()
    {
        $response = $this->getJson('/api/models');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        $providers = array_unique(array_column($data['models'], 'provider'));
        
        $this->assertContains('anthropic', $providers);
        $this->assertContains('openai', $providers);
        $this->assertContains('google', $providers);
    }

    public function test_health_endpoint_works_without_authentication()
    {
        // Ensure no authentication is required for health check
        $response = $this->getJson('/api/health');
        
        $response->assertStatus(200);
    }

    public function test_models_endpoint_works_without_authentication()
    {
        // Ensure no authentication is required for models list
        $response = $this->getJson('/api/models');
        
        $response->assertStatus(200);
    }
}