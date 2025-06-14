<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set application language from user preference or session fallback
 */
class SetLanguage
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = 'lt'; // Default to Lithuanian
        
        // Try to get language from authenticated user first
        if (Auth::check()) {
            $user = Auth::user();
            $language = $user->getLanguage();
        } else {
            // Fallback to session for non-authenticated users
            $language = Session::get('language', 'lt');
        }
        
        // Validate language
        $supportedLanguages = ['lt', 'en'];
        if (!in_array($language, $supportedLanguages)) {
            $language = 'lt';
        }
        
        // Set application locale
        App::setLocale($language);
        
        return $next($request);
    }
}