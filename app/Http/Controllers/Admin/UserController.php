<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserApiKey;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isSuperAdmin()) {
                abort(403, 'Access denied. Superadmin role required.');
            }
            return $next($request);
        });
    }

    public function index()
    {
        $users = User::with(['apiKeys'])->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:superadmin,admin,user',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        AuditLog::log('created', 'User', $user->id, [], $user->toArray());

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $user->load(['apiKeys', 'auditLogs' => function($query) {
            $query->latest()->limit(50);
        }]);
        
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:superadmin,admin,user',
            'is_active' => 'boolean',
        ]);

        $oldData = $user->toArray();

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        AuditLog::log('updated', 'User', $user->id, $oldData, $user->fresh()->toArray());

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $oldData = $user->toArray();
        $userId = $user->id;
        
        $user->delete();

        AuditLog::log('deleted', 'User', $userId, $oldData, []);

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function apiKeys(User $user)
    {
        $user->load('apiKeys');
        return view('admin.users.api-keys', compact('user'));
    }

    public function storeApiKey(Request $request, User $user)
    {
        $validated = $request->validate([
            'provider' => 'required|in:anthropic,openai,google',
            'api_key' => 'required|string',
        ]);

        $existingKey = $user->apiKeys()->where('provider', $validated['provider'])->first();
        
        if ($existingKey) {
            $oldData = $existingKey->toArray();
            $existingKey->update(['api_key' => $validated['api_key']]);
            AuditLog::log('updated', 'UserApiKey', $existingKey->id, $oldData, $existingKey->fresh()->toArray());
        } else {
            $apiKey = $user->apiKeys()->create([
                'provider' => $validated['provider'],
                'api_key' => $validated['api_key'],
            ]);
            AuditLog::log('created', 'UserApiKey', $apiKey->id, [], $apiKey->toArray());
        }

        return redirect()->route('admin.users.api-keys', $user)
            ->with('success', 'API key saved successfully.');
    }

    public function destroyApiKey(User $user, UserApiKey $apiKey)
    {
        if ($apiKey->user_id !== $user->id) {
            abort(404);
        }

        $oldData = $apiKey->toArray();
        $apiKeyId = $apiKey->id;
        
        $apiKey->delete();

        AuditLog::log('deleted', 'UserApiKey', $apiKeyId, $oldData, []);

        return redirect()->route('admin.users.api-keys', $user)
            ->with('success', 'API key deleted successfully.');
    }
}
