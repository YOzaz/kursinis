<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;

/**
 * Language switching controller
 */
class LanguageController extends Controller
{
    /**
     * Switch the application language
     */
    public function switch(Request $request, string $language): RedirectResponse
    {
        // Validate language
        $supportedLanguages = ['lt', 'en'];
        if (!in_array($language, $supportedLanguages)) {
            $language = 'lt'; // Default to Lithuanian
        }
        
        // Save to user preference if authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $user->setLanguage($language);
        } else {
            // Fallback to session for non-authenticated users
            Session::put('language', $language);
        }
        
        // Set for current request
        App::setLocale($language);
        
        // Redirect back to previous page
        return redirect()->back()->with('success', 
            $language === 'lt' ? 'Kalba pakeista į lietuvių' : 'Language changed to English'
        );
    }
}