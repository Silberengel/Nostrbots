<?php

/**
 * Nostrbots - Direct Document Publishing for Nostr
 * 
 * Usage: php nostrbots.php publish <document> [options]
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

function showHelp(): void
{
    echo "Nostrbots - Direct Document Publishing for Nostr" . PHP_EOL;
    echo "===============================================" . PHP_EOL . PHP_EOL;
    echo "Usage: php nostrbots.php publish <document> [options]" . PHP_EOL . PHP_EOL;
    echo "Arguments:" . PHP_EOL;
    echo "  document      Path to document file (.adoc or .md)" . PHP_EOL . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "  --dry-run     Validate configuration without publishing events" . PHP_EOL;
    echo "  --verbose     Enable verbose output and detailed error reporting" . PHP_EOL;
    echo "  --profile     Enable performance profiling and memory monitoring" . PHP_EOL;
    echo "  --content-level <n>  Header level that becomes content sections (default: 4)" . PHP_EOL;
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
    echo "  # Basic publishing" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.adoc" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.md" . PHP_EOL . PHP_EOL;
    echo "  # With custom options" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.adoc --content-level 3" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.adoc --content-kind 30023" . PHP_EOL . PHP_EOL;
    echo "  # Testing and debugging" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.adoc --dry-run --verbose" . PHP_EOL;
    echo "  php nostrbots.php publish my-document.adoc --profile" . PHP_EOL . PHP_EOL;
    echo "Document Format:" . PHP_EOL;
    echo "  = Document Title" . PHP_EOL;
    echo "  author: Your Name" . PHP_EOL;
    echo "  version: 1.0" . PHP_EOL;
    echo "  relays: favorite-relays" . PHP_EOL;
    echo "  auto_update: true" . PHP_EOL;
    echo "  summary: Brief description" . PHP_EOL;
    echo "  type: article" . PHP_EOL . PHP_EOL;
    echo "  Your content here..." . PHP_EOL . PHP_EOL;
    echo "See docs/DIRECT_PUBLISHING.md for complete documentation." . PHP_EOL;
}

function main(array $argv): int
{
    $argc = count($argv);

    // Check for help flag
    if ($argc < 2 || in_array('--help', $argv) || in_array('-h', $argv)) {
        showHelp();
        return 0;
    }

    // Check if this is the publish command
    if ($argv[1] !== 'publish') {
        echo "Error: Invalid command. Use 'publish' to publish documents." . PHP_EOL;
        echo "Usage: php nostrbots.php publish <document> [options]" . PHP_EOL;
        echo "Run 'php nostrbots.php --help' for more information." . PHP_EOL;
        return 1;
    }
    
    // Check if document path is provided
    if ($argc < 3) {
        echo "Error: Document path required for publishing" . PHP_EOL;
        echo "Usage: php nostrbots.php publish <document> [options]" . PHP_EOL;
        return 1;
    }
    
    $documentPath = $argv[2];
    
    // Parse options
    $dryRun = in_array('--dry-run', $argv);
    $verbose = in_array('--verbose', $argv);
    $profile = in_array('--profile', $argv);
    
    // Parse content-level option
    $contentLevel = 4; // default
    $contentLevelIndex = array_search('--content-level', $argv);
    if ($contentLevelIndex !== false && isset($argv[$contentLevelIndex + 1])) {
        $contentLevel = (int)$argv[$contentLevelIndex + 1];
    }
    
    // Parse content-kind option
    $contentKind = '30041'; // default
    $contentKindIndex = array_search('--content-kind', $argv);
    if ($contentKindIndex !== false && isset($argv[$contentKindIndex + 1])) {
        $contentKind = $argv[$contentKindIndex + 1];
    }
    
    // Validate document path
    if (!file_exists($documentPath)) {
        echo "Error: Document file '{$documentPath}' not found" . PHP_EOL;
        return 1;
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));
    if (!in_array($extension, ['adoc', 'md'])) {
        echo "Error: Document must be .adoc or .md file" . PHP_EOL;
        return 1;
    }
    
    // Initialize error handling and performance monitoring
    $errorHandler = new ErrorHandler($verbose);
    $performanceManager = new PerformanceManager($profile);
    
    try {
        $performanceManager->startTimer('direct_publishing');
        
        echo "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
        echo "📄 Nostrbots - Direct Document Publishing" . PHP_EOL;
        echo "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL . PHP_EOL;
        
        if ($verbose) {
            echo "Document: {$documentPath}" . PHP_EOL;
            echo "Content Level: {$contentLevel}" . PHP_EOL;
            echo "Content Kind: {$contentKind}" . PHP_EOL;
            echo "Dry Run: " . ($dryRun ? 'Yes' : 'No') . PHP_EOL . PHP_EOL;
        }
        
        // Create direct publisher
        $publisher = new DirectDocumentPublisher();
        
        // Publish the document
        $result = $publisher->publishDocument($documentPath, $contentLevel, $contentKind, $dryRun);
        
        // Display results
        echo PHP_EOL . "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
        echo "📊 Publishing Summary" . PHP_EOL;
        echo "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
        
        if ($result['success']) {
            echo "Status: ✅ Success" . PHP_EOL;
            echo "Document: {$result['document_title']}" . PHP_EOL;
            
            if (isset($result['dry_run'])) {
                echo "Mode: 🔍 Dry Run (No events published)" . PHP_EOL;
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
            echo "Status: ❌ Failed" . PHP_EOL;
            echo "Published Events: {$result['total_published']}/{$result['total_expected']}" . PHP_EOL;
        }
        
        // Show errors if any
        if (!empty($result['errors'])) {
            echo PHP_EOL . "Errors:" . PHP_EOL;
            foreach ($result['errors'] as $error) {
                echo "  ❌ {$error}" . PHP_EOL;
            }
        }
        
        echo PHP_EOL . "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
        echo $result['success'] ? "🎉 Document publishing completed successfully!" : "💥 Document publishing failed!";
        echo PHP_EOL . "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
        
        // Performance monitoring
        $performanceManager->endTimer('direct_publishing');
        if ($profile) {
            echo PHP_EOL;
            $performanceManager->printPerformanceReport();
        }
        
        return $result['success'] ? 0 : 1;
        
    } catch (\Exception $e) {
        $errorHandler->addError("Fatal error: " . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        echo "💥 Fatal error: " . $e->getMessage() . PHP_EOL;
        if ($verbose) {
            echo "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
        }
        
        return 1;
    }
}

// Run the application
exit(main($argv));