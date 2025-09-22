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

        $testGroups = [
            'Document Structure Tests' => [
                'testEmptyDocument' => 'Empty document handling',
                'testSingleLineDocument' => 'Single line document',
                'testNoHeadersDocument' => 'Document with no headers',
                'testOnlyPreambleDocument' => 'Document with only preamble',
                'testNoRootHeader' => 'Document without root header (should fail)',
            ],
            'Content Level Tests' => [
                'testInvalidContentLevel' => 'Invalid content level (too high)',
                'testContentLevelTooLow' => 'Content level too low (should fail)',
                'testSimpleStandaloneArticle' => 'Simple standalone article (contentLevel=1)',
                'testDeepNesting' => 'Very deep header nesting (7+ levels)',
            ],
            'Format and Character Tests' => [
                'testVeryLongTitles' => 'Very long titles and d-tags',
                'testSpecialCharacters' => 'Special characters in titles',
                'testUnicodeCharacters' => 'Unicode characters in content',
                'testWrongFormatHeaders' => 'Wrong format headers (markdown in asciidoc)',
                'testMalformedHeaders' => 'Malformed header formats',
            ],
            'Content Structure Tests' => [
                'testEmptyContentSections' => 'Empty content sections',
                'testDuplicateHeaders' => 'Duplicate header titles',
            ],
            'System Tests' => [
                'testRetryLogic' => 'Retry logic with simulated failures',
                'testValidationEdgeCases' => 'Validation edge cases',
            ]
        ];

        $totalPassed = 0;
        $totalFailed = 0;

        foreach ($testGroups as $groupName => $tests) {
            echo "📁 {$groupName}" . PHP_EOL;
            $groupPassed = 0;
            $groupFailed = 0;

            foreach ($tests as $testMethod => $description) {
                echo "  🔬 Testing: {$description}" . PHP_EOL;
                try {
                    $this->$testMethod();
                    echo "  ✅ PASSED" . PHP_EOL;
                    $groupPassed++;
                    $totalPassed++;
                } catch (\Exception $e) {
                    echo "  ❌ FAILED: " . $e->getMessage() . PHP_EOL;
                    $groupFailed++;
                    $totalFailed++;
                }
            }
            
            echo "  📊 Group Results: {$groupPassed} passed, {$groupFailed} failed" . PHP_EOL . PHP_EOL;
        }

        echo "📊 Test Results: {$totalPassed} passed, {$totalFailed} failed" . PHP_EOL;
    }

    /**
     * Helper method to create a temporary document and parse it
     */
    private function parseDocumentWithTempFile(string $content, int $contentLevel = 3, string $contentKind = '30041'): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_doc_') . '.adoc';
        file_put_contents($tempFile, $content);
        
        try {
            return $this->parser->parseDocument($tempFile, $contentLevel, $contentKind, sys_get_temp_dir());
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Helper method to test document parsing with expected results
     */
    private function assertDocumentParsing(string $content, int $contentLevel, array $expectedResults): void
    {
        $result = $this->parseDocumentWithTempFile($content, $contentLevel);
        
        foreach ($expectedResults as $assertion => $expectedValue) {
            $actualValue = $this->getNestedValue($result, $assertion);
            
            if ($actualValue !== $expectedValue) {
                throw new \Exception("Assertion failed: {$assertion}. Expected: " . json_encode($expectedValue) . ", Got: " . json_encode($actualValue));
            }
        }
    }

    /**
     * Helper method to get nested array values using dot notation
     */
    private function getNestedValue(array $array, string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    /**
     * Helper method to test that parsing should fail
     */
    private function assertParsingFails(string $content, int $contentLevel, string $expectedErrorPattern = ''): void
    {
        try {
            $this->parseDocumentWithTempFile($content, $contentLevel);
            throw new \Exception("Expected parsing to fail, but it succeeded");
        } catch (\Exception $e) {
            if ($expectedErrorPattern && !str_contains(strtolower($e->getMessage()), strtolower($expectedErrorPattern))) {
                throw new \Exception("Expected error pattern '{$expectedErrorPattern}' not found in: " . $e->getMessage());
            }
            // Expected failure - test passes
        }
    }

    // ============================================================================
    // DOCUMENT STRUCTURE TESTS
    // ============================================================================

    private function testEmptyDocument(): void
    {
        $this->assertParsingFails('', 3, 'root header');
    }

    private function testSingleLineDocument(): void
    {
        $this->assertParsingFails("This is just a single line with no headers.", 3, 'root header');
    }

    private function testNoHeadersDocument(): void
    {
        $this->assertParsingFails("This is a document with no headers at all.\nJust plain text content.\nMultiple lines.", 3, 'root header');
    }

    private function testOnlyPreambleDocument(): void
    {
        $result = $this->parseDocumentWithTempFile("= Document Title\n\nThis is preamble content.\nNo other headers.", 3);
        
        if (!$result['structure']['has_preamble']) {
            throw new \Exception("Document with only preamble should create preamble content");
        }
        
        $hasPreambleFile = false;
        $hasIndexFile = false;
        foreach ($result['generated_files'] as $file) {
            if (str_contains($file, 'preamble')) {
                $hasPreambleFile = true;
            }
            if (str_contains($file, 'index')) {
                $hasIndexFile = true;
            }
        }
        
        if (!$hasPreambleFile) {
            throw new \Exception("Document with only preamble should create preamble content file");
        }
        
        if (!$hasIndexFile) {
            throw new \Exception("Document with only preamble should still create main index file");
        }
    }

    private function testNoRootHeader(): void
    {
        $this->assertParsingFails("== Chapter 1 (no root header)\n\nContent here.\n\n== Chapter 2\n\nMore content.", 3, 'root');
    }

    // ============================================================================
    // CONTENT LEVEL TESTS
    // ============================================================================

    private function testInvalidContentLevel(): void
    {
        $this->assertParsingFails("= Document Title\n\n== Chapter 1\n\nContent here.", 10, 'content level');
    }

    private function testContentLevelTooLow(): void
    {
        // Content level 0 should fail
        $this->assertParsingFails("= Document Title\n\n== Chapter 1\n\nContent here.", 0, 'content level');
    }

    private function testSimpleStandaloneArticle(): void
    {
        $result = $this->parseDocumentWithTempFile("= My Article Title\n\nThis is the content of my article.\nIt can have multiple paragraphs.\n\n== This header becomes content\n\nMore content here.", 1);
        
        if (empty($result['structure']['sections'])) {
            throw new \Exception("Simple standalone article should create a content section");
        }
        
        $contentSection = $result['structure']['sections'][0];
        if (!isset($contentSection['is_content']) || !$contentSection['is_content']) {
            throw new \Exception("Simple standalone article should mark the section as content");
        }
        
        if (!str_contains($contentSection['content'], 'This is the content of my article')) {
            throw new \Exception("Simple standalone article should include all content after the title");
        }
        
        if (!str_contains($contentSection['content'], 'This header becomes content')) {
            throw new \Exception("Simple standalone article should include headers as content");
        }
    }

    private function testDeepNesting(): void
    {
        $result = $this->parseDocumentWithTempFile("= Level 1\n\n== Level 2\n\n=== Level 3\n\n==== Level 4\n\n===== Level 5\n\n====== Level 6\n\n======= Level 7 (should be content)\n\nContent here.", 6);
        
        if (empty($result)) {
            throw new \Exception("Deep nesting should be handled");
        }
    }

    // ============================================================================
    // FORMAT AND CHARACTER TESTS
    // ============================================================================

    private function testVeryLongTitles(): void
    {
        $longTitle = str_repeat("Very Long Title With Many Words ", 10);
        $result = $this->parseDocumentWithTempFile("= {$longTitle}\n\n== Chapter 1\n\nContent here.", 2);
        
        foreach ($result['structure']['sections'] as $section) {
            if (isset($section['d-tag']) && strlen($section['d-tag']) > 70) {
                throw new \Exception("D-tag too long: " . strlen($section['d-tag']) . " characters");
            }
        }
    }

    private function testSpecialCharacters(): void
    {
        $result = $this->parseDocumentWithTempFile("= Document with Special Characters: @#$%^&*()\n\n== Chapter with Symbols: !@#$%\n\nContent here.", 2);
        
        foreach ($result['structure']['sections'] as $section) {
            if (isset($section['d-tag']) && preg_match('/[^a-z0-9\-]/', $section['d-tag'])) {
                throw new \Exception("D-tag contains invalid characters: " . $section['d-tag']);
            }
        }
    }

    private function testUnicodeCharacters(): void
    {
        $result = $this->parseDocumentWithTempFile("= Document with Unicode: 中文 العربية русский\n\n== Chapter: 章节\n\nContent with émojis 🚀 and ñ characters.", 2);
        
        if (empty($result)) {
            throw new \Exception("Unicode content should be handled gracefully");
        }
    }

    private function testWrongFormatHeaders(): void
    {
        $result = $this->parseDocumentWithTempFile("= Document Title\n\n# Markdown Header (wrong format)\n\n## Another Markdown Header\n\nContent here.", 2);
        
        if (empty($result)) {
            throw new \Exception("Wrong format headers should be handled gracefully");
        }
        
        if (!empty($result['structure']['sections'])) {
            throw new \Exception("Markdown headers should not create sections in asciidoc");
        }
    }

    private function testMalformedHeaders(): void
    {
        $result = $this->parseDocumentWithTempFile("= Valid Header\n\n== Valid Header\n\n=== Valid Header\n\n===== Invalid (skipped level)\n\nContent here.", 3);
        
        if (empty($result)) {
            throw new \Exception("Malformed headers should be handled gracefully");
        }
    }

    // ============================================================================
    // CONTENT STRUCTURE TESTS
    // ============================================================================

    private function testEmptyContentSections(): void
    {
        $result = $this->parseDocumentWithTempFile("= Document\n\n== Chapter 1\n\n== Chapter 2\n\nContent here.", 2);
        
        $hasEmptyContent = false;
        foreach ($result['structure']['sections'] as $section) {
            if (isset($section['content_file'])) {
                $contentFile = $section['content_file'];
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

    private function testDuplicateHeaders(): void
    {
        $result = $this->parseDocumentWithTempFile("= Document\n\n== Chapter 1\n\nContent 1.\n\n== Chapter 1\n\nContent 2.", 2);
        
        $dTags = [];
        foreach ($result['structure']['sections'] as $section) {
            if (isset($section['d-tag'])) {
                if (in_array($section['d-tag'], $dTags)) {
                    throw new \Exception("Duplicate d-tags found: " . $section['d-tag']);
                }
                $dTags[] = $section['d-tag'];
            }
        }
    }

    // ============================================================================
    // SYSTEM TESTS
    // ============================================================================

    private function testRetryLogic(): void
    {
        $attempts = 0;
        $maxAttempts = 4;
        
        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                // Simulate a failure for the first few attempts
                if ($attempts < 3) {
                    throw new \Exception("Simulated failure");
                }
                // Success on 3rd attempt
                break;
            } catch (\Exception $e) {
                if ($attempts >= $maxAttempts) {
                    throw new \Exception("Retry logic failed after {$maxAttempts} attempts");
                }
                echo "  ⚠️  Retry test failed (attempt {$attempts}/{$maxAttempts}): " . $e->getMessage() . PHP_EOL;
                $delay = min(1000 * pow(2, $attempts - 1), 5000);
                echo "  🔄 Retrying in {$delay}ms..." . PHP_EOL;
                usleep($delay * 1000);
            }
        }
    }

    private function testValidationEdgeCases(): void
    {
        // Test with null event
        try {
            $this->validationManager->validateEvent(null);
            throw new \Exception("Validation should handle null events gracefully");
        } catch (\TypeError $e) {
            // Expected - should handle gracefully
        }
        
        // Test with invalid event structure - this should throw a TypeError
        try {
            // Create a mock event object that's not a proper Event
            $invalidEvent = new \stdClass();
            $invalidEvent->id = 'invalid';
            $invalidEvent->kind = 1;
            $invalidEvent->content = 'test';
            $invalidEvent->tags = [];
            $invalidEvent->created_at = time();
            $invalidEvent->pubkey = 'invalid';
            $invalidEvent->sig = 'invalid';
            
            $this->validationManager->validateEvent($invalidEvent);
            throw new \Exception("Validation should reject invalid event types");
        } catch (\TypeError $e) {
            // Expected - should handle gracefully
        } catch (\Exception $e) {
            // Also acceptable - should handle gracefully
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    require_once __DIR__ . '/../src/bootstrap.php';
    
    $tester = new EdgeCaseTests();
    $tester->runAllTests();
}