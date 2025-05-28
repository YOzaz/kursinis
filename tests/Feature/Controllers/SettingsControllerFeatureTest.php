<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class SettingsControllerFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_index_loads_successfully(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/settings');

        $response->assertStatus(200)
                ->assertSee('Sistemos nustatymai')
                ->assertSee('LLM')
                ->assertViewIs('settings.index');
    }

    public function test_settings_redirects_when_not_authenticated(): void
    {
        $response = $this->get('/settings');

        $response->assertRedirect('/login');
    }

    public function test_settings_displays_llm_configuration(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/settings');

        $content = $response->getContent();
        $this->assertStringContainsString('claude-opus-4', $content);
        $this->assertStringContainsString('claude-sonnet-4', $content);
        $this->assertStringContainsString('gpt-4.1', $content);
        $this->assertStringContainsString('gemini-2.5-pro', $content);
    }

    public function test_settings_clear_cache_functionality(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        // Set some cache data
        Cache::put('test_cache_key', 'test_value', 60);
        $this->assertTrue(Cache::has('test_cache_key'));

        $response = $this->post('/settings/clear-cache');

        $response->assertRedirect('/settings')
                ->assertSessionHas('success');

        // Cache should be cleared
        $this->assertFalse(Cache::has('test_cache_key'));
    }

    public function test_settings_page_contains_forms(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/settings');

        $content = $response->getContent();
        $this->assertStringContainsString('<form', $content);
        $this->assertStringContainsString('method="POST"', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function test_settings_displays_system_information(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/settings');

        $content = $response->getContent();
        $this->assertStringContainsString('PHP versi', $content);
        $this->assertStringContainsString('Laravel', $content);
        $this->assertStringContainsString('Cache', $content);
    }

    public function test_clear_cache_requires_authentication(): void
    {
        $response = $this->post('/settings/clear-cache');

        $response->assertRedirect('/login');
    }

    public function test_settings_shows_llm_status(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/settings');

        $content = $response->getContent();
        // Should show status indicators for LLM services
        $this->assertStringContainsString('badge', $content);
        $this->assertStringContainsString('status', $content);
    }
}