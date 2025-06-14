<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'user'
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role' => 'user'
        ]);
    }

    public function test_user_role_checks()
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertTrue($superadmin->isSuperAdmin());
        $this->assertTrue($superadmin->isAdmin());

        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($admin->isAdmin());

        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_api_key_relationship()
    {
        $user = User::factory()->create();
        
        $apiKey = UserApiKey::factory()->create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => 'test-key'
        ]);

        $this->assertCount(1, $user->apiKeys);
        $this->assertEquals('anthropic', $user->apiKeys->first()->provider);
    }

    public function test_user_can_get_api_key_for_provider()
    {
        $user = User::factory()->create();
        
        UserApiKey::factory()->create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => 'test-anthropic-key',
            'is_active' => true
        ]);

        $this->assertEquals('test-anthropic-key', $user->getApiKey('anthropic'));
        $this->assertNull($user->getApiKey('openai'));
    }

    public function test_user_has_api_key_check()
    {
        $user = User::factory()->create();
        
        UserApiKey::factory()->create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => 'test-key',
            'is_active' => true
        ]);

        $this->assertTrue($user->hasApiKey('anthropic'));
        $this->assertFalse($user->hasApiKey('openai'));
    }

    public function test_inactive_api_keys_are_ignored()
    {
        $user = User::factory()->create();
        
        UserApiKey::factory()->create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => 'test-key',
            'is_active' => false
        ]);

        $this->assertFalse($user->hasApiKey('anthropic'));
        $this->assertNull($user->getApiKey('anthropic'));
    }

    public function test_user_has_default_language()
    {
        $user = User::factory()->create();
        
        $this->assertEquals('lt', $user->getLanguage());
    }

    public function test_user_can_set_language()
    {
        $user = User::factory()->create();
        
        $user->setLanguage('en');
        
        $this->assertEquals('en', $user->language);
        $this->assertEquals('en', $user->getLanguage());
        
        // Check it was saved to database
        $user->refresh();
        $this->assertEquals('en', $user->language);
    }

    public function test_user_language_validation()
    {
        $user = User::factory()->create();
        
        // Valid language
        $user->setLanguage('en');
        $this->assertEquals('en', $user->language);
        
        // Invalid language should not change current setting
        $user->setLanguage('invalid');
        $this->assertEquals('en', $user->language); // Should remain unchanged
        
        // Another valid language
        $user->setLanguage('lt');
        $this->assertEquals('lt', $user->language);
    }

    public function test_user_language_supports_only_lithuanian_and_english()
    {
        $user = User::factory()->create();
        
        // Test Lithuanian
        $user->setLanguage('lt');
        $this->assertEquals('lt', $user->language);
        
        // Test English
        $user->setLanguage('en');
        $this->assertEquals('en', $user->language);
        
        // Test unsupported languages
        $originalLanguage = $user->language;
        
        $user->setLanguage('fr'); // French
        $this->assertEquals($originalLanguage, $user->language);
        
        $user->setLanguage('de'); // German
        $this->assertEquals($originalLanguage, $user->language);
        
        $user->setLanguage(''); // Empty string
        $this->assertEquals($originalLanguage, $user->language);
    }
}
