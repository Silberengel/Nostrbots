<?php

/**
 * Core Functionality Test
 * 
 * Tests the main systems without complex edge cases
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\RetryManager;
use Nostrbots\Utils\ErrorHandler;
use Nostrbots\Utils\PerformanceManager;
use Nostrbots\Utils\RelayManager;

function testRetryManager(): void
{
    echo "🔄 Testing RetryManager..." . PHP_EOL;
    
    $retryManager = RetryManager::forRelays();
    
    $attemptCount = 0;
    $maxAttempts = 2;
    
    try {
        $result = $retryManager->execute(function() use (&$attemptCount, $maxAttempts) {
            $attemptCount++;
            if ($attemptCount < $maxAttempts) {
                throw new \Exception("Simulated failure");
            }
            return "success";
        }, "Retry test");
        
        if ($result === "success" && $attemptCount === $maxAttempts) {
            echo "  ✅ Retry logic working correctly" . PHP_EOL;
        } else {
            echo "  ❌ Retry logic failed" . PHP_EOL;
        }
    } catch (\Exception $e) {
        echo "  ❌ Retry test failed: " . $e->getMessage() . PHP_EOL;
    }
}

function testErrorHandler(): void
{
    echo "🚨 Testing ErrorHandler..." . PHP_EOL;
    
    $errorHandler = new ErrorHandler(false);
    
    // Test adding errors
    $errorHandler->addError("Test error");
    $errorHandler->addWarning("Test warning");
    $errorHandler->addInfo("Test info");
    
    if ($errorHandler->hasErrors() && $errorHandler->hasWarnings()) {
        echo "  ✅ Error handling working correctly" . PHP_EOL;
    } else {
        echo "  ❌ Error handling failed" . PHP_EOL;
    }
    
    $summary = $errorHandler->getErrorSummary();
    if ($summary['error_count'] === 1 && $summary['warning_count'] === 1) {
        echo "  ✅ Error counting working correctly" . PHP_EOL;
    } else {
        echo "  ❌ Error counting failed" . PHP_EOL;
    }
}

function testPerformanceManager(): void
{
    echo "⚡ Testing PerformanceManager..." . PHP_EOL;
    
    $performanceManager = new PerformanceManager(true);
    
    $performanceManager->startTimer('test_timer');
    usleep(10000); // 10ms
    $duration = $performanceManager->endTimer('test_timer');
    
    if ($duration > 0.005 && $duration < 0.1) { // Should be around 10ms
        echo "  ✅ Performance timing working correctly" . PHP_EOL;
    } else {
        echo "  ❌ Performance timing failed: {$duration}s" . PHP_EOL;
    }
    
    $performanceManager->takeMemorySnapshot('test_snapshot');
    $report = $performanceManager->getPerformanceReport();
    
    if (isset($report['memory_snapshots']['test_snapshot'])) {
        echo "  ✅ Memory monitoring working correctly" . PHP_EOL;
    } else {
        echo "  ❌ Memory monitoring failed" . PHP_EOL;
    }
}

function testRelayManager(): void
{
    echo "📡 Testing RelayManager..." . PHP_EOL;
    
    try {
        $relayManager = new RelayManager();
        $relays = $relayManager->getRelays('write');
        
        if (is_array($relays) && count($relays) > 0) {
            echo "  ✅ Relay discovery working correctly" . PHP_EOL;
            echo "  📊 Found " . count($relays) . " relays" . PHP_EOL;
        } else {
            echo "  ⚠️  No relays found (may be expected)" . PHP_EOL;
        }
    } catch (\Exception $e) {
        echo "  ⚠️  Relay test failed: " . $e->getMessage() . PHP_EOL;
    }
}

function testDocumentParser(): void
{
    echo "📄 Testing DocumentParser..." . PHP_EOL;
    
    // Create a simple test document
    $testContent = "= Test Document\n\nThis is a test preamble.\n\n== Chapter 1\n\nContent here.";
    $tempFile = tempnam(sys_get_temp_dir(), 'nostrbots_test') . '.adoc';
    file_put_contents($tempFile, $testContent);
    
    try {
        $parser = new \Nostrbots\Utils\DocumentParser();
        $result = $parser->parseDocument($tempFile, 2, '30041', sys_get_temp_dir());
        
        if (is_array($result) && count($result) > 0) {
            echo "  ✅ Document parsing working correctly" . PHP_EOL;
            echo "  📊 Generated " . count($result) . " configurations" . PHP_EOL;
        } else {
            echo "  ❌ Document parsing failed" . PHP_EOL;
        }
    } catch (\Exception $e) {
        echo "  ❌ Document parsing test failed: " . $e->getMessage() . PHP_EOL;
    } finally {
        unlink($tempFile);
    }
}

function main(): void
{
    echo "🧪 Core Functionality Test" . PHP_EOL;
    echo "=========================" . PHP_EOL . PHP_EOL;
    
    $performanceManager = new PerformanceManager(true);
    $performanceManager->startTimer('total_test');
    
    testRetryManager();
    echo PHP_EOL;
    
    testErrorHandler();
    echo PHP_EOL;
    
    testPerformanceManager();
    echo PHP_EOL;
    
    testRelayManager();
    echo PHP_EOL;
    
    testDocumentParser();
    echo PHP_EOL;
    
    $performanceManager->endTimer('total_test');
    
    echo "📊 Test Performance Report:" . PHP_EOL;
    $performanceManager->printPerformanceReport();
    
    echo PHP_EOL . "✅ Core functionality tests completed!" . PHP_EOL;
}

// Run tests if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    main();
}
