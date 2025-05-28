<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Http\Controllers\AnalysisController;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Services\MetricsService;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class AnalysisControllerShowTest extends TestCase
{
    use RefreshDatabase;


    /**
     * Test with no analyses.
     */
    public function test_show_method_with_no_analyses(): void
    {
        $job = AnalysisJob::factory()->completed()->create([
            'total_texts' => 0
        ]);

        $controller = new AnalysisController(new MetricsService(), new ExportService());
        $request = new Request();
        
        $response = $controller->show($job->job_id, $request);
        $viewData = $response->getData();

        $this->assertEquals(0, $viewData['actualTextCount']);
        $this->assertEquals(0, $viewData['modelCount']);
        $this->assertEmpty($viewData['usedModels']);
    }
}