<?php
/**
 * Simple key decryption script for Jenkins pipeline
 * Only outputs the decrypted key, no other text
 */

/**
 * Get the default password for encryption (copied from generate-key.php)
 */
function getDefaultPassword(): string
{
    // A difficult-to-guess but deterministic default password
    // Based on the project name and a consistent salt
    return hash('sha256', 'nostrbots-jenkins-default-password-2024-secure');
}

/**
 * Decrypt a key using AES-256-CBC with password-based key derivation (copied from generate-key.php)
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

// Get the encrypted key from environment
$encryptedKey = getenv('NOSTR_BOT_KEY_ENCRYPTED');
if (empty($encryptedKey)) {
    fwrite(STDERR, "Error: NOSTR_BOT_KEY_ENCRYPTED environment variable not set\n");
    exit(1);
}

try {
    // Get the default password and decrypt the key
    $password = getDefaultPassword();
    $decryptedKey = decryptKeyWithPassword($encryptedKey, $password);
    
    // Output only the decrypted key (no extra text)
    if (empty($decryptedKey)) {
        fwrite(STDERR, "Error: Decrypted key is empty\n");
        exit(1);
    }
    
    echo trim($decryptedKey);
} catch (Exception $e) {
    fwrite(STDERR, "Error decrypting key: " . $e->getMessage() . "\n");
    exit(1);
}
