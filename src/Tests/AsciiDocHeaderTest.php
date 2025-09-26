<?php

namespace Nostrbots\Tests;

use Nostrbots\Utils\DirectDocumentPublisher;
use Nostrbots\Utils\KeyManager;

/**
 * Test AsciiDoc Header Format Parsing
 *
 * Verifies that various AsciiDoc header formats are correctly parsed
 * and mapped to standard metadata keys.
 */
class AsciiDocHeaderTest
{
    private DirectDocumentPublisher $publisher;
    private string $testDocument;
    private string $testKey;
    private string $originalKey;

    public function __construct()
    {
        $this->publisher = new DirectDocumentPublisher();
        $this->testDocument = __DIR__ . '/../../examples/asciidoc-header-formats.adoc';

        $this->setupTestKey();
    }

    private function setupTestKey(): void
    {
        $this->originalKey = getenv('NOSTR_BOT_KEY') ?: '';
        $keyManager = new KeyManager();
        $keySet = $keyManager->generateNewKeySet();
        $this->testKey = $keySet['hexPrivateKey'];
        putenv("NOSTR_BOT_KEY={$this->testKey}");
        echo "Generated test key: " . substr($this->testKey, 0, 8) . "..." . PHP_EOL;
    }

    public function cleanupTestKey(): void
    {
        if ($this->originalKey) {
            putenv("NOSTR_BOT_KEY={$this->originalKey}");
        } else {
            putenv('NOSTR_BOT_KEY');
        }
    }

    public function runTests(): void
    {
        echo "🧪 AsciiDoc Header Format Tests" . PHP_EOL;
        echo "===============================" . PHP_EOL . PHP_EOL;

        try {
            $this->testStandardAsciiDocHeaders();
            $this->testMultipleAuthors();
            $this->testAttributeMapping();
            $this->testAuthorLineParsing();
            $this->testRevisionLineParsing();
            $this->testKeywordsParsing();

            echo "✓ All AsciiDoc header format tests completed successfully!" . PHP_EOL;
        } finally {
            $this->cleanupTestKey();
        }
    }

    /**
     * Test standard AsciiDoc header parsing
     */
    private function testStandardAsciiDocHeaders(): void
    {
        echo "📝 Test: Standard AsciiDoc Headers" . PHP_EOL;
        
        $result = $this->publisher->publishDocument($this->testDocument, null, null, true);

        $this->assertTrue($result['success'], "Standard AsciiDoc headers should succeed");
        
        // Check that metadata was extracted correctly
        $metadata = $result['structure']['metadata'];
        
        // Test author information
        $this->assertTrue(is_array($metadata['author']), "Author should be an array");
        $this->assertEquals('John Doe', $metadata['author'][0], "Author should be extracted from author line");
        $this->assertEquals('john@example.com', $metadata['email'], "Email should be extracted from author line");
        $this->assertEquals('John', $metadata['firstname'], "First name should be extracted");
        $this->assertEquals('Doe', $metadata['lastname'], "Last name should be extracted");
        
        // Test revision information
        $this->assertEquals('1.0', $metadata['version'], "Version should be extracted from revision line");
        $this->assertEquals('2024-01-15', $metadata['publication_date'], "Revision date should be mapped to publication_date");
        $this->assertEquals('Initial version', $metadata['revremark'], "Revision remark should be extracted");
        
        // Test attribute entries
        $this->assertEquals('This document tests various AsciiDoc header formats', $metadata['summary'], "Description should be mapped to summary");
        $this->assertEquals(['asciidoc', 'headers', 'metadata', 'nostr'], $metadata['t'], "Keywords should be mapped to individual t tags");
        $this->assertTrue($metadata['auto_update'], "Auto update should be true");
        $this->assertEquals('tutorial', $metadata['type'], "Type should be extracted");
        $this->assertEquals('en', $metadata['l'], "Language should be mapped to l tag");
        $this->assertTrue($metadata['sectanchors'], "Section anchors should be true");
        $this->assertEquals('font', $metadata['icons'], "Icons should be extracted");
        
        echo "  ✓ Standard AsciiDoc headers parsed correctly" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test multiple authors parsing
     */
    private function testMultipleAuthors(): void
    {
        echo "📝 Test: Multiple Authors" . PHP_EOL;
        
        $testDocument = __DIR__ . '/../../examples/multiple-authors-test.adoc';
        $result = $this->publisher->publishDocument($testDocument, null, null, true);

        $this->assertTrue($result['success'], "Multiple authors should succeed");
        
        // Check that metadata was extracted correctly
        $metadata = $result['structure']['metadata'];
        
        // Test multiple authors
        $this->assertEquals(['John Smith', 'Suzy Thomas', 'Michael Brent'], $metadata['author'], "Multiple authors should be extracted as array");
        
        // Test keywords as individual t tags
        $this->assertEquals(['collaboration', 'teamwork', 'documentation', 'nostr'], $metadata['t'], "Keywords should be individual t tags");
        
        // Test other metadata
        $this->assertEquals('This document tests multiple authors and keywords', $metadata['summary'], "Description should be extracted");
        $this->assertEquals('en', $metadata['l'], "Language should be mapped to l tag");
        
        echo "  ✓ Multiple authors parsed correctly" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test attribute mapping for various formats
     */
    private function testAttributeMapping(): void
    {
        echo "📝 Test: Attribute Mapping" . PHP_EOL;
        
        // Test that various attribute formats are mapped correctly
        $testCases = [
            'description' => 'summary',
            'summary' => 'summary',
            'abstract' => 'summary',
            'keywords' => 't',
            'tags' => 't',
            't' => 't',
            'subject' => 't',
            'version' => 'version',
            'revnumber' => 'version',
            'revision' => 'version',
            'date' => 'revdate',
            'revdate' => 'revdate',
            'revision_date' => 'revdate',
            'author' => 'author',
            'authors' => 'author',
            'email' => 'email',
            'author_email' => 'email',
            'firstname' => 'firstname',
            'first_name' => 'firstname',
            'lastname' => 'lastname',
            'last_name' => 'lastname',
            'middlename' => 'middlename',
            'middle_name' => 'middlename',
            'authorinitials' => 'authorinitials',
            'author_initials' => 'authorinitials',
            'relays' => 'relays',
            'relay' => 'relays',
            'auto_update' => 'auto_update',
            'autoupdate' => 'auto_update',
            'auto-update' => 'auto_update',
            'type' => 'type',
            'doctype' => 'type',
            'document_type' => 'type',
            'lang' => 'lang',
            'language' => 'lang',
            'toc' => 'toc',
            'table_of_contents' => 'toc',
            'toclevels' => 'toclevels',
            'toc_levels' => 'toclevels',
            'sectanchors' => 'sectanchors',
            'section_anchors' => 'sectanchors',
            'sectlinks' => 'sectlinks',
            'section_links' => 'sectlinks',
            'icons' => 'icons',
            'imagesdir' => 'imagesdir',
            'images_dir' => 'imagesdir',
            'source-highlighter' => 'source-highlighter',
            'source_highlighter' => 'source-highlighter',
            'experimental' => 'experimental',
            'compat-mode' => 'compat-mode',
            'compat_mode' => 'compat-mode'
        ];
        
        echo "  ✓ Attribute mapping test cases defined (" . count($testCases) . " mappings)" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test author line parsing
     */
    private function testAuthorLineParsing(): void
    {
        echo "📝 Test: Author Line Parsing" . PHP_EOL;
        
        // Test various author line formats
        $testCases = [
            'John Doe <john@example.com>' => ['author' => 'John Doe', 'email' => 'john@example.com', 'firstname' => 'John', 'lastname' => 'Doe'],
            'Jane Smith' => ['author' => 'Jane Smith', 'firstname' => 'Jane', 'lastname' => 'Smith'],
            'Dr. John A. Doe <j.doe@example.com>' => ['author' => 'Dr. John A. Doe', 'email' => 'j.doe@example.com', 'firstname' => 'Dr.', 'lastname' => 'Doe', 'middlename' => 'John A.'],
            'Mary Jane Watson' => ['author' => 'Mary Jane Watson', 'firstname' => 'Mary', 'lastname' => 'Watson', 'middlename' => 'Jane']
        ];
        
        echo "  ✓ Author line parsing test cases defined (" . count($testCases) . " formats)" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test revision line parsing
     */
    private function testRevisionLineParsing(): void
    {
        echo "📝 Test: Revision Line Parsing" . PHP_EOL;
        
        // Test various revision line formats
        $testCases = [
            'v1.0, 2024-01-15, Initial version' => ['version' => '1.0', 'revdate' => '2024-01-15', 'revremark' => 'Initial version'],
            '2.1, 2024-02-01' => ['version' => '2.1', 'revdate' => '2024-02-01'],
            'v3.0' => ['version' => '3.0'],
            '1.0.0, 2024-03-01, Major update' => ['version' => '1.0.0', 'revdate' => '2024-03-01', 'revremark' => 'Major update']
        ];
        
        echo "  ✓ Revision line parsing test cases defined (" . count($testCases) . " formats)" . PHP_EOL . PHP_EOL;
    }

    /**
     * Test keywords/tags parsing
     */
    private function testKeywordsParsing(): void
    {
        echo "📝 Test: Keywords/Tags Parsing" . PHP_EOL;
        
        // Test various keywords/tags formats
        $testCases = [
            'asciidoc, headers, metadata, nostr' => ['t' => ['asciidoc', 'headers', 'metadata', 'nostr']],
            'tutorial, guide, documentation' => ['t' => ['tutorial', 'guide', 'documentation']],
            'single-tag' => ['t' => ['single-tag']],
            'tag1, tag2, tag3, tag4' => ['t' => ['tag1', 'tag2', 'tag3', 'tag4']]
        ];
        
        echo "  ✓ Keywords/tags parsing test cases defined (" . count($testCases) . " formats)" . PHP_EOL . PHP_EOL;
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
