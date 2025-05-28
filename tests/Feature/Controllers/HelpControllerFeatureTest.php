<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HelpControllerFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_index_page_loads_successfully(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/help');

        $response->assertStatus(200)
                ->assertSee('Pagalba')
                ->assertSee('ATSPARA')
                ->assertViewIs('help.index');
    }

    public function test_help_faq_page_loads_successfully(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/help/faq');

        $response->assertStatus(200)
                ->assertSee('FAQ')
                ->assertSee('Dažniausiai užduodami klausimai')
                ->assertViewIs('help.faq');
    }

    public function test_help_pages_redirect_when_not_authenticated(): void
    {
        $response = $this->get('/help');
        $response->assertRedirect('/login');

        $response = $this->get('/help/faq');
        $response->assertRedirect('/login');
    }

    public function test_help_pages_contain_navigation_elements(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/help');

        $content = $response->getContent();
        $this->assertStringContainsString('navbar', $content);
        $this->assertStringContainsString('Dashboard', $content);
        $this->assertStringContainsString('Nustatymai', $content);
    }

    public function test_help_faq_contains_important_sections(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/help/faq');

        $content = $response->getContent();
        $this->assertStringContainsString('propagandos technikos', $content);
        $this->assertStringContainsString('LLM', $content);
    }
}