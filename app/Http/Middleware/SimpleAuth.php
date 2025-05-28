<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SimpleAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is already authenticated
        if ($request->session()->has('authenticated')) {
            return $next($request);
        }
        
        // If trying to login, validate credentials
        if ($request->isMethod('post') && $request->path() === 'login') {
            $username = $request->input('username');
            $password = $request->input('password');
            
            // Simple hardcoded credentials (in production, use database/hash)
            $validUsers = [
                'admin' => env('ADMIN_PASSWORD', 'propaganda2025'),
                'marijus' => env('ADMIN_PASSWORD', 'propaganda2025'),
                'darius' => env('ADMIN_PASSWORD', 'propaganda2025'),
            ];
            
            if (isset($validUsers[$username]) && $validUsers[$username] === $password) {
                $request->session()->put('authenticated', true);
                $request->session()->put('username', $username);
                return redirect()->intended('/');
            }
            
            return back()->withErrors(['credentials' => 'Neteisingi prisijungimo duomenys.']);
        }
        
        // If not authenticated and not login page, redirect to login
        if ($request->path() !== 'login') {
            return redirect('/login');
        }
        
        return $next($request);
    }
}