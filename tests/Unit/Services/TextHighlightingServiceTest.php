<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Http\Controllers\AnalysisController;
use App\Services\MetricsService;
use App\Services\ExportService;
use ReflectionClass;

class TextHighlightingServiceTest extends TestCase
{
    private AnalysisController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $metricsService = $this->createMock(MetricsService::class);
        $exportService = $this->createMock(ExportService::class);
        $this->controller = new AnalysisController($metricsService, $exportService);
    }

    public function test_create_legend_with_empty_techniques()
    {
        $legend = $this->callPrivateMethod('createLegend', [[]]);
        
        $this->assertIsArray($legend);
        $this->assertEmpty($legend);
    }

    public function test_create_legend_with_single_technique()
    {
        $techniques = ['Emocinė raiška'];
        $legend = $this->callPrivateMethod('createLegend', [$techniques]);
        
        $this->assertCount(1, $legend);
        $this->assertEquals('Emocinė raiška', $legend[0]['technique']);
        $this->assertEquals(1, $legend[0]['number']);
        $this->assertStringStartsWith('#', $legend[0]['color']);
    }

    public function test_create_legend_with_multiple_techniques()
    {
        $techniques = ['Emocinė raiška', 'Whataboutism', 'Supaprastinimas', 'Pakartojimas'];
        $legend = $this->callPrivateMethod('createLegend', [$techniques]);
        
        $this->assertCount(4, $legend);
        
        // Check that techniques are sorted alphabetically
        $expectedOrder = ['Emocinė raiška', 'Pakartojimas', 'Supaprastinimas', 'Whataboutism'];
        $actualOrder = array_column($legend, 'technique');
        $this->assertEquals($expectedOrder, $actualOrder);
        
        // Check numbering
        for ($i = 0; $i < count($legend); $i++) {
            $this->assertEquals($i + 1, $legend[$i]['number']);
        }
        
        // Check that all colors are different (for first few items)
        $colors = array_column($legend, 'color');
        $uniqueColors = array_unique($colors);
        $this->assertCount(count($colors), $uniqueColors);
    }

    public function test_create_legend_with_many_techniques()
    {
        // Test with more techniques than available colors
        $techniques = [];
        for ($i = 1; $i <= 25; $i++) {
            $techniques[] = "Technika $i";
        }
        
        $legend = $this->callPrivateMethod('createLegend', [$techniques]);
        
        $this->assertCount(25, $legend);
        
        // Check that color cycling works (some colors may repeat)
        foreach ($legend as $item) {
            $this->assertStringStartsWith('#', $item['color']);
            $this->assertIsString($item['technique']);
            $this->assertIsInt($item['number']);
        }
    }

    public function test_create_legend_color_consistency()
    {
        $techniques = ['Alpha', 'Beta', 'Gamma'];
        
        // Call multiple times to ensure consistent colors
        $legend1 = $this->callPrivateMethod('createLegend', [$techniques]);
        $legend2 = $this->callPrivateMethod('createLegend', [$techniques]);
        
        $this->assertEquals($legend1, $legend2);
    }

    public function test_create_legend_handles_special_characters()
    {
        $techniques = ['Emocinė raiška', 'Sūkurius-verpetas', 'Į/iš konteksto'];
        $legend = $this->callPrivateMethod('createLegend', [$techniques]);
        
        $this->assertCount(3, $legend);
        
        foreach ($legend as $item) {
            $this->assertIsString($item['technique']);
            $this->assertNotEmpty($item['technique']);
        }
    }

    public function test_legend_colors_are_valid_hex()
    {
        $techniques = ['Test1', 'Test2', 'Test3'];
        $legend = $this->callPrivateMethod('createLegend', [$techniques]);
        
        foreach ($legend as $item) {
            $color = $item['color'];
            $this->assertStringStartsWith('#', $color);
            $this->assertEquals(7, strlen($color)); // #rrggbb format
            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $color);
        }
    }

    /**
     * Helper method to call private methods
     */
    private function callPrivateMethod(string $methodName, array $args = [])
    {
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($this->controller, $args);
    }
}