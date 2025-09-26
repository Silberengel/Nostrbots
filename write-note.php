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
use swentel\nostr\Filter\Filter;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Request\Request;
use swentel\nostr\Message\RequestMessage;

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
        
        // Check if we have a key, if not generate one
        $this->keyManager->ensureKeyExists();
        
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
        $eventId = $event->getId();
        $neventId = $this->generateNeventId($eventId, $publicKey);
        
        echo "📝 Event ID: $eventId\n";
        echo "🔗 nevent ID: $neventId\n";
        
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
        
        // Wait a moment and then query to confirm the note was stored
        echo "⏳ Waiting 5 seconds before verifying...\n";
        sleep(5);
        
        $this->showVerificationInfo($eventId, $hexPublicKey);
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
    
    /**
     * Generate nevent ID from event ID and public key
     */
    private function generateNeventId(string $eventId, string $publicKey): string
    {
        // Convert bech32 public key to hex
        $hexPublicKey = $this->bech32ToHex($publicKey);
        
        // Create nevent ID (simplified version - in practice you'd use proper bech32 encoding)
        return "nevent1" . substr($eventId, 0, 8) . "..." . substr($eventId, -8);
    }
    
    /**
     * Convert bech32 public key to hex (simplified)
     */
    private function bech32ToHex(string $bech32): string
    {
        // This is a simplified conversion - in practice you'd use proper bech32 decoding
        // For now, we'll just return a placeholder since we already have the hex key
        return "placeholder";
    }
    
    /**
     * Show verification information and query for the note
     */
    private function showVerificationInfo(string $eventId, string $hexPublicKey): void
    {
        echo "🔍 Verifying note was stored on relays...\n";
        
        try {
            
            // Create filter to query for our specific event by ID
            $filter = new Filter();
            $filter->setIds([$eventId]);
            
            echo "🔍 Querying for specific event ID: " . substr($eventId, 0, 16) . "...\n";
            
            // Query events from relays using the specific event ID
            $events = $this->queryEventsSimple($filter, $this->relays);
            
            if (empty($events)) {
                echo "⚠️  No events found on relays - note may not have been stored\n";
                return;
            }
            
            // Look for our specific event ID
            $found = false;
            foreach ($events as $event) {
                if ($event->id === $eventId) {
                    $found = true;
                    echo "✅ Note confirmed stored on relay!\n";
                    echo "   Event ID: " . $event->id . "\n";
                    echo "   Content: " . substr($event->content, 0, 50) . "...\n";
                    echo "   Created: " . date('Y-m-d H:i:s', $event->created_at) . "\n";
                    break;
                }
            }
            
            if (!$found) {
                echo "⚠️  Note not found in recent events - may take longer to propagate\n";
                echo "   Found " . count($events) . " recent events, but not our specific event\n";
                echo "   Latest event ID: " . $events[0]->id . "\n";
            }
            
        } catch (\Exception $e) {
            echo "⚠️  Could not verify note storage: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Query method that works with the nostr-php library using RelaySet
     */
    private function queryEventsSimple(Filter $filter, array $relays): array
    {
        $allEvents = [];
        
        try {
            // Create RelaySet for querying (like ValidationManager does)
            $relaySet = new RelaySet();
            foreach ($relays as $relayUrl) {
                echo "📡 Adding relay: $relayUrl\n";
                $relaySet->addRelay(new \swentel\nostr\Relay\Relay($relayUrl));
            }
            
            // Create subscription and request message
            $subscription = new Subscription();
            $subscriptionId = $subscription->setId();
            $requestMessage = new RequestMessage($subscriptionId, [$filter]);
            
            $request = new Request($relaySet, $requestMessage);
            $response = $request->send();
            
            // Process the response to extract events (like ValidationManager does)
            foreach ($response as $relayUrl => $relayResponses) {
                echo "📥 Processing responses from: $relayUrl\n";
                foreach ($relayResponses as $responseItem) {
                    if (is_object($responseItem) && isset($responseItem->type) && $responseItem->type === 'EVENT') {
                        $event = $responseItem->event;
                        $allEvents[] = $event;
                        echo "✅ Found event: " . $event->id . "\n";
                    }
                }
            }
            
            echo "📊 Total events retrieved: " . count($allEvents) . "\n";
            
        } catch (\Exception $e) {
            echo "⚠️  Failed to query events: " . $e->getMessage() . "\n";
        }
        
        return $allEvents;
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
