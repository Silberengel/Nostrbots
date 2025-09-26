<?php
/**
 * Test script to verify relay authentication with specific relays
 */

require_once __DIR__ . '/../vendor/autoload.php';

use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Filter\Filter;
use Nostrbots\Utils\KeyManager;

class RelayAuthTester
{
    private KeyManager $keyManager;
    private ?string $privateKey;

    public function __construct()
    {
        $this->keyManager = new KeyManager();
        $this->privateKey = $this->getPrivateKey();
    }

    private function log(string $message): void
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

    public function testRelay(string $relayUrl, string $expectedResult): bool
    {
        $this->log("Testing relay: {$relayUrl}");
        $this->log("Expected result: {$expectedResult}");
        
        try {
            // Create relay set
            $relaySet = new RelaySet();
            $relaySet->addRelay(new Relay($relayUrl));
            
            // Create filter to get recent events
            $filter = new Filter();
            $filter->setLimit(5); // Just get a few recent events
            
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
            
            $this->log("Sending request to relay...");
            $response = $request->send();
            
            $this->log("Response received from relay");
            
            // Analyze the response
            $eventCount = 0;
            $authRequired = false;
            $errorMessages = [];
            
            foreach ($response as $relay => $relayResponses) {
                $this->log("Processing responses from: {$relay}");
                
                foreach ($relayResponses as $responseItem) {
                    if (is_object($responseItem)) {
                        $this->log("Response type: {$responseItem->type}");
                        
                        if ($responseItem->type === 'EVENT') {
                            $eventCount++;
                        } elseif ($responseItem->type === 'OK' && isset($responseItem->message)) {
                            if (str_contains($responseItem->message, 'auth-required')) {
                                $authRequired = true;
                                $this->log("Authentication required: {$responseItem->message}");
                            } elseif (str_contains($responseItem->message, 'restricted')) {
                                $this->log("Access restricted: {$responseItem->message}");
                            }
                        } elseif ($responseItem->type === 'NOTICE') {
                            $errorMessages[] = $responseItem->message;
                            $this->log("Notice: {$responseItem->message}");
                        } elseif ($responseItem->type === 'EOSE') {
                            $this->log("End of stored events");
                        }
                    }
                }
            }
            
            $this->log("Results:");
            $this->log("- Events received: {$eventCount}");
            $this->log("- Auth required: " . ($authRequired ? 'Yes' : 'No'));
            $this->log("- Error messages: " . count($errorMessages));
            
            // Determine if test passed based on expected result
            if ($expectedResult === 'should work') {
                if ($eventCount > 0 || !$authRequired) {
                    $this->log("✅ Test PASSED - Relay is accessible");
                    return true;
                } else {
                    $this->log("❌ Test FAILED - Expected to work but got auth required or no events");
                    return false;
                }
            } elseif ($expectedResult === 'should not work') {
                if ($authRequired || count($errorMessages) > 0) {
                    $this->log("✅ Test PASSED - Relay correctly requires authentication or blocks access");
                    return true;
                } else {
                    $this->log("❌ Test FAILED - Expected to be blocked but got access");
                    return false;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log("❌ Test FAILED with exception: " . $e->getMessage());
            return false;
        }
    }

    public function runTests(): bool
    {
        $this->log("Starting relay authentication tests...");
        
        $tests = [
            'wss://aggr.nostr.land' => 'should work',
            'wss://relay.damus.io' => 'should work'
        ];
        
        $allPassed = true;
        
        foreach ($tests as $relay => $expected) {
            $this->log("=" . str_repeat("=", 60));
            if (!$this->testRelay($relay, $expected)) {
                $allPassed = false;
            }
            $this->log("=" . str_repeat("=", 60));
        }
        
        if ($allPassed) {
            $this->log("✅ All relay authentication tests passed!");
        } else {
            $this->log("❌ Some relay authentication tests failed");
        }
        
        return $allPassed;
    }
}

// Include the AuthenticatedRequest class from the main script
require_once __DIR__ . '/index-relay-events.php';

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new RelayAuthTester();
    $success = $tester->runTests();
    exit($success ? 0 : 1);
}
