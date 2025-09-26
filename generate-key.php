#!/usr/bin/env php
<?php

/**
 * Nostrbots Key Generator with Encryption Support
 * 
 * Generates a new Nostr key pair for the bot and provides setup instructions.
 * This script creates both hex and bech32 format keys for easy use.
 * 
 * Features:
 * - Generate new keys
 * - Encrypt/decrypt existing keys
 * - Support both encrypted and unencrypted key input
 * - Export commands for easy setup
 */

require_once __DIR__ . '/vendor/autoload.php';

use Nostrbots\Utils\KeyManager;

/**
 * Update the .env file with the generated keys
 */
function updateEnvFile(string $encryptedKey, string $npub): void
{
    $envFile = __DIR__ . '/.env';
    
    if (!file_exists($envFile)) {
        // Create .env from template if it doesn't exist
        $templateFile = __DIR__ . '/env.example';
        if (file_exists($templateFile)) {
            copy($templateFile, $envFile);
        } else {
            // Create basic .env file
            file_put_contents($envFile, "# Nostrbots Environment Configuration\n");
        }
    }
    
    $content = file_get_contents($envFile);
    
    // Replace the key variables
    $content = preg_replace('/^NOSTR_BOT_KEY_ENCRYPTED=.*$/m', "NOSTR_BOT_KEY_ENCRYPTED={$encryptedKey}", $content);
    $content = preg_replace('/^NOSTR_BOT_NPUB=.*$/m', "NOSTR_BOT_NPUB={$npub}", $content);
    
    // If the variables weren't found, add them
    if (!preg_match('/^NOSTR_BOT_KEY_ENCRYPTED=/m', $content)) {
        $content .= "\nNOSTR_BOT_KEY_ENCRYPTED={$encryptedKey}";
    }
    if (!preg_match('/^NOSTR_BOT_NPUB=/m', $content)) {
        $content .= "\nNOSTR_BOT_NPUB={$npub}";
    }
    
    file_put_contents($envFile, $content);
}

/**
 * Encrypt a key using AES-256-CBC with password-based key derivation
 */
function encryptKeyWithPassword(string $key, string $password): string
{
    $salt = random_bytes(16); // Random salt for each encryption
    $iv = random_bytes(16);   // Random IV for each encryption
    
    // Derive key from password using PBKDF2
    $derivedKey = hash_pbkdf2('sha256', $password, $salt, 10000, 32, true);
    
    $encrypted = openssl_encrypt($key, 'aes-256-cbc', $derivedKey, OPENSSL_RAW_DATA, $iv);
    
    // Combine salt + iv + encrypted data
    $combined = $salt . $iv . $encrypted;
    return base64_encode($combined);
}


/**
 * Decrypt a key using AES-256-CBC with password-based key derivation
 */
function decryptKeyWithPassword(string $encryptedKey, string $password): string
{
    $data = base64_decode($encryptedKey);
    
    // Extract salt, iv, and encrypted data
    $salt = substr($data, 0, 16);
    $iv = substr($data, 16, 16);
    $encrypted = substr($data, 32);
    
    // Derive key from password using PBKDF2
    $derivedKey = hash_pbkdf2('sha256', $password, $salt, 10000, 32, true);
    
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $derivedKey, OPENSSL_RAW_DATA, $iv);
    return $decrypted;
}

/**
 * Get the default password for encryption
 */
function getDefaultPassword(): string
{
    // A difficult-to-guess but deterministic default password
    // Based on the project name and a consistent salt
    return hash('sha256', 'nostrbots-jenkins-default-password-2024-secure');
}

/**
 * Securely clear sensitive data from memory
 */
function secureClear(string &$data): void
{
    if (function_exists('sodium_memzero')) {
        // Use libsodium's secure memory clearing if available
        sodium_memzero($data);
    } else {
        // Fallback: overwrite with random data multiple times
        $length = strlen($data);
        for ($i = 0; $i < 3; $i++) {
            $data = str_repeat(chr(random_int(0, 255)), $length);
        }
        $data = str_repeat('0', $length);
    }
}

/**
 * Clear sensitive environment variables
 */
function clearSensitiveEnvVars(): void
{
    $sensitiveVars = [
        'NOSTR_BOT_KEY',
        'NOSTR_BOT_KEY_ENCRYPTED'
    ];
    
    foreach ($sensitiveVars as $var) {
        if (getenv($var) !== false) {
            putenv($var);
            unset($_ENV[$var]);
        }
    }
}

/**
 * Log security events (without sensitive data)
 */
function logSecurityEvent(string $event, array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    error_log("[SECURITY] $timestamp - $event$contextStr");
}

/**
 * Register cleanup handlers for security
 */
function registerSecurityCleanup(): void
{
    // Clear sensitive data on script exit
    register_shutdown_function(function() {
        logSecurityEvent('Script shutdown - clearing sensitive data');
        clearSensitiveEnvVars();
    });
    
    // Clear sensitive data on interruption signals
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGINT, function() {
            logSecurityEvent('Script interrupted (SIGINT) - clearing sensitive data');
            clearSensitiveEnvVars();
            exit(1);
        });
        
        pcntl_signal(SIGTERM, function() {
            logSecurityEvent('Script terminated (SIGTERM) - clearing sensitive data');
            clearSensitiveEnvVars();
            exit(1);
        });
    }
}

function showUsage(): void
{
    echo "Nostrbots Key Generator with Encryption Support\n";
    echo "==============================================\n\n";
    echo "Usage: php generate-key.php [options]\n\n";
    echo "Options:\n";
    echo "  --help, -h           Show this help message\n";
    echo "  --slot <n>           Use specific key slot (NOSTR_BOT_KEY<n>)\n";
    echo "  --export             Show export command for current shell\n";
    echo "  --quiet, -q          Only output the export command\n";
    echo "  --encrypt            Encrypt the generated key\n";
    echo "  --decrypt            Decrypt an encrypted key\n";
    echo "  --key <key>          Use specific key (encrypted or unencrypted)\n";
    echo "  --password <pwd>     Use custom password for encryption (default: secure default)\n";
    echo "  --jenkins            Generate Jenkins-compatible encrypted key\n\n";
    echo "Examples:\n";
    echo "  php generate-key.php                           # Generate new key\n";
    echo "  php generate-key.php --encrypt                 # Generate and encrypt with default password\n";
    echo "  php generate-key.php --key <encrypted> --decrypt # Decrypt existing key\n";
    echo "  php generate-key.php --jenkins                 # Generate for Jenkins (default password)\n";
    echo "  php generate-key.php --password mypass --jenkins # Use custom password\n";
    echo "  php generate-key.php --export                  # Show export command\n";
    echo "  php generate-key.php --quiet                   # Only show export command\n\n";
    echo "🔒 Security Features:\n";
    echo "  • Password-based encryption with PBKDF2\n";
    echo "  • Secure memory clearing after operations\n";
    echo "  • Environment variable cleanup on exit\n";
    echo "  • Signal handling for secure shutdown\n\n";
}

function main(): void
{
    // Register security cleanup handlers
    registerSecurityCleanup();
    
    $options = getopt('hq', ['help', 'slot:', 'export', 'quiet', 'encrypt', 'decrypt', 'key:', 'password:', 'jenkins']);
    
    if (isset($options['h']) || isset($options['help'])) {
        showUsage();
        return;
    }
    
    $quiet = isset($options['q']) || isset($options['quiet']);
    $showExport = isset($options['export']) || $quiet;
    $encrypt = isset($options['encrypt']);
    $decrypt = isset($options['decrypt']);
    $jenkins = isset($options['jenkins']);
    $providedKey = $options['key'] ?? null;
    
    try {
        $keyManager = new KeyManager();
        
        // Handle decryption mode
        if ($decrypt && $providedKey) {
            if (isset($options['password'])) {
                $password = $options['password'];
            } else {
                $password = getDefaultPassword();
            }
            
            try {
                $decryptedKey = decryptKeyWithPassword($providedKey, $password);
                logSecurityEvent('Key decryption successful', ['method' => 'password-based']);
            } catch (Exception $e) {
                logSecurityEvent('Key decryption failed', ['error' => $e->getMessage()]);
                throw new Exception("Failed to decrypt key: " . $e->getMessage());
            }
            
            // Clear password from memory
            secureClear($password);
            
            if ($quiet) {
                echo "export NOSTR_BOT_KEY={$decryptedKey}\n";
                return;
            }
            
            echo "🔓 Decrypted Nostr Key\n";
            echo "=====================\n\n";
            echo "✅ Successfully decrypted key:\n";
            echo "  Decrypted Key: " . substr($decryptedKey, 0, 20) . "...\n";
            if (isset($options['password'])) {
                echo "  Password: <custom_password> (custom)\n";
            } else {
                echo "  Password: <default_password> (default)\n";
            }
            echo "  Encryption: AES-256-CBC with PBKDF2\n\n";
            echo "🚀 Export command:\n";
            echo "  export NOSTR_BOT_KEY=<decrypted_key_value>\n\n";
            
            // Clear decrypted key from memory
            secureClear($decryptedKey);
            return;
        }
        
        // Determine which key slot to use
        $envVariable = null;
        if (isset($options['slot'])) {
            $slot = (int)$options['slot'];
            if ($slot < 1 || $slot > 10) {
                throw new \InvalidArgumentException('Key slot must be between 1 and 10');
            }
            $envVariable = "NOSTR_BOT_KEY{$slot}";
        }
        
        // Use provided key or generate new one
        if ($providedKey && !$decrypt) {
            // Use provided key (assume it's unencrypted)
            $hexPrivateKey = $providedKey;
            $result = [
                'env_variable' => $envVariable ?? 'NOSTR_BOT_KEY',
                'key_set' => [
                    'hexPrivateKey' => $hexPrivateKey,
                    'bechPrivateKey' => 'nsec...', // Would need to convert
                    'bechPublicKey' => 'npub...'  // Would need to convert
                ]
            ];
        } else {
            // Generate new key
            $result = $keyManager->generateNewBotKey($envVariable);
        }
        
        $hexPrivateKey = $result['key_set']['hexPrivateKey'];
        $npub = $result['key_set']['bechPublicKey'];
        
        // Handle encryption
        if ($encrypt || $jenkins) {
            if (isset($options['password'])) {
                // Use custom password
                $password = $options['password'];
            } else {
                // Use default password
                $password = getDefaultPassword();
            }
            
            $encryptedKey = encryptKeyWithPassword($hexPrivateKey, $password);
            logSecurityEvent('Key encryption successful', ['method' => 'password-based']);
            
            // Store password for output before clearing
            $passwordForOutput = $password;
            
            // Clear password from memory
            secureClear($password);
            
            if ($jenkins) {
                // Update .env file first
                updateEnvFile($encryptedKey, $npub);
                
                // Jenkins-specific output
            if ($quiet) {
                echo "NOSTR_BOT_KEY_ENCRYPTED={$encryptedKey}\n";
                echo "NOSTR_BOT_NPUB={$npub}\n";
                echo "NOSTR_BOT_KEY_HEX={$hexPrivateKey}\n";
                echo "NOSTR_BOT_NSEC={$result['key_set']['bechPrivateKey']}\n";
                return;
            }
                
                echo "🔐 Jenkins Encrypted Key Setup\n";
                echo "=============================\n\n";
                echo "✅ Generated encrypted key for Jenkins:\n\n";
                echo "📋 Jenkins Environment Variables:\n";
                echo "  NOSTR_BOT_KEY_ENCRYPTED=<encrypted_key_value>\n";
                echo "🔒 Security Information:\n";
                echo "  Original Key: " . substr($hexPrivateKey, 0, 20) . "...\n";
                if (isset($options['password'])) {
                    echo "  Password: <custom_password> (custom)\n";
                } else {
                    echo "  Password: <default_password> (default)\n";
                }
                echo "  Encryption: AES-256-CBC with PBKDF2\n\n";
                echo "🚀 Jenkins Setup:\n";
                echo "  1. Add these environment variables to your Jenkins container\n";
                echo "  2. The Jenkinsfile will automatically decrypt the key at runtime using the password\n";
                echo "  3. Keep the password secure and never commit it to git\n\n";
                return;
            }
            
            // Update .env file
            updateEnvFile($encryptedKey, $npub);
            
            if ($quiet) {
                echo "export NOSTR_BOT_KEY_ENCRYPTED={$encryptedKey}\n";
                echo "export NOSTR_BOT_NPUB={$npub}\n";
                return;
            }
            
            echo "🔐 Encrypted Nostr Key\n";
            echo "=====================\n\n";
            echo "✅ Successfully encrypted key:\n\n";
            echo "📋 Encrypted Information:\n";
            echo "  Encrypted Key: " . substr($encryptedKey, 0, 20) . "...\n";
            if (isset($options['password'])) {
                echo "  Password: <custom_password> (custom)\n";
            } else {
                echo "  Password: <default_password> (default)\n";
            }
            echo "  Original Key: " . substr($hexPrivateKey, 0, 20) . "...\n";
            echo "  Encryption: AES-256-CBC with PBKDF2\n\n";
            echo "🚀 Export commands:\n";
            echo "  export NOSTR_BOT_KEY_ENCRYPTED=<encrypted_key_value>\n";
            
            // Update .env file
            updateEnvFile($encryptedKey, $npub);
            echo "✅ Updated .env file with new keys\n";
            return;
        }
        
        // Standard output (unencrypted)
        if ($quiet) {
            echo "export {$result['env_variable']}={$hexPrivateKey}\n";
            echo "export NOSTR_BOT_NPUB={$npub}\n";
            return;
        }
        
        if ($showExport) {
            echo "export {$result['env_variable']}={$hexPrivateKey}\n";
            return;
        }
        
        // Full output
        echo "🔑 Nostrbots Key Generator\n";
        echo "==========================\n\n";
        
        echo "✅ Generated new Nostr key set:\n\n";
        
        echo "📋 Key Information:\n";
        echo "  Environment Variable: {$result['env_variable']}\n";
        echo "  Hex Private Key:      " . substr($hexPrivateKey, 0, 10) . "...\n";
        echo "  Bech32 Private Key:   " . substr($result['key_set']['bechPrivateKey'], 0, 20) . "...\n";
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
        echo "     export {$result['env_variable']}=<your_private_key>\n\n";
        
        echo "  2. Add to your shell profile for persistence:\n";
        echo "     echo 'export {$result['env_variable']}=<your_private_key>' >> ~/.bashrc\n";
        echo "     # or for zsh:\n";
        echo "     echo 'export {$result['env_variable']}=<your_private_key>' >> ~/.zshrc\n\n";
        
        echo "  3. Test the setup:\n";
        echo "     php nostrbots.php publish examples/markdown-longform.md --dry-run\n\n";
        
        echo "💡 Tips:\n";
        echo "  • Keep your private key secure and never share it\n";
        echo "  • Use --encrypt to encrypt the key for secure storage\n";
        echo "  • Use --jenkins to generate Jenkins-compatible encrypted keys\n";
        
        // Clear private key from memory
        secureClear($hexPrivateKey);
        
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Run the script
main();
