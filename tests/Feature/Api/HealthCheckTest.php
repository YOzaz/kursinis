<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_status()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'ok',
                    'timestamp' => true
                ])
                ->assertJsonStructure([
                    'status',
                    'timestamp',
                    'services' => [
                        'database',
                        'redis',
                        'queue'
                    ]
                ]);
    }

    public function test_health_endpoint_includes_service_status()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $this->assertArrayHasKey('services', $data);
        $this->assertArrayHasKey('database', $data['services']);
        $this->assertArrayHasKey('redis', $data['services']);
        $this->assertArrayHasKey('queue', $data['services']);
        
        // Database should be working since tests are running
        $this->assertEquals('ok', $data['services']['database']);
    }

    public function test_health_endpoint_timestamp_format()
    {
        $response = $this->getJson('/api/health');

        $data = $response->json();
        
        $this->assertIsString($data['timestamp']);
        $this->assertNotEmpty($data['timestamp']);
        
        // Verify timestamp is in a valid format
        $timestamp = strtotime($data['timestamp']);
        $this->assertNotFalse($timestamp);
        $this->assertGreaterThan(time() - 10, $timestamp); // Should be recent
    }
}