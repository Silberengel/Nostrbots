<?php

/**
 * Test Runner for Nostrbots
 * 
 * Runs comprehensive tests including edge cases, performance tests, and integration tests
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Tests\EdgeCaseTests;
use Nostrbots\Utils\ErrorHandler;
use Nostrbots\Utils\PerformanceManager;

function runTests(): void
{
    echo "🧪 Nostrbots Test Suite" . PHP_EOL;
    echo "=======================" . PHP_EOL . PHP_EOL;
    
    $errorHandler = new ErrorHandler(true);
    $performanceManager = new PerformanceManager(true);
    
    try {
        $performanceManager->startTimer('test_suite');
        
        // Run edge case tests
        echo "🔬 Running Edge Case Tests..." . PHP_EOL;
        $performanceManager->startTimer('edge_case_tests');
        
        $edgeCaseTests = new EdgeCaseTests();
        $edgeCaseTests->runAllTests();
        
        $performanceManager->endTimer('edge_case_tests');
        
        // Run performance tests
        echo PHP_EOL . "⚡ Running Performance Tests..." . PHP_EOL;
        $performanceManager->startTimer('performance_tests');
        
        runPerformanceTests();
        
        $performanceManager->endTimer('performance_tests');
        
        // Run integration tests
        echo PHP_EOL . "🔗 Running Integration Tests..." . PHP_EOL;
        $performanceManager->startTimer('integration_tests');
        
        runIntegrationTests();
        
        $performanceManager->endTimer('integration_tests');
        
        $performanceManager->endTimer('test_suite');
        
        echo PHP_EOL . "📊 Test Suite Performance Report:" . PHP_EOL;
        $performanceManager->printPerformanceReport();
        
        echo PHP_EOL . "✅ All tests completed!" . PHP_EOL;
        
    } catch (\Exception $e) {
        $errorHandler->addError("Test suite failed: " . $e->getMessage());
        echo "❌ Test suite failed: " . $e->getMessage() . PHP_EOL;
        
        if ($errorHandler->hasErrors()) {
            $errorHandler->printErrorSummary();
        }
    }
}

function runPerformanceTests(): void
{
    echo "  📈 Testing document parsing performance..." . PHP_EOL;
    
    // Test with large document
    $largeContent = generateLargeDocument();
    
    $startTime = microtime(true);
    $parser = new \Nostrbots\Utils\DocumentParser();
    $tempFile = tempnam(sys_get_temp_dir(), 'perf_test_') . '.adoc';
    file_put_contents($tempFile, $largeContent);
    
    try {
        $result = $parser->parseDocumentForDirectPublishing($tempFile, 3, '30041');
    } finally {
        unlink($tempFile);
    }
    $endTime = microtime(true);
    
    $duration = round(($endTime - $startTime) * 1000, 2);
    $memoryUsage = memory_get_peak_usage(true);
    
    echo "    ✅ Parsed large document in {$duration}ms" . PHP_EOL;
    echo "    📊 Peak memory usage: " . formatBytes($memoryUsage) . PHP_EOL;
    echo "    📄 Generated " . count($result['publish_order']) . " events to publish" . PHP_EOL;
    
    // Test memory efficiency
    if ($memoryUsage > 50 * 1024 * 1024) { // 50MB
        echo "    ⚠️  High memory usage detected" . PHP_EOL;
    } else {
        echo "    ✅ Memory usage within acceptable limits" . PHP_EOL;
    }
}

function runIntegrationTests(): void
{
    echo "  🔗 Testing bot configuration loading..." . PHP_EOL;
    
    // Test configuration loading
    $configPath = __DIR__ . '/botData/longFormExample/config.yml';
    if (file_exists($configPath)) {
        try {
            $bot = new \Nostrbots\Bot\NostrBot();
            $bot->loadConfig($configPath);
            echo "    ✅ Configuration loaded successfully" . PHP_EOL;
        } catch (\Exception $e) {
            echo "    ❌ Configuration loading failed: " . $e->getMessage() . PHP_EOL;
        }
    } else {
        echo "    ⚠️  Test configuration not found" . PHP_EOL;
    }
    
    echo "  🔗 Testing relay connectivity..." . PHP_EOL;
    
    try {
        $relayManager = new \Nostrbots\Utils\RelayManager();
        $relays = $relayManager->getRelays('write');
        echo "    ✅ Found " . count($relays) . " working relays" . PHP_EOL;
    } catch (\Exception $e) {
        echo "    ⚠️  Relay connectivity test failed: " . $e->getMessage() . PHP_EOL;
    }
}

function generateLargeDocument(): string
{
    $content = "= Large Test Document\n\n";
    $content .= "This is a preamble for testing performance with large documents.\n\n";
    
    for ($i = 1; $i <= 50; $i++) {
        $content .= "== Chapter {$i}\n\n";
        $content .= "This is chapter {$i} content.\n\n";
        
        for ($j = 1; $j <= 10; $j++) {
            $content .= "=== Section {$i}.{$j}\n\n";
            $content .= "This is section {$i}.{$j} content with some additional text to make it more realistic.\n";
            $content .= "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\n\n";
            
            for ($k = 1; $k <= 5; $k++) {
                $content .= "==== Subsection {$i}.{$j}.{$k}\n\n";
                $content .= "This is subsection {$i}.{$j}.{$k} content.\n\n";
            }
        }
    }
    
    return $content;
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}

// Run tests if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    runTests();
}
