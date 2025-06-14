<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'user:create-superadmin {--email=} {--name=} {--password=}';

    protected $description = 'Create or upgrade a user to superadmin role';

    public function handle()
    {
        $this->info('Creating/upgrading superadmin user...');

        $email = $this->option('email') ?: $this->ask('Enter email address');
        $name = $this->option('name') ?: $this->ask('Enter full name');
        $password = $this->option('password') ?: $this->secret('Enter password');

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => 'required|email',
            'name' => 'required|string|min:2',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->role === 'superadmin') {
                $this->info('User is already a superadmin. Updating password...');
            } else {
                $this->info('User exists. Upgrading to superadmin and updating password...');
            }

            $oldRole = $existingUser->role;
            $existingUser->update([
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'superadmin',
                'is_active' => true,
            ]);

            AuditLog::create([
                'user_id' => null,
                'action' => 'upgraded_to_superadmin',
                'resource_type' => 'User',
                'resource_id' => $existingUser->id,
                'old_values' => ['role' => $oldRole],
                'new_values' => ['role' => 'superadmin'],
                'ip_address' => 'console',
                'user_agent' => 'artisan_command',
            ]);

            $this->info("Superadmin updated successfully: {$existingUser->email}");
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'superadmin',
                'is_active' => true,
            ]);

            AuditLog::create([
                'user_id' => null,
                'action' => 'created_superadmin',
                'resource_type' => 'User',
                'resource_id' => $user->id,
                'old_values' => [],
                'new_values' => ['role' => 'superadmin', 'email' => $email],
                'ip_address' => 'console',
                'user_agent' => 'artisan_command',
            ]);

            $this->info("Superadmin created successfully: {$user->email}");
        }

        $superAdminCount = User::where('role', 'superadmin')->count();
        $this->info("Total superadmins in system: {$superAdminCount}");

        return self::SUCCESS;
    }
}
