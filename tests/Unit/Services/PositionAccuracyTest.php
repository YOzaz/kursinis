<?php

namespace Tests\Unit\Services;

use App\Services\MetricsService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PositionAccuracyTest extends TestCase
{
    private MetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MetricsService();
    }

    /**
     * Test the new IAA position accuracy formula with perfect overlap.
     */
    public function test_position_accuracy_perfect_overlap()
    {
        $expertLabels = [
            ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]
        ];
        $modelLabels = [
            ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]
        ];

        $accuracy = $this->callPrivateMethod('calculatePositionAccuracy', [$expertLabels, $modelLabels]);
        
        $this->assertEquals(1.0, $accuracy, 'Perfect overlap should result in 1.0 accuracy');
    }

    /**
     * Test IAA formula with partial overlap.
     */
    public function test_position_accuracy_partial_overlap()
    {
        $expertLabels = [
            ['start' => 0, 'end' => 20, 'text' => 'propaganda text here', 'labels' => ['propaganda']]
        ];
        $modelLabels = [
            ['start' => 5, 'end' => 15, 'text' => 'ganda text', 'labels' => ['propaganda']]
        ];

        $accuracy = $this->callPrivateMethod('calculatePositionAccuracy', [$expertLabels, $modelLabels]);
        
        // Expert: 20 chars, Model: 10 chars, Overlap: 10 chars
        // IAA = 10 / min(20, 10) = 10 / 10 = 1.0
        $this->assertEquals(1.0, $accuracy, 'Overlap of 10 chars with min set of 10 should be 1.0');
    }

    /**
     * Test IAA formula with no overlap.
     */
    public function test_position_accuracy_no_overlap()
    {
        $expertLabels = [
            ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]
        ];
        $modelLabels = [
            ['start' => 20, 'end' => 30, 'text' => 'different', 'labels' => ['propaganda']]
        ];

        $accuracy = $this->callPrivateMethod('calculatePositionAccuracy', [$expertLabels, $modelLabels]);
        
        $this->assertEquals(0.0, $accuracy, 'No overlap should result in 0.0 accuracy');
    }

    /**
     * Test IAA formula with multiple annotations.
     */
    public function test_position_accuracy_multiple_annotations()
    {
        $expertLabels = [
            ['start' => 0, 'end' => 10, 'text' => 'first part', 'labels' => ['propaganda']],
            ['start' => 20, 'end' => 30, 'text' => 'second part', 'labels' => ['propaganda']]
        ];
        $modelLabels = [
            ['start' => 5, 'end' => 15, 'text' => 'overlaps first', 'labels' => ['propaganda']],
            ['start' => 25, 'end' => 35, 'text' => 'overlaps second', 'labels' => ['propaganda']]
        ];

        $accuracy = $this->callPrivateMethod('calculatePositionAccuracy', [$expertLabels, $modelLabels]);
        
        // Expert total: (10-0) + (30-20) = 20 chars
        // Model total: (15-5) + (35-25) = 20 chars  
        // Intersection: (10-5) + (30-25) = 5 + 5 = 10 chars
        // IAA = 10 / min(20, 20) = 10 / 20 = 0.5
        $this->assertEquals(0.5, $accuracy, 'Multiple annotations with partial overlap should calculate correctly');
    }

    /**
     * Test empty annotations edge case.
     */
    public function test_position_accuracy_empty_annotations()
    {
        $accuracy1 = $this->callPrivateMethod('calculatePositionAccuracy', [[], []]);
        $this->assertEquals(1.0, $accuracy1, 'Both empty should result in perfect agreement');

        $expertLabels = [['start' => 0, 'end' => 10, 'text' => 'test', 'labels' => ['propaganda']]];
        $accuracy2 = $this->callPrivateMethod('calculatePositionAccuracy', [$expertLabels, []]);
        $this->assertEquals(0.0, $accuracy2, 'One empty should result in no agreement');

        $accuracy3 = $this->callPrivateMethod('calculatePositionAccuracy', [[], $expertLabels]);
        $this->assertEquals(0.0, $accuracy3, 'One empty should result in no agreement');
    }

    /**
     * Test intersection length calculation.
     */
    public function test_intersection_length_calculation()
    {
        $expertLabels = [
            ['start' => 0, 'end' => 15, 'text' => 'expert annotation', 'labels' => ['propaganda']],
            ['start' => 30, 'end' => 40, 'text' => 'second expert', 'labels' => ['propaganda']]
        ];
        $modelLabels = [
            ['start' => 10, 'end' => 25, 'text' => 'model annotation', 'labels' => ['propaganda']],
            ['start' => 35, 'end' => 45, 'text' => 'second model', 'labels' => ['propaganda']]
        ];

        $intersectionLength = $this->callPrivateMethod('calculateIntersectionLength', [$expertLabels, $modelLabels]);
        
        // First intersection: max(0,10) to min(15,25) = 10 to 15 = 5 chars
        // Second intersection: max(30,35) to min(40,45) = 35 to 40 = 5 chars
        // Total: 5 + 5 = 10 chars
        $this->assertEquals(10, $intersectionLength, 'Intersection length should be calculated correctly');
    }

    /**
     * Test IAA formula bias prevention.
     * Model with more annotations shouldn't be penalized when expert has fewer.
     */
    public function test_position_accuracy_bias_prevention()
    {
        $expertLabels = [
            ['start' => 0, 'end' => 10, 'text' => 'short expert', 'labels' => ['propaganda']]
        ];
        $modelLabels = [
            ['start' => 0, 'end' => 10, 'text' => 'exact match', 'labels' => ['propaganda']],
            ['start' => 15, 'end' => 25, 'text' => 'extra model', 'labels' => ['propaganda']]
        ];

        $accuracy = $this->callPrivateMethod('calculatePositionAccuracy', [$expertLabels, $modelLabels]);
        
        // Expert: 10 chars, Model: 20 chars, Overlap: 10 chars
        // IAA = 10 / min(10, 20) = 10 / 10 = 1.0
        // The model gets perfect score for matching the expert annotation
        $this->assertEquals(1.0, $accuracy, 'Model should get full credit for matching expert annotation');
    }

    /**
     * Helper method to call private methods for testing.
     */
    private function callPrivateMethod(string $methodName, array $args = [])
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->service, $args);
    }
}