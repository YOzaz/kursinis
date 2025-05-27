<?php
/**
 * API Keys Verification Test Script (Final Version)
 */

// Simple version that works with Laravel
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel properly
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== API Keys Verification Test ===\n\n";

// Get all models configuration
$modelsConfig = config('llm.models');

if (!$modelsConfig) {
    echo "❌ Failed to load LLM configuration\n";
    exit(1);
}

echo "Found " . count($modelsConfig) . " configured models:\n\n";

foreach ($modelsConfig as $modelKey => $config) {
    echo "=== Testing $modelKey ===\n";
    echo "Model: " . $config['model'] . "\n";
    echo "API Key: " . (isset($config['api_key']) && $config['api_key'] ? 'Configured (' . substr($config['api_key'], 0, 15) . '...)' : 'Missing') . "\n";
    
    if (isset($config['api_key']) && $config['api_key']) {
        testModelAPI($modelKey, $config);
    }
    echo "\n";
}

// Test Laravel services
echo "=== Testing Laravel Services ===\n";
testLaravelServices();

echo "\n=== Issues Found ===\n";
checkIssues();

echo "\n=== Test Complete ===\n";

function testModelAPI($modelKey, $config) {
    switch ($modelKey) {
        case 'claude-4':
            testClaudeAPI($config);
            break;
        case 'gemini-2.5-pro':
            testGeminiAPI($config);
            break;
        case 'gpt-4.1':
            testOpenAIAPI($config);
            break;
    }
}

function testClaudeAPI($config) {
    $url = 'https://api.anthropic.com/v1/messages';
    $data = [
        'model' => $config['model'],
        'max_tokens' => 50,
        'messages' => [
            ['role' => 'user', 'content' => 'Respond with just "OK"']
        ]
    ];
    
    $result = makeRequest($url, $data, [
        'Content-Type: application/json',
        'x-api-key: ' . $config['api_key'],
        'anthropic-version: 2023-06-01'
    ]);
    
    if ($result['success']) {
        echo "✅ API Test: PASSED\n";
        $response = json_decode($result['response'], true);
        if (isset($response['content'][0]['text'])) {
            echo "✅ Response: " . trim($response['content'][0]['text']) . "\n";
        }
    } else {
        echo "❌ API Test: FAILED\n";
        echo "Error: " . $result['error'] . "\n";
    }
}

function testGeminiAPI($config) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$config['model']}:generateContent?key={$config['api_key']}";
    $data = [
        'contents' => [
            ['parts' => [['text' => 'Respond with just "OK"']]]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 50,
            'temperature' => 0.1
        ]
    ];
    
    $result = makeRequest($url, $data, ['Content-Type: application/json']);
    
    if ($result['success']) {
        echo "✅ API Test: PASSED\n";
        $response = json_decode($result['response'], true);
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            echo "✅ Response: " . trim($response['candidates'][0]['content']['parts'][0]['text']) . "\n";
        }
    } else {
        echo "❌ API Test: FAILED\n";
        echo "Error: " . $result['error'] . "\n";
    }
}

function testOpenAIAPI($config) {
    $url = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => $config['model'],
        'messages' => [
            ['role' => 'user', 'content' => 'Respond with just "OK"']
        ],
        'max_tokens' => 50,
        'temperature' => 0.1
    ];
    
    $result = makeRequest($url, $data, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key']
    ]);
    
    if ($result['success']) {
        echo "✅ API Test: PASSED\n";
        $response = json_decode($result['response'], true);
        if (isset($response['choices'][0]['message']['content'])) {
            echo "✅ Response: " . trim($response['choices'][0]['message']['content']) . "\n";
        }
    } else {
        echo "❌ API Test: FAILED\n";
        echo "Error: " . $result['error'] . "\n";
    }
}

function makeRequest($url, $data, $headers) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => "cURL Error: $error"];
    }
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP $httpCode: " . substr($response, 0, 200)];
    }
    
    return ['success' => true, 'response' => $response];
}

function testLaravelServices() {
    try {
        $claudeService = app(\App\Services\ClaudeService::class);
        echo "Claude Service: " . ($claudeService->isConfigured() ? "✅ OK" : "❌ NOT CONFIGURED") . " (Model: {$claudeService->getModelName()})\n";
        
        $geminiService = app(\App\Services\GeminiService::class);
        echo "Gemini Service: " . ($geminiService->isConfigured() ? "✅ OK" : "❌ NOT CONFIGURED") . " (Model: {$geminiService->getModelName()})\n";
        
        $openaiService = app(\App\Services\OpenAIService::class);
        echo "OpenAI Service: " . ($openaiService->isConfigured() ? "✅ OK" : "❌ NOT CONFIGURED") . " (Model: {$openaiService->getModelName()})\n";
        
    } catch (\Exception $e) {
        echo "❌ Error testing services: " . $e->getMessage() . "\n";
    }
}

function checkIssues() {
    $config = config('llm.models');
    
    // Check model name consistency
    echo "Model Name Issues:\n";
    
    if (isset($config['claude-4']['model'])) {
        $claudeModel = $config['claude-4']['model'];
        if (strpos($claudeModel, 'claude-3') !== false) {
            echo "⚠️  Claude config shows Claude 3 model but docs mention Claude 4\n";
        } elseif (strpos($claudeModel, 'claude-sonnet-4') !== false || strpos($claudeModel, 'claude-4') !== false) {
            echo "✅ Claude model appears to be Claude 4 (Sonnet)\n";
        }
        echo "   Configured: $claudeModel\n";
    }
    
    // Check rate limiting
    echo "\nRate Limiting Issues:\n";
    foreach ($config as $modelKey => $modelConfig) {
        if (isset($modelConfig['rate_limit'])) {
            echo "⚠️  Rate limit configured for $modelKey: {$modelConfig['rate_limit']} (but you noted APIs don't have rate limits)\n";
        }
    }
    
    echo "\nModel Storage Issues:\n";
    echo "⚠️  Analysis results store model key (e.g., 'claude-4') but should also store actual model name (e.g., 'claude-sonnet-4-20250514')\n";
}