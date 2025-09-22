<?php

/**
 * Key Management Utility for Nostrbots
 * 
 * Manages the single Nostr bot key, displays key information with profile,
 * and provides key generation capabilities.
 * 
 * Usage: php manage-keys.php [command]
 * 
 * Commands:
 *   list                    - Show current bot key information
 *   generate                - Generate a new key
 *   validate                - Validate the current key
 *   profile                 - Show profile for the current key
 *   delete                  - Show instructions for deleting the key
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\KeyManager;

function printUsage(): void
{
    echo "🔑 Nostrbots Key Manager" . PHP_EOL;
    echo "========================" . PHP_EOL . PHP_EOL;
    echo "Usage: php manage-keys.php [command]" . PHP_EOL . PHP_EOL;
    echo "Commands:" . PHP_EOL;
    echo "  list                    - Show current bot key information" . PHP_EOL;
    echo "  generate                - Generate a new key" . PHP_EOL;
    echo "  validate                - Validate the current key" . PHP_EOL;
    echo "  profile                 - Show profile for the current key" . PHP_EOL;
    echo "  delete                  - Show instructions for deleting the key" . PHP_EOL . PHP_EOL;
    echo "Environment Variable:" . PHP_EOL;
    echo "  NOSTR_BOT_KEY           - The single bot key environment variable" . PHP_EOL . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "  --help, -h              - Show this help message" . PHP_EOL . PHP_EOL;
}

function listKeys(KeyManager $keyManager): void
{
    echo "🔑 Bot Key Information" . PHP_EOL;
    echo "======================" . PHP_EOL . PHP_EOL;
    
    $key = $keyManager->getBotKey('NOSTR_BOT_KEY');
    
    if ($key === null) {
        echo "❌ No bot key found. Generate one with:" . PHP_EOL;
        echo "   php manage-keys.php generate" . PHP_EOL . PHP_EOL;
        echo "Then set the environment variable:" . PHP_EOL;
        echo "   export NOSTR_BOT_KEY=your_private_key_here" . PHP_EOL . PHP_EOL;
        return;
    }
    
    echo "🔐 NOSTR_BOT_KEY" . PHP_EOL;
    echo "   NPub: {$key['npub']}" . PHP_EOL;
    echo "   Display Name: {$key['display_name']}" . PHP_EOL;
    if ($key['profile_pic']) {
        echo "   Profile Pic: {$key['profile_pic']}" . PHP_EOL;
    }
    echo PHP_EOL;
    echo "✅ Bot key is configured and ready to use!" . PHP_EOL . PHP_EOL;
}

function generateKey(KeyManager $keyManager): void
{
    echo "🔑 Generating New Bot Key" . PHP_EOL;
    echo "=========================" . PHP_EOL . PHP_EOL;
    
    try {
        $result = $keyManager->generateNewBotKey('NOSTR_BOT_KEY');
        
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
        echo "   export NOSTR_BOT_KEY={$result['key_set']['hexPrivateKey']}" . PHP_EOL . PHP_EOL;
        
        echo "2. Update your bot configuration file:" . PHP_EOL;
        echo "   npub:" . PHP_EOL;
        echo "     environment_variable: \"NOSTR_BOT_KEY\"" . PHP_EOL;
        echo "     public_key: \"{$result['key_set']['bechPublicKey']}\"" . PHP_EOL . PHP_EOL;
        
        echo "🎉 You're ready to use this key!" . PHP_EOL;
        
    } catch (\Exception $e) {
        echo "❌ Error generating key: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

function validateKey(KeyManager $keyManager): void
{
    echo "🔍 Validating Bot Key" . PHP_EOL;
    echo "=====================" . PHP_EOL . PHP_EOL;
    
    try {
        $key = $keyManager->getBotKey('NOSTR_BOT_KEY');
        
        if ($key === null) {
            echo "❌ Key not found or invalid: NOSTR_BOT_KEY" . PHP_EOL;
            echo "   Make sure the environment variable is set and contains a valid private key." . PHP_EOL;
            exit(1);
        }
        
        echo "✅ Key validation successful!" . PHP_EOL . PHP_EOL;
        echo "🔐 Environment Variable: NOSTR_BOT_KEY" . PHP_EOL;
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

function showProfile(KeyManager $keyManager): void
{
    echo "👤 Bot Key Profile" . PHP_EOL;
    echo "==================" . PHP_EOL . PHP_EOL;
    
    try {
        $key = $keyManager->getBotKey('NOSTR_BOT_KEY');
        
        if ($key === null) {
            echo "❌ Key not found: NOSTR_BOT_KEY" . PHP_EOL;
            exit(1);
        }
        
        echo "🔐 Environment Variable: NOSTR_BOT_KEY" . PHP_EOL;
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

function deleteKey(KeyManager $keyManager): void
{
    echo "🗑️  Deleting Bot Key" . PHP_EOL;
    echo "====================" . PHP_EOL . PHP_EOL;
    
    try {
        // Check if the key exists
        $key = $keyManager->getBotKey('NOSTR_BOT_KEY');
        
        if ($key === null) {
            echo "❌ Key not found: NOSTR_BOT_KEY" . PHP_EOL;
            exit(1);
        }
        
        echo "🔐 Found key: {$key['npub']}" . PHP_EOL;
        echo "👤 Display Name: {$key['display_name']}" . PHP_EOL . PHP_EOL;
        
        // Note: In a real implementation, you would need to actually remove the environment variable
        // For now, we'll just show what would be deleted
        echo "⚠️  Note: This would remove the environment variable NOSTR_BOT_KEY" . PHP_EOL;
        echo "   In a production environment, you would need to:" . PHP_EOL;
        echo "   1. Remove the variable from your shell profile (.bashrc, .zshrc, etc.)" . PHP_EOL;
        echo "   2. Unset the variable in your current session: unset NOSTR_BOT_KEY" . PHP_EOL;
        echo "   3. Restart your terminal or source your profile" . PHP_EOL . PHP_EOL;
        
        echo "✅ Key deletion information displayed" . PHP_EOL;
        
    } catch (\Exception $e) {
        echo "❌ Error deleting key: " . $e->getMessage() . PHP_EOL;
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
    
    // Check for help flag
    if ($command === '--help' || $command === '-h') {
        printUsage();
        exit(0);
    }
    
    $keyManager = new KeyManager();
    
    switch ($command) {
        case 'list':
            listKeys($keyManager);
            break;
            
        case 'generate':
            generateKey($keyManager);
            break;
            
        case 'validate':
            validateKey($keyManager);
            break;
            
        case 'profile':
            showProfile($keyManager);
            break;
            
        case 'delete':
            deleteKey($keyManager);
            break;
            
        default:
            echo "❌ Unknown command: {$command}" . PHP_EOL . PHP_EOL;
            printUsage();
            exit(1);
    }
}

main($argv);
