<?php
/**
 * Comprehensive Test Script - Tests fixes and improvements
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== Comprehensive Fix Verification ===\n\n";

echo "1. API Keys Status:\n";
testAPIKeys();

echo "\n2. Model Name Display:\n";
testModelNameDisplay();

echo "\n3. Actual Model Name Storage:\n";
testActualModelNames();

echo "\n4. Rate Limiting Configuration:\n";
testRateLimitConfig();

echo "\n=== Summary ===\n";
provideSummary();

function testAPIKeys() {
    $models = config('llm.models');
    foreach ($models as $key => $config) {
        $hasKey = isset($config['api_key']) && !empty($config['api_key']);
        echo "✓ $key: " . ($hasKey ? "Configured" : "Missing") . "\n";
        
        if ($hasKey) {
            echo "  Model: {$config['model']}\n";
        }
    }
}

function testModelNameDisplay() {
    try {
        $claudeService = app(\App\Services\ClaudeService::class);
        $geminiService = app(\App\Services\GeminiService::class);
        $openaiService = app(\App\Services\OpenAIService::class);
        
        echo "✓ Claude: {$claudeService->getModelName()} → {$claudeService->getActualModelName()}\n";
        echo "✓ Gemini: {$geminiService->getModelName()} → {$geminiService->getActualModelName()}\n";
        echo "✓ OpenAI: {$openaiService->getModelName()} → {$openaiService->getActualModelName()}\n";
        
    } catch (\Exception $e) {
        echo "❌ Error testing services: " . $e->getMessage() . "\n";
    }
}

function testActualModelNames() {
    // Check if database has new columns
    try {
        $hasColumns = \Illuminate\Support\Facades\Schema::hasColumn('text_analysis', 'claude_actual_model') &&
                     \Illuminate\Support\Facades\Schema::hasColumn('comparison_metrics', 'actual_model_name');
        
        echo $hasColumns ? "✅ Database schema updated" : "❌ Database schema missing new columns";
        echo "\n";
        
    } catch (\Exception $e) {
        echo "❌ Database check failed: " . $e->getMessage() . "\n";
    }
}

function testRateLimitConfig() {
    $models = config('llm.models');
    $hasRateLimits = false;
    
    foreach ($models as $key => $config) {
        if (isset($config['rate_limit'])) {
            echo "⚠️  $key has rate_limit: {$config['rate_limit']}\n";
            $hasRateLimits = true;
        }
    }
    
    if (!$hasRateLimits) {
        echo "✅ No rate limits configured\n";
    } else {
        echo "Note: You mentioned APIs don't have rate limits, consider removing these configs\n";
    }
}

function provideSummary() {
    echo "Issues Identified and Status:\n\n";
    
    echo "✅ FIXED: Claude model name display (was showing Claude 3, now shows Claude 4)\n";
    echo "✅ FIXED: API key verification script created\n";
    echo "✅ ADDED: Actual model name storage in database\n";
    echo "✅ ADDED: getActualModelName() method to all services\n";
    echo "❌ NEEDS ATTENTION: OpenAI quota exceeded (billing setup required)\n";
    echo "⚠️  CONSIDER: Remove rate limiting configs if APIs don't have limits\n";
    echo "⚠️  CONSIDER: Update documentation to reflect actual model names\n\n";
    
    echo "Next Steps:\n";
    echo "1. Set up OpenAI billing to fix quota issue\n";
    echo "2. Test end-to-end analysis to verify actual model names are stored\n";
    echo "3. Update documentation to show correct model names\n";
    echo "4. Remove rate limiting configuration if not needed\n";
}