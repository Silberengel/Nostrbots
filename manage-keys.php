<?php

/**
 * Key Management Utility for Nostrbots
 * 
 * Manages multiple Nostr bot keys, displays available keys with profiles,
 * and provides key generation capabilities.
 * 
 * Usage: php manage-keys.php [command] [options]
 * 
 * Commands:
 *   list                    - List all available bot keys
 *   generate [--env-var VAR] - Generate a new key
 *   validate [--env-var VAR] - Validate a specific key
 *   profile [--env-var VAR]  - Show profile for a specific key
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\KeyManager;

function printUsage(): void
{
    echo "🔑 Nostrbots Key Manager" . PHP_EOL;
    echo "========================" . PHP_EOL . PHP_EOL;
    echo "Usage: php manage-keys.php [command] [options]" . PHP_EOL . PHP_EOL;
    echo "Commands:" . PHP_EOL;
    echo "  list                    - List all available bot keys" . PHP_EOL;
    echo "  generate [--env-var VAR] - Generate a new key" . PHP_EOL;
    echo "  validate [--env-var VAR] - Validate a specific key" . PHP_EOL;
    echo "  profile [--env-var VAR]  - Show profile for a specific key" . PHP_EOL . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "  --env-var VAR           - Specify environment variable name (e.g., NOSTR_BOT_KEY2)" . PHP_EOL;
    echo "  --help, -h              - Show this help message" . PHP_EOL . PHP_EOL;
}

function listKeys(KeyManager $keyManager): void
{
    echo "🔑 Available Bot Keys" . PHP_EOL;
    echo "=====================" . PHP_EOL . PHP_EOL;
    
    $keys = $keyManager->getAllBotKeys();
    
    if (empty($keys)) {
        echo "❌ No bot keys found. Generate one with:" . PHP_EOL;
        echo "   php manage-keys.php generate" . PHP_EOL . PHP_EOL;
        return;
    }
    
    foreach ($keys as $key) {
        echo "🔐 {$key['env_variable']}" . PHP_EOL;
        echo "   NPub: {$key['npub']}" . PHP_EOL;
        echo "   Display Name: {$key['display_name']}" . PHP_EOL;
        if ($key['profile_pic']) {
            echo "   Profile Pic: {$key['profile_pic']}" . PHP_EOL;
        }
        echo PHP_EOL;
    }
    
    echo "✅ Found " . count($keys) . " configured key(s)" . PHP_EOL . PHP_EOL;
}

function generateKey(KeyManager $keyManager, ?string $envVar = null): void
{
    echo "🔑 Generating New Bot Key" . PHP_EOL;
    echo "=========================" . PHP_EOL . PHP_EOL;
    
    try {
        $result = $keyManager->generateNewBotKey($envVar);
        
        echo "✅ New key pair generated successfully!" . PHP_EOL . PHP_EOL;
        
        echo "🔐 Private Key (hex): " . $result['key_set']['hexPrivateKey'] . PHP_EOL;
        echo "🔐 Private Key (nsec): " . $result['key_set']['bechPrivateKey'] . PHP_EOL;
        echo "🆔 Public Key (hex): " . $result['key_set']['hexPublicKey'] . PHP_EOL;
        echo "🆔 Public Key (npub): " . $result['key_set']['bechPublicKey'] . PHP_EOL . PHP_EOL;
        
        echo "⚠️  SECURITY WARNING:" . PHP_EOL;
        echo "   • Keep your private key secret!" . PHP_EOL;
        echo "   • Never share your private key with anyone" . PHP_EOL;
        echo "   • Store it securely (password manager, encrypted file, etc.)" . PHP_EOL . PHP_EOL;
        
        echo "📋 Setup Instructions:" . PHP_EOL;
        echo "1. Set the environment variable:" . PHP_EOL;
        echo "   export {$result['env_variable']}={$result['key_set']['hexPrivateKey']}" . PHP_EOL . PHP_EOL;
        
        echo "2. Update your bot configuration file:" . PHP_EOL;
        echo "   npub:" . PHP_EOL;
        echo "     environment_variable: \"{$result['env_variable']}\"" . PHP_EOL;
        echo "     public_key: \"{$result['key_set']['bechPublicKey']}\"" . PHP_EOL . PHP_EOL;
        
        echo "🎉 You're ready to use this key!" . PHP_EOL;
        
    } catch (\Exception $e) {
        echo "❌ Error generating key: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

function validateKey(KeyManager $keyManager, string $envVar): void
{
    echo "🔍 Validating Bot Key: {$envVar}" . PHP_EOL;
    echo "================================" . PHP_EOL . PHP_EOL;
    
    try {
        $key = $keyManager->getBotKey($envVar);
        
        if ($key === null) {
            echo "❌ Key not found or invalid: {$envVar}" . PHP_EOL;
            echo "   Make sure the environment variable is set and contains a valid private key." . PHP_EOL;
            exit(1);
        }
        
        echo "✅ Key validation successful!" . PHP_EOL . PHP_EOL;
        echo "🔐 Environment Variable: {$key['env_variable']}" . PHP_EOL;
        echo "🆔 NPub: {$key['npub']}" . PHP_EOL;
        echo "👤 Display Name: {$key['display_name']}" . PHP_EOL;
        if ($key['profile_pic']) {
            echo "🖼️  Profile Pic: {$key['profile_pic']}" . PHP_EOL;
        }
        
    } catch (\Exception $e) {
        echo "❌ Error validating key: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

function showProfile(KeyManager $keyManager, string $envVar): void
{
    echo "👤 Bot Key Profile: {$envVar}" . PHP_EOL;
    echo "=============================" . PHP_EOL . PHP_EOL;
    
    try {
        $key = $keyManager->getBotKey($envVar);
        
        if ($key === null) {
            echo "❌ Key not found: {$envVar}" . PHP_EOL;
            exit(1);
        }
        
        echo "🔐 Environment Variable: {$key['env_variable']}" . PHP_EOL;
        echo "🆔 NPub: {$key['npub']}" . PHP_EOL;
        echo "👤 Display Name: {$key['display_name']}" . PHP_EOL;
        
        if ($key['profile_pic']) {
            echo "🖼️  Profile Picture: {$key['profile_pic']}" . PHP_EOL;
        }
        
        echo PHP_EOL . "📋 Full Profile Data:" . PHP_EOL;
        foreach ($key['profile'] as $field => $value) {
            if ($value !== null) {
                echo "   {$field}: {$value}" . PHP_EOL;
            }
        }
        
    } catch (\Exception $e) {
        echo "❌ Error fetching profile: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

function main(array $argv): void
{
    $argc = count($argv);
    
    if ($argc < 2) {
        printUsage();
        exit(1);
    }
    
    $command = $argv[1];
    $envVar = null;
    
    // Parse command line arguments
    for ($i = 2; $i < $argc; $i++) {
        if (($argv[$i] === '--env-var' || $argv[$i] === '-e') && isset($argv[$i + 1])) {
            $envVar = $argv[$i + 1];
            $i++; // Skip the next argument
        } elseif ($argv[$i] === '--help' || $argv[$i] === '-h') {
            printUsage();
            exit(0);
        }
    }
    
    $keyManager = new KeyManager();
    
    switch ($command) {
        case 'list':
            listKeys($keyManager);
            break;
            
        case 'generate':
            generateKey($keyManager, $envVar);
            break;
            
        case 'validate':
            if ($envVar === null) {
                echo "❌ Error: --env-var is required for validate command" . PHP_EOL;
                exit(1);
            }
            validateKey($keyManager, $envVar);
            break;
            
        case 'profile':
            if ($envVar === null) {
                echo "❌ Error: --env-var is required for profile command" . PHP_EOL;
                exit(1);
            }
            showProfile($keyManager, $envVar);
            break;
            
        default:
            echo "❌ Unknown command: {$command}" . PHP_EOL . PHP_EOL;
            printUsage();
            exit(1);
    }
}

main($argv);
