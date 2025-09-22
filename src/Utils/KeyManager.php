<?php

namespace Nostrbots\Utils;

use swentel\nostr\Key\Key;

/**
 * Manages Nostr keys for the bot system
 * 
 * Handles key validation, environment variable management, and key generation.
 */
class KeyManager
{
    /**
     * Get a private key from environment variable and validate it
     * 
     * @param string $envVariable Environment variable name
     * @param string $expectedPubkey Expected public key (npub format)
     * @return string The hex private key
     * @throws \InvalidArgumentException If key is invalid or doesn't match
     */
    public function getPrivateKey(string $envVariable, string $expectedPubkey): string
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

        // Normalize the expected public key to bech32 format
        $normalizedExpectedPubkey = $this->normalizePublicKey($expectedPubkey);

        // Validate that the private key matches the expected public key
        $keySet = $this->getKeySet($privateKey);
        if ($keySet['bechPublicKey'] !== $normalizedExpectedPubkey) {
            throw new \InvalidArgumentException("Private key does not match the expected public key");
        }

        echo "✓ Key validation successful for {$expectedPubkey}" . PHP_EOL;
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
}
