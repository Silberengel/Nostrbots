<?php

namespace Nostrbots\Tests;

use Nostrbots\Utils\DocumentParser;
use Nostrbots\Utils\DirectDocumentPublisher;
use Nostrbots\Utils\KeyManager;

/**
 * Test Different Event Kinds and Document Formats
 * 
 * Verifies that different event kinds (30023, 30040, 30041, 30818) work correctly
 * with different document formats and edge cases like no-preamble documents.
 */
class EventKindTest
{
    private DocumentParser $parser;
    private DirectDocumentPublisher $publisher;
    private string $testKey;
    private string $originalKey;

    public function __construct()
    {
        $this->parser = new DocumentParser();
        $this->publisher = new DirectDocumentPublisher();
        
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
        echo "🧪 Event Kind and Format Tests" . PHP_EOL;
        echo "==============================" . PHP_EOL . PHP_EOL;

        try {
            $this->testNoPreambleDocument();
            $this->testMarkdownLongform();
            $this->testAsciiDocWiki();
            $this->testAllEventKinds();

            echo "✓ All event kind tests completed successfully!" . PHP_EOL;
        } finally {
            // Always cleanup the test key
            $this->cleanupTestKey();
        }
    }
    
    /**
     * Test document without preamble
     */
    private function testNoPreambleDocument(): void
    {
        echo "📝 Test: No Preamble Document" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/no-preamble-guide.adoc';
        $result = $this->publisher->publishDocument($testDocument, 2, '30041', true);

        $this->assertTrue($result['success'], "No preamble document should succeed");
        // Should have 4 content sections: one for each == header
        $this->assertEquals(4, $result['content_sections'], "No preamble document should have 4 content sections");
        // Should have 5 index sections: 1 main + 4 == sections
        $this->assertEquals(5, $result['index_sections'], "No preamble document should have 5 index sections");
        $this->assertEquals(9, $result['total_events'], "No preamble document should have 9 total events");
        
        echo "  ✓ No preamble: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Test Markdown longform content (30023)
     */
    private function testMarkdownLongform(): void
    {
        echo "📝 Test: Markdown Longform (30023)" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/markdown-longform.md';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "Markdown longform should succeed");
        // Should have 1 content section (flat article)
        $this->assertEquals(1, $result['content_sections'], "Markdown longform should have 1 content section");
        // Should have 0 index sections (flat article)
        $this->assertEquals(0, $result['index_sections'], "Markdown longform should have 0 index sections");
        $this->assertEquals(1, $result['total_events'], "Markdown longform should have 1 total event");
        
        // Verify event kinds
        $this->verifyEventKinds($result, [], ['30023']); // No index events, content events should be 30023
        
        echo "  ✓ Markdown longform: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Test AsciiDoc wiki article (30818)
     */
    private function testAsciiDocWiki(): void
    {
        echo "📝 Test: AsciiDoc Wiki (30818)" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/asciidoc-wiki.adoc';
        $result = $this->publisher->publishDocument($testDocument, 0, '30818', true);

        $this->assertTrue($result['success'], "AsciiDoc wiki should succeed");
        // Should have 1 content section (flat article)
        $this->assertEquals(1, $result['content_sections'], "AsciiDoc wiki should have 1 content section");
        // Should have 0 index sections (flat article)
        $this->assertEquals(0, $result['index_sections'], "AsciiDoc wiki should have 0 index sections");
        $this->assertEquals(1, $result['total_events'], "AsciiDoc wiki should have 1 total event");
        
        // Verify event kinds
        $this->verifyEventKinds($result, [], ['30818']); // No index events, content events should be 30818
        
        echo "  ✓ AsciiDoc wiki: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Test all event kinds with the same document
     */
    private function testAllEventKinds(): void
    {
        echo "📝 Test: All Event Kinds" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/simple-guide.adoc';
        $eventKinds = ['30023', '30041', '30818'];
        
        foreach ($eventKinds as $eventKind) {
            $result = $this->publisher->publishDocument($testDocument, 2, $eventKind, true);
            
            $this->assertTrue($result['success'], "Event kind {$eventKind} should succeed");
            
            // All event kinds with content-level 2: hierarchical structure
            $this->assertEquals(3, $result['content_sections'], "Event kind {$eventKind} should have 3 content sections");
            $this->assertEquals(3, $result['index_sections'], "Event kind {$eventKind} should have 3 index sections");
            $this->assertEquals(6, $result['total_events'], "Event kind {$eventKind} should have 6 total events");
            
            // Verify event kinds
            $this->verifyEventKinds($result, ['30040', '30040', '30040'], [$eventKind, $eventKind, $eventKind]);
            
            echo "  ✓ Event kind {$eventKind}: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL;
        }
        
        echo PHP_EOL;
    }

    /**
     * Verify that event kinds match expected values
     */
    private function verifyEventKinds(array $result, array $expectedIndexKinds, array $expectedContentKinds): void
    {
        // Check index event kinds
        if (!empty($expectedIndexKinds)) {
            $this->assertTrue(isset($result['structure']['index_sections']), "Result should contain index_sections");
            $indexSections = $result['structure']['index_sections'];
            $this->assertEquals(count($expectedIndexKinds), count($indexSections), "Should have " . count($expectedIndexKinds) . " index sections");
            
            foreach ($indexSections as $index => $section) {
                $expectedKind = $expectedIndexKinds[$index] ?? '30040';
                $this->assertEquals($expectedKind, $section['event_kind'], "Index section {$index} should be kind {$expectedKind}, got {$section['event_kind']}");
            }
        }
        
        // Check content event kinds
        if (!empty($expectedContentKinds)) {
            $this->assertTrue(isset($result['structure']['content_sections']), "Result should contain content_sections");
            $contentSections = $result['structure']['content_sections'];
            $this->assertEquals(count($expectedContentKinds), count($contentSections), "Should have " . count($expectedContentKinds) . " content sections");
            
            foreach ($contentSections as $index => $section) {
                $expectedKind = $expectedContentKinds[$index] ?? '30041';
                $this->assertEquals($expectedKind, $section['event_kind'], "Content section {$index} should be kind {$expectedKind}, got {$section['event_kind']}");
            }
        }
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
}

// Run tests if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    require_once __DIR__ . '/../bootstrap.php';
    
    $test = new EventKindTest();
    
    try {
        $test->runTests();
        
        echo "🎉 All event kind tests passed successfully!" . PHP_EOL;
        exit(0);
    } catch (\Exception $e) {
        echo "✗ Test failed: " . $e->getMessage() . PHP_EOL;
        exit(1);
    } finally {
        // Ensure cleanup happens even if tests fail
        $test->cleanupTestKey();
    }
}
