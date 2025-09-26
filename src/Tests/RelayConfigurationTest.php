<?php

namespace Nostrbots\Tests;

use Nostrbots\Utils\DirectDocumentPublisher;

/**
 * Test relay configuration priority and functionality
 */
class RelayConfigurationTest
{
    private DirectDocumentPublisher $publisher;

    public function __construct()
    {
        $this->publisher = new DirectDocumentPublisher();
    }

    /**
     * Run all relay configuration tests
     */
    public function runTests(): void
    {
        echo "🧪 Running Relay Configuration Tests" . PHP_EOL . PHP_EOL;
        
        $this->testDocumentMetadataRelays();
        $this->testRelayCategory();
        $this->testDefaultFallback();
        $this->testInvalidRelayCategory();
        $this->testRelayUrlsInContentReferences();
        
        echo "✓ All relay configuration tests completed!" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test: Document metadata relays (highest priority)
     */
    private function testDocumentMetadataRelays(): void
    {
        echo "📝 Test: Document Metadata Relays (Highest Priority)" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/relay-urls-test.adoc';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "Document metadata relays should succeed");
        
        // Check that the structure contains the correct relay configuration
        $this->assertTrue(isset($result['structure']['relays']), "Result should contain relay configuration");
        $this->assertStringContains('wss://relay1.example.com', $result['structure']['relays'], "Should use document-specified relay URLs");
        $this->assertStringContains('wss://relay2.example.com', $result['structure']['relays'], "Should use document-specified relay URLs");
        
        echo "  ✓ Document metadata relays: {$result['structure']['relays']}" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test: Relay category from relays.yml (medium priority)
     */
    private function testRelayCategory(): void
    {
        echo "📝 Test: Relay Category from relays.yml (Medium Priority)" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/relay-category-test.adoc';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "Relay category should succeed");
        
        // Check that the structure contains the correct relay configuration
        $this->assertTrue(isset($result['structure']['relays']), "Result should contain relay configuration");
        $this->assertStringContains('wss://', $result['structure']['relays'], "Should use relays from category");
        
        echo "  ✓ Relay category: {$result['structure']['relays']}" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test: Default fallback (lowest priority)
     */
    private function testDefaultFallback(): void
    {
        echo "📝 Test: Default Fallback (Lowest Priority)" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/default-test.adoc';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "Default fallback should succeed");
        
        // Check that the structure contains the default relay configuration
        $this->assertTrue(isset($result['structure']['relays']), "Result should contain relay configuration");
        $this->assertStringContains('wss://', $result['structure']['relays'], "Should use default relay configuration (resolved to actual URLs)");
        
        echo "  ✓ Default fallback: {$result['structure']['relays']}" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test: Invalid relay category should fall back to defaults
     */
    private function testInvalidRelayCategory(): void
    {
        echo "📝 Test: Invalid Relay Category (Should Fall Back to Defaults)" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/invalid-relay-test.adoc';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "Invalid relay category should succeed with fallback");
        
        // Check that the structure contains the default relay configuration (fallback)
        $this->assertTrue(isset($result['structure']['relays']), "Result should contain relay configuration");
        $this->assertStringContains('wss://', $result['structure']['relays'], "Should fall back to default relay configuration");
        
        echo "  ✓ Invalid relay category fell back to defaults: {$result['structure']['relays']}" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test: Relay URLs should be included in content references for index events
     */
    private function testRelayUrlsInContentReferences(): void
    {
        echo "📝 Test: Relay URLs in Content References" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/relay-urls-test.adoc';
        $result = $this->publisher->publishDocument($testDocument, 2, null, true); // Use content-level 2 to create indexes

        $this->assertTrue($result['success'], "Relay URLs in content references should succeed");
        
        // Check that index sections have content_references with relay information
        if (isset($result['structure']['index_sections'])) {
            foreach ($result['structure']['index_sections'] as $indexSection) {
                if (isset($indexSection['content_references'])) {
                    foreach ($indexSection['content_references'] as $reference) {
                        if (isset($reference['relay'])) {
                            $this->assertStringContains('wss://relay1.example.com', $reference['relay'], "Content reference should contain relay URL");
                            $this->assertStringContains('wss://relay2.example.com', $reference['relay'], "Content reference should contain backup relay URL");
                        }
                    }
                }
            }
        }
        
        echo "  ✓ Relay URLs included in content references" . PHP_EOL . PHP_EOL;
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
     * Assert that a condition is false
     */
    private function assertFalse(bool $condition, string $message): void
    {
        if ($condition) {
            throw new \AssertionError("Assertion failed: {$message}");
        }
    }

    /**
     * Assert that a string contains a substring
     */
    private function assertStringContains(string $needle, string $haystack, string $message): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new \AssertionError("Assertion failed: {$message}. Expected '{$haystack}' to contain '{$needle}'");
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
                throw new \AssertionError("Assertion failed: {$message}. Expected " . json_encode($expected) . ", got " . json_encode($actual));
            }
        } else {
            // Convert both to strings for comparison to handle type mismatches
            $expectedStr = (string)$expected;
            $actualStr = (string)$actual;
            
            if ($expectedStr !== $actualStr) {
                throw new \AssertionError("Assertion failed: {$message}. Expected '{$expected}', got '{$actual}'");
            }
        }
    }
}
