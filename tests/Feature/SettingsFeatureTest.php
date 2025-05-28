<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class SettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test LLM configuration
        Config::set('llm.models', [
            'claude-opus-4' => [
                'temperature' => 0.05,
                'max_tokens' => 4096,
                'provider' => 'anthropic',
                'description' => 'Claude Opus 4'
            ],
            'gpt-4.1' => [
                'temperature' => 0.05,
                'max_tokens' => 4096,
                'provider' => 'openai', 
                'description' => 'GPT-4.1'
            ]
        ]);
    }

    public function test_settings_page_requires_authentication()
    {
        $response = $this->get('/settings');
        
        $response->assertRedirect('/login');
    }

    public function test_settings_page_accessible_when_authenticated()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/settings');
        
        $response->assertStatus(200)
                ->assertSee('Sistemos nustatymai')
                ->assertSee('LLM modeliai')
                ->assertSee('Claude')
                ->assertSee('GPT');
    }

    public function test_settings_page_displays_model_configurations()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/settings');
        
        $response->assertStatus(200)
                ->assertSee('claude-opus-4')
                ->assertSee('gpt-4.1')
                ->assertSee('Temperature')
                ->assertSee('Max tokens')
                ->assertSee('0.05') // Default temperature
                ->assertSee('4096'); // Default max tokens
    }

    public function test_settings_page_shows_providers()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/settings');
        
        $response->assertStatus(200)
                ->assertSee('Anthropic')
                ->assertSee('OpenAI')
                ->assertSee('Google');
    }

    public function test_update_defaults_requires_authentication()
    {
        $response = $this->post('/settings/defaults', [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => 0.1,
                    'max_tokens' => 2048
                ]
            ]
        ]);
        
        $response->assertRedirect('/login');
    }

    public function test_update_defaults_validates_temperature_range()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->post('/settings/defaults', [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => 2.5, // Invalid: > 2
                    'max_tokens' => 2048
                ]
            ]
        ]);
        
        $response->assertRedirect()
                ->assertSessionHasErrors();
    }

    public function test_update_defaults_validates_negative_temperature()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->post('/settings/defaults', [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => -0.1, // Invalid: < 0
                    'max_tokens' => 2048
                ]
            ]
        ]);
        
        $response->assertRedirect()
                ->assertSessionHasErrors();
    }

    public function test_update_defaults_validates_max_tokens()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->post('/settings/defaults', [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => 0.1,
                    'max_tokens' => 0 // Invalid: must be positive
                ]
            ]
        ]);
        
        $response->assertRedirect()
                ->assertSessionHasErrors();
    }

    public function test_update_defaults_with_valid_data()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->post('/settings/defaults', [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => 0.1,
                    'max_tokens' => 2048
                ],
                'gpt-4.1' => [
                    'temperature' => 0.2,
                    'max_tokens' => 3000
                ]
            ]
        ]);
        
        $response->assertRedirect('/settings')
                ->assertSessionHas('success');
    }

    public function test_update_defaults_stores_settings_in_cache()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        Cache::shouldReceive('put')
            ->with('llm_settings', Mockery::any(), Mockery::any())
            ->once();
        
        $response = $this->post('/settings/defaults', [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => 0.15,
                    'max_tokens' => 2500
                ]
            ]
        ]);
        
        $response->assertRedirect('/settings');
    }

    public function test_reset_settings_requires_authentication()
    {
        $response = $this->post('/settings/reset');
        
        $response->assertRedirect('/login');
    }

    public function test_reset_settings_clears_cached_settings()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // First set some custom settings
        Cache::put('llm_settings', ['custom' => 'data'], 60);
        
        $response = $this->post('/settings/reset');
        
        $response->assertRedirect('/settings')
                ->assertSessionHas('success');
        
        // Cache should be cleared
        $this->assertNull(Cache::get('llm_settings'));
    }

    public function test_reset_settings_success_message()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->post('/settings/reset');
        
        $response->assertRedirect('/settings')
                ->assertSessionHas('success', 'Nustatymai atstatyti į pradinius.');
    }

    public function test_settings_form_has_csrf_token()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/settings');
        
        $content = $response->getContent();
        $this->assertStringContainsString('_token', $content);
        $this->assertStringContainsString('csrf_token', $content);
    }

    public function test_settings_page_shows_current_values()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // Set custom cached settings
        Cache::put('llm_settings', [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => 0.25,
                    'max_tokens' => 3500
                ]
            ]
        ], 60);
        
        $response = $this->get('/settings');
        
        $response->assertStatus(200)
                ->assertSee('0.25') // Custom temperature
                ->assertSee('3500'); // Custom max tokens
    }

    public function test_settings_page_uses_correct_view()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/settings');
        
        $response->assertStatus(200)
                ->assertViewIs('settings.index');
    }

    public function test_settings_page_passes_required_data_to_view()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/settings');
        
        $response->assertStatus(200)
                ->assertViewHas('models')
                ->assertViewHas('providers');
    }

    public function test_update_defaults_handles_partial_model_updates()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->post('/settings/defaults', [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => 0.3
                    // max_tokens not provided, should use default
                ]
            ]
        ]);
        
        $response->assertRedirect('/settings')
                ->assertSessionHas('success');
    }

    public function test_settings_page_handles_missing_models_gracefully()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // Override config with empty models
        Config::set('llm.models', []);
        
        $response = $this->get('/settings');
        
        $response->assertStatus(200)
                ->assertSee('Sistemos nustatymai');
    }
}