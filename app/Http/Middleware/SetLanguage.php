<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set application language from session
 */
class SetLanguage
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get language from session, default to Lithuanian
        $language = Session::get('language', 'lt');
        
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