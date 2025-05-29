<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ModelsTest extends TestCase
{
    public function test_models_endpoint_returns_available_models()
    {
        $response = $this->getJson('/api/models');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'models' => [
                        '*' => [
                            'key',
                            'name',
                            'provider',
                            'model',
                            'configured',
                            'available'
                        ]
                    ]
                ]);
    }

    public function test_models_endpoint_includes_expected_providers()
    {
        $response = $this->getJson('/api/models');

        $data = $response->json();
        $providers = collect($data['models'])->pluck('provider')->unique()->values()->toArray();

        $this->assertContains('Anthropic', $providers);
        $this->assertContains('OpenAI', $providers);
        $this->assertContains('Google', $providers);
    }

    public function test_models_endpoint_includes_claude_models()
    {
        $response = $this->getJson('/api/models');

        $data = $response->json();
        $claudeModels = collect($data['models'])
            ->where('provider', 'Anthropic')
            ->pluck('key')
            ->toArray();

        $this->assertContains('claude-opus-4', $claudeModels);
        $this->assertContains('claude-sonnet-4', $claudeModels);
    }

    public function test_models_endpoint_includes_openai_models()
    {
        $response = $this->getJson('/api/models');

        $data = $response->json();
        $openaiModels = collect($data['models'])
            ->where('provider', 'OpenAI')
            ->pluck('key')
            ->toArray();

        $this->assertContains('gpt-4.1', $openaiModels);
    }

    public function test_models_endpoint_includes_gemini_models()
    {
        $response = $this->getJson('/api/models');

        $data = $response->json();
        $geminiModels = collect($data['models'])
            ->where('provider', 'Google')
            ->pluck('key')
            ->toArray();

        $this->assertContains('gemini-2.5-pro', $geminiModels);
    }

    public function test_models_have_required_fields()
    {
        $response = $this->getJson('/api/models');

        $data = $response->json();
        
        foreach ($data['models'] as $model) {
            $this->assertArrayHasKey('key', $model);
            $this->assertArrayHasKey('name', $model);
            $this->assertArrayHasKey('provider', $model);
            $this->assertArrayHasKey('available', $model);
            $this->assertArrayHasKey('configured', $model);
            
            $this->assertNotEmpty($model['key']);
            $this->assertNotEmpty($model['name']);
            $this->assertNotEmpty($model['provider']);
            $this->assertIsBool($model['available']);
            $this->assertIsBool($model['configured']);
        }
    }
}