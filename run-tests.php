<?php

/**
 * Test Runner for Nostrbots
 * 
 * Runs comprehensive tests for the direct publishing system
 */

require __DIR__ . '/src/bootstrap.php';

function runTests(): void
{
    echo "🧪 Nostrbots Test Suite" . PHP_EOL;
    echo "=======================" . PHP_EOL . PHP_EOL;
    
    try {
        // Run simplified edge case tests
        echo "🔬 Running Simplified Edge Case Tests..." . PHP_EOL;
        $simplifiedEdgeCaseTests = new \Nostrbots\Tests\SimplifiedEdgeCaseTests();
        $simplifiedEdgeCaseTests->runTests();
        echo PHP_EOL;
        
        // Run direct document publisher tests
        echo "📄 Running Direct Document Publisher Tests..." . PHP_EOL;
        $directDocumentPublisherTest = new \Nostrbots\Tests\DirectDocumentPublisherTest();
        $directDocumentPublisherTest->runTests();
        echo PHP_EOL;
        
        // Run content level tests
        echo "📊 Running Content Level Tests..." . PHP_EOL;
        $contentLevelTest = new \Nostrbots\Tests\ContentLevelTest();
        $contentLevelTest->runTests();
        
        // Run complex hierarchical tests
        echo "🏗️  Running Complex Hierarchical Tests..." . PHP_EOL;
        $complexHierarchicalTest = new \Nostrbots\Tests\ComplexHierarchicalTest();
        $complexHierarchicalTest->runTests();
        echo PHP_EOL;

        // Run event kind tests
        echo "🎭 Running Event Kind Tests..." . PHP_EOL;
        $eventKindTest = new \Nostrbots\Tests\EventKindTest();
        $eventKindTest->runTests();
        echo PHP_EOL;

        // Run header priority tests
        echo "📋 Running Header Priority Tests..." . PHP_EOL;
        $headerPriorityTest = new \Nostrbots\Tests\HeaderPriorityTest();
        $headerPriorityTest->runTests();
        echo PHP_EOL;

        // Run AsciiDoc header format tests
        echo "📄 Running AsciiDoc Header Format Tests..." . PHP_EOL;
        $asciidocHeaderTest = new \Nostrbots\Tests\AsciiDocHeaderTest();
        $asciidocHeaderTest->runTests();
        echo PHP_EOL;
        
        // Run relay configuration tests
        echo "Running Relay Configuration Tests..." . PHP_EOL;
        $relayConfigTest = new \Nostrbots\Tests\RelayConfigurationTest();
        $relayConfigTest->runTests();
        echo PHP_EOL;
        
        // Run performance tests
        echo "⚡ Running Performance Tests..." . PHP_EOL;
        runPerformanceTests();
        echo PHP_EOL;
        
        echo "✓ All tests completed successfully!" . PHP_EOL;
        
    } catch (\Exception $e) {
        echo "✗ Test suite failed: " . $e->getMessage() . PHP_EOL;
        exit(1);
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
    
    echo "    ✓ Parsed large document in {$duration}ms" . PHP_EOL;
    echo "    📊 Peak memory usage: " . formatBytes($memoryUsage) . PHP_EOL;
    echo "    📄 Generated " . count($result['publish_order']) . " events to publish" . PHP_EOL;
    
    // Test memory efficiency
    if ($memoryUsage > 50 * 1024 * 1024) { // 50MB
        echo "    ⚠  High memory usage detected" . PHP_EOL;
    } else {
        echo "    ✓ Memory usage within acceptable limits" . PHP_EOL;
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