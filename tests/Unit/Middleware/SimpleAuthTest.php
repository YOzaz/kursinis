<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SimpleAuth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\Store;
use Tests\TestCase;
use Mockery;

class SimpleAuthTest extends TestCase
{
    private SimpleAuth $middleware;
    private $mockSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SimpleAuth();
        $this->mockSession = Mockery::mock(Store::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_authenticated_user_passes_through()
    {
        $request = Request::create('/dashboard', 'GET');
        $request->setLaravelSession($this->mockSession);
        
        $this->mockSession->shouldReceive('has')
            ->with('authenticated')
            ->once()
            ->andReturn(true);

        $next = function ($req) {
            return new Response('Success');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals('Success', $response->getContent());
    }

    public function test_unauthenticated_user_redirected_to_login()
    {
        $request = Request::create('/dashboard', 'GET');
        $request->setLaravelSession($this->mockSession);
        
        $this->mockSession->shouldReceive('has')
            ->with('authenticated')
            ->once()
            ->andReturn(false);

        $next = function ($req) {
            return new Response('Success');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('http://propaganda.local/login', $response->headers->get('Location'));
    }

    public function test_login_page_accessible_without_authentication()
    {
        $request = Request::create('/login', 'GET');
        $request->setLaravelSession($this->mockSession);
        
        $this->mockSession->shouldReceive('has')
            ->with('authenticated')
            ->once()
            ->andReturn(false);

        $next = function ($req) {
            return new Response('Login page');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals('Login page', $response->getContent());
    }

    public function test_valid_login_credentials_authenticate_user()
    {
        $request = Request::create('/login', 'POST', [
            'username' => 'admin',
            'password' => 'propaganda2025'
        ]);
        $request->setLaravelSession($this->mockSession);
        
        $this->mockSession->shouldReceive('has')
            ->with('authenticated')
            ->once()
            ->andReturn(false);
            
        $this->mockSession->shouldReceive('put')
            ->with('authenticated', true)
            ->once();
            
        $this->mockSession->shouldReceive('put')
            ->with('username', 'admin')
            ->once();

        $next = function ($req) {
            return new Response('Success');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('http://propaganda.local', $response->headers->get('Location'));
    }

    public function test_invalid_login_credentials_return_error()
    {
        $request = Request::create('/login', 'POST', [
            'username' => 'invalid',
            'password' => 'wrong'
        ]);
        $request->setLaravelSession($this->mockSession);
        
        $this->mockSession->shouldReceive('has')
            ->with('authenticated')
            ->once()
            ->andReturn(false);

        $next = function ($req) {
            return new Response('Success');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_valid_marijus_credentials()
    {
        $request = Request::create('/login', 'POST', [
            'username' => 'marijus',
            'password' => 'propaganda2025'
        ]);
        $request->setLaravelSession($this->mockSession);
        
        $this->mockSession->shouldReceive('has')
            ->with('authenticated')
            ->once()
            ->andReturn(false);
            
        $this->mockSession->shouldReceive('put')
            ->with('authenticated', true)
            ->once();
            
        $this->mockSession->shouldReceive('put')
            ->with('username', 'marijus')
            ->once();

        $next = function ($req) {
            return new Response('Success');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_valid_darius_credentials()
    {
        $request = Request::create('/login', 'POST', [
            'username' => 'darius',
            'password' => 'propaganda2025'
        ]);
        $request->setLaravelSession($this->mockSession);
        
        $this->mockSession->shouldReceive('has')
            ->with('authenticated')
            ->once()
            ->andReturn(false);
            
        $this->mockSession->shouldReceive('put')
            ->with('authenticated', true)
            ->once();
            
        $this->mockSession->shouldReceive('put')
            ->with('username', 'darius')
            ->once();

        $next = function ($req) {
            return new Response('Success');
        };

        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(302, $response->getStatusCode());
    }
}