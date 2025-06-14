<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_can_be_created()
    {
        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'test_action',
            'resource_type' => 'TestResource',
            'resource_id' => 123,
            'old_values' => ['status' => 'old'],
            'new_values' => ['status' => 'new'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser'
        ]);

        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals('test_action', $auditLog->action);
        $this->assertEquals('TestResource', $auditLog->resource_type);
        $this->assertEquals(123, $auditLog->resource_id);
        $this->assertEquals('127.0.0.1', $auditLog->ip_address);
    }

    public function test_audit_log_belongs_to_user()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true
        ]);

        $auditLog = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'user_login',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'old_values' => [],
            'new_values' => ['last_login' => now()],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0'
        ]);

        $this->assertEquals($user->id, $auditLog->user->id);
        $this->assertEquals($user->email, $auditLog->user->email);
    }

    public function test_audit_log_handles_json_values()
    {
        $oldValues = ['role' => 'user', 'active' => true];
        $newValues = ['role' => 'admin', 'active' => true];

        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'role_change',
            'resource_type' => 'User',
            'resource_id' => 456,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'API Client'
        ]);

        $this->assertIsArray($auditLog->old_values);
        $this->assertIsArray($auditLog->new_values);
        $this->assertEquals('user', $auditLog->old_values['role']);
        $this->assertEquals('admin', $auditLog->new_values['role']);
    }

    public function test_audit_log_with_console_commands()
    {
        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'created_superadmin',
            'resource_type' => 'User',
            'resource_id' => 1,
            'old_values' => [],
            'new_values' => ['role' => 'superadmin', 'email' => 'admin@example.com'],
            'ip_address' => 'console',
            'user_agent' => 'artisan_command'
        ]);

        $this->assertNull($auditLog->user_id);
        $this->assertEquals('console', $auditLog->ip_address);
        $this->assertEquals('artisan_command', $auditLog->user_agent);
        $this->assertEquals('created_superadmin', $auditLog->action);
    }
}
