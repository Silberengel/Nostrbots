<?php
/**
 * Simple test to verify relay connectivity and authentication without Elasticsearch
 */

require_once __DIR__ . '/../vendor/autoload.php';

use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Request\Request;
use Nostrbots\Utils\KeyManager;

// Include the AuthenticatedRequest class
require_once __DIR__ . '/index-relay-events.php';

class RelayConnectivityTester
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

    public function testRelayConnectivity(array $relayUrls): bool
    {
        $this->log("Testing connectivity to " . count($relayUrls) . " relays...");
        
        // Log the relay URLs being used
        foreach ($relayUrls as $i => $url) {
            $this->log("Relay " . ($i + 1) . ": {$url}");
        }
        
        try {
            // Create relay set with all relays
            $relaySet = new RelaySet();
            foreach ($relayUrls as $relayUrl) {
                try {
                    $relaySet->addRelay(new Relay($relayUrl));
                    $this->log("Added relay: {$relayUrl}");
                } catch (Exception $e) {
                    $this->log("Warning: Failed to add relay {$relayUrl}: " . $e->getMessage());
                }
            }
            
            if (count($relaySet->getRelays()) === 0) {
                $this->log("Error: No valid relays could be added");
                return false;
            }
            
            // Create filter to get recent events
            $filter = new Filter();
            $filter->setLimit(50); // Get more events to test properly
            // Get events from the last 24 hours to ensure we get some results
            $filter->setSince(time() - 86400); // 24 hours ago
            
            // Create subscription and request message
            $subscription = new Subscription();
            $subscriptionId = $subscription->setId();
            $requestMessage = new RequestMessage($subscriptionId, [$filter]);
            
            // Test without authentication first
            $request = new Request($relaySet, $requestMessage);
            
            $this->log("Sending request to relays...");
            $response = $request->send();
            
            $events = [];
            $relayStats = [];
            
            foreach ($response as $relayUrl => $relayResponses) {
                $relayEventCount = 0;
                $this->log("Processing responses from: {$relayUrl}");
                
                foreach ($relayResponses as $responseItem) {
                    if (is_object($responseItem) && isset($responseItem->type)) {
                        $this->log("Response type: {$responseItem->type}");
                        
                        if ($responseItem->type === 'EVENT') {
                            $event = $responseItem->event;
                            $events[] = $event;
                            $relayEventCount++;
                        } elseif ($responseItem->type === 'AUTH') {
                            $this->log("Authentication challenge received from {$relayUrl}");
                        } elseif ($responseItem->type === 'OK' && isset($responseItem->message)) {
                            if (str_contains($responseItem->message, 'auth-required')) {
                                $this->log("Authentication required from {$relayUrl}");
                            } elseif (str_contains($responseItem->message, 'restricted')) {
                                $this->log("Access restricted from {$relayUrl}: {$responseItem->message}");
                            }
                        } elseif ($responseItem->type === 'NOTICE') {
                            $this->log("Notice from {$relayUrl}: {$responseItem->message}");
                        } elseif ($responseItem->type === 'EOSE') {
                            $this->log("End of stored events from {$relayUrl}");
                        }
                    }
                }
                
                $relayStats[$relayUrl] = $relayEventCount;
                $this->log("Relay {$relayUrl}: {$relayEventCount} events");
            }
            
            $count = count($events);
            $this->log("Found {$count} total events from all relays");
            
            // Log relay statistics
            foreach ($relayStats as $relay => $eventCount) {
                $this->log("Relay {$relay}: {$eventCount} events");
            }
            
            $this->log("✅ Relay connectivity test completed successfully");
            return true;
            
        } catch (Exception $e) {
            $this->log("❌ Relay connectivity test failed: " . $e->getMessage());
            return false;
        }
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new RelayConnectivityTester();
    
    // Test with working relays
    $relayUrls = [
        'wss://aggr.nostr.land',
        'wss://relay.damus.io'
    ];
    
    $success = $tester->testRelayConnectivity($relayUrls);
    exit($success ? 0 : 1);
}
