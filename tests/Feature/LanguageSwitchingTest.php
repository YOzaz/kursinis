<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class LanguageSwitchingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_switch_language_for_authenticated_user()
    {
        // Create a user
        $user = User::factory()->create([
            'language' => 'lt'
        ]);

        // Authenticate the user
        $this->actingAs($user);

        // Switch to English
        $response = $this->get(route('language.switch', 'en'));

        // Assert redirect
        $response->assertStatus(302);

        // Check user language was updated
        $user->refresh();
        $this->assertEquals('en', $user->language);
    }

    /** @test */
    public function it_can_switch_language_for_unauthenticated_user_using_session()
    {
        // Switch to English without authentication
        $response = $this->get(route('language.switch', 'en'));

        // Assert redirect
        $response->assertStatus(302);
        
        // Check session has language set
        $this->assertTrue(Session::has('language'));
        $this->assertEquals('en', Session::get('language'));
    }

    /** @test */
    public function it_defaults_to_lithuanian_for_invalid_language()
    {
        $user = User::factory()->create([
            'language' => 'lt'
        ]);

        $this->actingAs($user);

        // Try to switch to invalid language
        $response = $this->get(route('language.switch', 'invalid'));

        // Check user language defaults to Lithuanian
        $user->refresh();
        $this->assertEquals('lt', $user->language);
    }

    /** @test */
    public function it_supports_lithuanian_language_switch()
    {
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->actingAs($user);

        // Switch to Lithuanian
        $response = $this->get(route('language.switch', 'lt'));

        $response->assertStatus(302);

        // Check user language was updated
        $user->refresh();
        $this->assertEquals('lt', $user->language);
    }

    /** @test */
    public function language_switcher_appears_in_mission_control()
    {
        $response = $this->get(route('mission-control'));

        $response->assertStatus(200);
        $response->assertSee('lang-btn');
        $response->assertSee('LT');
        $response->assertSee('EN');
    }

    /** @test */
    public function language_middleware_sets_locale_from_user_preference()
    {
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->actingAs($user);

        // Make any request to trigger middleware
        $this->get(route('mission-control'));

        // Check that locale was set correctly
        $this->assertEquals('en', app()->getLocale());
    }

    /** @test */
    public function language_middleware_falls_back_to_session_for_guests()
    {
        // Set session language
        Session::put('language', 'en');

        // Make request as guest
        $this->get(route('mission-control'));

        // Check that locale was set from session
        $this->assertEquals('en', app()->getLocale());
    }

    /** @test */
    public function language_middleware_defaults_to_lithuanian_for_new_users()
    {
        // Make request as guest without session
        $this->get(route('mission-control'));

        // Check that locale defaults to Lithuanian
        $this->assertEquals('lt', app()->getLocale());
    }

    /** @test */
    public function dashboard_displays_correct_language_content()
    {
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        
        // Check English translations are displayed
        $response->assertSee('Model Performance');
        $response->assertSee('F1 Score');
        $response->assertSee('Precision');
        $response->assertSee('Recall');
        $response->assertSee('Speed');
        $response->assertSee('Model Rating');
        $response->assertSee('Recent Analyses');
        
        // Make sure Lithuanian text is not displayed
        $response->assertDontSee('Modelių našumas');
        $response->assertDontSee('F1 balas');
        $response->assertDontSee('Tikslumas');
        $response->assertDontSee('Atsaukimas');
        $response->assertDontSee('Greitis');
    }

    /** @test */
    public function analysis_create_form_displays_correct_language_content()
    {
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->actingAs($user);

        $response = $this->get(route('create'));

        $response->assertStatus(200);
        
        // Check English translations are displayed
        $response->assertSee('How to use the system');
        $response->assertSee('RISEN Prompt Configuration');
        $response->assertSee('Start Analysis');
        
        // Make sure Lithuanian text is not displayed
        $response->assertDontSee('Kaip naudoti sistemą');
        $response->assertDontSee('RISEN Prompt Konfigūracija');
        $response->assertDontSee('Pradėti analizę');
    }

    /** @test */
    public function analysis_list_displays_correct_language_content()
    {
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->actingAs($user);

        $response = $this->get(route('analyses.index'));

        $response->assertStatus(200);
        
        // Check English translations are displayed
        $response->assertSee('Analysis List');
        $response->assertSee('All completed propaganda analyses');
        $response->assertSee('Analysis Types');
        $response->assertSee('Search');
        $response->assertSee('Status');
        $response->assertSee('Type');
        
        // Make sure Lithuanian text is not displayed
        $response->assertDontSee('Analizių sąrašas');
        $response->assertDontSee('Visos atliktos propagandos analizės');
        $response->assertDontSee('Analizių tipai');
        $response->assertDontSee('Paieška');
        $response->assertDontSee('Statusas');
        $response->assertDontSee('Tipas');
    }

    /** @test */
    public function javascript_rendered_content_uses_correct_language()
    {
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->actingAs($user);

        // Test dashboard DataTable language
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Search:');
        $response->assertSee('Show _MENU_ entries');
        $response->assertDontSee('Ieškoti:');
        $response->assertDontSee('Rodyti _MENU_ įrašų');
    }

    /** @test */
    public function language_switch_preserves_current_page()
    {
        $user = User::factory()->create([
            'language' => 'lt'
        ]);

        $this->actingAs($user);

        // Visit analyses page
        $this->get(route('analyses.index'));

        // Switch language
        $response = $this->get(route('language.switch', 'en'));

        // Should redirect back to analyses page
        $response->assertRedirect(route('analyses.index'));
    }
}