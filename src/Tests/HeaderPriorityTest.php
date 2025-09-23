<?php

namespace Nostrbots\Tests;

use Nostrbots\Utils\DocumentParser;
use Nostrbots\Utils\DirectDocumentPublisher;
use Nostrbots\Utils\KeyManager;

/**
 * Test Header Priority System
 * 
 * Verifies that the priority system works correctly:
 * 1. Defaults (content-level 0, content-kind 30041 for AsciiDoc)
 * 2. File headers override defaults
 * 3. Command line overrides both file headers and defaults
 */
class HeaderPriorityTest
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
        
        echo "🔑 Generated test key: " . substr($this->testKey, 0, 8) . "..." . PHP_EOL;
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
        echo "🧪 Header Priority Tests" . PHP_EOL;
        echo "========================" . PHP_EOL . PHP_EOL;

        try {
            $this->testDefaults();
            $this->testFileHeaders();
            $this->testFileHeadersLevel2();
            $this->testCommandLineOverride();
            $this->testMarkdownDefaults();
            $this->testConstraints();

            echo "✅ All header priority tests completed successfully!" . PHP_EOL;
        } finally {
            // Always cleanup the test key
            $this->cleanupTestKey();
        }
    }
    
    /**
     * Test default behavior (no headers, no command line)
     */
    private function testDefaults(): void
    {
        echo "📝 Test: Default Behavior (AsciiDoc)" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/default-test.adoc';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "Default behavior should succeed");
        // Should use true defaults: content-level 0, content-kind 30041
        $this->assertEquals(1, $result['content_sections'], "Default should have 1 content section (flat article)");
        $this->assertEquals(0, $result['index_sections'], "Default should have 0 index sections (flat article)");
        $this->assertEquals(1, $result['total_events'], "Default should have 1 total event");
        
        // Verify event kinds
        $this->verifyEventKinds($result, [], ['30041']); // No index events, content events should be 30041
        
        echo "  ✅ Defaults: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Test file header overrides
     */
    private function testFileHeaders(): void
    {
        echo "📝 Test: File Header Overrides" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/header-priority-test.adoc';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "File header overrides should succeed");
        // Should use file headers: content-level 1, content-kind 30041
        $this->assertEquals(1, $result['content_sections'], "File headers should have 1 content section (content-level 1)");
        $this->assertEquals(1, $result['index_sections'], "File headers should have 1 index section (content-level 1)");
        $this->assertEquals(2, $result['total_events'], "File headers should have 2 total events");
        
        // Verify event kinds
        $this->verifyEventKinds($result, ['30040'], ['30041']); // 1 index event (30040), 1 content event (30041)
        
        echo "  ✅ File headers: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Test file header overrides with simple-guide.adoc (content-level 2)
     */
    private function testFileHeadersLevel2(): void
    {
        echo "📝 Test: File Header Overrides (Level 2)" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/simple-guide.adoc';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "File header overrides should succeed");
        // Should use file headers: content-level 2, content-kind 30041 (default)
        $this->assertEquals(3, $result['content_sections'], "File headers should have 3 content sections (content-level 2)");
        $this->assertEquals(3, $result['index_sections'], "File headers should have 3 index sections (content-level 2)");
        $this->assertEquals(6, $result['total_events'], "File headers should have 6 total events");
        
        // Verify event kinds
        $this->verifyEventKinds($result, ['30040', '30040', '30040'], ['30041', '30041', '30041']); // 3 index events (30040), 3 content events (30041)
        
        echo "  ✅ File headers (level 2): {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Test command line override
     */
    private function testCommandLineOverride(): void
    {
        echo "📝 Test: Command Line Override" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/header-priority-test.adoc';
        $result = $this->publisher->publishDocument($testDocument, 2, '30023', true);

        $this->assertTrue($result['success'], "Command line override should succeed");
        // Should use command line: content-level 2, content-kind 30023
        $this->assertEquals(4, $result['content_sections'], "Command line override should have 4 content sections (content-level 2)");
        $this->assertEquals(4, $result['index_sections'], "Command line override should have 4 index sections (content-level 2)");
        $this->assertEquals(8, $result['total_events'], "Command line override should have 8 total events");
        
        // Verify event kinds
        $this->verifyEventKinds($result, ['30040', '30040', '30040', '30040'], ['30023', '30023', '30023', '30023']); // 4 index events (30040), 4 content events (30023)
        
        echo "  ✅ Command line override: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Test Markdown defaults
     */
    private function testMarkdownDefaults(): void
    {
        echo "📝 Test: Markdown Defaults" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/markdown-longform.md';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "Markdown defaults should succeed");
        // Should use Markdown defaults: content-level 0, content-kind 30023
        $this->assertEquals(1, $result['content_sections'], "Markdown should have 1 content section (flat article)");
        $this->assertEquals(0, $result['index_sections'], "Markdown should have 0 index sections (flat article)");
        $this->assertEquals(1, $result['total_events'], "Markdown should have 1 total event");
        
        // Verify event kinds
        $this->verifyEventKinds($result, [], ['30023']); // No index events, content events should be 30023
        
        echo "  ✅ Markdown defaults: {$result['content_sections']} content, {$result['index_sections']} indexes, {$result['total_events']} total" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test constraint validation
     */
    private function testConstraints(): void
    {
        echo "📝 Test: Constraint Validation" . PHP_EOL;
        
        // Test Markdown with content-level (should fail)
        $markdownDocument = __DIR__ . '/../../examples/markdown-longform.md';
        $result = $this->publisher->publishDocument($markdownDocument, 1, null, true);
        $this->assertTrue(!$result['success'], "Markdown with content-level should fail");
        $this->assertTrue(in_array("Markdown files cannot have content-level parameters. They are always flat articles (content-level 0).", $result['errors']), "Should have correct error message");
        echo "  ✅ Markdown content-level constraint passed" . PHP_EOL;
        
        // Test Markdown with content-kind (should fail)
        $result = $this->publisher->publishDocument($markdownDocument, null, '30041', true);
        $this->assertTrue(!$result['success'], "Markdown with content-kind should fail");
        $this->assertTrue(in_array("Markdown files cannot have content-kind parameters. They always use 30023 (Long-form Content).", $result['errors']), "Should have correct error message");
        echo "  ✅ Markdown content-kind constraint passed" . PHP_EOL;
        
        // Test 30023 with content-level 0 (should fail)
        $asciidocDocument = __DIR__ . '/../../examples/simple-guide.adoc';
        $result = $this->publisher->publishDocument($asciidocDocument, 0, '30023', true);
        $this->assertTrue(!$result['success'], "30023 with content-level 0 should fail");
        $this->assertTrue(in_array("30023 (Long-form Content) requires content-level > 0. Use --content-level 1 or higher for hierarchical publications.", $result['errors']), "Should have correct error message");
        echo "  ✅ 30023 content-level constraint passed" . PHP_EOL;
        
        // Test 30023 with Markdown source (should fail)
        $result = $this->publisher->publishDocument($markdownDocument, 1, '30023', true);
        $this->assertTrue(!$result['success'], "30023 with Markdown source should fail");
        // Should fail on Markdown constraint first (content-level not allowed for Markdown)
        $this->assertTrue(in_array("Markdown files cannot have content-level parameters. They are always flat articles (content-level 0).", $result['errors']), "Should have correct error message");
        echo "  ✅ 30023 source format constraint passed" . PHP_EOL;
        
        // Test 30041 with Markdown source (should fail)
        $result = $this->publisher->publishDocument($markdownDocument, 1, '30041', true);
        $this->assertTrue(!$result['success'], "30041 with Markdown source should fail");
        // Should fail on Markdown constraint first (content-level not allowed for Markdown)
        $this->assertTrue(in_array("Markdown files cannot have content-level parameters. They are always flat articles (content-level 0).", $result['errors']), "Should have correct error message");
        echo "  ✅ 30041 source format constraint passed" . PHP_EOL;
        
        // Test 30818 with Markdown source (should fail)
        $result = $this->publisher->publishDocument($markdownDocument, 1, '30818', true);
        $this->assertTrue(!$result['success'], "30818 with Markdown source should fail");
        // Should fail on Markdown constraint first (content-level not allowed for Markdown)
        $this->assertTrue(in_array("Markdown files cannot have content-level parameters. They are always flat articles (content-level 0).", $result['errors']), "Should have correct error message");
        echo "  ✅ 30818 source format constraint passed" . PHP_EOL;
        
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
    
    $test = new HeaderPriorityTest();
    
    try {
        $test->runTests();
        
        echo "🎉 All header priority tests passed successfully!" . PHP_EOL;
        exit(0);
    } catch (\Exception $e) {
        echo "❌ Test failed: " . $e->getMessage() . PHP_EOL;
        exit(1);
    } finally {
        // Ensure cleanup happens even if tests fail
        $test->cleanupTestKey();
    }
}
