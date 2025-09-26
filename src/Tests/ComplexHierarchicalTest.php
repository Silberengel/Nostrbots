<?php

namespace Nostrbots\Tests;

use Nostrbots\Utils\DocumentParser;
use Nostrbots\Utils\DirectDocumentPublisher;
use Nostrbots\Utils\KeyManager;

/**
 * Test Complex Hierarchical Document Logic
 * 
 * Verifies that content levels work correctly for complex hierarchical structures
 * with deep nesting (up to 5 levels: =, ==, ===, ====, =====)
 */
class ComplexHierarchicalTest
{
    private DocumentParser $parser;
    private DirectDocumentPublisher $publisher;
    private string $testDocument;
    private string $testKey;
    private string $originalKey;

    public function __construct()
    {
        $this->parser = new DocumentParser();
        $this->publisher = new DirectDocumentPublisher();
        $this->testDocument = __DIR__ . '/../../examples/complex-hierarchical-guide.adoc';
        
        // Generate a test key and set it as environment variable
        $this->setupTestKey();
    }

    /**
     * Setup test key for testing
     */
    private function setupTestKey(): void
    {
        // Store original key if it exists
        $this->originalKey = getenv('NOSTR_BOT_KEY') ?: '';
        
        // Generate a new test key
        $keyManager = new KeyManager();
        $keySet = $keyManager->generateNewKeySet();
        $this->testKey = $keySet['hexPrivateKey'];
        
        // Set the test key as environment variable
        putenv("NOSTR_BOT_KEY={$this->testKey}");
        
        echo "Generated test key: " . substr($this->testKey, 0, 8) . "..." . PHP_EOL;
    }

    /**
     * Cleanup test key
     */
    public function cleanupTestKey(): void
    {
        // Restore original key or unset if it wasn't set
        if ($this->originalKey) {
            putenv("NOSTR_BOT_KEY={$this->originalKey}");
        } else {
            putenv('NOSTR_BOT_KEY');
        }
    }
    
    public function runTests(): void
    {
        echo "🧪 Complex Hierarchical Document Tests" . PHP_EOL;
        echo "======================================" . PHP_EOL . PHP_EOL;

        try {
            $this->testContentLevel0();
            $this->testContentLevel1();
            $this->testContentLevel2();
            $this->testContentLevel3();
            $this->testContentLevel4();
            $this->testContentLevel5();

            echo "✓ All complex hierarchical tests completed successfully!" . PHP_EOL;
        } finally {
            // Always cleanup the test key
            $this->cleanupTestKey();
        }
    }
    
    /**
     * Test content level 0 (flat article)
     */
    private function testContentLevel0(): void
    {
        echo "📝 Test: Content Level 0 (Flat Article)" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, 0, '30041', true);

        $this->assertTrue($result['success'], "Content level 0 should succeed");
        $this->assertEquals(1, $result['content_sections'], "Content level 0 should have 1 content section");
        $this->assertEquals(0, $result['index_sections'], "Content level 0 should have 0 index sections");
        $this->assertEquals(1, $result['total_events'], "Content level 0 should have 1 total event");
        
        echo "  ✓ Content level 0: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Test content level 1 (index + content)
     */
    private function testContentLevel1(): void
    {
        echo "📝 Test: Content Level 1 (Index + Content)" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, 1, '30041', true);

        $this->assertTrue($result['success'], "Content level 1 should succeed");
        $this->assertEquals(1, $result['content_sections'], "Content level 1 should have 1 content section");
        $this->assertEquals(1, $result['index_sections'], "Content level 1 should have 1 index section");
        $this->assertEquals(2, $result['total_events'], "Content level 1 should have 2 total events");
        
        echo "  ✓ Content level 1: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Test content level 2 (part indices)
     */
    private function testContentLevel2(): void
    {
        echo "📝 Test: Content Level 2 (Part Indices)" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, 2, '30041', true);

        $this->assertTrue($result['success'], "Content level 2 should succeed");
        // Should have 3 content sections: preamble + 2 parts (Part I, Part II)
        $this->assertEquals(3, $result['content_sections'], "Content level 2 should have 3 content sections");
        // Should have 3 index sections: main + 2 parts
        $this->assertEquals(3, $result['index_sections'], "Content level 2 should have 3 index sections");
        $this->assertEquals(6, $result['total_events'], "Content level 2 should have 6 total events");
        
        echo "  ✓ Content level 2: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test content level 3 (chapter indices)
     */
    private function testContentLevel3(): void
    {
        echo "📝 Test: Content Level 3 (Chapter Indices)" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, 3, '30041', true);

        $this->assertTrue($result['success'], "Content level 3 should succeed");
        // Should have 4 content sections: preamble + 3 chapters
        $this->assertEquals(4, $result['content_sections'], "Content level 3 should have 4 content sections");
        // Should have 6 index sections: main + 2 parts + 3 chapters
        $this->assertEquals(6, $result['index_sections'], "Content level 3 should have 6 index sections");
        $this->assertEquals(10, $result['total_events'], "Content level 3 should have 10 total events");
        
        echo "  ✓ Content level 3: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test content level 4 (section indices)
     */
    private function testContentLevel4(): void
    {
        echo "📝 Test: Content Level 4 (Section Indices)" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, 4, '30041', true);

        $this->assertTrue($result['success'], "Content level 4 should succeed");
        // Should have even more content sections for each section level
        $this->assertGreaterThan(5, $result['content_sections'], "Content level 4 should have more than 5 content sections");
        $this->assertGreaterThan(5, $result['index_sections'], "Content level 4 should have more than 5 index sections");
        
        echo "  ✓ Content level 4: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test content level 5 (subsection indices)
     */
    private function testContentLevel5(): void
    {
        echo "📝 Test: Content Level 5 (Subsection Indices)" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, 5, '30041', true);

        $this->assertTrue($result['success'], "Content level 5 should succeed");
        // Should have the most content sections for each subsection level
        $this->assertGreaterThan(10, $result['content_sections'], "Content level 5 should have more than 10 content sections");
        $this->assertGreaterThan(10, $result['index_sections'], "Content level 5 should have more than 10 index sections");
        
        echo "  ✓ Content level 5: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }

    /**
     * Assert helper method
     */
    private function assertTrue($condition, string $message = ''): void
    {
        if (!$condition) {
            throw new \AssertionError($message ?: 'Assertion failed');
        }
    }

    /**
     * Assert helper method
     */
    private function assertEquals($expected, $actual, string $message = ''): void
    {
        // Handle arrays by converting to JSON for comparison
        if (is_array($expected) && is_array($actual)) {
            if (json_encode($expected) !== json_encode($actual)) {
                throw new \AssertionError($message ?: "Expected " . json_encode($expected) . ", got " . json_encode($actual));
            }
        } else {
            // Convert both to strings for comparison to handle type mismatches
            $expectedStr = (string)$expected;
            $actualStr = (string)$actual;
            
            if ($expectedStr !== $actualStr) {
                throw new \AssertionError($message ?: "Expected {$expected}, got {$actual}");
            }
        }
    }

    /**
     * Assert helper method
     */
    private function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            throw new \AssertionError($message ?: "Expected value greater than {$expected}, got {$actual}");
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    require_once __DIR__ . '/../bootstrap.php';
    
$test = new ComplexHierarchicalTest();
    
    try {
$test->runTests();
        
        echo "🎉 All complex hierarchical tests passed successfully!" . PHP_EOL;
        exit(0);
    } catch (\Exception $e) {
        echo "✗ Test failed: " . $e->getMessage() . PHP_EOL;
        exit(1);
    } finally {
        // Ensure cleanup happens even if tests fail
        $test->cleanupTestKey();
    }
}
