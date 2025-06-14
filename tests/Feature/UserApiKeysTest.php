<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

class UserApiKeysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Simulate SimpleAuth session
        session(['authenticated' => true, 'username' => 'testuser']);
    }

    public function test_settings_page_shows_api_key_section_for_authenticated_users()
    {
        $response = $this->get('/settings');
        
        $response->assertStatus(200);
        $response->assertSee('Mano API raktai');
        $response->assertSee('Anthropic');
        $response->assertSee('OpenAI');
        $response->assertSee('Google');
    }

    public function test_user_can_save_api_keys()
    {
        $response = $this->post('/settings/api-keys', [
            'api_keys' => [
                'anthropic' => 'sk-ant-test-key-123',
                'openai' => 'sk-test-key-456',
                'google' => 'AIza-test-key-789',
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'API raktai sėkmingai atnaujinti!');

        // Check that user was created
        $user = User::where('email', 'testuser@local')->first();
        $this->assertNotNull($user);

        // Check that API keys were saved
        $this->assertEquals(3, $user->apiKeys()->count());
        
        $anthropicKey = $user->apiKeys()->where('provider', 'anthropic')->first();
        $this->assertNotNull($anthropicKey);
        $this->assertEquals('sk-ant-test-key-123', Crypt::decryptString($anthropicKey->api_key));
        $this->assertTrue($anthropicKey->is_active);
    }

    public function test_user_can_delete_api_key()
    {
        // Create user with API key
        $user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@local',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
        ]);

        $apiKey = UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => Crypt::encryptString('sk-ant-test-key-123'),
            'is_active' => true,
        ]);

        $response = $this->delete('/settings/api-keys', [
            'provider' => 'anthropic'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'API raktas pašalintas!');

        $this->assertDatabaseMissing('user_api_keys', [
            'id' => $apiKey->id
        ]);
    }

    public function test_api_keys_are_masked_in_view()
    {
        // Create user with API key
        $user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@local',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
        ]);

        UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => Crypt::encryptString('sk-ant-api-03-very-long-test-key-123456789'),
            'is_active' => true,
        ]);

        $response = $this->get('/settings');
        
        $response->assertStatus(200);
        // Check that we have the configured badge
        $response->assertSee(__('messages.configured'));
        // Check that the original key is not visible
        $response->assertDontSee('sk-ant-api-03-very-long-test-key-123456789');
    }

    public function test_llm_service_uses_user_api_key()
    {
        // Create user with API key
        $user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@local',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
        ]);

        UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => Crypt::encryptString('sk-ant-user-specific-key'),
            'is_active' => true,
        ]);

        $llmService = new \App\Services\LLMService();
        $apiKey = $llmService->getApiKey('anthropic', $user);
        
        $this->assertEquals('sk-ant-user-specific-key', $apiKey);
    }

    public function test_invalid_api_key_validation()
    {
        $response = $this->post('/settings/api-keys', [
            'api_keys' => [
                'anthropic' => 'short', // Too short
            ]
        ]);

        $response->assertSessionHasErrors(['api_keys.anthropic']);
    }

    public function test_usage_stats_are_updated_when_api_key_is_used()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@local',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
        ]);

        $apiKey = UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => Crypt::encryptString('sk-ant-test-key'),
            'is_active' => true,
        ]);

        $llmService = new \App\Services\LLMService();
        $llmService->getApiKey('anthropic', $user);

        $apiKey->refresh();
        $this->assertNotNull($apiKey->last_used_at);
        $this->assertEquals(1, $apiKey->usage_stats['total_requests']);
    }
}