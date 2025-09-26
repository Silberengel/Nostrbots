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
use swentel\nostr\Sign\Sign;
use swentel\nostr\Event\Event;

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
    
    private function ensureKeyExists(): void
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
        
        // 2. Check environment variable
        if (!$privateKey && getenv('NOSTR_BOT_KEY') !== false && !empty(getenv('NOSTR_BOT_KEY'))) {
            $privateKey = getenv('NOSTR_BOT_KEY');
            $keySource = 'environment variable';
            echo "🔑 Found key in environment variable\n";
        }
        
        // 3. Check for encrypted key in environment (for production setups)
        if (!$privateKey && getenv('NOSTR_BOT_KEY_ENCRYPTED') !== false && !empty(getenv('NOSTR_BOT_KEY_ENCRYPTED'))) {
            echo "🔑 Found encrypted key, attempting to decrypt...\n";
            try {
                // Try to decrypt the key
                $decryptScript = __DIR__ . '/decrypt-key.php';
                if (file_exists($decryptScript)) {
                    $output = shell_exec("php $decryptScript 2>/dev/null");
                    if ($output && !empty(trim($output))) {
                        $privateKey = trim($output);
                        $keySource = 'encrypted key';
                        echo "✅ Successfully decrypted key\n";
                    }
                }
            } catch (\Exception $e) {
                echo "⚠️  Failed to decrypt key: " . $e->getMessage() . "\n";
            }
        }
        
        // 4. Generate new key if none found
        if (!$privateKey) {
            echo "🔑 No key found in Docker secrets, environment variables, or encrypted storage\n";
            echo "   Generating new key...\n";
            
            try {
                // Generate a new key set
                $keySet = $this->keyManager->generateNewKeySet();
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
        
        // Check if we have a key, if not generate one
        $this->ensureKeyExists();
        
        // Get the private key
        $privateKey = $this->keyManager->getPrivateKey('NOSTR_BOT_KEY');
        
        // Get the key set to derive the public key
        $keySet = $this->keyManager->getKeySet($privateKey);
        $publicKey = $keySet['bechPublicKey'];
        $hexPublicKey = $keySet['hexPublicKey'];
        
        echo "🔑 Using public key: $publicKey\n";
        
        // Create the event
        $event = $this->createEvent($content, $privateKey, $publicKey, $hexPublicKey);
        
        // Publish to relays using RelayManager
        $minSuccessCount = 1; // At least one relay must succeed
        $publishResults = $this->relayManager->publishWithRetry($event, $this->relays, $minSuccessCount);
        
        $successCount = 0;
        foreach ($publishResults as $relayUrl => $success) {
            if ($success) {
                $successCount++;
                echo "✅ Published to: $relayUrl\n";
            } else {
                echo "❌ Failed to publish to: $relayUrl\n";
            }
        }
        
        if ($successCount === 0) {
            throw new \Exception("Failed to publish to any relay");
        }
        
        echo "✅ Note published successfully!\n";
    }
    
    private function createEvent(string $content, string $privateKey, string $publicKey, string $hexPublicKey): Event
    {
        // Create event using nostr-php Event class
        $event = new Event();
        $event->setKind(1); // Kind 1 = regular note
        $event->setContent($content);
        $event->setCreatedAt(time());
        $event->setTags([]);
        
        // Sign the event using nostr-php library
        $signer = new Sign();
        $signer->signEvent($event, $privateKey);
        
        return $event;
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
