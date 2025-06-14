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

    /** @test */
    public function contact_page_displays_correct_language_content()
    {
        // Test English version
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->actingAs($user);

        $response = $this->get(route('contact'));

        $response->assertStatus(200);
        
        // Check English translations are displayed
        $response->assertSee('Contacts');
        $response->assertSee('Contact the system creators');
        $response->assertSee('Thesis Author');
        $response->assertSee('Scientific Supervisor');
        $response->assertSee('Department of Data Science and Digital Technologies');
        $response->assertSee('Responsible for');
        $response->assertSee('System architecture and development');
        $response->assertSee('AI model integration');
        $response->assertSee('RISEN methodology implementation');
        $response->assertSee('ATSPARA Project');
        $response->assertSee('Academic Research');
        
        // Make sure Lithuanian text is not displayed
        $response->assertDontSee('Kontaktai');
        $response->assertDontSee('Susisiekite su sistemos kūrėjais');
        $response->assertDontSee('Kursinio darbo autorius');
        $response->assertDontSee('Mokslinis vadovas');
        $response->assertDontSee('Duomenų mokslo ir skaitmeninių technologijų katedra');
    }

    /** @test */
    public function contact_page_displays_lithuanian_content_when_language_is_lithuanian()
    {
        $user = User::factory()->create([
            'language' => 'lt'
        ]);

        $this->actingAs($user);

        $response = $this->get(route('contact'));

        $response->assertStatus(200);
        
        // Check Lithuanian content is displayed
        $response->assertSee('Kontaktai');
        $response->assertSee('Susisiekite su sistemos kūrėjais');
        $response->assertSee('Kursinio darbo autorius');
        $response->assertSee('Mokslinis vadovas');
        
        // Make sure English text is not displayed
        $response->assertDontSee('Contacts');
        $response->assertDontSee('Contact the system creators');
        $response->assertDontSee('Thesis Author');
        $response->assertDontSee('Scientific Supervisor');
    }

    /** @test */
    public function legal_page_displays_correct_language_content()
    {
        // Test English version
        $user = User::factory()->create([
            'language' => 'en'
        ]);

        $this->actingAs($user);

        $response = $this->get(route('legal'));

        $response->assertStatus(200);
        
        // Check English translations are displayed
        $response->assertSee('Legal Information');
        $response->assertSee('Data usage, privacy and responsibility');
        $response->assertSee('General Information');
        $response->assertSee('Data Usage and Security');
        $response->assertSee('GDPR and Privacy Protection');
        $response->assertSee('Liability Disclaimer');
        $response->assertSee('Academic Ethics Principles');
        $response->assertSee('Your rights:');
        $response->assertSee('Access right');
        $response->assertSee('Rectification right');
        $response->assertSee('Erasure right');
        $response->assertSee('Portability right');
        
        // Make sure Lithuanian text is not displayed
        $response->assertDontSee('Teisinė informacija');
        $response->assertDontSee('Duomenų naudojimas, privatumas ir atsakomybė');
        $response->assertDontSee('Bendroji informacija');
        $response->assertDontSee('Duomenų naudojimas ir saugumas');
        $response->assertDontSee('GDPR ir privatumo apsauga');
        $response->assertDontSee('Atsakomybės apribojimas');
        $response->assertDontSee('Akademinės etikos principai');
    }

    /** @test */
    public function legal_page_displays_lithuanian_content_when_language_is_lithuanian()
    {
        $user = User::factory()->create([
            'language' => 'lt'
        ]);

        $this->actingAs($user);

        $response = $this->get(route('legal'));

        $response->assertStatus(200);
        
        // Check Lithuanian content is displayed
        $response->assertSee('Teisinė informacija');
        $response->assertSee('Duomenų naudojimas, privatumas ir atsakomybė');
        $response->assertSee('Bendroji informacija');
        
        // Make sure English text is not displayed
        $response->assertDontSee('Legal Information');
        $response->assertDontSee('Data usage, privacy and responsibility');
        $response->assertDontSee('General Information');
    }

    /** @test */
    public function contact_page_works_for_unauthenticated_users_with_session_language()
    {
        // Set session language to English
        Session::put('language', 'en');

        $response = $this->get(route('contact'));

        $response->assertStatus(200);
        
        // Check English translations are displayed
        $response->assertSee('Contacts');
        $response->assertSee('Contact the system creators');
    }

    /** @test */
    public function legal_page_works_for_unauthenticated_users_with_session_language()
    {
        // Set session language to English
        Session::put('language', 'en');

        $response = $this->get(route('legal'));

        $response->assertStatus(200);
        
        // Check English translations are displayed
        $response->assertSee('Legal Information');
        $response->assertSee('Data usage, privacy and responsibility');
    }
}