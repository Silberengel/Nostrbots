<?php

namespace Nostrbots\Tests;

use Nostrbots\Utils\DocumentParser;
use Nostrbots\Utils\DirectDocumentPublisher;
use Nostrbots\Utils\KeyManager;

/**
 * Test Content Level Logic
 * 
 * Verifies that content levels work correctly for different hierarchical structures
 */
class ContentLevelTest
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
        $this->testDocument = __DIR__ . '/../../examples/simple-guide.adoc';
        
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

    /**
     * Run all content level tests
     */
    public function runTests(): void
    {
        echo "🧪 Content Level Logic Tests" . PHP_EOL;
        echo "============================" . PHP_EOL . PHP_EOL;

        try {
            $this->testContentLevel0();
            $this->testContentLevel1();
            $this->testContentLevel2();
            $this->testContentLevel3();
            $this->testContentLevel4();

            echo "✅ All content level tests completed successfully!" . PHP_EOL;
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
        
        echo "  ✅ Content level 0: 1 content, 0 indexes, 1 total" . PHP_EOL . PHP_EOL;
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
        
        echo "  ✅ Content level 1: 1 content, 1 index, 2 total" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test content level 2 (chapter indices)
     */
    private function testContentLevel2(): void
    {
        echo "📝 Test: Content Level 2 (Chapter Indices)" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, 2, '30041', true);
        
        $this->assertTrue($result['success'], "Content level 2 should succeed");
        $this->assertEquals(3, $result['content_sections'], "Content level 2 should have 3 content sections");
        $this->assertEquals(3, $result['index_sections'], "Content level 2 should have 3 index sections");
        $this->assertEquals(6, $result['total_events'], "Content level 2 should have 6 total events");
        
        echo "  ✅ Content level 2: 3 content, 3 indexes, 6 total" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test content level 3 (section indices)
     */
    private function testContentLevel3(): void
    {
        echo "📝 Test: Content Level 3 (Section Indices)" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, 3, '30041', true);
        
        $this->assertTrue($result['success'], "Content level 3 should succeed");
        $this->assertEquals(7, $result['content_sections'], "Content level 3 should have 7 content sections");
        $this->assertEquals(7, $result['index_sections'], "Content level 3 should have 7 index sections");
        $this->assertEquals(14, $result['total_events'], "Content level 3 should have 14 total events");
        
        echo "  ✅ Content level 3: 7 content, 7 indexes, 14 total" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test content level 4 (all sections)
     */
    private function testContentLevel4(): void
    {
        echo "📝 Test: Content Level 4 (All Sections)" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, 4, '30041', true);
        
        $this->assertTrue($result['success'], "Content level 4 should succeed");
        $this->assertEquals(9, $result['content_sections'], "Content level 4 should have 9 content sections");
        $this->assertEquals(9, $result['index_sections'], "Content level 4 should have 9 index sections");
        $this->assertEquals(18, $result['total_events'], "Content level 4 should have 18 total events");
        
        echo "  ✅ Content level 4: 9 content, 9 indexes, 18 total" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test document structure parsing
     */
    public function testDocumentStructure(): void
    {
        echo "📝 Test: Document Structure Parsing" . PHP_EOL;
        
        $structure = $this->parser->parseDocumentForDirectPublishing($this->testDocument, 2, '30041');
        
        $this->assertEquals('Simple Nostr Guide', $structure['document_title'], "Document title should be correct");
        $this->assertEquals(2, $structure['content_level'], "Content level should be preserved");
        $this->assertEquals('30041', $structure['content_kind'], "Content kind should be preserved");
        
        // Verify we have the expected sections
        $this->assertGreaterThan(0, count($structure['content_sections']), "Should have content sections");
        $this->assertGreaterThan(0, count($structure['index_sections']), "Should have index sections");
        $this->assertNotNull($structure['main_index'], "Should have a main index");
        
        echo "  ✅ Document structure parsing works correctly" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test content event structure
     */
    public function testContentEventStructure(): void
    {
        echo "📝 Test: Content Event Structure" . PHP_EOL;
        
        $structure = $this->parser->parseDocumentForDirectPublishing($this->testDocument, 2, '30041');
        
        foreach ($structure['content_sections'] as $contentSection) {
            $this->assertArrayHasKey('title', $contentSection, "Content section should have title");
            $this->assertArrayHasKey('slug', $contentSection, "Content section should have slug");
            $this->assertArrayHasKey('content', $contentSection, "Content section should have content");
            $this->assertArrayHasKey('event_kind', $contentSection, "Content section should have event_kind");
            $this->assertArrayHasKey('d_tag', $contentSection, "Content section should have d_tag");
            
            $this->assertEquals('30041', $contentSection['event_kind'], "Content events should be kind 30041");
            // Preamble has different d_tag format, others should end with -content
            if (!str_ends_with($contentSection['d_tag'], '-preamble')) {
                $this->assertStringEndsWith('-content', $contentSection['d_tag'], "Content d_tag should end with -content");
            }
        }
        
        echo "  ✅ Content event structure is correct" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test index event structure
     */
    public function testIndexEventStructure(): void
    {
        echo "📝 Test: Index Event Structure" . PHP_EOL;
        
        $structure = $this->parser->parseDocumentForDirectPublishing($this->testDocument, 2, '30041');
        
        foreach ($structure['index_sections'] as $indexSection) {
            $this->assertArrayHasKey('title', $indexSection, "Index section should have title");
            $this->assertArrayHasKey('slug', $indexSection, "Index section should have slug");
            $this->assertArrayHasKey('event_kind', $indexSection, "Index section should have event_kind");
            $this->assertArrayHasKey('d_tag', $indexSection, "Index section should have d_tag");
            $this->assertArrayHasKey('content_references', $indexSection, "Index section should have content_references");
            
            $this->assertEquals(30040, $indexSection['event_kind'], "Index events should be kind 30040");
            $this->assertIsArray($indexSection['content_references'], "Content references should be an array");
        }
        
        // Test main index
        $this->assertEquals(30040, $structure['main_index']['event_kind'], "Main index should be kind 30040");
        $this->assertIsArray($structure['main_index']['content_references'], "Main index should have content references");
        
        echo "  ✅ Index event structure is correct" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test hierarchical references
     */
    public function testHierarchicalReferences(): void
    {
        echo "📝 Test: Hierarchical References" . PHP_EOL;
        
        $structure = $this->parser->parseDocumentForDirectPublishing($this->testDocument, 2, '30041');
        
        // Main index should reference preamble content and chapter indices
        $mainIndexRefs = $structure['main_index']['content_references'];
        $this->assertGreaterThan(0, count($mainIndexRefs), "Main index should have references");
        
        // Check that references have proper structure
        foreach ($mainIndexRefs as $ref) {
            $this->assertArrayHasKey('kind', $ref, "Reference should have kind");
            $this->assertArrayHasKey('d_tag', $ref, "Reference should have d_tag");
            $this->assertArrayHasKey('order', $ref, "Reference should have order");
            $this->assertContains($ref['kind'], [30040, 30041], "Reference kind should be valid");
        }
        
        echo "  ✅ Hierarchical references are correct" . PHP_EOL . PHP_EOL;
    }

    /**
     * Assert that a condition is true
     */
    private function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \AssertionError("Assertion failed: {$message}");
        }
    }

    /**
     * Assert that two values are equal
     */
    private function assertEquals($expected, $actual, string $message): void
    {
        // Handle arrays by converting to JSON for comparison
        if (is_array($expected) && is_array($actual)) {
            if (json_encode($expected) !== json_encode($actual)) {
                throw new \AssertionError("Assertion failed: {$message}. Expected: " . json_encode($expected) . ", Actual: " . json_encode($actual));
            }
        } else {
            // Convert both to strings for comparison to handle type mismatches
            $expectedStr = (string)$expected;
            $actualStr = (string)$actual;
            
            if ($expectedStr !== $actualStr) {
                throw new \AssertionError("Assertion failed: {$message}. Expected: {$expected}, Actual: {$actual}");
            }
        }
    }

    /**
     * Assert that an array has a key
     */
    private function assertArrayHasKey($key, array $array, string $message): void
    {
        if (!array_key_exists($key, $array)) {
            throw new \AssertionError("Assertion failed: {$message}. Key '{$key}' not found in array");
        }
    }

    /**
     * Assert that a value is an array
     */
    private function assertIsArray($value, string $message): void
    {
        if (!is_array($value)) {
            throw new \AssertionError("Assertion failed: {$message}. Expected array, got " . gettype($value));
        }
    }

    /**
     * Assert that a string ends with a suffix
     */
    private function assertStringEndsWith(string $suffix, string $string, string $message): void
    {
        if (!str_ends_with($string, $suffix)) {
            throw new \AssertionError("Assertion failed: {$message}. String '{$string}' does not end with '{$suffix}'");
        }
    }

    /**
     * Assert that a count is greater than a value
     */
    private function assertGreaterThan($expected, $actual, string $message): void
    {
        if ($actual <= $expected) {
            throw new \AssertionError("Assertion failed: {$message}. Expected greater than {$expected}, got {$actual}");
        }
    }

    /**
     * Assert that a value is not null
     */
    private function assertNotNull($value, string $message): void
    {
        if ($value === null) {
            throw new \AssertionError("Assertion failed: {$message}. Value is null");
        }
    }

    /**
     * Assert that a value is contained in an array
     */
    private function assertContains($needle, array $haystack, string $message): void
    {
        if (!in_array($needle, $haystack)) {
            throw new \AssertionError("Assertion failed: {$message}. Value '{$needle}' not found in array");
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    require_once __DIR__ . '/../bootstrap.php';
    
    $test = new ContentLevelTest();
    
    try {
        $test->runTests();
        $test->testDocumentStructure();
        $test->testContentEventStructure();
        $test->testIndexEventStructure();
        $test->testHierarchicalReferences();
        
        echo "🎉 All tests passed successfully!" . PHP_EOL;
        exit(0);
    } catch (\Exception $e) {
        echo "❌ Test failed: " . $e->getMessage() . PHP_EOL;
        exit(1);
    } finally {
        // Ensure cleanup happens even if tests fail
        $test->cleanupTestKey();
    }
}
