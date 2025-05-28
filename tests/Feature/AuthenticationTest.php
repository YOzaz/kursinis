<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible()
    {
        $response = $this->get('/login');
        
        $response->assertStatus(200)
                ->assertSee('Prisijungimas')
                ->assertSee('username')
                ->assertSee('password');
    }

    public function test_valid_login_redirects_to_home()
    {
        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'propaganda2025'
        ]);

        $response->assertRedirect('/')
                ->assertSessionHas('authenticated', true)
                ->assertSessionHas('username', 'admin');
    }

    public function test_invalid_login_returns_error()
    {
        $response = $this->post('/login', [
            'username' => 'invalid',
            'password' => 'wrong'
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors('credentials');
    }

    public function test_valid_marijus_login()
    {
        $response = $this->post('/login', [
            'username' => 'marijus',
            'password' => 'propaganda2025'
        ]);

        $response->assertRedirect('/')
                ->assertSessionHas('authenticated', true)
                ->assertSessionHas('username', 'marijus');
    }

    public function test_valid_darius_login()
    {
        $response = $this->post('/login', [
            'username' => 'darius',
            'password' => 'propaganda2025'
        ]);

        $response->assertRedirect('/')
                ->assertSessionHas('authenticated', true)
                ->assertSessionHas('username', 'darius');
    }

    public function test_logout_clears_session()
    {
        // First login
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->post('/logout');

        $response->assertRedirect('/login')
                ->assertSessionMissing('authenticated')
                ->assertSessionMissing('username');
    }

    public function test_protected_pages_redirect_to_login()
    {
        $protectedRoutes = [
            '/',
            '/dashboard',
            '/settings',
            '/help',
            '/analyses/test-job-id'
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    public function test_authenticated_user_can_access_protected_pages()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/');
        $response->assertStatus(200);

        $response = $this->get('/help');
        $response->assertStatus(200);
    }

    public function test_login_with_empty_credentials()
    {
        $response = $this->post('/login', [
            'username' => '',
            'password' => ''
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors('credentials');
    }

    public function test_login_with_only_username()
    {
        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => ''
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors('credentials');
    }

    public function test_login_with_only_password()
    {
        $response = $this->post('/login', [
            'username' => '',
            'password' => 'propaganda2025'
        ]);

        $response->assertRedirect()
                ->assertSessionHasErrors('credentials');
    }
}