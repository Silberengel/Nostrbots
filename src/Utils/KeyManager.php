<?php

namespace Nostrbots\Utils;

use swentel\nostr\Key\Key;

/**
 * Manages Nostr keys for the bot system
 * 
 * Handles key validation, environment variable management, key generation,
 * and profile fetching from Nostr relays.
 */
class KeyManager
{
    private const PROFILE_RELAY = 'wss://profiles.nostr1.com';
    private const MAX_KEYS = 10; // Support up to NOSTR_BOT_KEY1 through NOSTR_BOT_KEY10
    /**
     * Get a private key from environment variable and validate it
     * 
     * @param string $envVariable Environment variable name
     * @param string|null $expectedPubkey Expected public key (npub format), null to derive from private key
     * @return string The hex private key
     * @throws \InvalidArgumentException If key is invalid or doesn't match
     */
    public function getPrivateKey(string $envVariable, ?string $expectedPubkey = null): string
    {
        // Check if environment variable is set
        $privateKey = getenv($envVariable);
        if ($privateKey === false || empty($privateKey)) {
            throw new \InvalidArgumentException("Environment variable '{$envVariable}' is not set or is empty");
        }

        // Convert bech32 nsec to hex if needed
        if (str_starts_with($privateKey, 'nsec')) {
            try {
                $key = new Key();
                $privateKey = $key->convertToHex($privateKey);
                echo "✓ Converted nsec to hex format" . PHP_EOL;
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Invalid nsec format: " . $e->getMessage());
            }
        }

        // Validate hex format
        if (!ctype_xdigit($privateKey) || strlen($privateKey) !== 64) {
            throw new \InvalidArgumentException("Private key must be a 64-character hex string or valid nsec format");
        }

        // Get the key set to derive the public key
        $keySet = $this->getKeySet($privateKey);
        
        // If expected public key is provided, validate it matches
        if ($expectedPubkey !== null) {
            $normalizedExpectedPubkey = $this->normalizePublicKey($expectedPubkey);
            if ($keySet['bechPublicKey'] !== $normalizedExpectedPubkey) {
                throw new \InvalidArgumentException("Private key does not match the expected public key");
            }
            echo "✓ Key validation successful for {$expectedPubkey}" . PHP_EOL;
        } else {
            echo "✓ Key validation successful for {$keySet['bechPublicKey']}" . PHP_EOL;
        }
        return $privateKey;
    }

    /**
     * Generate a complete key set from a hex private key
     * 
     * @param string $hexPrivateKey The hex private key
     * @return array Key set with hex and bech32 formats
     * @throws \InvalidArgumentException If key format is invalid
     */
    public function getKeySet(string $hexPrivateKey): array
    {
        if (str_starts_with($hexPrivateKey, 'nsec')) {
            throw new \InvalidArgumentException("Please use hex private keys, not nsec format");
        }

        if (!ctype_xdigit($hexPrivateKey) || strlen($hexPrivateKey) !== 64) {
            throw new \InvalidArgumentException("Private key must be a 64-character hex string");
        }

        try {
            $key = new Key();
            
            // Get public key in hex format
            $hexPublicKey = $key->getPublicKey($hexPrivateKey);
            
            // Convert to bech32 formats
            $bechPrivateKey = $key->convertPrivateKeyToBech32($hexPrivateKey);
            $bechPublicKey = $key->convertPublicKeyToBech32($hexPublicKey);

            return [
                'hexPrivateKey' => $hexPrivateKey,
                'hexPublicKey' => $hexPublicKey,
                'bechPrivateKey' => $bechPrivateKey,
                'bechPublicKey' => $bechPublicKey,
            ];

        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Failed to process key: " . $e->getMessage());
        }
    }

    /**
     * Generate a new key set
     * 
     * @return array Complete key set
     */
    public function generateNewKeySet(): array
    {
        $key = new Key();
        $hexPrivateKey = $key->generatePrivateKey();
        
        return $this->getKeySet($hexPrivateKey);
    }

    /**
     * Normalize a public key to bech32 npub format
     * 
     * @param string $publicKey The public key in any format (hex or bech32)
     * @return string The normalized bech32 npub
     * @throws \InvalidArgumentException If key format is invalid
     */
    public function normalizePublicKey(string $publicKey): string
    {
        // If it's already bech32 npub, validate and return
        if (str_starts_with($publicKey, 'npub1')) {
            if (strlen($publicKey) !== 63) {
                throw new \InvalidArgumentException("Invalid npub format: wrong length");
            }
            return $publicKey;
        }

        // If it's hex format, convert to bech32
        if (ctype_xdigit($publicKey) && strlen($publicKey) === 64) {
            try {
                $key = new Key();
                return $key->convertPublicKeyToBech32($publicKey);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Invalid hex public key: " . $e->getMessage());
            }
        }

        throw new \InvalidArgumentException("Public key must be a 64-character hex string or valid npub format");
    }

    /**
     * Validate a public key (npub) format
     * 
     * @param string $npub The npub to validate
     * @return bool True if valid
     */
    public function validateNpub(string $npub): bool
    {
        return str_starts_with($npub, 'npub1') && strlen($npub) === 63;
    }

    /**
     * Check if an environment variable is properly set
     * 
     * @param string $envVariable Environment variable name
     * @return bool True if set and not empty
     */
    public function isEnvironmentVariableSet(string $envVariable): bool
    {
        $value = getenv($envVariable);
        return $value !== false && !empty($value);
    }

    /**
     * Get environment variable setup instructions
     * 
     * @param string $envVariable Environment variable name
     * @param string $hexPrivateKey The private key to set
     * @return string Setup instructions
     */
    public function getSetupInstructions(string $envVariable, string $hexPrivateKey): string
    {
        return "To set up the environment variable, run:\n" .
               "export {$envVariable}={$hexPrivateKey}\n\n" .
               "For Jenkins, add this as a 'Secret text' credential with ID '{$envVariable}'\n" .
               "at http://your-jenkins-server/credentials/";
    }

    /**
     * Get all available bot keys from environment variables
     * 
     * @return array Array of key information with npub, profile data, and env var name
     */
    public function getAllBotKeys(): array
    {
        $keys = [];
        
        for ($i = 1; $i <= self::MAX_KEYS; $i++) {
            $envVar = "NOSTR_BOT_KEY{$i}";
            
            if (!$this->isEnvironmentVariableSet($envVar)) {
                continue; // Skip if this key doesn't exist
            }
            
            try {
                $privateKey = getenv($envVar);
                
                // Convert nsec to hex if needed
                if (str_starts_with($privateKey, 'nsec')) {
                    $key = new Key();
                    $privateKey = $key->convertToHex($privateKey);
                }
                
                // Validate hex format
                if (!ctype_xdigit($privateKey) || strlen($privateKey) !== 64) {
                    continue; // Skip invalid keys
                }
                
                $keySet = $this->getKeySet($privateKey);
                $profile = $this->fetchProfile($keySet['bechPublicKey']);
                
                $keys[] = [
                    'env_variable' => $envVar,
                    'npub' => $keySet['bechPublicKey'],
                    'hex_public_key' => $keySet['hexPublicKey'],
                    'profile' => $profile,
                    'display_name' => $profile['display_name'] ?? $profile['name'] ?? 'Unknown',
                    'profile_pic' => $profile['picture'] ?? null,
                ];
                
            } catch (\Exception $e) {
                // Skip keys that can't be processed
                continue;
            }
        }
        
        return $keys;
    }

    /**
     * Get a specific bot key by environment variable name
     * 
     * @param string $envVariable Environment variable name
     * @return array|null Key information or null if not found
     */
    public function getBotKey(string $envVariable): ?array
    {
        if (!$this->isEnvironmentVariableSet($envVariable)) {
            return null;
        }
        
        try {
            $privateKey = getenv($envVariable);
            
            // Convert nsec to hex if needed
            if (str_starts_with($privateKey, 'nsec')) {
                $key = new Key();
                $privateKey = $key->convertToHex($privateKey);
            }
            
            // Validate hex format
            if (!ctype_xdigit($privateKey) || strlen($privateKey) !== 64) {
                return null;
            }
            
            $keySet = $this->getKeySet($privateKey);
            $profile = $this->fetchProfile($keySet['bechPublicKey']);
            
            return [
                'env_variable' => $envVariable,
                'npub' => $keySet['bechPublicKey'],
                'hex_public_key' => $keySet['hexPublicKey'],
                'hex_private_key' => $privateKey,
                'profile' => $profile,
                'display_name' => $profile['display_name'] ?? $profile['name'] ?? 'Unknown',
                'profile_pic' => $profile['picture'] ?? null,
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch profile information from Nostr relay
     * 
     * @param string $npub The npub to fetch profile for
     * @return array Profile data
     */
    public function fetchProfile(string $npub): array
    {
        // For now, return a basic structure
        // In a full implementation, this would connect to the relay and fetch profile data
        return [
            'name' => null,
            'display_name' => null,
            'about' => null,
            'picture' => null,
            'banner' => null,
            'website' => null,
            'lud16' => null,
            'nip05' => null,
        ];
    }

    /**
     * Get the next available environment variable name for a new key
     * 
     * @return string|null Next available env var name or null if all slots are full
     */
    public function getNextAvailableKeySlot(): ?string
    {
        // First check the default slot
        if (!$this->isEnvironmentVariableSet('NOSTR_BOT_KEY')) {
            return 'NOSTR_BOT_KEY';
        }
        
        // Then check numbered slots
        for ($i = 1; $i <= self::MAX_KEYS; $i++) {
            $envVar = "NOSTR_BOT_KEY{$i}";
            if (!$this->isEnvironmentVariableSet($envVar)) {
                return $envVar;
            }
        }
        
        return null; // All slots are full
    }

    /**
     * Generate a new key and return setup information
     * 
     * @param string|null $envVariable Specific env var name, or null to use next available
     * @return array Key generation result with setup instructions
     */
    public function generateNewBotKey(?string $envVariable = null): array
    {
        if ($envVariable === null) {
            $envVariable = $this->getNextAvailableKeySlot();
            if ($envVariable === null) {
                throw new \RuntimeException('No available key slots. Maximum of ' . self::MAX_KEYS . ' keys supported.');
            }
        }
        
        $keySet = $this->generateNewKeySet();
        $profile = $this->fetchProfile($keySet['bechPublicKey']);
        
        return [
            'env_variable' => $envVariable,
            'key_set' => $keySet,
            'profile' => $profile,
            'setup_instructions' => $this->getSetupInstructions($envVariable, $keySet['hexPrivateKey']),
        ];
    }

    /**
     * Validate that a key exists and is properly configured
     * 
     * @param string $envVariable Environment variable name
     * @return bool True if key is valid and configured
     */
    public function validateBotKey(string $envVariable): bool
    {
        $key = $this->getBotKey($envVariable);
        return $key !== null;
    }

    /**
     * Ensure a Nostr key is available from various sources with fallback mechanisms
     * 
     * @return string The private key (hex format)
     * @throws \Exception If no key can be found or generated
     */
    public function ensureKeyExists(): string
    {
        $privateKey = null;
        $keySource = '';
        
        // 1. Check Docker secrets first (for containerized environments)
        $dockerSecretPath = '/run/secrets/nostr_bot_key';
        if (file_exists($dockerSecretPath)) {
            $privateKey = trim(file_get_contents($dockerSecretPath));
            $keySource = 'Docker secret';
            echo "🔑 Found key in Docker secret\n";
        }
        
        // 2. Check environment variables (NOSTR_BOT_KEY first, then CUSTOM_PRIVATE_KEY)
        if (!$privateKey && getenv('NOSTR_BOT_KEY') !== false && !empty(getenv('NOSTR_BOT_KEY'))) {
            $privateKey = getenv('NOSTR_BOT_KEY');
            $keySource = 'NOSTR_BOT_KEY environment variable';
            echo "🔑 Found key in NOSTR_BOT_KEY environment variable\n";
        } elseif (!$privateKey && getenv('CUSTOM_PRIVATE_KEY') !== false && !empty(getenv('CUSTOM_PRIVATE_KEY'))) {
            $privateKey = getenv('CUSTOM_PRIVATE_KEY');
            $keySource = 'CUSTOM_PRIVATE_KEY environment variable';
            echo "🔑 Found key in CUSTOM_PRIVATE_KEY environment variable\n";
        }
        
        // 3. Check for encrypted key in environment (for production setups)
        if (!$privateKey && getenv('NOSTR_BOT_KEY_ENCRYPTED') !== false && !empty(getenv('NOSTR_BOT_KEY_ENCRYPTED'))) {
            echo "🔑 Found encrypted key, attempting to decrypt...\n";
            try {
                $privateKey = $this->decryptKey(getenv('NOSTR_BOT_KEY_ENCRYPTED'));
                if ($privateKey) {
                    $keySource = 'encrypted key';
                    echo "✅ Successfully decrypted key\n";
                }
            } catch (\Exception $e) {
                echo "⚠️  Failed to decrypt key: " . $e->getMessage() . "\n";
            }
        }
        
        // 4. Check for encrypted key in .env file
        if (!$privateKey) {
            $envFile = __DIR__ . '/../../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                        list($key, $value) = explode('=', $line, 2);
                        if ($key === 'NOSTR_BOT_KEY_ENCRYPTED' && !empty($value)) {
                            echo "🔑 Found encrypted key in .env file, attempting to decrypt...\n";
                            try {
                                $privateKey = $this->decryptKey($value);
                                if ($privateKey) {
                                    $keySource = 'encrypted key from .env';
                                    echo "✅ Successfully decrypted key from .env\n";
                                    break;
                                }
                            } catch (\Exception $e) {
                                echo "⚠️  Failed to decrypt key from .env: " . $e->getMessage() . "\n";
                            }
                        }
                    }
                }
            }
        }
        
        // 5. Generate new key if none found
        if (!$privateKey) {
            echo "🔑 No key found in Docker secrets, environment variables, or encrypted storage\n";
            echo "   Generating new key...\n";
            
            try {
                // Generate a new key set
                $keySet = $this->generateNewKeySet();
                $privateKey = $keySet['hexPrivateKey'];
                $keySource = 'newly generated';
                
                // Set the environment variable for this session
                putenv('NOSTR_BOT_KEY=' . $privateKey);
                
                echo "✅ Generated new key set:\n";
                echo "   Public Key (npub): " . $keySet['bechPublicKey'] . "\n";
                echo "   Private Key: " . substr($privateKey, 0, 8) . "...\n";
                echo "   Environment variable NOSTR_BOT_KEY has been set for this session\n";
                echo "   To persist this key, run: export NOSTR_BOT_KEY=" . $privateKey . "\n\n";
                
            } catch (\Exception $e) {
                throw new \Exception("Failed to generate key: " . $e->getMessage());
            }
        } else {
            echo "✅ Using existing key from $keySource\n";
        }
        
        // Ensure the key is set in the environment for this session
        if (!getenv('NOSTR_BOT_KEY')) {
            putenv('NOSTR_BOT_KEY=' . $privateKey);
        }
        
        // If we found a CUSTOM_PRIVATE_KEY, also set it as NOSTR_BOT_KEY for compatibility
        if ($keySource === 'CUSTOM_PRIVATE_KEY environment variable' && !getenv('NOSTR_BOT_KEY')) {
            putenv('NOSTR_BOT_KEY=' . $privateKey);
            echo "🔧 Set NOSTR_BOT_KEY from CUSTOM_PRIVATE_KEY for compatibility\n";
        }
        
        return $privateKey;
    }

    /**
     * Decrypt a key using the decrypt-key.php script
     * 
     * @param string $encryptedKey The encrypted key
     * @return string|null The decrypted key or null if decryption failed
     */
    private function decryptKey(string $encryptedKey): ?string
    {
        try {
            // Try to decrypt the key using the decrypt-key.php script
            $decryptScript = __DIR__ . '/../../decrypt-key.php';
            if (file_exists($decryptScript)) {
                $output = shell_exec("php $decryptScript 2>/dev/null");
                if ($output && !empty(trim($output))) {
                    return trim($output);
                }
            }
        } catch (\Exception $e) {
            // Decryption failed, return null
        }
        
        return null;
    }
}
