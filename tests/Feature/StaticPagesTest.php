<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StaticPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_is_accessible()
    {
        $this->withoutMiddleware();
        
        $response = $this->get('/contact');
        
        $response->assertStatus(200)
                ->assertSeeText(__('messages.contacts'));
    }

    public function test_legal_page_is_accessible()
    {
        $this->withoutMiddleware();
        
        $response = $this->get('/legal');
        
        $response->assertStatus(200)
                ->assertSeeText(__('messages.legal_information'));
    }

    public function test_contact_page_redirects_when_not_authenticated()
    {
        $response = $this->get('/contact');
        
        $response->assertRedirect('/login');
    }

    public function test_legal_page_redirects_when_not_authenticated()
    {
        $response = $this->get('/legal');
        
        $response->assertRedirect('/login');
    }

    public function test_contact_page_accessible_when_authenticated()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/contact');
        
        $response->assertStatus(200)
                ->assertSeeText(__('messages.contacts'));
    }

    public function test_legal_page_accessible_when_authenticated()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/legal');
        
        $response->assertStatus(200)
                ->assertSeeText(__('messages.legal_information'));
    }

}