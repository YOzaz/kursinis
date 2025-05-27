<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Automatically fake HTTP for all tests unless explicitly disabled
        if (env('HTTP_FAKE', true)) {
            Http::fake();
        }
        
        // Set up test database
        $this->setUpDatabase();
        
        // Clear any cached data
        $this->clearApplicationCache();
    }

    protected function setUpDatabase(): void
    {
        // Additional database setup if needed
        // This runs after the standard Laravel test setup
    }

    protected function clearApplicationCache(): void
    {
        $this->app['cache']->flush();
        $this->app['config']->set('cache.default', 'array');
    }

    /**
     * Create a mock LLM response for testing
     */
    protected function mockLLMResponse(string $service = 'claude', array $response = null): void
    {
        $defaultResponse = [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 10,
                        'text' => 'test text',
                        'labels' => ['test_technique']
                    ]
                ]
            ],
            'desinformationTechnique' => ['choices' => ['test_technique']]
        ];

        $responseData = $response ?? $defaultResponse;

        switch ($service) {
            case 'claude':
                Http::fake([
                    'api.anthropic.com/*' => Http::response([
                        'content' => [['text' => json_encode($responseData)]]
                    ], 200)
                ]);
                break;
                
            case 'gemini':
                Http::fake([
                    'generativelanguage.googleapis.com/*' => Http::response([
                        'candidates' => [
                            [
                                'content' => [
                                    'parts' => [
                                        ['text' => json_encode($responseData)]
                                    ]
                                ]
                            ]
                        ]
                    ], 200)
                ]);
                break;
                
            case 'openai':
                Http::fake([
                    'api.openai.com/*' => Http::response([
                        'choices' => [
                            [
                                'message' => [
                                    'content' => json_encode($responseData)
                                ]
                            ]
                        ]
                    ], 200)
                ]);
                break;
                
            case 'all':
                $this->mockLLMResponse('claude', $responseData);
                $this->mockLLMResponse('gemini', $responseData);
                $this->mockLLMResponse('openai', $responseData);
                break;
        }
    }

    /**
     * Assert that a database table has a specific count
     */
    protected function assertDatabaseRecordCount(string $table, int $count): void
    {
        $actual = $this->app['db']->table($table)->count();
        $this->assertEquals($count, $actual, "Expected {$count} records in {$table} table, but found {$actual}");
    }

    /**
     * Assert that response contains specific JSON structure
     */
    protected function assertJsonStructureExact(array $structure, array $data): void
    {
        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                $this->assertArrayHasKey($key, $data);
                $this->assertJsonStructureExact($value, $data[$key]);
            } else {
                $this->assertArrayHasKey($value, $data);
            }
        }
    }

    /**
     * Create test CSV content for upload testing
     */
    protected function createTestCsvContent(int $rows = 3): string
    {
        $csv = "text,expert_annotations\n";
        
        for ($i = 1; $i <= $rows; $i++) {
            $text = "Test propaganda text number {$i}";
            $annotations = json_encode([
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 0,
                            'end' => strlen($text),
                            'text' => $text,
                            'labels' => ['emotional_appeal']
                        ]
                    ]
                ],
                'desinformationTechnique' => ['choices' => ['emotional_appeal']]
            ]);
            
            $csv .= "\"{$text}\",\"{$annotations}\"\n";
        }
        
        return $csv;
    }

    /**
     * Create a temporary file for upload testing
     */
    protected function createTestFile(string $content, string $filename = 'test.csv'): \Illuminate\Http\UploadedFile
    {
        $path = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($path, $content);
        
        return new \Illuminate\Http\UploadedFile(
            $path,
            $filename,
            'text/csv',
            null,
            true
        );
    }

    /**
     * Assert that view has specific data structure
     */
    protected function assertViewHasData(string $key, $expected, $response): void
    {
        $viewData = $response->viewData($key);
        $this->assertEquals($expected, $viewData);
    }

    /**
     * Run test with specific configuration
     */
    protected function withConfig(array $config, callable $test): void
    {
        $originalConfig = [];
        
        foreach ($config as $key => $value) {
            $originalConfig[$key] = config($key);
            config([$key => $value]);
        }
        
        try {
            $test();
        } finally {
            foreach ($originalConfig as $key => $value) {
                config([$key => $value]);
            }
        }
    }

    /**
     * Assert that experiment has correct RISEN structure
     */
    protected function assertValidRisenConfig(array $config): void
    {
        $requiredKeys = ['role', 'instructions', 'situation', 'execution', 'needle'];
        
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $config, "RISEN config missing required key: {$key}");
            $this->assertNotEmpty($config[$key], "RISEN config key '{$key}' should not be empty");
        }
    }

    /**
     * Assert that statistics have correct structure
     */
    protected function assertValidStatisticsStructure(array $statistics): void
    {
        $requiredKeys = ['models', 'metrics', 'comparison', 'charts'];
        
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $statistics, "Statistics missing required key: {$key}");
        }
        
        if (!empty($statistics['models'])) {
            foreach ($statistics['models'] as $model => $modelStats) {
                $this->assertArrayHasKey('total_analyses', $modelStats);
                $this->assertArrayHasKey('avg_execution_time', $modelStats);
                $this->assertArrayHasKey('avg_precision', $modelStats);
                $this->assertArrayHasKey('avg_recall', $modelStats);
                $this->assertArrayHasKey('avg_f1', $modelStats);
                $this->assertArrayHasKey('avg_kappa', $modelStats);
            }
        }
    }
}