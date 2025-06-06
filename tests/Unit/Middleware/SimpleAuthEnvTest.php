<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\SimpleAuth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class SimpleAuthEnvTest extends TestCase
{
    private SimpleAuth $middleware;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SimpleAuth();
    }

    /** @test */
    public function it_loads_auth_users_from_env_variable()
    {
        // Set test AUTH_USERS environment variable
        Config::set('AUTH_USERS', 'testuser:testpass,admin:adminpass');
        putenv('AUTH_USERS=testuser:testpass,admin:adminpass');
        
        $request = Request::create('/login', 'POST', [
            'username' => 'testuser',
            'password' => 'testpass'
        ]);
        
        $response = $this->middleware->handle($request, function () {
            return new Response('success');
        });
        
        $this->assertTrue(session()->has('authenticated'));
        $this->assertEquals('testuser', session()->get('username'));
    }

    /** @test */
    public function it_handles_multiple_users_from_env()
    {
        Config::set('AUTH_USERS', 'user1:pass1,user2:pass2,user3:pass3');
        putenv('AUTH_USERS=user1:pass1,user2:pass2,user3:pass3');
        
        // Test first user
        $request1 = Request::create('/login', 'POST', [
            'username' => 'user1',
            'password' => 'pass1'
        ]);
        
        $response1 = $this->middleware->handle($request1, function () {
            return new Response('success');
        });
        
        $this->assertTrue(session()->has('authenticated'));
        
        // Clear session for next test
        session()->flush();
        
        // Test second user
        $request2 = Request::create('/login', 'POST', [
            'username' => 'user2',
            'password' => 'pass2'
        ]);
        
        $response2 = $this->middleware->handle($request2, function () {
            return new Response('success');
        });
        
        $this->assertTrue(session()->has('authenticated'));
        $this->assertEquals('user2', session()->get('username'));
    }

    /** @test */
    public function it_rejects_invalid_credentials_from_env()
    {
        Config::set('AUTH_USERS', 'validuser:validpass');
        putenv('AUTH_USERS=validuser:validpass');
        
        $request = Request::create('/login', 'POST', [
            'username' => 'validuser',
            'password' => 'wrongpass'
        ]);
        
        $response = $this->middleware->handle($request, function () {
            return new Response('success');
        });
        
        $this->assertFalse(session()->has('authenticated'));
    }

    /** @test */
    public function it_falls_back_to_default_when_no_env_users()
    {
        Config::set('AUTH_USERS', '');
        Config::set('ADMIN_PASSWORD', 'defaultpass');
        putenv('AUTH_USERS=');
        putenv('ADMIN_PASSWORD=defaultpass');
        
        $request = Request::create('/login', 'POST', [
            'username' => 'admin',
            'password' => 'defaultpass'
        ]);
        
        $response = $this->middleware->handle($request, function () {
            return new Response('success');
        });
        
        $this->assertTrue(session()->has('authenticated'));
        $this->assertEquals('admin', session()->get('username'));
    }

    /** @test */
    public function it_handles_malformed_env_users_gracefully()
    {
        Config::set('AUTH_USERS', 'usernopass,user:pass:extra,normaluser:normalpass');
        putenv('AUTH_USERS=usernopass,user:pass:extra,normaluser:normalpass');
        
        // Should only work for properly formatted user
        $request = Request::create('/login', 'POST', [
            'username' => 'normaluser',
            'password' => 'normalpass'
        ]);
        
        $response = $this->middleware->handle($request, function () {
            return new Response('success');
        });
        
        $this->assertTrue(session()->has('authenticated'));
        
        // Clear session
        session()->flush();
        
        // Should reject malformed entries
        $request2 = Request::create('/login', 'POST', [
            'username' => 'usernopass',
            'password' => ''
        ]);
        
        $response2 = $this->middleware->handle($request2, function () {
            return new Response('success');
        });
        
        $this->assertFalse(session()->has('authenticated'));
    }

    /** @test */
    public function it_trims_whitespace_from_env_users()
    {
        Config::set('AUTH_USERS', ' user1 : pass1 , user2: pass2 ');
        putenv('AUTH_USERS= user1 : pass1 , user2: pass2 ');
        
        $request = Request::create('/login', 'POST', [
            'username' => 'user1',
            'password' => 'pass1'
        ]);
        
        $response = $this->middleware->handle($request, function () {
            return new Response('success');
        });
        
        $this->assertTrue(session()->has('authenticated'));
        $this->assertEquals('user1', session()->get('username'));
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('AUTH_USERS');
        putenv('ADMIN_PASSWORD');
        parent::tearDown();
    }
}