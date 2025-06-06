<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\AnalysisController;
use App\Models\TextAnalysis;
use Illuminate\Http\Request;

/**
 * Test UTF-8 character positioning fixes for Lithuanian text.
 * 
 * Tests the fix for issue where AI model annotations didn't match
 * displayed text boundaries due to byte vs character positioning.
 * 
 * @group utf8
 * @group positioning
 */
class UTF8PositioningTest extends TestCase
{
    public function test_utf8_character_vs_byte_positioning()
    {
        // Lithuanian text with special characters
        $text = 'Visų pirma nusiimkim spalvotus vaikiškus akinėlius, kuriuos mums kasdien uždeda ekranai iš visų pasaulio šalių, tada taip greit viską suprasim, kad ilgai stebėsimės, kaip anksčiau to nesupratom. Elementari tiesa, kurią nuo mūsų slepia – globalioje politikoje nėra jokių vertybių ir jokios tiesos';
        
        // Test that byte and character lengths differ
        $byteLength = strlen($text);
        $charLength = mb_strlen($text, 'UTF-8');
        
        $this->assertNotEquals($byteLength, $charLength);
        $this->assertGreaterThan($charLength, $byteLength);
        
        // Test position 280 extraction
        $position280_bytes = substr($text, 0, 280);
        $position280_chars = mb_substr($text, 0, 280, 'UTF-8');
        
        // Character-based extraction should be longer and more accurate
        $this->assertNotEquals($position280_bytes, $position280_chars);
        $this->assertStringEndsWith('vertybių i', $position280_chars);
        $this->assertFalse(str_ends_with($position280_bytes, 'vertybių i'));
    }

    public function test_ai_annotation_text_extraction()
    {
        $originalText = 'Visų pirma nusiimkim spalvotus vaikiškus akinėlius, kuriuos mums kasdien uždeda ekranai iš visų pasaulio šalių, tada taip greit viską suprasim, kad ilgai stebėsimės, kaip anksčiau to nesupratom. Elementari tiesa, kurią nuo mūsų slepia – globalioje politikoje nėra jokių vertybių ir jokios tiesos';
        
        // Mock AI annotation with provided text (trust AI text approach)
        $annotation = [
            'value' => [
                'start' => 0,
                'end' => 280,
                'text' => 'Visų pirma nusiimkim spalvotus vaikiškus akinėlius, kuriuos mums kasdien uždeda ekranai iš visų pasaulio šalių, tada taip greit viską suprasim, kad ilgai stebėsimės, kaip anksčiau to nesupratom. Elementari tiesa, kurią nuo mūsų slepia – globalioje politikoje nėra jokių vertybių ir jokios tiesos',
                'labels' => ['simplification', 'emotionalExpression'] // Multiple techniques like Claude
            ]
        ];
        
        // Test the "trust provided text" approach
        $providedText = $annotation['value']['text'] ?? '';
        $start = $annotation['value']['start'];
        $end = $annotation['value']['end'];
        
        // Use provided text if available, otherwise extract from coordinates
        $finalText = !empty($providedText) ? $providedText : mb_substr($originalText, $start, $end - $start, 'UTF-8');
        
        $this->assertEquals($providedText, $finalText);
        $this->assertStringEndsWith('jokios tiesos', $finalText);
        $this->assertCount(2, $annotation['value']['labels']);
        $this->assertContains('simplification', $annotation['value']['labels']);
        $this->assertContains('emotionalExpression', $annotation['value']['labels']);
    }

    public function test_annotation_coordinate_mismatch_handling()
    {
        $originalText = 'Tekstas su lietuviškais simboliais: ąčęėįšųūž';
        
        // Simulate AI providing inconsistent coordinates vs text
        $annotation = [
            'value' => [
                'start' => 0,
                'end' => 20, // Coordinates suggest 20 characters
                'text' => 'Tekstas su lietuviškais simboliais: ąčęėįšųūž', // But text is longer
                'labels' => ['test']
            ]
        ];
        
        $providedText = $annotation['value']['text'] ?? '';
        $start = $annotation['value']['start'];
        $end = $annotation['value']['end'];
        
        // System should trust provided text over coordinates
        $finalText = !empty($providedText) ? $providedText : mb_substr($originalText, $start, $end - $start, 'UTF-8');
        
        $this->assertEquals($providedText, $finalText);
        $this->assertEquals('Tekstas su lietuviškais simboliais: ąčęėįšųūž', $finalText);
        
        // Verify what coordinate extraction would give (should be different)
        $coordinateExtraction = mb_substr($originalText, $start, $end - $start, 'UTF-8');
        $this->assertNotEquals($providedText, $coordinateExtraction);
        $this->assertEquals('Tekstas su lietuvišk', $coordinateExtraction);
    }

    public function test_lithuanian_characters_single_character_count()
    {
        $lithuanianChars = 'ąčęėįšųūž';
        
        // Each Lithuanian character should count as 1 character
        $this->assertEquals(9, mb_strlen($lithuanianChars, 'UTF-8'));
        
        // But they use more bytes in UTF-8
        $this->assertGreaterThan(9, strlen($lithuanianChars));
        
        // Test individual character extraction
        for ($i = 0; $i < 9; $i++) {
            $char = mb_substr($lithuanianChars, $i, 1, 'UTF-8');
            $this->assertEquals(1, mb_strlen($char, 'UTF-8'));
        }
    }

    public function test_text_analysis_annotation_processing()
    {
        // Test annotation structure without database interaction
        $expertAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Propagandos tekstas su lietuviškais simboliais: ąčę',
                    'labels' => ['emotionalExpression']
                ]
            ]
        ];
        
        // Test that UTF-8 text is preserved in annotation structure
        $this->assertIsArray($expertAnnotations);
        $this->assertEquals('Propagandos tekstas su lietuviškais simboliais: ąčę', 
                          $expertAnnotations[0]['value']['text']);
        $this->assertStringContainsString('ą', $expertAnnotations[0]['value']['text']);
        $this->assertStringContainsString('č', $expertAnnotations[0]['value']['text']);
        $this->assertStringContainsString('ę', $expertAnnotations[0]['value']['text']);
    }

    public function test_prompt_service_utf8_instructions()
    {
        $promptService = app(\App\Services\PromptService::class);
        
        // Get the standard RISEN prompt
        $risenPrompt = $promptService->getStandardRisenPrompt();
        
        // Verify UTF-8 instructions are included
        $this->assertStringContainsString('UNICODE SIMBOLIŲ pozicijas', $risenPrompt['execution']);
        $this->assertStringContainsString('ą,č,ę,ė,į,š,ų,ū,ž kaip po 1 simbolį', $risenPrompt['execution']);
        
        // Test public method for prompt generation
        $fullPrompt = $promptService->generateAnalysisPrompt('Test text');
        
        $this->assertStringContainsString('UNICODE simbolių pozicijas', $fullPrompt);
        $this->assertStringContainsString('Lietuviški simboliai (ą,č,ę,ė,į,š,ų,ū,ž)', $fullPrompt);
    }

    public function test_metrics_service_position_accuracy()
    {
        $metricsService = app(\App\Services\MetricsService::class);
        
        // Test position accuracy calculation with UTF-8 coordinates
        $expertLabels = [
            [
                'start' => 0,
                'end' => 30,
                'text' => 'Tekstas su ąčęėįšųūž simboliais',
                'labels' => ['test']
            ]
        ];
        
        $modelLabels = [
            [
                'start' => 0,
                'end' => 30,
                'text' => 'Tekstas su ąčęėįšųūž simboliais',
                'labels' => ['test']
            ]
        ];
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($metricsService);
        $method = $reflection->getMethod('calculatePositionAccuracy');
        $method->setAccessible(true);
        
        $accuracy = $method->invoke($metricsService, $expertLabels, $modelLabels);
        
        // Should have perfect accuracy when positions match exactly
        $this->assertEquals(1.0, $accuracy);
    }

    public function test_multiple_techniques_metrics_calculation()
    {
        $metricsService = app(\App\Services\MetricsService::class);
        
        // Expert annotation with single technique
        $expertLabels = [
            [
                'start' => 0,
                'end' => 100,
                'text' => 'Test propaganda text',
                'labels' => ['simplification']
            ]
        ];
        
        // AI annotation with multiple techniques (like Claude response)
        $modelLabels = [
            [
                'start' => 0,
                'end' => 100,
                'text' => 'Test propaganda text',
                'labels' => ['simplification', 'emotionalExpression'] 
            ]
        ];
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($metricsService);
        $method = $reflection->getMethod('labelsOverlap');
        $method->setAccessible(true);
        
        $overlap = $method->invoke($metricsService, $expertLabels[0], $modelLabels[0]);
        
        // Should return true because 'simplification' is in both expert and model labels
        $this->assertTrue($overlap);
    }

    public function test_no_text_duplication_when_ai_provides_full_text()
    {
        // Simulate the scenario where AI provides complete text that doesn't match coordinates
        $annotation = [
            'start' => 0,
            'end' => 280, // Coordinates suggest 280 characters
            'text' => 'Visų pirma nusiimkim spalvotus vaikiškus akinėlius, kuriuos mums kasdien uždeda ekranai iš visų pasaulio šalių, tada taip greit viską suprasim, kad ilgai stebėsimės, kaip anksčiau to nesupratom. Elementari tiesa, kurią nuo mūsų slepia – globalioje politikoje nėra jokių vertybių ir jokios tiesos', // But full text is 295 characters
            'technique' => 'simplification',
            'labels' => ['simplification', 'emotionalExpression']
        ];
        
        $originalText = 'Visų pirma nusiimkim spalvotus vaikiškus akinėlius, kuriuos mums kasdien uždeda ekranai iš visų pasaulio šalių, tada taip greit viską suprasim, kad ilgai stebėsimės, kaip anksčiau to nesupratom. Elementari tiesa, kurią nuo mūsų slepia – globalioje politikoje nėra jokių vertybių ir jokios tiesos, išskyrus kovą dėl įtakų, resursų, pelnų, valdžios ir teritorijų.';
        
        // Verify that AI text is complete and doesn't get truncated
        $this->assertStringEndsWith('jokios tiesos', $annotation['text']);
        $this->assertNotEquals(280, mb_strlen($annotation['text'], 'UTF-8')); // Text is longer than coordinates suggest
        
        // Verify the text appears in original content
        $this->assertStringContainsString($annotation['text'], $originalText);
        
        // Find position of AI text in original content
        $foundPosition = mb_strpos($originalText, $annotation['text'], 0, 'UTF-8');
        $this->assertEquals(0, $foundPosition); // Should be at the beginning
        
        // Verify no duplication would occur - text after annotation should not repeat
        $afterPosition = $foundPosition + mb_strlen($annotation['text'], 'UTF-8');
        $textAfter = mb_substr($originalText, $afterPosition, 20, 'UTF-8');
        $this->assertEquals(', išskyrus kovą dėl ', $textAfter);
        $this->assertStringNotContainsString('jokios tiesos', $textAfter); // No duplication
    }
}