<?php

/**
 * Nostrbots - Direct Document Publishing for Nostr
 * 
 * Usage: php nostrbots.php [publish] <document> [options]
 * 
 * Options:
 *   --dry-run     Validate configuration without publishing
 *   --verbose     Enable verbose output
 *   --profile     Enable performance profiling
 *   --content-level <n>  Header level that becomes content (default: 4)
 *   --content-kind <kind> Content kind (30023, 30041, 30818, default: 30041)
 *   --help        Show this help message
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\DirectDocumentPublisher;
use Nostrbots\EventKinds\EventKindRegistry;
use Nostrbots\Utils\ErrorHandler;
use Nostrbots\Utils\PerformanceManager;

/**
 * Show help information
 */
function showHelp(): void
{
    echo "Nostrbots - Direct Document Publishing for Nostr" . PHP_EOL;
    echo "===============================================" . PHP_EOL . PHP_EOL;
    echo "Usage: php nostrbots.php [publish] <document> [options]" . PHP_EOL . PHP_EOL;
    echo "Arguments:" . PHP_EOL;
    echo "  document      Path to document file (.adoc or .md)" . PHP_EOL . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "  --dry-run     Validate configuration without publishing events" . PHP_EOL;
    echo "  --verbose     Enable verbose output and detailed error reporting" . PHP_EOL;
    echo "  --profile     Enable performance profiling and memory monitoring" . PHP_EOL;
    echo "  --content-level <n>  Header level that becomes content sections (default: 0)" . PHP_EOL;
    echo "  --content-kind <kind> Content kind (30023, 30041, 30818, default: 30041)" . PHP_EOL;
    echo "  --help        Show this help message" . PHP_EOL . PHP_EOL;
    echo "Supported Event Kinds:" . PHP_EOL;
    
    // Initialize registry first
    EventKindRegistry::registerDefaults();
    $kindInfo = EventKindRegistry::getKindInfo();
    foreach ($kindInfo as $info) {
        echo "  {$info['kind']}: {$info['name']} - {$info['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "Examples:" . PHP_EOL;
    echo "  # Basic publishing (both forms work)" . PHP_EOL;
    echo "  php nostrbots.php my-document.adoc" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.md" . PHP_EOL . PHP_EOL;
    echo "  # With custom options" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.adoc --content-level 1" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.adoc --content-kind 30023" . PHP_EOL . PHP_EOL;
    echo "  # Testing and debugging" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.adoc --dry-run --verbose" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.adoc --profile" . PHP_EOL . PHP_EOL;
    echo "Document Format:" . PHP_EOL;
    echo "  = Document Title" . PHP_EOL;
    echo "  author: Your Name" . PHP_EOL;
    echo "  version: 1.0" . PHP_EOL;
    echo "  relays: document-relays" . PHP_EOL;
    echo "  auto_update: true" . PHP_EOL;
    echo "  summary: Brief description" . PHP_EOL;
    echo "  type: article" . PHP_EOL . PHP_EOL;
    echo "  Your content here..." . PHP_EOL . PHP_EOL;
    echo "See docs/DIRECT_PUBLISHING.md for complete documentation." . PHP_EOL;
}

/**
 * Parse command line arguments
 */
function parseArguments(array $argv): array
{
    $argc = count($argv);

    // Check for help flag
    if ($argc < 2 || in_array('--help', $argv) || in_array('-h', $argv)) {
        showHelp();
        exit(0);
    }

    // Check if first argument is a subcommand or document path
    if ($argv[1] === 'publish') {
        // Explicit publish command
        if ($argc < 3) {
            echo "Error: Document path required for publishing" . PHP_EOL;
            echo "Usage: php nostrbots.php publish <document> [options]" . PHP_EOL;
            exit(1);
        }
        $documentPath = $argv[2];
    } else {
        // Treat first argument as document path (implicit publish)
        $documentPath = $argv[1];
    }
    
    return [
        'document_path' => $documentPath,
        'dry_run' => in_array('--dry-run', $argv),
        'verbose' => in_array('--verbose', $argv),
        'profile' => in_array('--profile', $argv),
        'content_level' => parseContentLevel($argv),
        'content_kind' => parseContentKind($argv)
    ];
}

/**
 * Parse content level from arguments
 */
function parseContentLevel(array $argv): ?int
{
    $contentLevelIndex = array_search('--content-level', $argv);
    if ($contentLevelIndex !== false && isset($argv[$contentLevelIndex + 1])) {
        return (int)$argv[$contentLevelIndex + 1];
    }
    return null; // Use file headers or defaults
}

/**
 * Parse content kind from arguments
 */
function parseContentKind(array $argv): ?string
{
    $contentKindIndex = array_search('--content-kind', $argv);
    if ($contentKindIndex !== false && isset($argv[$contentKindIndex + 1])) {
        return $argv[$contentKindIndex + 1];
    }
    return null; // Use file headers or defaults
}

/**
 * Validate document file
 */
function validateDocument(string $documentPath): void
{
    if (!file_exists($documentPath)) {
        echo "Error: Document file '{$documentPath}' not found" . PHP_EOL;
        exit(1);
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));
    if (!in_array($extension, ['adoc', 'md'])) {
        echo "Error: Document must be .adoc or .md file" . PHP_EOL;
        exit(1);
    }
}

/**
 * Display publishing header
 */
function displayPublishingHeader(array $args): void
{
    echo "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
    echo "📄 Nostrbots - Direct Document Publishing" . PHP_EOL;
    echo "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL . PHP_EOL;
    
    if ($args['verbose']) {
        echo "Document: {$args['document_path']}" . PHP_EOL;
        $levelDisplay = $args['content_level'] ?? 'file/default';
        $kindDisplay = $args['content_kind'] ?? 'file/default';
        echo "Content Level: {$levelDisplay}" . PHP_EOL;
        echo "Content Kind: {$kindDisplay}" . PHP_EOL;
        echo "Dry Run: " . ($args['dry_run'] ? 'Yes' : 'No') . PHP_EOL . PHP_EOL;
    }
}

/**
 * Display publishing results
 */
function displayResults(array $result, bool $verbose): void
{
    echo PHP_EOL . "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
    echo "📊 Publishing Summary" . PHP_EOL;
    echo "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
    
    if ($result['success']) {
        echo "Status: ✓ Success" . PHP_EOL;
        echo "Document: {$result['document_title']}" . PHP_EOL;
        
        if (isset($result['dry_run'])) {
            echo "Mode: Dry Run (No events published)" . PHP_EOL;
            echo "Content Sections: {$result['content_sections']}" . PHP_EOL;
            echo "Index Sections: {$result['index_sections']}" . PHP_EOL;
            echo "Total Events: {$result['total_events']}" . PHP_EOL;
        } else {
            echo "Published Events: {$result['total_published']}/{$result['total_expected']}" . PHP_EOL;
            
            if ($verbose && !empty($result['published_events'])) {
                echo PHP_EOL . "Published Events:" . PHP_EOL;
                foreach ($result['published_events'] as $event) {
                    echo "  📝 {$event['event_id']} (kind {$event['kind']}) - {$event['title']}" . PHP_EOL;
                }
            }
        }
    } else {
        echo "Status: ✗ Failed" . PHP_EOL;
        echo "Published Events: {$result['total_published']}/{$result['total_expected']}" . PHP_EOL;
    }
    
    // Show errors if any
    if (!empty($result['errors'])) {
        echo PHP_EOL . "Errors:" . PHP_EOL;
        foreach ($result['errors'] as $error) {
            echo "  ✗ {$error}" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
    echo $result['success'] ? "🎉 Document publishing completed successfully!" : "💥 Document publishing failed!";
    echo PHP_EOL . "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
}

/**
 * Main application entry point
 */
function main(array $argv): int
{
    try {
        // Parse and validate arguments
        $args = parseArguments($argv);
        validateDocument($args['document_path']);
        
        // Initialize components
        $errorHandler = new ErrorHandler($args['verbose']);
        $performanceManager = new PerformanceManager($args['profile']);
        $publisher = new DirectDocumentPublisher();
        
        // Start performance monitoring
        $performanceManager->startTimer('direct_publishing');
        
        // Display header
        displayPublishingHeader($args);
        
        // Publish the document
        $result = $publisher->publishDocument(
            $args['document_path'],
            $args['content_level'],
            $args['content_kind'],
            $args['dry_run']
        );
        
        // Display results
        displayResults($result, $args['verbose']);
        
        // End performance monitoring
        $performanceManager->endTimer('direct_publishing');
        if ($args['profile']) {
            echo PHP_EOL;
            $performanceManager->printPerformanceReport();
        }
        
        return $result['success'] ? 0 : 1;
        
    } catch (\Exception $e) {
        echo "💥 Fatal error: " . $e->getMessage() . PHP_EOL;
        if (isset($args['verbose']) && $args['verbose']) {
            echo "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
        }
        return 1;
    }
}

// Run the application
exit(main($argv));