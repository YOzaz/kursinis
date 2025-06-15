<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Models\ModelResult;
use App\Models\User;
use App\Services\CachedStatisticsService;

class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
    }
    
    public function test_dashboard_loads_successfully()
    {
        // Simulate authenticated session
        $this->withSession(['authenticated' => true, 'username' => 'test']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200);
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('globalStats');
        $response->assertViewHas('recentAnalyses');
    }
    
    public function test_dashboard_caches_statistics()
    {
        // Simulate authenticated session
        $this->withSession(['authenticated' => true, 'username' => 'test']);
        
        // Create test data
        $this->createTestData();
        
        // First request should generate cache
        $start1 = microtime(true);
        $response1 = $this->get('/dashboard');
        $time1 = microtime(true) - $start1;
        
        $response1->assertStatus(200);
        
        // Second request should use cache (much faster)
        $start2 = microtime(true);
        $response2 = $this->get('/dashboard');
        $time2 = microtime(true) - $start2;
        
        $response2->assertStatus(200);
        
        // Cached request should be at least 50% faster
        $this->assertLessThan($time1 * 0.5, $time2);
        
        // Verify cache exists
        $this->assertNotNull(Cache::get('dashboard_stats_global'));
    }
    
    public function test_cache_invalidation_on_data_change()
    {
        // Simulate authenticated session
        $this->withSession(['authenticated' => true, 'username' => 'test']);
        
        // Create initial data
        $this->createTestData();
        
        // Load dashboard to populate cache
        $this->get('/dashboard');
        $this->assertNotNull(Cache::get('dashboard_stats_global'));
        
        // Create new analysis job (should invalidate cache)
        AnalysisJob::create([
            'job_id' => 'test-job-2',
            'name' => 'New Test Job',
            'status' => 'pending',
            'total_texts' => 5,
            'processed_texts' => 0,
        ]);
        
        // Cache should be cleared
        $this->assertNull(Cache::get('dashboard_stats_global'));
    }
    
    public function test_cached_statistics_service_returns_correct_data()
    {
        $this->createTestData();
        
        $service = new CachedStatisticsService();
        $stats = $service->getGlobalStatistics();
        
        $this->assertArrayHasKey('total_analyses', $stats);
        $this->assertArrayHasKey('total_texts', $stats);
        $this->assertArrayHasKey('total_metrics', $stats);
        $this->assertArrayHasKey('model_performance', $stats);
        $this->assertArrayHasKey('avg_execution_times', $stats);
        $this->assertArrayHasKey('top_techniques', $stats);
        $this->assertArrayHasKey('time_series_data', $stats);
        
        $this->assertEquals(1, $stats['total_analyses']);
        $this->assertEquals(2, $stats['total_texts']);
        $this->assertEquals(2, $stats['total_metrics']);
    }
    
    public function test_model_performance_calculation()
    {
        $this->createTestDataWithMetrics();
        
        $service = new CachedStatisticsService();
        $stats = $service->getGlobalStatistics();
        
        $this->assertArrayHasKey('claude-opus-4', $stats['model_performance']);
        
        $claudeStats = $stats['model_performance']['claude-opus-4'];
        $this->assertArrayHasKey('total_analyses', $claudeStats);
        $this->assertArrayHasKey('total_propaganda_texts', $claudeStats);
        $this->assertArrayHasKey('avg_precision', $claudeStats);
        $this->assertArrayHasKey('avg_recall', $claudeStats);
        $this->assertArrayHasKey('avg_f1_score', $claudeStats);
        $this->assertArrayHasKey('overall_score', $claudeStats);
        $this->assertArrayHasKey('propaganda_detection_accuracy', $claudeStats);
        $this->assertArrayHasKey('propaganda_tp', $claudeStats);
        $this->assertArrayHasKey('propaganda_fp', $claudeStats);
        $this->assertArrayHasKey('propaganda_tn', $claudeStats);
        $this->assertArrayHasKey('propaganda_fn', $claudeStats);
    }
    
    public function test_cache_clear_command()
    {
        // Simulate authenticated session
        $this->withSession(['authenticated' => true, 'username' => 'test']);
        
        $this->createTestData();
        
        // Load dashboard to populate cache
        $this->get('/dashboard');
        $this->assertNotNull(Cache::get('dashboard_stats_global'));
        
        // Clear cache using service method
        CachedStatisticsService::invalidateCache();
        
        // All cache keys should be cleared
        $this->assertNull(Cache::get('dashboard_stats_global'));
        $this->assertNull(Cache::get('dashboard_stats_total_analyses'));
        $this->assertNull(Cache::get('dashboard_stats_total_texts'));
        $this->assertNull(Cache::get('dashboard_stats_total_metrics'));
        $this->assertNull(Cache::get('dashboard_stats_model_performance'));
        $this->assertNull(Cache::get('dashboard_stats_execution_times'));
        $this->assertNull(Cache::get('dashboard_stats_top_techniques'));
    }
    
    private function createTestData()
    {
        $job = AnalysisJob::create([
            'job_id' => 'test-job-1',
            'name' => 'Test Job',
            'status' => 'completed',
            'total_texts' => 2,
            'processed_texts' => 2,
        ]);
        
        // Create text analyses
        for ($i = 1; $i <= 2; $i++) {
            $textAnalysis = TextAnalysis::create([
                'job_id' => $job->job_id,
                'text_id' => "text-$i",
                'content' => "Test content $i",
                'expert_annotations' => [
                    [
                        'result' => [
                            ['type' => 'choices', 'value' => ['choices' => ['yes']]]
                        ]
                    ]
                ],
            ]);
            
            // Create comparison metric
            ComparisonMetric::create([
                'job_id' => $job->job_id,
                'text_id' => "text-$i",
                'model_name' => 'claude-opus-4',
                'precision' => 0.85,
                'recall' => 0.80,
                'f1_score' => 0.825,
                'position_accuracy' => 0.90,
            ]);
        }
    }
    
    private function createTestDataWithMetrics()
    {
        $job = AnalysisJob::create([
            'job_id' => 'test-job-metrics',
            'name' => 'Test Job with Metrics',
            'status' => 'completed',
            'total_texts' => 2,
            'processed_texts' => 2,
        ]);
        
        // Text 1: Propaganda text (expert: yes, model: yes) - TP
        TextAnalysis::create([
            'job_id' => $job->job_id,
            'text_id' => 'text-propaganda-1',
            'content' => 'Propaganda text content',
            'expert_annotations' => [
                [
                    'result' => [
                        ['type' => 'choices', 'value' => ['choices' => ['yes']]]
                    ]
                ]
            ],
        ]);
        
        ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'text-propaganda-1',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'status' => 'completed',
            'annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => []
            ],
            'execution_time_ms' => 1500,
        ]);
        
        ComparisonMetric::create([
            'job_id' => $job->job_id,
            'text_id' => 'text-propaganda-1',
            'model_name' => 'claude-opus-4',
            'precision' => 0.85,
            'recall' => 0.80,
            'f1_score' => 0.825,
            'position_accuracy' => 0.90,
            'analysis_execution_time_ms' => 1500,
        ]);
        
        // Text 2: Non-propaganda text (expert: no, model: no) - TN
        TextAnalysis::create([
            'job_id' => $job->job_id,
            'text_id' => 'text-normal-1',
            'content' => 'Normal text content',
            'expert_annotations' => [
                [
                    'result' => [
                        ['type' => 'choices', 'value' => ['choices' => ['no']]]
                    ]
                ]
            ],
        ]);
        
        ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'text-normal-1',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'status' => 'completed',
            'annotations' => [
                'primaryChoice' => ['choices' => ['no']],
                'annotations' => []
            ],
            'execution_time_ms' => 1200,
        ]);
    }
}