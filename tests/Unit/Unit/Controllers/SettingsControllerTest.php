<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Cache;

class SettingsControllerTest extends TestCase
{
    public function test_index_displays_settings_page()
    {
        $response = $this->get('/settings');
        
        $response->assertStatus(200);
        $response->assertViewIs('settings.index');
        $response->assertViewHas(['models', 'providers']);
    }
    
    public function test_get_model_settings_returns_default_config()
    {
        // Clear any cached settings
        Cache::forget('llm_settings_override');
        
        $settings = SettingsController::getModelSettings();
        
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('claude-opus-4', $settings);
        $this->assertEquals(0.05, $settings['claude-opus-4']['temperature']);
    }
    
    public function test_get_model_settings_merges_overrides()
    {
        // Set up override
        $override = [
            'claude-opus-4' => [
                'temperature' => 0.3,
                'max_tokens' => 8000
            ]
        ];
        Cache::put('llm_settings_override', $override);
        
        $settings = SettingsController::getModelSettings();
        
        $this->assertEquals(0.3, $settings['claude-opus-4']['temperature']);
        $this->assertEquals(8000, $settings['claude-opus-4']['max_tokens']);
        $this->assertEquals(0.95, $settings['claude-opus-4']['top_p']); // Should keep default
        
        // Clean up
        Cache::forget('llm_settings_override');
    }
    
    public function test_update_defaults_validates_input()
    {
        $invalidData = [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => 3.0, // Invalid: max is 2
                    'max_tokens' => 50, // Invalid: min is 100
                ]
            ]
        ];
        
        $response = $this->post('/settings/defaults', $invalidData);
        
        $response->assertSessionHasErrors([
            'models.claude-opus-4.temperature',
            'models.claude-opus-4.max_tokens'
        ]);
    }
    
    public function test_update_defaults_saves_to_cache()
    {
        $validData = [
            'models' => [
                'claude-opus-4' => [
                    'temperature' => 0.2,
                    'max_tokens' => 2000,
                    'top_p' => 0.9
                ]
            ]
        ];
        
        $response = $this->post('/settings/defaults', $validData);
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $cached = Cache::get('llm_settings_override');
        $this->assertIsArray($cached);
        $this->assertEquals(0.2, $cached['claude-opus-4']['temperature']);
    }
    
    public function test_reset_defaults_clears_cache()
    {
        // Set up some cached data
        Cache::put('llm_settings_override', ['test' => 'data']);
        
        $response = $this->post('/settings/reset');
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNull(Cache::get('llm_settings_override'));
    }
}