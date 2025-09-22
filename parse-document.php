<?php

/**
 * Document Parser CLI - Convert structured documents to Nostr publications
 * 
 * Usage: php parse-document.php <document_file> <content_level> <content_kind> [output_dir]
 * 
 * Arguments:
 *   document_file   Path to .adoc or .md file to parse
 *   content_level   Header level that becomes content sections (1-6)
 *   content_kind    Type of content: 30023/longform, 30041/publication, 30818/wiki
 *   output_dir      Directory to save generated files (optional, defaults to ./parsed-output)
 * 
 * Examples:
 *   php parse-document.php bible.adoc 4 30041 ./bible-configs
 *   php parse-document.php guide.md 3 wiki ./guide-configs
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\DocumentParser;

function showHelp(): void
{
    echo "Document Parser - Convert structured documents to Nostr publications" . PHP_EOL;
    echo "=================================================================" . PHP_EOL . PHP_EOL;
    echo "Usage: php parse-document.php <document_file> <content_level> <content_kind> [output_dir]" . PHP_EOL . PHP_EOL;
    echo "Arguments:" . PHP_EOL;
    echo "  document_file   Path to .adoc or .md file to parse" . PHP_EOL;
    echo "  content_level   Header level that becomes content sections (1-6)" . PHP_EOL;
    echo "                  Example: 3 means === (AsciiDoc) or ### (Markdown) becomes content" . PHP_EOL;
    echo "  content_kind    Type of content to generate:" . PHP_EOL;
    echo "                  • 30023 or 'longform' - Long-form articles (NIP-23)" . PHP_EOL;
    echo "                  • 30041 or 'publication' - Publication sections" . PHP_EOL;
    echo "                  • 30818 or 'wiki' - Wiki articles (NIP-54)" . PHP_EOL;
    echo "  output_dir      Directory to save generated files (optional)" . PHP_EOL . PHP_EOL;
    echo "Header Level Examples:" . PHP_EOL;
    echo "  Level 1: = Title (AsciiDoc) or # Title (Markdown)" . PHP_EOL;
    echo "  Level 2: == Chapter (AsciiDoc) or ## Chapter (Markdown)" . PHP_EOL;
    echo "  Level 3: === Section (AsciiDoc) or ### Section (Markdown)" . PHP_EOL;
    echo "  Level 4: ==== Subsection (AsciiDoc) or #### Subsection (Markdown)" . PHP_EOL . PHP_EOL;
    echo "Bible Example (4-level hierarchy):" . PHP_EOL;
    echo "  = Bible (Level 1 - Main index)" . PHP_EOL;
    echo "  == Genesis (Level 2 - Book index)" . PHP_EOL;
    echo "  === Chapter 1 (Level 3 - Chapter index)" . PHP_EOL;
    echo "  ==== Verse 1 (Level 4 - Content sections) ← content_level=4" . PHP_EOL . PHP_EOL;
    echo "Examples:" . PHP_EOL;
    echo "  php parse-document.php bible.adoc 4 30041 ./bible-configs" . PHP_EOL;
    echo "  php parse-document.php technical-guide.md 3 longform ./guide-configs" . PHP_EOL;
    echo "  php parse-document.php wiki-content.adoc 2 wiki ./wiki-configs" . PHP_EOL . PHP_EOL;
}

function main(array $argv): int
{
    $argc = count($argv);

    // Check for help flag
    if ($argc < 2 || in_array('--help', $argv) || in_array('-h', $argv)) {
        showHelp();
        return 0;
    }

    // Validate arguments
    if ($argc < 4) {
        echo "❌ Error: Missing required arguments" . PHP_EOL;
        echo "Usage: php parse-document.php <document_file> <content_level> <content_kind> [output_dir]" . PHP_EOL;
        echo "Run with --help for detailed usage information." . PHP_EOL;
        return 1;
    }

    $documentFile = $argv[1];
    $contentLevel = (int)$argv[2];
    $contentKind = $argv[3];
    $outputDir = $argv[4] ?? './parsed-output';

    echo "📚 Nostrbots Document Parser" . PHP_EOL;
    echo "============================" . PHP_EOL . PHP_EOL;

    // Validate inputs
    if (!file_exists($documentFile)) {
        echo "❌ Error: Document file not found: {$documentFile}" . PHP_EOL;
        return 1;
    }

    if ($contentLevel < 1 || $contentLevel > 6) {
        echo "❌ Error: Content level must be between 1 and 6, got: {$contentLevel}" . PHP_EOL;
        return 1;
    }

    $supportedKinds = ['30023', 'longform', '30041', 'publication', '30818', 'wiki'];
    if (!in_array(strtolower($contentKind), array_map('strtolower', $supportedKinds))) {
        echo "❌ Error: Unsupported content kind: {$contentKind}" . PHP_EOL;
        echo "Supported kinds: " . implode(', ', $supportedKinds) . PHP_EOL;
        return 1;
    }

    try {
        echo "📖 Parsing document: {$documentFile}" . PHP_EOL;
        echo "🎯 Content level: {$contentLevel}" . PHP_EOL;
        echo "📝 Content kind: {$contentKind}" . PHP_EOL;
        echo "📁 Output directory: {$outputDir}" . PHP_EOL . PHP_EOL;

        $parser = new DocumentParser();
        $results = $parser->parseDocument($documentFile, $contentLevel, $contentKind, $outputDir);

        echo "✅ Document parsed successfully!" . PHP_EOL . PHP_EOL;

        // Display results
        echo "📊 Parse Results:" . PHP_EOL;
        echo "=================" . PHP_EOL;
        echo "📚 Document title: {$results['document_title']}" . PHP_EOL;
        echo "🔗 Base slug: {$results['base_slug']}" . PHP_EOL;
        echo "📄 Generated files: " . count($results['generated_files']) . PHP_EOL . PHP_EOL;

        // Show structure
        echo "🏗️  Document Structure:" . PHP_EOL;
        echo "======================" . PHP_EOL;
        
        $structure = $results['structure'];
        if ($structure['has_preamble']) {
            echo "📄 Preamble (content)" . PHP_EOL;
        }
        
        foreach ($structure['sections'] as $section) {
            $indent = str_repeat('  ', $section['level'] - 1);
            $icon = $section['type'] === 'content' ? '📝' : '📁';
            $typeLabel = $section['type'] === 'content' ? 'content' : 'index';
            echo "{$indent}{$icon} {$section['title']} (level {$section['level']}, {$typeLabel})" . PHP_EOL;
        }

        echo PHP_EOL . "📁 Generated Files:" . PHP_EOL;
        echo "==================" . PHP_EOL;
        foreach ($results['generated_files'] as $file) {
            $filename = basename($file);
            echo "  📄 {$filename}" . PHP_EOL;
        }

        echo PHP_EOL . "🚀 Next Steps:" . PHP_EOL;
        echo "=============" . PHP_EOL;
        echo "1. Review the generated configuration files in: {$outputDir}" . PHP_EOL;
        echo "2. Adjust any configuration settings as needed" . PHP_EOL;
        echo "3. Test with dry-run: php nostrbots.php <bot_folder> --dry-run" . PHP_EOL;
        echo "4. Publish your content: php nostrbots.php <bot_folder>" . PHP_EOL . PHP_EOL;

        echo "💡 Tip: Start by publishing the main index, then work through sections in order." . PHP_EOL;

        return 0;

    } catch (\Exception $e) {
        echo "💥 Error: " . $e->getMessage() . PHP_EOL;
        return 1;
    }
}

// Run the application
exit(main($argv));
