<?php

/**
 * Key Generation Utility for Nostrbots
 * 
 * Generates new Nostr key pairs for bot usage.
 * Usage: php generate-keys.php [--env-var ENV_VAR_NAME]
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\KeyManager;

function main(array $argv): void
{
    $argc = count($argv);
    $envVarName = 'NOSTR_BOT_KEY1';

    // Parse command line arguments
    for ($i = 1; $i < $argc; $i++) {
        if ($argv[$i] === '--env-var' && isset($argv[$i + 1])) {
            $envVarName = $argv[$i + 1];
            $i++; // Skip the next argument
        }
    }

    echo "🔑 Nostrbots Key Generator" . PHP_EOL;
    echo "=========================" . PHP_EOL . PHP_EOL;

    try {
        $keyManager = new KeyManager();
        $keySet = $keyManager->generateNewKeySet();

        echo "✅ New key pair generated successfully!" . PHP_EOL . PHP_EOL;

        echo "🔐 Private Key (hex): " . $keySet['hexPrivateKey'] . PHP_EOL;
        echo "🔐 Private Key (nsec): " . $keySet['bechPrivateKey'] . PHP_EOL;
        echo "🆔 Public Key (hex): " . $keySet['hexPublicKey'] . PHP_EOL;
        echo "🆔 Public Key (npub): " . $keySet['bechPublicKey'] . PHP_EOL . PHP_EOL;

        echo "⚠️  SECURITY WARNING:" . PHP_EOL;
        echo "   • Keep your private key secret!" . PHP_EOL;
        echo "   • Never share your private key with anyone" . PHP_EOL;
        echo "   • Store it securely (password manager, encrypted file, etc.)" . PHP_EOL . PHP_EOL;

        echo "📋 Setup Instructions:" . PHP_EOL;
        echo "1. Set the environment variable (you can use either format):" . PHP_EOL;
        echo "   # Hex format (recommended for scripts):" . PHP_EOL;
        echo "   export {$envVarName}={$keySet['hexPrivateKey']}" . PHP_EOL . PHP_EOL;
        echo "   # Bech32 format (human-readable):" . PHP_EOL;
        echo "   export {$envVarName}={$keySet['bechPrivateKey']}" . PHP_EOL . PHP_EOL;

        echo "2. Update your bot configuration file:" . PHP_EOL;
        echo "   npub:" . PHP_EOL;
        echo "     environment_variable: \"{$envVarName}\"" . PHP_EOL;
        echo "     public_key: \"{$keySet['bechPublicKey']}\"" . PHP_EOL . PHP_EOL;

        echo "3. For Jenkins, add '{$envVarName}' as a 'Secret text' credential" . PHP_EOL;
        echo "   with either the hex or bech32 private key as the value." . PHP_EOL . PHP_EOL;

        echo "🎉 You're ready to start using Nostrbots!" . PHP_EOL;

    } catch (\Exception $e) {
        echo "❌ Error generating keys: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

main($argv);
