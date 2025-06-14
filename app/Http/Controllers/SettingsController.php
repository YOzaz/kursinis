<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\UserApiKey;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Crypt;

class SettingsController extends Controller
{
    /**
     * Show the settings page.
     */
    public function index()
    {
        $llmConfig = config('llm');
        $models = $llmConfig['models'];
        $providers = $llmConfig['providers'];
        
        // Get current user from session (SimpleAuth)
        $user = $this->getCurrentUser();
        $userApiKeys = [];
        
        if ($user) {
            // Load user's API keys
            $userApiKeys = $user->apiKeys()->get()->keyBy('provider');
        }
        
        return view('settings.index', compact('models', 'providers', 'userApiKeys', 'user'));
    }
    
    /**
     * Update default model settings.
     */
    public function updateDefaults(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'models' => 'required|array',
            'models.*.temperature' => 'numeric|min:0|max:2',
            'models.*.top_p' => 'numeric|min:0|max:1',
            'models.*.top_k' => 'nullable|integer|min:1|max:100',
            'models.*.max_tokens' => 'integer|min:100|max:8192',
            'models.*.frequency_penalty' => 'nullable|numeric|min:-2|max:2',
            'models.*.presence_penalty' => 'nullable|numeric|min:-2|max:2',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Save to cache (in a real application, you'd save to database or config file)
        Cache::put('llm_settings_override', $request->models, now()->addDays(30));
        
        return back()->with('success', __('messages.settings_updated'));
    }
    
    /**
     * Reset to default settings.
     */
    public function resetDefaults()
    {
        Cache::forget('llm_settings_override');
        return back()->with('success', __('messages.settings_restored'));
    }
    
    /**
     * Get current model settings (including overrides).
     */
    public static function getModelSettings(): array
    {
        $defaultConfig = config('llm.models');
        $overrides = Cache::get('llm_settings_override', []);
        
        foreach ($overrides as $modelKey => $settings) {
            if (isset($defaultConfig[$modelKey])) {
                $defaultConfig[$modelKey] = array_merge($defaultConfig[$modelKey], $settings);
            }
        }
        
        return $defaultConfig;
    }
    
    /**
     * Get current authenticated user based on SimpleAuth session.
     */
    protected function getCurrentUser(): ?User
    {
        // SimpleAuth stores username in session
        $username = session('username');
        if (!$username) {
            return null;
        }
        
        // Find or create user based on username
        $user = User::where('email', $username . '@local')->first();
        
        if (!$user) {
            // Create user if doesn't exist
            $user = User::create([
                'name' => ucfirst($username),
                'email' => $username . '@local',
                'password' => bcrypt(uniqid()), // Random password since we use SimpleAuth
                'role' => 'user',
                'is_active' => true,
            ]);
            
            AuditLog::log('user_created', User::class, $user->id, [], [
                'username' => $username,
                'created_from' => 'SimpleAuth',
            ]);
        }
        
        return $user;
    }
    
    /**
     * Update user API keys.
     */
    public function updateApiKeys(Request $request)
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return back()->with('error', __('messages.user_not_found'));
        }
        
        $validator = Validator::make($request->all(), [
            'api_keys' => 'required|array',
            'api_keys.anthropic' => 'nullable|string|min:10',
            'api_keys.openai' => 'nullable|string|min:10',
            'api_keys.google' => 'nullable|string|min:10',
        ]);
        
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        $providers = ['anthropic', 'openai', 'google'];
        
        foreach ($providers as $provider) {
            $apiKey = $request->input("api_keys.{$provider}");
            
            if ($apiKey) {
                // Check if it's a masked key (no update needed)
                $existingKey = $user->apiKeys()->where('provider', $provider)->first();
                if ($existingKey && $apiKey === $existingKey->masked_api_key) {
                    continue;
                }
                
                // Update or create API key
                $userApiKey = $user->apiKeys()->updateOrCreate(
                    ['provider' => $provider],
                    [
                        'api_key' => Crypt::encryptString($apiKey),
                        'is_active' => true,
                    ]
                );
                
                AuditLog::log('api_key_updated', UserApiKey::class, $userApiKey->id, [], [
                    'provider' => $provider,
                    'user_id' => $user->id,
                ]);
            }
        }
        
        // Clear LLM config cache to use new keys
        Cache::forget('llm_settings_override');
        
        return back()->with('success', __('messages.api_keys_updated'));
    }
    
    /**
     * Delete user API key.
     */
    public function deleteApiKey(Request $request)
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return back()->with('error', __('messages.user_not_found'));
        }
        
        $provider = $request->input('provider');
        if (!in_array($provider, ['anthropic', 'openai', 'google'])) {
            return back()->with('error', __('messages.invalid_provider'));
        }
        
        $apiKey = $user->apiKeys()->where('provider', $provider)->first();
        if ($apiKey) {
            AuditLog::log('api_key_deleted', UserApiKey::class, $apiKey->id, [
                'provider' => $provider,
                'user_id' => $user->id,
            ], []);
            
            $apiKey->delete();
        }
        
        return back()->with('success', __('messages.api_key_deleted'));
    }
}