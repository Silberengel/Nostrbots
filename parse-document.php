<?php

/**
 * Document Parser CLI - Convert structured documents to Nostr events
 * 
 * Usage: php parse-document.php <document_file> <content_kind> [options]
 * 
 * Arguments:
 *   document_file   Path to .adoc or .md file to parse
 *   content_kind    Type of content: 30023/longform, 30041/publication, 30818/wiki
 * 
 * Options:
 *   --hierarchical, -h    Create hierarchical publication with indices (default: simple standalone article)
 *   --content-level N     Header level that becomes content sections (2-6, only for hierarchical)
 *   --output-dir DIR      Directory to save generated files (default: ./parsed-output)
 * 
 * Examples:
 *   # Simple standalone article (default)
 *   php parse-document.php article.adoc longform
 *   
 *   # Hierarchical publication (like a book)
 *   php parse-document.php bible.adoc 30041 --hierarchical --content-level 4
 *   php parse-document.php guide.md wiki -h --content-level 3
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\DocumentParser;

function showHelp(): void
{
    echo "Document Parser - Convert structured documents to Nostr events" . PHP_EOL;
    echo "=============================================================" . PHP_EOL . PHP_EOL;
    echo "Usage: php parse-document.php <document_file> <content_kind> [options]" . PHP_EOL . PHP_EOL;
    echo "Arguments:" . PHP_EOL;
    echo "  document_file   Path to .adoc or .md file to parse" . PHP_EOL;
    echo "  content_kind    Type of content to generate:" . PHP_EOL;
    echo "                  • 30023 or 'longform' - Long-form articles (NIP-23)" . PHP_EOL;
    echo "                  • 30041 or 'publication' - Publication sections" . PHP_EOL;
    echo "                  • 30818 or 'wiki' - Wiki articles (NIP-54)" . PHP_EOL . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "  --hierarchical, -h    Create hierarchical publication with indices" . PHP_EOL;
    echo "                        (default: simple standalone article)" . PHP_EOL;
    echo "  --content-level N     Header level that becomes content sections (2-6)" . PHP_EOL;
    echo "                        Only used with --hierarchical flag" . PHP_EOL;
    echo "  --output-dir DIR      Directory to save generated files" . PHP_EOL;
    echo "                        (default: ./parsed-output)" . PHP_EOL . PHP_EOL;
    echo "Modes:" . PHP_EOL;
    echo "  Simple Article (default):" . PHP_EOL;
    echo "    = Article Title" . PHP_EOL;
    echo "    " . PHP_EOL;
    echo "    This is the content. All text after the title becomes the article content." . PHP_EOL;
    echo "    Even headers like == Section become part of the content." . PHP_EOL . PHP_EOL;
    echo "  Hierarchical Publication (--hierarchical):" . PHP_EOL;
    echo "    = Bible (Level 1 - Main index)" . PHP_EOL;
    echo "    == Genesis (Level 2 - Book index)" . PHP_EOL;
    echo "    === Chapter 1 (Level 3 - Chapter index)" . PHP_EOL;
    echo "    ==== Verse 1 (Level 4 - Content sections) ← --content-level=4" . PHP_EOL . PHP_EOL;
    echo "Examples:" . PHP_EOL;
    echo "  # Simple standalone article" . PHP_EOL;
    echo "  php parse-document.php article.adoc longform" . PHP_EOL;
    echo "  php parse-document.php blog-post.md 30023" . PHP_EOL . PHP_EOL;
    echo "  # Hierarchical publication" . PHP_EOL;
    echo "  php parse-document.php bible.adoc 30041 --hierarchical --content-level 4" . PHP_EOL;
    echo "  php parse-document.php guide.md wiki -h --content-level 3" . PHP_EOL . PHP_EOL;
}

function main(array $argv): int
{
    $argc = count($argv);

    // Check for help flag
    if ($argc < 2 || in_array('--help', $argv)) {
        showHelp();
        return 0;
    }

    // Parse arguments
    $documentFile = null;
    $contentKind = null;
    $hierarchical = false;
    $contentLevel = 1; // Default for simple articles
    $outputDir = './parsed-output';

    // Parse positional arguments and flags
    $positionalArgs = [];
    for ($i = 1; $i < $argc; $i++) {
        $arg = $argv[$i];
        
        if (str_starts_with($arg, '--')) {
            // Handle long options
            switch ($arg) {
                case '--hierarchical':
                    $hierarchical = true;
                    break;
                case '--content-level':
                    if ($i + 1 < $argc) {
                        $contentLevel = (int)$argv[++$i];
                    } else {
                        echo "❌ Error: --content-level requires a value" . PHP_EOL;
                        return 1;
                    }
                    break;
                case '--output-dir':
                    if ($i + 1 < $argc) {
                        $outputDir = $argv[++$i];
                    } else {
                        echo "❌ Error: --output-dir requires a value" . PHP_EOL;
                        return 1;
                    }
                    break;
                default:
                    echo "❌ Error: Unknown option: {$arg}" . PHP_EOL;
                    return 1;
            }
        } elseif (str_starts_with($arg, '-')) {
            // Handle short options
            switch ($arg) {
                case '-h':
                    $hierarchical = true;
                    break;
                default:
                    echo "❌ Error: Unknown option: {$arg}" . PHP_EOL;
                    return 1;
            }
        } else {
            // Positional argument
            $positionalArgs[] = $arg;
        }
    }

    // Validate positional arguments
    if (count($positionalArgs) < 2) {
        echo "❌ Error: Missing required arguments" . PHP_EOL;
        echo "Usage: php parse-document.php <document_file> <content_kind> [options]" . PHP_EOL;
        echo "Run with --help for detailed usage information." . PHP_EOL;
        return 1;
    }

    $documentFile = $positionalArgs[0];
    $contentKind = $positionalArgs[1];

    // Validate hierarchical mode
    if ($hierarchical && $contentLevel < 2) {
        echo "❌ Error: Hierarchical mode requires --content-level to be 2 or higher" . PHP_EOL;
        return 1;
    }

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
        echo "📝 Content kind: {$contentKind}" . PHP_EOL;
        echo "🔧 Mode: " . ($hierarchical ? "Hierarchical Publication" : "Simple Standalone Article") . PHP_EOL;
        if ($hierarchical) {
            echo "🎯 Content level: {$contentLevel}" . PHP_EOL;
        }
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
