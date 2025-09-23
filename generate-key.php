#!/usr/bin/env php
<?php

/**
 * Nostrbots Key Generator
 * 
 * Generates a new Nostr key pair for the bot and provides setup instructions.
 * This script creates both hex and bech32 format keys for easy use.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Nostrbots\Utils\KeyManager;

function showUsage(): void
{
    echo "Nostrbots Key Generator\n";
    echo "======================\n\n";
    echo "Usage: php generate-key.php [options]\n\n";
    echo "Options:\n";
    echo "  --help, -h     Show this help message\n";
    echo "  --slot <n>     Use specific key slot (NOSTR_BOT_KEY<n>)\n";
    echo "  --export       Show export command for current shell\n";
    echo "  --quiet, -q    Only output the export command\n\n";
    echo "Examples:\n";
    echo "  php generate-key.php                    # Generate key for next available slot\n";
    echo "  php generate-key.php --slot 1           # Generate key for NOSTR_BOT_KEY1\n";
    echo "  php generate-key.php --export           # Show export command\n";
    echo "  php generate-key.php --quiet            # Only show export command\n\n";
}

function main(): void
{
    $options = getopt('hq', ['help', 'slot:', 'export', 'quiet']);
    
    if (isset($options['h']) || isset($options['help'])) {
        showUsage();
        return;
    }
    
    $quiet = isset($options['q']) || isset($options['quiet']);
    $showExport = isset($options['export']) || $quiet;
    
    try {
        $keyManager = new KeyManager();
        
        // Determine which key slot to use
        $envVariable = null;
        if (isset($options['slot'])) {
            $slot = (int)$options['slot'];
            if ($slot < 1 || $slot > 10) {
                throw new \InvalidArgumentException('Key slot must be between 1 and 10');
            }
            $envVariable = "NOSTR_BOT_KEY{$slot}";
        }
        
        // Generate the key
        $result = $keyManager->generateNewBotKey($envVariable);
        
        if ($quiet) {
            // Only show the export command
            echo "export {$result['env_variable']}={$result['key_set']['hexPrivateKey']}\n";
            return;
        }
        
        if ($showExport) {
            // Show just the export command
            echo "export {$result['env_variable']}={$result['key_set']['hexPrivateKey']}\n";
            return;
        }
        
        // Full output
        echo "🔑 Nostrbots Key Generator\n";
        echo "==========================\n\n";
        
        echo "✅ Generated new Nostr key set:\n\n";
        
        echo "📋 Key Information:\n";
        echo "  Environment Variable: {$result['env_variable']}\n";
        echo "  Hex Private Key:      {$result['key_set']['hexPrivateKey']}\n";
        echo "  Bech32 Private Key:   {$result['key_set']['bechPrivateKey']}\n";
        echo "  Bech32 Public Key:    {$result['key_set']['bechPublicKey']}\n";
        
        if (!empty($result['profile'])) {
            echo "\n👤 Profile Information:\n";
            $profile = $result['profile'];
            if (!empty($profile['name'])) {
                echo "  Name: {$profile['name']}\n";
            }
            if (!empty($profile['about'])) {
                echo "  About: {$profile['about']}\n";
            }
            if (!empty($profile['picture'])) {
                echo "  Picture: {$profile['picture']}\n";
            }
        }
        
        echo "\n🚀 Setup Instructions:\n";
        echo "  1. Set the environment variable:\n";
        echo "     export {$result['env_variable']}={$result['key_set']['hexPrivateKey']}\n\n";
        
        echo "  2. Add to your shell profile for persistence:\n";
        echo "     echo 'export {$result['env_variable']}={$result['key_set']['hexPrivateKey']}' >> ~/.bashrc\n";
        echo "     # or for zsh:\n";
        echo "     echo 'export {$result['env_variable']}={$result['key_set']['hexPrivateKey']}' >> ~/.zshrc\n\n";
        
        echo "  3. Test the setup:\n";
        echo "     php nostrbots.php publish examples/markdown-longform.md --dry-run\n\n";
        
        echo "💡 Tips:\n";
        echo "  • Keep your private key secure and never share it\n";
        echo "  • You can use the bech32 format (nsec...) instead of hex if preferred\n";
        echo "  • Use --export flag to get just the export command\n";
        echo "  • Use --quiet flag for script automation\n\n";
        
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Run the script
main();
