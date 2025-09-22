<?php

/**
 * Test Publishing Script
 * 
 * Tests actual publishing to test relays with validation
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Bot\NostrBot;
use Nostrbots\Utils\ErrorHandler;
use Nostrbots\Utils\PerformanceManager;

function testPublishing(): void
{
    echo "🧪 Test Publishing to Test Relays" . PHP_EOL;
    echo "=================================" . PHP_EOL . PHP_EOL;
    
    $errorHandler = new ErrorHandler(true);
    $performanceManager = new PerformanceManager(true);
    
    try {
        $performanceManager->startTimer('test_publish');
        
        // Load test bot configuration
        $configFile = __DIR__ . '/botData/testBot/config.yml';
        if (!file_exists($configFile)) {
            throw new \Exception("Test bot configuration not found: {$configFile}");
        }
        
        echo "📋 Loading test bot configuration..." . PHP_EOL;
        $bot = new NostrBot();
        $bot->loadConfig($configFile);
        
        echo "🔍 Configuration loaded successfully" . PHP_EOL;
        echo "📊 Bot name: " . $bot->getName() . PHP_EOL;
        echo "📝 Description: " . $bot->getDescription() . PHP_EOL;
        
        // Check if test mode is enabled
        $config = $bot->getConfig();
        if (!($config['test_mode'] ?? false)) {
            echo "⚠️  Warning: Test mode not enabled in configuration" . PHP_EOL;
        } else {
            echo "✅ Test mode enabled - will use test relays" . PHP_EOL;
        }
        
        echo PHP_EOL . "🚀 Publishing test event..." . PHP_EOL;
        
        // Run the bot
        $result = $bot->run();
        
        $performanceManager->endTimer('test_publish');
        
        // Display results
        echo PHP_EOL . "📊 Publishing Results:" . PHP_EOL;
        echo "=====================" . PHP_EOL;
        
        if ($result->isSuccess()) {
            echo "✅ Publishing successful!" . PHP_EOL;
            
            $publishedEvents = $result->getPublishedEvents();
            echo "📄 Published " . count($publishedEvents) . " events:" . PHP_EOL;
            
            foreach ($publishedEvents as $event) {
                echo "  - Event ID: " . $event['event_id'] . PHP_EOL;
                echo "    Kind: " . $event['kind'] . PHP_EOL;
                echo "    Relay: " . $event['relay'] . PHP_EOL;
                if (isset($event['metadata']['title'])) {
                    echo "    Title: " . $event['metadata']['title'] . PHP_EOL;
                }
            }
            
            if ($result->hasViewingLinks()) {
                $links = $result->getViewingLinks();
                echo PHP_EOL . "🔗 Viewing Links:" . PHP_EOL;
                foreach ($links as $type => $url) {
                    echo "  {$type}: {$url}" . PHP_EOL;
                }
            }
            
        } else {
            echo "❌ Publishing failed!" . PHP_EOL;
            
            $errors = $result->getErrors();
            if (!empty($errors)) {
                echo "🚨 Errors:" . PHP_EOL;
                foreach ($errors as $error) {
                    echo "  - {$error}" . PHP_EOL;
                }
            }
            
            $warnings = $result->getWarnings();
            if (!empty($warnings)) {
                echo "⚠️  Warnings:" . PHP_EOL;
                foreach ($warnings as $warning) {
                    echo "  - {$warning}" . PHP_EOL;
                }
            }
        }
        
        echo PHP_EOL . "📊 Performance Report:" . PHP_EOL;
        $performanceManager->printPerformanceReport();
        
        echo PHP_EOL . "✅ Test publishing completed!" . PHP_EOL;
        
    } catch (\Exception $e) {
        $errorHandler->addError("Test publishing failed: " . $e->getMessage());
        echo "❌ Test publishing failed: " . $e->getMessage() . PHP_EOL;
        
        if ($errorHandler->hasErrors()) {
            $errorHandler->printErrorSummary();
        }
    }
}

// Run test if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    testPublishing();
}
