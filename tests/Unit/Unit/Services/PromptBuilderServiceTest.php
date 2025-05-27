<?php

namespace Tests\Unit\Unit\Services;

use App\Services\PromptBuilderService;
use PHPUnit\Framework\TestCase;

class PromptBuilderServiceTest extends TestCase
{
    private PromptBuilderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PromptBuilderService();
    }

    public function test_builds_risen_prompt_with_all_components(): void
    {
        $config = [
            'role' => 'Test role content',
            'instructions' => 'Test instructions content',
            'situation' => 'Test situation content',
            'execution' => 'Test execution content',
            'needle' => 'Test needle content',
        ];

        $prompt = $this->service->buildRisenPrompt($config);

        $this->assertStringContainsString('Test role content', $prompt);
        $this->assertStringContainsString('Test instructions content', $prompt);
        $this->assertStringContainsString('Test situation content', $prompt);
        $this->assertStringContainsString('Test execution content', $prompt);
        $this->assertStringContainsString('Test needle content', $prompt);
    }

    public function test_builds_risen_prompt_with_proper_structure(): void
    {
        $config = [
            'role' => 'Role',
            'instructions' => 'Instructions',
            'situation' => 'Situation',
            'execution' => 'Execution',
            'needle' => 'Needle',
        ];

        $prompt = $this->service->buildRisenPrompt($config);

        // Check that components are separated by double newlines
        $this->assertEquals("Role\n\nInstructions\n\nSituation\n\nExecution\n\nNeedle", $prompt);
    }

    public function test_builds_risen_prompt_with_missing_components(): void
    {
        $config = [
            'role' => 'Role content',
            'instructions' => 'Instructions content',
            // Missing situation, execution, needle
        ];

        $prompt = $this->service->buildRisenPrompt($config);

        $this->assertStringContainsString('Role content', $prompt);
        $this->assertStringContainsString('Instructions content', $prompt);
        // Should use empty strings for missing components - just check it doesn't crash
        $this->assertIsString($prompt);
    }

    public function test_gets_default_risen_config(): void
    {
        $config = $this->service->getDefaultRisenConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('role', $config);
        $this->assertArrayHasKey('instructions', $config);
        $this->assertArrayHasKey('situation', $config);
        $this->assertArrayHasKey('execution', $config);
        $this->assertArrayHasKey('needle', $config);
    }

    public function test_default_config_has_lithuanian_content(): void
    {
        $config = $this->service->getDefaultRisenConfig();

        $this->assertStringContainsString('propagandos', $config['role']);
        $this->assertStringContainsString('Išanalizuok', $config['instructions']);
        $this->assertStringContainsString('tekstas', $config['situation']);
        $this->assertStringContainsString('analizę', $config['execution']);
        $this->assertStringContainsString('JSON', $config['needle']);
    }

    public function test_default_config_contains_risen_methodology_elements(): void
    {
        $config = $this->service->getDefaultRisenConfig();

        // Role should define expertise
        $this->assertStringContainsString('ekspertas', $config['role']);
        
        // Instructions should contain analysis steps
        $this->assertStringContainsString('propagandos technikas', $config['instructions']);
        
        // Situation should describe context
        $this->assertStringContainsString('tekstas gali būti', $config['situation']);
        
        // Execution should provide step-by-step guidance
        $this->assertStringContainsString('Atlikdamas', $config['execution']);
        
        // Needle should specify output format
        $this->assertStringContainsString('JSON formatu', $config['needle']);
    }

    public function test_builds_prompt_with_empty_config(): void
    {
        $prompt = $this->service->buildRisenPrompt([]);

        // Should still return a string with newlines
        $this->assertIsString($prompt);
        $this->assertStringContainsString("\n\n", $prompt);
    }

    public function test_handles_null_values_in_config(): void
    {
        $config = [
            'role' => 'Valid role',
            'instructions' => null,
            'situation' => 'Valid situation',
            'execution' => null,
            'needle' => 'Valid needle',
        ];

        $prompt = $this->service->buildRisenPrompt($config);

        $this->assertStringContainsString('Valid role', $prompt);
        $this->assertStringContainsString('Valid situation', $prompt);
        $this->assertStringContainsString('Valid needle', $prompt);
    }
}
