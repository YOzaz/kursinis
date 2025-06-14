<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Support\Facades\Hash;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_api_keys()
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

        $this->assertDatabaseHas('user_api_keys', [
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'is_active' => true
        ]);

        // The API key should be encrypted when stored and retrieved
        $retrievedKey = $user->getApiKey('anthropic');
        $this->assertNotNull($retrievedKey);
        $this->assertEquals('test-api-key', decrypt($retrievedKey));
    }

    public function test_user_role_hierarchy()
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'is_active' => true
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true
        ]);

        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true
        ]);

        $this->assertTrue($superadmin->isSuperAdmin());
        $this->assertTrue($superadmin->isAdmin());
        
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertTrue($admin->isAdmin());
        
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->isAdmin());
    }
}
