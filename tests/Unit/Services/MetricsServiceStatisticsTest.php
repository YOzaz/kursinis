<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MetricsService;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MetricsServiceStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private MetricsService $metricsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsService = new MetricsService();
    }


    /**
     * Test that empty job returns proper default values.
     */
    public function test_calculate_job_statistics_empty_job(): void
    {
        $job = AnalysisJob::factory()->completed()->create();

        $statistics = $this->metricsService->calculateJobStatistics($job);

        $this->assertEquals(0, $statistics['total_texts']);
        $this->assertEmpty($statistics['models_used']);
        $this->assertEmpty($statistics['overall_metrics']);
        $this->assertEmpty($statistics['per_model_metrics']);
        $this->assertEquals(0, $statistics['execution_time']);
    }
}