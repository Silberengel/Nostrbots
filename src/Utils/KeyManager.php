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
}
