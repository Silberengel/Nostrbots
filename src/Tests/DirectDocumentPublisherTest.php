<?php

namespace Nostrbots\Tests;

require_once __DIR__ . '/../bootstrap.php';

use Nostrbots\Utils\DirectDocumentPublisher;
use Nostrbots\Utils\DocumentParser;

/**
 * Test suite for DirectDocumentPublisher
 */
class DirectDocumentPublisherTest
{
    private DirectDocumentPublisher $publisher;
    private string $testDir;

    public function __construct()
    {
        $this->publisher = new DirectDocumentPublisher();
        $this->testDir = sys_get_temp_dir() . '/nostrbots_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    public function runTests(): void
    {
        echo "🧪 Running DirectDocumentPublisher Tests" . PHP_EOL;
        echo "=========================================" . PHP_EOL . PHP_EOL;

        $this->testDocumentWithMetadata();
        $this->testDocumentWithoutMetadata();
        $this->testInvalidDocument();
        $this->testDryRunMode();
        
        // Cleanup
        $this->cleanup();
        
        echo PHP_EOL . "✅ All DirectDocumentPublisher tests completed!" . PHP_EOL;
    }

    private function testDocumentWithMetadata(): void
    {
        echo "📝 Test: Document with metadata" . PHP_EOL;
        
        $content = '= Test Document with Metadata
author: Test Author
version: 1.0
relays: test-relays
auto_update: true
summary: Test document with metadata
type: test
content_level: 0

This is the preamble content.

== Chapter 1

Chapter 1 content here.

=== Section 1.1

Section 1.1 content.';

        $file = $this->testDir . '/test_metadata.adoc';
        file_put_contents($file, $content);

        try {
            $result = $this->publisher->publishDocument($file, 3, '30041', true); // dry run
            
            if ($result['success']) {
                echo "  ✅ Parsed successfully" . PHP_EOL;
                echo "  📋 Title: {$result['document_title']}" . PHP_EOL;
                echo "  📊 Content sections: {$result['content_sections']}" . PHP_EOL;
                echo "  📊 Index sections: {$result['index_sections']}" . PHP_EOL;
                echo "  📊 Total events: {$result['total_events']}" . PHP_EOL;
                
                // Check metadata extraction
                if (isset($result['metadata'])) {
                    echo "  📋 Metadata extracted:" . PHP_EOL;
                    foreach ($result['metadata'] as $key => $value) {
                        if (is_bool($value)) {
                            $displayValue = $value ? 'true' : 'false';
                        } elseif (is_array($value)) {
                            $displayValue = '[' . implode(', ', $value) . ']';
                        } else {
                            $displayValue = $value;
                        }
                        echo "    - {$key}: {$displayValue}" . PHP_EOL;
                    }
                }
            } else {
                echo "  ❌ Failed: " . implode(', ', $result['errors']) . PHP_EOL;
            }
        } catch (\Exception $e) {
            echo "  ❌ Exception: " . $e->getMessage() . PHP_EOL;
        }
        
        unlink($file);
        echo PHP_EOL;
    }

    private function testDocumentWithoutMetadata(): void
    {
        echo "📝 Test: Document without metadata (should use defaults)" . PHP_EOL;
        
        $content = '= Test Document Without Metadata

This is the preamble content.

== Chapter 1

Chapter 1 content here.

=== Section 1.1

Section 1.1 content.';

        $file = $this->testDir . '/test_no_metadata.adoc';
        file_put_contents($file, $content);

        try {
            $result = $this->publisher->publishDocument($file, 3, '30041', true); // dry run
            
            if ($result['success']) {
                echo "  ✅ Parsed successfully with defaults" . PHP_EOL;
                echo "  📋 Title: {$result['document_title']}" . PHP_EOL;
                
                // Check that defaults were applied
                if (isset($result['metadata'])) {
                    echo "  📋 Default metadata applied:" . PHP_EOL;
                    echo "    - relays: " . ($result['structure']['relays'] ?? 'default') . PHP_EOL;
                    echo "    - auto_update: " . ($result['metadata']['auto_update'] ? 'true' : 'false') . PHP_EOL;
                    echo "    - type: {$result['metadata']['type']}" . PHP_EOL;
                }
            } else {
                echo "  ❌ Failed: " . implode(', ', $result['errors']) . PHP_EOL;
            }
        } catch (\Exception $e) {
            echo "  ❌ Exception: " . $e->getMessage() . PHP_EOL;
        }
        
        unlink($file);
        echo PHP_EOL;
    }

    private function testInvalidDocument(): void
    {
        echo "📝 Test: Invalid document (multiple headers)" . PHP_EOL;
        
        $content = '= First Document Title

This is preamble content.

= Second Document Title

This should fail because there are two document headers.

== Chapter 1

Content here.';

        $file = $this->testDir . '/test_invalid.adoc';
        file_put_contents($file, $content);

        try {
            $result = $this->publisher->publishDocument($file, 3, '30041', true); // dry run
            
            if (!$result['success'] && !empty($result['errors'])) {
                echo "  ✅ Correctly caught invalid document: " . $result['errors'][0] . PHP_EOL;
            } else {
                echo "  ❌ Should have failed for multiple document headers" . PHP_EOL;
            }
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'multiple document headers') !== false) {
                echo "  ✅ Correctly caught multiple document headers: " . $e->getMessage() . PHP_EOL;
            } else {
                echo "  ❌ Wrong error: " . $e->getMessage() . PHP_EOL;
            }
        }
        
        unlink($file);
        echo PHP_EOL;
    }

    private function testDryRunMode(): void
    {
        echo "📝 Test: Dry run mode verification" . PHP_EOL;
        
        $content = '= Dry Run Test Document
author: Test Author
version: 1.0
relays: test-relays
auto_update: false
summary: Testing dry run mode
type: test

This is test content.

== Test Chapter

Test chapter content.';

        $file = $this->testDir . '/test_dry_run.adoc';
        file_put_contents($file, $content);

        try {
            $result = $this->publisher->publishDocument($file, 3, '30041', true); // dry run
            
            if ($result['success'] && isset($result['dry_run'])) {
                echo "  ✅ Dry run mode working correctly" . PHP_EOL;
                echo "  📊 Would publish: {$result['total_events']} events" . PHP_EOL;
                echo "  📊 Content sections: {$result['content_sections']}" . PHP_EOL;
                echo "  📊 Index sections: {$result['index_sections']}" . PHP_EOL;
            } else {
                echo "  ❌ Dry run mode not working correctly" . PHP_EOL;
            }
        } catch (\Exception $e) {
            echo "  ❌ Exception: " . $e->getMessage() . PHP_EOL;
        }
        
        unlink($file);
        echo PHP_EOL;
    }

    private function cleanup(): void
    {
        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new DirectDocumentPublisherTest();
    $test->runTests();
}
