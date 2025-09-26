<?php
/**
 * Test script to verify event fetching from relays
 */

require_once __DIR__ . '/../vendor/autoload.php';

use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Filter\Filter;
use Nostrbots\Utils\KeyManager;

// Include the AuthenticatedRequest class
require_once __DIR__ . '/index-relay-events.php';

class EventFetchingTester
{
    private KeyManager $keyManager;
    private ?string $privateKey;

    public function __construct()
    {
        $this->keyManager = new KeyManager();
        $this->privateKey = $this->getPrivateKey();
    }

    public function log(string $message): void
    {
        $timestamp = date('c');
        echo "[{$timestamp}] {$message}" . PHP_EOL;
    }

    private function getPrivateKey(): ?string
    {
        try {
            $privateKey = $this->keyManager->ensureKeyExists();
            
            if ($privateKey) {
                // Convert nsec to hex if needed
                if (str_starts_with($privateKey, 'nsec')) {
                    $key = new \swentel\nostr\Key\Key();
                    $hexKey = $key->convertToHex($privateKey);
                    $this->log("Found nsec private key, converted to hex for authentication");
                    return $hexKey;
                } else {
                    $this->log("Found hex private key for authentication");
                    return $privateKey;
                }
            }
        } catch (Exception $e) {
            $this->log("Warning: Could not get private key for authentication: " . $e->getMessage());
        }
        
        $this->log("No private key available - authentication will not be possible");
        return null;
    }

    public function testEventFetching(array $relayUrls): void
    {
        $this->log("Testing event fetching from " . count($relayUrls) . " relays...");
        
        foreach ($relayUrls as $i => $url) {
            $this->log("Relay " . ($i + 1) . ": {$url}");
        }
        
        $totalEvents = 0;
        $relayStats = [];
        
        foreach ($relayUrls as $relayUrl) {
            $this->log("\n" . str_repeat("=", 60));
            $this->log("Testing relay: {$relayUrl}");
            
            try {
                $events = $this->fetchEventsFromRelay($relayUrl);
                $eventCount = count($events);
                $totalEvents += $eventCount;
                $relayStats[$relayUrl] = $eventCount;
                
                $this->log("✅ {$relayUrl}: Found {$eventCount} events");
                
                if ($eventCount > 0) {
                    $this->log("Sample events:");
                    $sampleEvents = array_slice($events, 0, 3);
                    foreach ($sampleEvents as $event) {
                        $this->log("  - Event ID: " . substr($event->id, 0, 16) . "...");
                        $this->log("    Kind: {$event->kind}");
                        $this->log("    Created: " . date('c', $event->created_at));
                        $this->log("    Content: " . substr($event->content ?? '', 0, 100) . "...");
                    }
                }
                
            } catch (Exception $e) {
                $this->log("❌ {$relayUrl}: Failed - " . $e->getMessage());
                $relayStats[$relayUrl] = 0;
            }
        }
        
        $this->log("\n" . str_repeat("=", 60));
        $this->log("SUMMARY:");
        $this->log("Total events found: {$totalEvents}");
        $this->log("Relay statistics:");
        foreach ($relayStats as $relay => $count) {
            $status = $count > 0 ? "✅" : "❌";
            $this->log("  {$status} {$relay}: {$count} events");
        }
    }

    private function fetchEventsFromRelay(string $relayUrl): array
    {
        $events = [];
        
        try {
            // Create relay set with single relay
            $relaySet = new RelaySet();
            $relay = new Relay($relayUrl);
            $relaySet->addRelay($relay);
            
            // Create filter to get recent events
            $filter = new Filter();
            $filter->setLimit(100); // Get up to 100 events
            $filter->setSince(time() - 86400); // Last 24 hours
            
            // Create subscription and request message
            $subscription = new Subscription();
            $subscriptionId = $subscription->setId();
            $requestMessage = new RequestMessage($subscriptionId, [$filter]);
            
            // Use our custom authenticated request if we have a private key
            if ($this->privateKey) {
                $request = new AuthenticatedRequest($relaySet, $requestMessage, $this->privateKey, $this);
            } else {
                $request = new \swentel\nostr\Request($relaySet, $requestMessage);
            }
            
            $this->log("Sending request to {$relayUrl}...");
            $response = $request->send();
            
            foreach ($response as $relay => $relayResponses) {
                $this->log("Processing responses from: {$relay}");
                
                foreach ($relayResponses as $responseItem) {
                    if (is_object($responseItem) && isset($responseItem->type)) {
                        if ($responseItem->type === 'EVENT') {
                            $event = $responseItem->event;
                            $events[] = $event;
                        } elseif ($responseItem->type === 'AUTH') {
                            $this->log("Authentication challenge received");
                        } elseif ($responseItem->type === 'EOSE') {
                            $this->log("End of stored events");
                        } elseif ($responseItem->type === 'NOTICE') {
                            $this->log("Notice: {$responseItem->message}");
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log("Error fetching from {$relayUrl}: " . $e->getMessage());
            throw $e;
        }
        
        return $events;
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new EventFetchingTester();
    
    // Get relay URLs from environment variable or use defaults
    $relayUrlsEnv = getenv('RELAY_URLS');
    if ($relayUrlsEnv) {
        // Parse comma-separated or space-separated relay URLs
        $relayUrls = array_filter(array_map('trim', preg_split('/[,\s]+/', $relayUrlsEnv)));
        $tester->log("Using relays from RELAY_URLS environment variable: " . implode(', ', $relayUrls));
    } else {
        // Default relays for testing
        $relayUrls = [
            'wss://relay.damus.io',
            'wss://nos.lol',
            'wss://relay.nostr.band',
            'wss://thecitadel.nostr1.com'
        ];
        $tester->log("Using default test relays (set RELAY_URLS env var to override)");
    }
    
    $tester->testEventFetching($relayUrls);
}
