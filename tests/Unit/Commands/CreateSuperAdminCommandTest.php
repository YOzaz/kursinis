<?php

namespace Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_new_superadmin_user()
    {
        $this->artisan('user:create-superadmin', [
            '--email' => 'admin@example.com',
            '--name' => 'Test Admin',
            '--password' => 'testpassword'
        ])
        ->expectsOutput('Superadmin created successfully: admin@example.com')
        ->assertExitCode(0);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test Admin', $user->name);
        $this->assertEquals('superadmin', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('testpassword', $user->password));
    }

    public function test_can_upgrade_existing_user_to_superadmin()
    {
        // Create regular user first
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('oldpassword'),
            'role' => 'user',
            'is_active' => true
        ]);

        $this->artisan('user:create-superadmin', [
            '--email' => 'user@example.com',
            '--name' => 'Upgraded Admin',
            '--password' => 'newpassword'
        ])
        ->expectsOutput('Superadmin updated successfully: user@example.com')
        ->assertExitCode(0);

        $user->refresh();
        $this->assertEquals('Upgraded Admin', $user->name);
        $this->assertEquals('superadmin', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('newpassword', $user->password));
    }

    public function test_command_validates_email_format()
    {
        $this->artisan('user:create-superadmin', [
            '--email' => 'invalid-email',
            '--name' => 'Test Admin',
            '--password' => 'testpassword'
        ])
        ->expectsOutput('The email field must be a valid email address.')
        ->assertExitCode(1);
    }

    public function test_command_validates_password_length()
    {
        $this->artisan('user:create-superadmin', [
            '--email' => 'admin@example.com',
            '--name' => 'Test Admin',
            '--password' => '123'
        ])
        ->expectsOutput('The password field must be at least 8 characters.')
        ->assertExitCode(1);
    }
}