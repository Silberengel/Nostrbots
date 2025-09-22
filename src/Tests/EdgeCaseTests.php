<?php

namespace Nostrbots\Tests;

use Nostrbots\Bot\NostrBot;
use Nostrbots\Utils\DocumentParser;
use Nostrbots\Utils\RetryManager;
use Nostrbots\Utils\ValidationManager;
use Nostrbots\Utils\RelayManager;

/**
 * Comprehensive test suite for edge cases and error scenarios
 */
class EdgeCaseTests
{
    private NostrBot $bot;
    private DocumentParser $parser;
    private RetryManager $retryManager;
    private ValidationManager $validationManager;
    private RelayManager $relayManager;

    public function __construct()
    {
        $this->bot = new NostrBot();
        $this->parser = new DocumentParser();
        $this->relayManager = new RelayManager();
        $this->validationManager = new ValidationManager($this->relayManager);
        $this->retryManager = RetryManager::forRelays();
    }

    /**
     * Run all edge case tests
     */
    public function runAllTests(): void
    {
        echo "🧪 Running Edge Case Tests..." . PHP_EOL . PHP_EOL;

        $tests = [
            'testEmptyDocument' => 'Empty document handling',
            'testSingleLineDocument' => 'Single line document',
            'testNoHeadersDocument' => 'Document with no headers',
            'testOnlyPreambleDocument' => 'Document with only preamble',
            'testVeryLongTitles' => 'Very long titles and d-tags',
            'testSpecialCharacters' => 'Special characters in titles',
            'testUnicodeCharacters' => 'Unicode characters in content',
            'testMalformedHeaders' => 'Malformed header formats',
            'testDeepNesting' => 'Very deep header nesting (7+ levels)',
            'testEmptyContentSections' => 'Empty content sections',
            'testDuplicateHeaders' => 'Duplicate header titles',
            'testRetryLogic' => 'Retry logic with simulated failures',
            'testValidationEdgeCases' => 'Validation edge cases'
        ];

        $passed = 0;
        $failed = 0;

        foreach ($tests as $testMethod => $description) {
            echo "🔬 Testing: {$description}" . PHP_EOL;
            try {
                $this->$testMethod();
                echo "✅ PASSED" . PHP_EOL;
                $passed++;
            } catch (\Exception $e) {
                echo "❌ FAILED: " . $e->getMessage() . PHP_EOL;
                $failed++;
            }
            echo PHP_EOL;
        }

        echo "📊 Test Results: {$passed} passed, {$failed} failed" . PHP_EOL;
    }

    /**
     * Test empty document handling
     */
    private function testEmptyDocument(): void
    {
        // Create a temporary empty file
        $tempFile = tempnam(sys_get_temp_dir(), 'nostrbots_test_empty');
        file_put_contents($tempFile, "");
        
        try {
            $result = $this->parser->parseDocument($tempFile, 3, '30041', sys_get_temp_dir());
            
            if (!empty($result)) {
                throw new \Exception("Empty document should return empty result");
            }
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Test single line document
     */
    private function testSingleLineDocument(): void
    {
        $content = "This is just a single line with no headers.";
        $result = $this->parser->parseDocument($content, 3, 30041);
        
        if (empty($result)) {
            throw new \Exception("Single line document should be treated as preamble");
        }
        
        // Should have preamble content
        $hasPreamble = false;
        foreach ($result as $config) {
            if (isset($config['d-tag']) && str_contains($config['d-tag'], 'preamble')) {
                $hasPreamble = true;
                break;
            }
        }
        
        if (!$hasPreamble) {
            throw new \Exception("Single line document should create preamble content");
        }
    }

    /**
     * Test document with no headers
     */
    private function testNoHeadersDocument(): void
    {
        $content = "This is a document with no headers at all.\nJust plain text content.\nMultiple lines.";
        $result = $this->parser->parseDocument($content, 3, 30041);
        
        if (empty($result)) {
            throw new \Exception("Document with no headers should still create content");
        }
    }

    /**
     * Test document with only preamble
     */
    private function testOnlyPreambleDocument(): void
    {
        $content = "= Document Title\n\nThis is preamble content.\nNo other headers.";
        $result = $this->parser->parseDocument($content, 3, 30041);
        
        $hasPreamble = false;
        $hasMainIndex = false;
        
        foreach ($result as $config) {
            if (isset($config['d-tag']) && str_contains($config['d-tag'], 'preamble')) {
                $hasPreamble = true;
            }
            if (isset($config['event_kind']) && $config['event_kind'] === 30040) {
                $hasMainIndex = true;
            }
        }
        
        if (!$hasPreamble) {
            throw new \Exception("Document with only preamble should create preamble content");
        }
        
        if (!$hasMainIndex) {
            throw new \Exception("Document with only preamble should still create main index");
        }
    }

    /**
     * Test very long titles and d-tags
     */
    private function testVeryLongTitles(): void
    {
        $longTitle = str_repeat("Very Long Title With Many Words ", 10);
        $content = "= {$longTitle}\n\n== Chapter 1\n\nContent here.";
        
        $result = $this->parser->parseDocument($content, 2, 30041);
        
        foreach ($result as $config) {
            if (isset($config['d-tag'])) {
                if (strlen($config['d-tag']) > 70) {
                    throw new \Exception("D-tag too long: " . strlen($config['d-tag']) . " characters");
                }
            }
        }
    }

    /**
     * Test special characters in titles
     */
    private function testSpecialCharacters(): void
    {
        $content = "= Document with Special Characters: @#$%^&*()\n\n== Chapter with Symbols: !@#$%\n\nContent here.";
        $result = $this->parser->parseDocument($content, 2, 30041);
        
        foreach ($result as $config) {
            if (isset($config['d-tag'])) {
                // D-tags should not contain special characters
                if (preg_match('/[^a-z0-9\-]/', $config['d-tag'])) {
                    throw new \Exception("D-tag contains invalid characters: " . $config['d-tag']);
                }
            }
        }
    }

    /**
     * Test Unicode characters in content
     */
    private function testUnicodeCharacters(): void
    {
        $content = "= Document with Unicode: 中文 العربية русский\n\n== Chapter: 章节\n\nContent with émojis 🚀 and ñ characters.";
        $result = $this->parser->parseDocument($content, 2, 30041);
        
        if (empty($result)) {
            throw new \Exception("Unicode content should be handled gracefully");
        }
    }

    /**
     * Test malformed header formats
     */
    private function testMalformedHeaders(): void
    {
        $content = "= Valid Header\n\n== Valid Header\n\n=== Valid Header\n\n===== Invalid (skipped level)\n\nContent here.";
        $result = $this->parser->parseDocument($content, 3, 30041);
        
        // Should handle malformed headers gracefully
        if (empty($result)) {
            throw new \Exception("Malformed headers should be handled gracefully");
        }
    }

    /**
     * Test very deep header nesting
     */
    private function testDeepNesting(): void
    {
        $content = "= Level 1\n\n== Level 2\n\n=== Level 3\n\n==== Level 4\n\n===== Level 5\n\n====== Level 6\n\n======= Level 7 (should be content)\n\nContent here.";
        $result = $this->parser->parseDocument($content, 7, 30041);
        
        if (empty($result)) {
            throw new \Exception("Deep nesting should be handled");
        }
    }

    /**
     * Test empty content sections
     */
    private function testEmptyContentSections(): void
    {
        $content = "= Document\n\n== Chapter 1\n\n== Chapter 2\n\nContent here.";
        $result = $this->parser->parseDocument($content, 2, 30041);
        
        $hasEmptyContent = false;
        foreach ($result as $config) {
            if (isset($config['content_file'])) {
                $contentFile = $config['content_file'];
                if (file_exists($contentFile)) {
                    $content = file_get_contents($contentFile);
                    if (empty(trim($content))) {
                        $hasEmptyContent = true;
                    }
                }
            }
        }
        
        if ($hasEmptyContent) {
            throw new \Exception("Empty content sections should be handled gracefully");
        }
    }

    /**
     * Test duplicate header titles
     */
    private function testDuplicateHeaders(): void
    {
        $content = "= Document\n\n== Chapter 1\n\nContent 1.\n\n== Chapter 1\n\nContent 2.";
        $result = $this->parser->parseDocument($content, 2, 30041);
        
        $dTags = [];
        foreach ($result as $config) {
            if (isset($config['d-tag'])) {
                if (in_array($config['d-tag'], $dTags)) {
                    throw new \Exception("Duplicate d-tags found: " . $config['d-tag']);
                }
                $dTags[] = $config['d-tag'];
            }
        }
    }

    /**
     * Test retry logic with simulated failures
     */
    private function testRetryLogic(): void
    {
        $retryManager = RetryManager::forRelays();
        
        $attemptCount = 0;
        $maxAttempts = 3;
        
        try {
            $retryManager->execute(function() use (&$attemptCount, $maxAttempts) {
                $attemptCount++;
                if ($attemptCount < $maxAttempts) {
                    throw new \Exception("Simulated failure");
                }
                return "success";
            }, "Retry test");
            
            if ($attemptCount !== $maxAttempts) {
                throw new \Exception("Retry logic should have attempted {$maxAttempts} times, got {$attemptCount}");
            }
        } catch (\Exception $e) {
            if ($attemptCount !== $maxAttempts + 1) {
                throw new \Exception("Retry logic failed unexpectedly");
            }
        }
    }

    /**
     * Test validation edge cases
     */
    private function testValidationEdgeCases(): void
    {
        // Test with null/empty event
        try {
            $this->validationManager->validateEvent(null);
            throw new \Exception("Validation should handle null events gracefully");
        } catch (\TypeError $e) {
            // Expected - should handle gracefully
        }
        
        // Test with invalid event structure
        try {
            $invalidEvent = new \stdClass();
            $this->validationManager->validateEvent($invalidEvent);
            throw new \Exception("Validation should handle invalid events gracefully");
        } catch (\Exception $e) {
            // Expected - should handle gracefully
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    require_once __DIR__ . '/../src/bootstrap.php';
    
    $tester = new EdgeCaseTests();
    $tester->runAllTests();
}
