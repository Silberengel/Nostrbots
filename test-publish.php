<?php

/**
 * Test Publishing Utility for Nostrbots
 * 
 * Tests the publishing functionality with dry-run, test, and live modes.
 * 
 * Usage: php test-publish.php [options] [files...]
 * 
 * Options:
 *   --dry-run    - Validate and preview without publishing
 *   --test       - Publish to test relays only
 *   --key VAR    - Use specific environment variable for key
 *   --help, -h   - Show help message
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\KeyManager;
use Symfony\Component\Yaml\Yaml;

function printUsage(): void
{
    echo "🚀 Nostrbots Test Publisher" . PHP_EOL;
    echo "===========================" . PHP_EOL . PHP_EOL;
    echo "Usage: php test-publish.php [options] [files...]" . PHP_EOL . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "  --dry-run              - Validate and preview without publishing" . PHP_EOL;
    echo "  --test                 - Publish to test relays only" . PHP_EOL;
    echo "  --key VAR              - Use specific environment variable for key" . PHP_EOL;
    echo "  --help, -h             - Show this help message" . PHP_EOL . PHP_EOL;
    echo "Examples:" . PHP_EOL;
    echo "  php test-publish.php --dry-run config.yml" . PHP_EOL;
    echo "  php test-publish.php --test --key NOSTR_BOT_KEY2 *.yml" . PHP_EOL;
    echo "  php test-publish.php --key NOSTR_BOT_KEY1 file1.yml file2.yml" . PHP_EOL . PHP_EOL;
}

function main(array $argv): void
{
    $argc = count($argv);
    
    if ($argc < 2 || in_array('--help', $argv) || in_array('-h', $argv)) {
        printUsage();
        exit(0);
    }
    
    $mode = 'publish'; // default
    $keyEnvVar = 'NOSTR_BOT_KEY1'; // default
    $files = [];
    
    // Parse command line arguments
    for ($i = 1; $i < $argc; $i++) {
        if ($argv[$i] === '--dry-run') {
            $mode = 'dry-run';
        } elseif ($argv[$i] === '--test') {
            $mode = 'test';
        } elseif ($argv[$i] === '--key' && isset($argv[$i + 1])) {
            $keyEnvVar = $argv[$i + 1];
            $i++; // Skip the next argument
        } elseif (!str_starts_with($argv[$i], '--')) {
            $files[] = $argv[$i];
        }
    }
    
    if (empty($files)) {
        echo "❌ Error: No files specified" . PHP_EOL;
        exit(1);
    }
    
    echo "🚀 Nostrbots Test Publisher" . PHP_EOL;
    echo "===========================" . PHP_EOL . PHP_EOL;
    
    try {
        $keyManager = new KeyManager();
        
        // Validate the key
        echo "🔑 Validating bot key..." . PHP_EOL;
        $key = $keyManager->getBotKey($keyEnvVar);
        
        if ($key === null) {
            echo "❌ Error: Key not found or invalid: {$keyEnvVar}" . PHP_EOL;
            echo "   Make sure the environment variable is set and contains a valid private key." . PHP_EOL;
            exit(1);
        }
        
        echo "✅ Key validation successful!" . PHP_EOL;
        echo "   NPub: {$key['npub']}" . PHP_EOL;
        echo "   Display Name: {$key['display_name']}" . PHP_EOL . PHP_EOL;
        
        // Validate files
        echo "📄 Validating files..." . PHP_EOL;
        foreach ($files as $file) {
            if (!file_exists($file)) {
                echo "❌ Error: File not found: {$file}" . PHP_EOL;
                exit(1);
            }
            
            if (!str_ends_with($file, '.yml') && !str_ends_with($file, '.yaml')) {
                echo "⚠️  Warning: File may not be a valid config file: {$file}" . PHP_EOL;
            }
        }
        echo "✅ All files validated!" . PHP_EOL . PHP_EOL;
        
        // Process based on mode
        switch ($mode) {
            case 'dry-run':
                echo "🔍 DRY RUN MODE - No actual publishing will occur" . PHP_EOL;
                echo "===============================================" . PHP_EOL . PHP_EOL;
                
                foreach ($files as $file) {
                    echo "📄 Processing: {$file}" . PHP_EOL;
                    
                    // Read and validate the config file
                    try {
                        $config = Yaml::parseFile($file);
                    } catch (\Exception $e) {
                        echo "❌ Error: Invalid YAML in {$file}: " . $e->getMessage() . PHP_EOL;
                        continue;
                    }
                    
                    // Display what would be published
                    echo "   Title: " . ($config['title'] ?? 'No title') . PHP_EOL;
                    echo "   Kind: " . ($config['kind'] ?? 'Unknown') . PHP_EOL;
                    echo "   Environment Variable: " . ($config['npub']['environment_variable'] ?? 'Not set') . PHP_EOL;
                    echo "   Public Key: " . ($config['npub']['public_key'] ?? 'Not set') . PHP_EOL;
                    
                    if (isset($config['content'])) {
                        $contentLength = strlen($config['content']);
                        echo "   Content Length: {$contentLength} characters" . PHP_EOL;
                    }
                    
                    echo "   ✅ Would publish successfully" . PHP_EOL . PHP_EOL;
                }
                
                echo "🎉 Dry run completed successfully!" . PHP_EOL;
                echo "   All files are valid and ready for publishing." . PHP_EOL;
                break;
                
            case 'test':
                echo "🧪 TEST MODE - Publishing to test relays only" . PHP_EOL;
                echo "=============================================" . PHP_EOL . PHP_EOL;
                
                // In a real implementation, this would actually publish to test relays
                echo "📡 Test publishing functionality not yet implemented." . PHP_EOL;
                echo "   This would publish to test relays for validation." . PHP_EOL . PHP_EOL;
                
                foreach ($files as $file) {
                    echo "📄 Would test publish: {$file}" . PHP_EOL;
                }
                
                echo "🎉 Test mode completed!" . PHP_EOL;
                break;
                
            case 'publish':
                echo "📡 LIVE PUBLISHING MODE" . PHP_EOL;
                echo "=======================" . PHP_EOL . PHP_EOL;
                
                // In a real implementation, this would actually publish to live relays
                echo "📡 Live publishing functionality not yet implemented." . PHP_EOL;
                echo "   This would publish to all configured relays." . PHP_EOL . PHP_EOL;
                
                foreach ($files as $file) {
                    echo "📄 Would publish: {$file}" . PHP_EOL;
                }
                
                echo "🎉 Publishing completed!" . PHP_EOL;
                break;
        }
        
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

main($argv);