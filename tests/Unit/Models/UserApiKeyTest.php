<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Support\Facades\Hash;

class UserApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_api_key_can_be_created()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true
        ]);

        $apiKey = UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => encrypt('test-api-key'),
            'is_active' => true
        ]);

        $this->assertInstanceOf(UserApiKey::class, $apiKey);
        $this->assertEquals($user->id, $apiKey->user_id);
        $this->assertEquals('anthropic', $apiKey->provider);
        $this->assertTrue($apiKey->is_active);
    }

    public function test_api_key_belongs_to_user()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true
        ]);

        $apiKey = UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'api_key' => encrypt('openai-key'),
            'is_active' => true
        ]);

        $this->assertEquals($user->id, $apiKey->user->id);
        $this->assertEquals($user->email, $apiKey->user->email);
    }

    public function test_api_key_is_encrypted()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true
        ]);

        $plainKey = 'secret-api-key';
        $apiKey = UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'api_key' => encrypt($plainKey),
            'is_active' => true
        ]);

        // The raw database value should be encrypted
        $this->assertNotEquals($plainKey, $apiKey->getRawOriginal('api_key'));
        
        // But when accessed through the model, it should be decrypted
        $this->assertEquals($plainKey, decrypt($apiKey->getRawOriginal('api_key')));
    }

    public function test_usage_stats_are_properly_handled()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true
        ]);

        $apiKey = UserApiKey::create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'api_key' => encrypt('test-key'),
            'is_active' => true,
            'usage_stats' => ['requests' => 10, 'tokens' => 1000]
        ]);

        $this->assertIsArray($apiKey->usage_stats);
        $this->assertEquals(10, $apiKey->usage_stats['requests']);
        $this->assertEquals(1000, $apiKey->usage_stats['tokens']);
    }
}
