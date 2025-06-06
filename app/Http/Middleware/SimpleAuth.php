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
            
            // Load valid users from environment configuration
            $validUsers = $this->getValidUsers();
            
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

    /**
     * Get valid users from environment configuration.
     */
    private function getValidUsers(): array
    {
        $users = [];
        
        // Parse AUTH_USERS from .env file
        // Format: "username1:password1,username2:password2"
        $authUsers = env('AUTH_USERS', 'admin:propaganda2025');
        
        if (!empty($authUsers)) {
            $userPairs = explode(',', $authUsers);
            
            foreach ($userPairs as $userPair) {
                $userPair = trim($userPair);
                if (str_contains($userPair, ':')) {
                    [$username, $password] = explode(':', $userPair, 2);
                    $users[trim($username)] = trim($password);
                }
            }
        }
        
        // Fallback to default admin user if no users configured
        if (empty($users)) {
            $users['admin'] = env('ADMIN_PASSWORD', 'propaganda2025');
        }
        
        return $users;
    }
}