<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;

class HelpControllerTest extends TestCase
{
    public function test_help_index_displays_correctly()
    {
        $response = $this->get('/help');
        
        $response->assertStatus(200);
        $response->assertViewIs('help.index');
        $response->assertSee('Pagalba ir dokumentacija');
        $response->assertSee('RISEN metodologija');
        $response->assertSee('ATSPARA propagandos technikos');
    }
    
    public function test_help_faq_displays_correctly()
    {
        $response = $this->get('/help/faq');
        
        $response->assertStatus(200);
        $response->assertViewIs('help.faq');
        $response->assertSee('Dažniausiai užduodami klausimai');
        $response->assertSee('ATSPARA metodologija');
        $response->assertSee('Sistemos tikslumas');
    }
    
    public function test_contact_page_displays_correctly()
    {
        $response = $this->get('/contact');
        
        $response->assertStatus(200);
        $response->assertViewIs('contact');
        $response->assertSee('Kontaktai');
        $response->assertSee('marijus.planciunas@mif.stud.vu.lt');
        $response->assertSee('Prof. Dr. Darius Plikynas');
    }
    
    public function test_legal_page_displays_correctly()
    {
        $response = $this->get('/legal');
        
        $response->assertStatus(200);
        $response->assertViewIs('legal');
        $response->assertSee('Teisinė informacija');
        $response->assertSee('GDPR ir privatumo apsauga');
        $response->assertSee('Vilniaus universitetas');
    }
}