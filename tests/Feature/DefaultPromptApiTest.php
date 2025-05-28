<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DefaultPromptApiTest extends TestCase
{
    /**
     * Test that the default prompt API returns a complete RISEN prompt.
     */
    public function test_default_prompt_api_returns_complete_risen_prompt(): void
    {
        $response = $this->get('/api/default-prompt');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'prompt'
        ]);

        $data = $response->json();
        $prompt = $data['prompt'];

        // Check that the prompt contains all RISEN elements
        $this->assertStringContainsString('**ROLE**:', $prompt);
        $this->assertStringContainsString('**INSTRUCTIONS**:', $prompt);
        $this->assertStringContainsString('**SITUATION**:', $prompt);
        $this->assertStringContainsString('**EXECUTION**:', $prompt);
        $this->assertStringContainsString('**NEEDLE**:', $prompt);

        // Check that it contains propaganda techniques
        $this->assertStringContainsString('**PROPAGANDOS TECHNIKOS (ATSPARA metodologija)**:', $prompt);
        $this->assertStringContainsString('emotionalAppeal:', $prompt);
        $this->assertStringContainsString('loadedLanguage:', $prompt);

        // Check that it contains disinformation narratives
        $this->assertStringContainsString('**DEZINFORMACIJOS NARATYVAI**:', $prompt);
        $this->assertStringContainsString('distrustOfLithuanianInstitutions:', $prompt);
        $this->assertStringContainsString('natoDistrust:', $prompt);

        // Check that it contains the JSON format specification
        $this->assertStringContainsString('**ATSAKYMO FORMATAS**:', $prompt);
        $this->assertStringContainsString('primaryChoice', $prompt);
        $this->assertStringContainsString('annotations', $prompt);
        $this->assertStringContainsString('desinformationTechnique', $prompt);
    }

    /**
     * Test that the prompt contains all configured propaganda techniques.
     */
    public function test_default_prompt_contains_all_propaganda_techniques(): void
    {
        $response = $this->get('/api/default-prompt');
        $data = $response->json();
        $prompt = $data['prompt'];

        $techniques = config('llm.propaganda_techniques');
        
        foreach ($techniques as $key => $description) {
            $this->assertStringContainsString($key . ':', $prompt, "Prompt should contain propaganda technique: {$key}");
        }
    }

    /**
     * Test that the prompt contains all configured disinformation narratives.
     */
    public function test_default_prompt_contains_all_disinformation_narratives(): void
    {
        $response = $this->get('/api/default-prompt');
        $data = $response->json();
        $prompt = $data['prompt'];

        $narratives = config('llm.disinformation_narratives');
        
        foreach ($narratives as $key => $description) {
            $this->assertStringContainsString($key . ':', $prompt, "Prompt should contain disinformation narrative: {$key}");
        }
    }
}