<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_settings_page_displays_current_configuration()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/settings');

        $response->assertStatus(200)
                ->assertSee('Nustatymai')
                ->assertSee('claude-opus-4')
                ->assertSee('gpt-4.1')
                ->assertSee('gemini-2.5-pro')
                ->assertSee('Default Models')
                ->assertSee('Provider Settings');
    }

    public function test_update_defaults_saves_and_caches_settings()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $newDefaults = [
            'anthropic' => 'claude-sonnet-4',
            'openai' => 'gpt-4o-latest',
            'google' => 'gemini-2.5-flash'
        ];

        $response = $this->post('/settings/update-defaults', [
            'default_models' => $newDefaults
        ]);

        $response->assertRedirect('/settings')
                ->assertSessionHas('success');

        // Check that settings are cached
        $cachedSettings = Cache::get('default_model_settings');
        $this->assertNotNull($cachedSettings);
        $this->assertEquals('claude-sonnet-4', $cachedSettings['anthropic']);
        $this->assertEquals('gpt-4o-latest', $cachedSettings['openai']);
        $this->assertEquals('gemini-2.5-flash', $cachedSettings['google']);
    }

    public function test_reset_defaults_clears_cache_and_restores_config()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        // First set some custom defaults
        Cache::put('default_model_settings', [
            'anthropic' => 'claude-sonnet-4',
            'openai' => 'gpt-4o-latest',
            'google' => 'gemini-2.5-flash'
        ]);

        $response = $this->post('/settings/reset');

        $response->assertRedirect('/settings')
                ->assertSessionHas('success');

        // Check that cache is cleared
        $this->assertNull(Cache::get('default_model_settings'));
    }

    public function test_get_model_settings_returns_cached_or_default_settings()
    {
        // Test without cache (should return config defaults)
        $settings = \App\Http\Controllers\SettingsController::getModelSettings();
        
        $this->assertArrayHasKey('anthropic', $settings);
        $this->assertArrayHasKey('openai', $settings);
        $this->assertArrayHasKey('google', $settings);
        $this->assertEquals('claude-opus-4', $settings['anthropic']);

        // Test with cache
        Cache::put('default_model_settings', [
            'anthropic' => 'claude-sonnet-4',
            'openai' => 'gpt-4o-latest',
            'google' => 'gemini-2.5-flash'
        ]);

        $cachedSettings = \App\Http\Controllers\SettingsController::getModelSettings();
        $this->assertEquals('claude-sonnet-4', $cachedSettings['anthropic']);
        $this->assertEquals('gpt-4o-latest', $cachedSettings['openai']);
        $this->assertEquals('gemini-2.5-flash', $cachedSettings['google']);
    }

    public function test_settings_require_authentication()
    {
        $response = $this->get('/settings');
        $response->assertRedirect('/login');

        $response = $this->post('/settings/defaults');
        $response->assertRedirect('/login');

        $response = $this->post('/settings/reset');
        $response->assertRedirect('/login');
    }

    public function test_update_defaults_validates_input()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        // Test with invalid model names
        $response = $this->post('/settings/update-defaults', [
            'default_models' => [
                'anthropic' => 'invalid-model',
                'openai' => 'another-invalid-model',
                'google' => 'yet-another-invalid-model'
            ]
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors();
    }

    public function test_update_defaults_validates_provider_keys()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        // Test with invalid provider keys
        $response = $this->post('/settings/update-defaults', [
            'default_models' => [
                'invalid_provider' => 'claude-opus-4',
                'another_invalid' => 'gpt-4.1'
            ]
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors();
    }

    public function test_settings_page_shows_current_cache_status()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        // Test without cached settings
        $response = $this->get('/settings');
        $response->assertStatus(200)
                ->assertSee('Using configuration defaults');

        // Test with cached settings
        Cache::put('default_model_settings', [
            'anthropic' => 'claude-sonnet-4',
            'openai' => 'gpt-4o-latest',
            'google' => 'gemini-2.5-flash'
        ]);

        $response = $this->get('/settings');
        $response->assertStatus(200)
                ->assertSee('claude-sonnet-4')
                ->assertSee('gpt-4o-latest')
                ->assertSee('gemini-2.5-flash');
    }

    public function test_settings_displays_model_information()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/settings');

        $response->assertStatus(200)
                ->assertSee("Anthropic's most advanced coding model")
                ->assertSee("OpenAI's multimodal flagship model")
                ->assertSee("Google's most advanced model")
                ->assertSee('Premium')
                ->assertSee('Standard');
    }

    public function test_concurrent_settings_updates_handle_cache_correctly()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        // Simulate concurrent updates
        $settings1 = ['anthropic' => 'claude-sonnet-4', 'openai' => 'gpt-4.1', 'google' => 'gemini-2.5-pro'];
        $settings2 = ['anthropic' => 'claude-opus-4', 'openai' => 'gpt-4o-latest', 'google' => 'gemini-2.5-flash'];

        $response1 = $this->post('/settings/update-defaults', ['default_models' => $settings1]);
        $response2 = $this->post('/settings/update-defaults', ['default_models' => $settings2]);

        $response1->assertRedirect('/settings');
        $response2->assertRedirect('/settings');

        // The last update should win
        $finalSettings = Cache::get('default_model_settings');
        $this->assertEquals('claude-opus-4', $finalSettings['anthropic']);
        $this->assertEquals('gpt-4o-latest', $finalSettings['openai']);
        $this->assertEquals('gemini-2.5-flash', $finalSettings['google']);
    }
}