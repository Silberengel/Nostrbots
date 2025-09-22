<?php

/**
 * Nostrbots - Main CLI runner
 * 
 * Usage: php nostrbots.php <bot_folder> [options]
 * 
 * Options:
 *   --dry-run     Validate configuration without publishing
 *   --verbose     Enable verbose output
 *   --help        Show this help message
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Bot\NostrBot;
use Nostrbots\EventKinds\EventKindRegistry;
use Nostrbots\Utils\ErrorHandler;
use Nostrbots\Utils\PerformanceManager;

function showHelp(): void
{
    echo "Nostrbots - Nostr Event Publishing Bot" . PHP_EOL;
    echo "=====================================" . PHP_EOL . PHP_EOL;
    echo "Usage: php nostrbots.php <bot_folder> [options]" . PHP_EOL . PHP_EOL;
    echo "Arguments:" . PHP_EOL;
    echo "  bot_folder    Name of the folder in botData/ containing bot configuration" . PHP_EOL . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "  --dry-run     Validate configuration without publishing events" . PHP_EOL;
    echo "  --verbose     Enable verbose output and detailed error reporting" . PHP_EOL;
    echo "  --profile     Enable performance profiling and memory monitoring" . PHP_EOL;
    echo "  --help        Show this help message" . PHP_EOL . PHP_EOL;
    echo "Supported Event Kinds:" . PHP_EOL;
    
    // Initialize registry first
    EventKindRegistry::registerDefaults();
    $kindInfo = EventKindRegistry::getKindInfo();
    foreach ($kindInfo as $info) {
        echo "  {$info['kind']}: {$info['name']} - {$info['description']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "Examples:" . PHP_EOL;
    echo "  php nostrbots.php myArticleBot" . PHP_EOL;
    echo "  php nostrbots.php myPublicationBot --dry-run" . PHP_EOL;
    echo "  php nostrbots.php myBot --verbose" . PHP_EOL . PHP_EOL;
}

function main(array $argv): int
{
    $argc = count($argv);

    // Check for help flag
    if ($argc < 2 || in_array('--help', $argv) || in_array('-h', $argv)) {
        showHelp();
        return 0;
    }

    // Parse arguments
    $botFolder = $argv[1];
    $dryRun = in_array('--dry-run', $argv);
    $verbose = in_array('--verbose', $argv);
    $profile = in_array('--profile', $argv);
    
    // Initialize error handling and performance monitoring
    $errorHandler = new ErrorHandler($verbose);
    $performanceManager = new PerformanceManager($profile);
    
    try {
        $performanceManager->startTimer('bot_execution');

        // Validate bot folder
        $botPath = __DIR__ . '/botData/' . $botFolder;
        if (!is_dir($botPath)) {
            echo "Error: Bot folder '{$botFolder}' not found in botData/" . PHP_EOL;
            echo "Available bot folders:" . PHP_EOL;
            
            $botDataDir = __DIR__ . '/botData';
            if (is_dir($botDataDir)) {
                $folders = array_filter(scandir($botDataDir), function($item) use ($botDataDir) {
                    return $item !== '.' && $item !== '..' && is_dir($botDataDir . '/' . $item);
                });
                foreach ($folders as $folder) {
                    echo "  - {$folder}" . PHP_EOL;
                }
            }
            return 1;
        }

        // Look for configuration file
        $configFile = $botPath . '/config.yml';
        if (!file_exists($configFile)) {
            echo "Error: Configuration file 'config.yml' not found in {$botFolder}/" . PHP_EOL;
            return 1;
        }

        echo "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
        echo "🤖 Welcome to Nostrbots!" . PHP_EOL;
        echo "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL . PHP_EOL;

        // Create and configure bot
        $bot = new NostrBot();
        $bot->loadConfig($configFile);

        if ($verbose) {
            echo "Configuration loaded:" . PHP_EOL;
            print_r($bot->getConfig());
            echo PHP_EOL;
        }

        // Validate configuration
        $errors = $bot->validateConfig();
        if (!empty($errors)) {
            echo "❌ Configuration validation failed:" . PHP_EOL;
            foreach ($errors as $error) {
                echo "   • {$error}" . PHP_EOL;
            }
            return 1;
        }

        echo "✅ Configuration validation passed" . PHP_EOL . PHP_EOL;

        if ($dryRun) {
            echo "🔍 Dry run mode - configuration is valid, no events will be published" . PHP_EOL;
            return 0;
        }

        // Run the bot
        $result = $bot->run();

        // Display results
        echo PHP_EOL . "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
        echo "📊 Execution Summary" . PHP_EOL;
        echo "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;

        $summary = $result->getSummary();
        echo "Status: " . ($result->isSuccess() ? "✅ Success" : "❌ Failed") . PHP_EOL;
        echo "Published Events: {$summary['published_events_count']}" . PHP_EOL;
        echo "Warnings: {$summary['warnings_count']}" . PHP_EOL;
        echo "Errors: {$summary['errors_count']}" . PHP_EOL;
        echo "Execution Time: " . number_format($summary['execution_time'], 3) . "s" . PHP_EOL;

        if ($verbose || !$result->isSuccess()) {
            // Show detailed results
            $publishedEvents = $result->getPublishedEvents();
            if (!empty($publishedEvents)) {
                echo PHP_EOL . "Published Events:" . PHP_EOL;
                foreach ($publishedEvents as $event) {
                    echo "  📝 {$event['event_id']} (kind {$event['kind']}) → {$event['relay']}" . PHP_EOL;
                }
            }

            $warnings = $result->getWarnings();
            if (!empty($warnings)) {
                echo PHP_EOL . "Warnings:" . PHP_EOL;
                foreach ($warnings as $warning) {
                    echo "  ⚠️  {$warning['message']}" . PHP_EOL;
                }
            }

            $errors = $result->getErrors();
            if (!empty($errors)) {
                echo PHP_EOL . "Errors:" . PHP_EOL;
                foreach ($errors as $error) {
                    echo "  ❌ {$error['message']}" . PHP_EOL;
                    if (isset($error['exception']) && $verbose) {
                        echo "     Exception: {$error['exception']['class']} in {$error['exception']['file']}:{$error['exception']['line']}" . PHP_EOL;
                    }
                }
            }
        }

        // Show viewing links
        $viewUrl = $result->getMetadata('view_url');
        $directUrl = $result->getMetadata('direct_url');
        
        if ($viewUrl || $directUrl) {
            echo PHP_EOL . "🔗 View Your Content:" . PHP_EOL;
            if ($viewUrl) echo "   📖 Content View: {$viewUrl}" . PHP_EOL;
            if ($directUrl) echo "   🔗 Direct Link: {$directUrl}" . PHP_EOL;
        }

        echo PHP_EOL . "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
        echo $result->isSuccess() ? "🎉 Bot execution completed successfully!" : "💥 Bot execution failed!";
        echo PHP_EOL . "═══════════════════════════════════════════════════════════════════════════════" . PHP_EOL;

        // Performance monitoring
        $performanceManager->endTimer('bot_execution');
        if ($profile) {
            echo PHP_EOL;
            $performanceManager->printPerformanceReport();
        }

        // Error summary
        if ($errorHandler->hasErrors() || $errorHandler->hasWarnings()) {
            echo PHP_EOL;
            $errorHandler->printErrorSummary();
        }

        return $result->isSuccess() ? 0 : 1;

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
        
        // Performance monitoring even on error
        if ($profile) {
            $performanceManager->endTimer('bot_execution');
            echo PHP_EOL;
            $performanceManager->printPerformanceReport();
        }
        
        return 1;
    }
}

// Run the application
exit(main($argv));
