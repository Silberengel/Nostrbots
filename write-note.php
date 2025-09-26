#!/usr/bin/env php
<?php

/**
 * Simple Nostr Note Writer
 * 
 * A simple script to write kind 1 notes (regular Nostr notes) to test relays.
 * Usage: php write-note.php "Your note content here"
 */

require_once __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\KeyManager;
use Nostrbots\Utils\RelayManager;
use Nostrbots\Utils\ErrorHandler;
use Symfony\Component\Yaml\Yaml;

class NoteWriter
{
    private KeyManager $keyManager;
    private RelayManager $relayManager;
    private ErrorHandler $errorHandler;
    private array $relays;
    
    public function __construct()
    {
        $this->errorHandler = new ErrorHandler(true);
        $this->keyManager = new KeyManager();
        $this->relayManager = new RelayManager();
        $this->loadRelays();
    }
    
    private function loadRelays(): void
    {
        $relaysFile = __DIR__ . '/src/relays.yml';
        if (!file_exists($relaysFile)) {
            throw new \Exception("Relays configuration file not found: $relaysFile");
        }
        
        // Parse YAML file using Symfony YAML parser (same as existing code)
        try {
            $relaysConfig = Yaml::parseFile($relaysFile);
        } catch (\Exception $e) {
            throw new \Exception("Failed to parse relay configuration file: " . $e->getMessage());
        }
        
        if (!is_array($relaysConfig) || !isset($relaysConfig['test-relays'])) {
            throw new \Exception("Invalid relay configuration or test-relays section not found");
        }
        
        $this->relays = $relaysConfig['test-relays'];
        
        if (empty($this->relays)) {
            throw new \Exception("No test relays configured");
        }
        
        echo "📡 Using test relays: " . implode(', ', $this->relays) . "\n";
    }
    
    public function writeNote(string $content): void
    {
        if (empty(trim($content))) {
            throw new \Exception("Note content cannot be empty");
        }
        
        if (strlen($content) > 280) {
            echo "⚠️  Warning: Note is longer than 280 characters (" . strlen($content) . " chars)\n";
        }
        
        echo "📝 Writing note: \"$content\"\n";
        
        // Get the decrypted private key
        $privateKey = $this->keyManager->getDecryptedPrivateKey();
        if (!$privateKey) {
            throw new \Exception("Failed to get decrypted private key");
        }
        
        // Get the public key
        $publicKey = $this->keyManager->getPublicKey();
        if (!$publicKey) {
            throw new \Exception("Failed to get public key");
        }
        
        echo "🔑 Using public key: $publicKey\n";
        
        // Create the event
        $event = $this->createEvent($content, $privateKey);
        
        // Publish to relays
        $this->publishToRelays($event);
        
        echo "✅ Note published successfully!\n";
    }
    
    private function createEvent(string $content, string $privateKey): array
    {
        $timestamp = time();
        
        // Create event data
        $eventData = [
            'kind' => 1,
            'created_at' => $timestamp,
            'tags' => [],
            'content' => $content,
            'pubkey' => $this->keyManager->getPublicKey()
        ];
        
        // Serialize event for signing
        $serialized = json_encode([
            0,
            $eventData['pubkey'],
            $eventData['created_at'],
            $eventData['kind'],
            $eventData['tags'],
            $eventData['content']
        ], JSON_UNESCAPED_SLASHES);
        
        // Create event hash
        $eventHash = hash('sha256', $serialized);
        
        // Sign the event
        $signature = $this->signEvent($eventHash, $privateKey);
        
        // Add signature to event
        $eventData['id'] = $eventHash;
        $eventData['sig'] = $signature;
        
        return $eventData;
    }
    
    private function signEvent(string $eventHash, string $privateKey): string
    {
        // Convert hex private key to binary
        $privateKeyBin = hex2bin($privateKey);
        
        // Sign the event hash
        $signature = sodium_crypto_sign_detached($eventHash, $privateKeyBin);
        
        // Convert signature to hex
        return bin2hex($signature);
    }
    
    private function publishToRelays(array $event): void
    {
        $successCount = 0;
        $totalRelays = count($this->relays);
        
        foreach ($this->relays as $relay) {
            try {
                echo "📡 Publishing to $relay... ";
                
                // Create WebSocket connection
                $ws = new WebSocket\Client($relay);
                
                // Send event
                $message = [
                    'type' => 'EVENT',
                    'event' => $event
                ];
                
                $ws->send(json_encode($message));
                
                // Wait for response
                $response = $ws->receive();
                $ws->close();
                
                $responseData = json_decode($response, true);
                
                if (isset($responseData['type']) && $responseData['type'] === 'OK') {
                    echo "✅ Success\n";
                    $successCount++;
                } else {
                    echo "❌ Failed: " . ($responseData['message'] ?? 'Unknown error') . "\n";
                }
                
            } catch (Exception $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n📊 Published to $successCount/$totalRelays relays\n";
        
        if ($successCount === 0) {
            throw new \Exception("Failed to publish to any relay");
        }
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        // Check if content was provided
        if ($argc < 2) {
            echo "Usage: php write-note.php \"Your note content here\"\n";
            echo "\n";
            echo "Examples:\n";
            echo "  php write-note.php \"Hello, Nostr!\"\n";
            echo "  php write-note.php \"Testing my Nostrbots setup\"\n";
            echo "  php write-note.php \"This is a longer note that might exceed 280 characters but that's okay for testing purposes\"\n";
            exit(1);
        }
        
        $content = $argv[1];
        
        // Check if it's a dry run
        $dryRun = false;
        if (isset($argv[2]) && $argv[2] === '--dry-run') {
            $dryRun = true;
            echo "🧪 DRY RUN MODE - No actual publishing will occur\n";
        }
        
        $writer = new NoteWriter();
        
        if ($dryRun) {
            echo "📝 Would write note: \"$content\"\n";
            echo "📡 Would publish to test relays\n";
            echo "✅ Dry run completed successfully!\n";
        } else {
            $writer->writeNote($content);
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
