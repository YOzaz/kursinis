<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

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
        
        return view('settings.index', compact('models', 'providers'));
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
        
        return back()->with('success', 'Numatytosios nuostatos sėkmingai atnaujintos!');
    }
    
    /**
     * Reset to default settings.
     */
    public function resetDefaults()
    {
        Cache::forget('llm_settings_override');
        return back()->with('success', 'Nuostatos grąžintos į pradinius nustatymus!');
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
}