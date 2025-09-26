<?php
/**
 * Debug script to test authentication flow in detail
 */

require_once __DIR__ . '/../vendor/autoload.php';

use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Message\AuthMessage;
use swentel\nostr\Nip42\AuthEvent;
use swentel\nostr\Sign\Sign;
use Nostrbots\Utils\KeyManager;

// Include the AuthenticatedRequest class
require_once __DIR__ . '/index-relay-events.php';

class AuthDebugger
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

    public function debugRelayAuth(string $relayUrl): void
    {
        $this->log("=== Debugging authentication for: {$relayUrl} ===");
        
        if (!$this->privateKey) {
            $this->log("❌ No private key available for authentication");
            return;
        }
        
        try {
            // Create relay set with single relay
            $relaySet = new RelaySet();
            $relay = new Relay($relayUrl);
            $relaySet->addRelay($relay);
            
            // Create filter to get recent events
            $filter = new Filter();
            $filter->setLimit(10);
            $filter->setSince(time() - 86400); // Last 24 hours
            
            // Create subscription and request message
            $subscription = new Subscription();
            $subscriptionId = $subscription->setId();
            $requestMessage = new RequestMessage($subscriptionId, [$filter]);
            
            $this->log("Sending initial request to {$relayUrl}...");
            
            // Use our custom authenticated request
            $request = new AuthenticatedRequest($relaySet, $requestMessage, $this->privateKey, $this);
            $response = $request->send();
            
            $this->log("Response received from {$relayUrl}");
            
            $events = [];
            foreach ($response as $relay => $relayResponses) {
                $this->log("Processing responses from: {$relay}");
                
                foreach ($relayResponses as $responseItem) {
                    if (is_object($responseItem) && isset($responseItem->type)) {
                        $this->log("Response type: {$responseItem->type}");
                        
                        if ($responseItem->type === 'EVENT') {
                            $event = $responseItem->event;
                            $events[] = $event;
                            $this->log("  ✅ Event received: " . substr($event->id, 0, 16) . "... (kind: {$event->kind})");
                        } elseif ($responseItem->type === 'AUTH') {
                            $this->log("  🔐 AUTH challenge: {$responseItem->message}");
                        } elseif ($responseItem->type === 'OK') {
                            $this->log("  ✅ OK response: {$responseItem->message}");
                        } elseif ($responseItem->type === 'NOTICE') {
                            $this->log("  📢 NOTICE: {$responseItem->message}");
                        } elseif ($responseItem->type === 'EOSE') {
                            $this->log("  🏁 End of stored events");
                        } elseif ($responseItem->type === 'CLOSED') {
                            $this->log("  🔒 CLOSED: {$responseItem->message}");
                        }
                    }
                }
            }
            
            $this->log("Total events received: " . count($events));
            
        } catch (Exception $e) {
            $this->log("❌ Error: " . $e->getMessage());
        }
    }
}

/**
 * Debug version of AuthenticatedRequest that logs everything
 */
class DebugAuthenticatedRequest extends \swentel\nostr\Request\Request
{
    private ?string $privateKey;
    private object $logger;

    public function __construct(\swentel\nostr\Relay\Relay|\swentel\nostr\Relay\RelaySet $relay, \swentel\nostr\MessageInterface $message, ?string $privateKey, object $logger)
    {
        parent::__construct($relay, $message);
        $this->privateKey = $privateKey;
        $this->logger = $logger;
    }

    private function getResponseFromRelay(\swentel\nostr\Relay\Relay $relay): array | \swentel\nostr\RelayResponse\RelayResponse
    {
        $client = $relay->getClient();
        $client->setTimeout(60);

        try {
            $this->logger->log("Sending payload: " . substr($this->payload, 0, 100) . "...");
            $client->text($this->payload);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
        
        $this->logger->log("Waiting for responses...");
        
        // The Nostr subscription lifecycle within a websocket connection lifecycle.
        while ($response = $client->receive()) {
            if ($response === null) {
                $response = [
                    'ERROR',
                    'Invalid response',
                ];
                $client->disconnect();
                return \swentel\nostr\RelayResponse\RelayResponse::create($response);
            } elseif ($response instanceof \WebSocket\Message\Ping) {
                // Send pong message.
                $pongMessage = new \WebSocket\Message\Pong();
                $client->text($pongMessage->getPayload());
            } elseif ($response instanceof \WebSocket\Message\Text) {
                $this->logger->log("Raw response: " . $response->getContent());
                $relayResponse = \swentel\nostr\RelayResponse\RelayResponse::create(json_decode($response->getContent()));
                $this->responses[] = $relayResponse;
                
                // NIP-01 - Response OK from the relay.
                if ($relayResponse->type === 'OK' && $relayResponse->status === false) {
                    if (str_starts_with($relayResponse->message, 'auth-required:')) {
                        // NIP-42 - Handle authentication with our private key
                        $this->logger->log("🔐 Authentication required, handling...");
                        $this->handleAuthentication($relay);
                        break;
                    }
                    // Something went wrong, see message from the relay why.
                    $client->disconnect();
                    throw new \RuntimeException($relayResponse->message);
                }
                if ($relayResponse->type === 'OK' && $relayResponse->status === true) {
                    if (str_starts_with($relayResponse->message, 'auth-required:')) {
                        // NIP-42 - Handle authentication with our private key
                        $this->logger->log("🔐 Authentication required, handling...");
                        $this->handleAuthentication($relay);
                        break;
                    }
                    if (str_starts_with($relayResponse->message, 'restricted:')) {
                        // For when a client has already performed AUTH but the key used to perform
                        // it is still not allowed by the relay or is exceeding its authorization.
                        $client->disconnect();
                        throw new \RuntimeException($relayResponse->message);
                    }
                    if (isset($relayResponse->eventId) && $relayResponse->eventId !== '') {
                        // Event is transmitted to the relay.
                        $client->disconnect();
                        break;
                    }
                }
                // NIP-01 - Response EVENT from the relay.
                if ($relayResponse->type === 'EVENT') {
                    // Do nothing.
                }
                // NIP-01 - Response NOTICE from the relay.
                if ($relayResponse->type === 'NOTICE') {
                    // Relay returns an error.
                    if (str_starts_with($relayResponse->message, 'ERROR:')) {
                        $client->disconnect();
                        break;
                    }
                }
                // NIP-01 - Response EOSE from the relay.
                if ($relayResponse->type === 'EOSE') {
                    $subscriptionId = $relayResponse->subscriptionId;
                    $this->sendCloseMessage($relay, $subscriptionId);
                    $client->disconnect();
                    break;
                }
                // NIP-42 - Response AUTH from the relay.
                if ($relayResponse->type === 'AUTH') {
                    // Save challenge string in session.
                    $_SESSION['challenge'] = $relayResponse->message;
                    $this->logger->log("🔐 Challenge saved: " . substr($relayResponse->message, 0, 20) . "...");
                }
                // NIP-01 - Response CLOSED from the relay.
                if ($relayResponse->type === 'CLOSED') {
                    if (str_starts_with($relayResponse->message, 'auth-required:')) {
                        // NIP-42 - Handle authentication with our private key
                        $this->logger->log("🔐 Authentication required, handling...");
                        $this->handleAuthentication($relay);
                        break;
                    }
                    if (str_starts_with($relayResponse->message, 'restricted:')) {
                        // For when a client has already performed AUTH but the key used to perform
                        // it is still not allowed by the relay or is exceeding its authorization.
                        $client->disconnect();
                        throw new \RuntimeException($relayResponse->message);
                    }
                    $client->disconnect();
                    break;
                }
            }
        }
        if ($client->isConnected()) {
            $client->disconnect();
        }
        $client->close();
        return $this->responses;
    }

    private function handleAuthentication(\swentel\nostr\Relay\Relay $relay): void
    {
        if (!$this->privateKey) {
            throw new \RuntimeException("No private key available for authentication");
        }

        $client = $relay->getClient();
        if (!isset($_SESSION['challenge'])) {
            $client->disconnect();
            $message = sprintf(
                'Relay %s requires auth and there is no challenge set in $_SESSION. Did we get an AUTH response first?',
                $relay->getUrl(),
            );
            throw new \RuntimeException($message);
        }

        $this->logger->log("🔐 Authenticating with relay: " . $relay->getUrl());
        $this->logger->log("🔐 Challenge: " . $_SESSION['challenge']);
        
        $authEvent = new AuthEvent($relay->getUrl(), $_SESSION['challenge']);
        $signer = new Sign();
        $signer->signEvent($authEvent, $this->privateKey);
        
        $this->logger->log("🔐 Auth event created:");
        $this->logger->log("  - Event ID: " . $authEvent->getId());
        $this->logger->log("  - Public Key: " . $authEvent->getPublicKey());
        $this->logger->log("  - Signature: " . substr($authEvent->getSignature(), 0, 20) . "...");
        
        // Convert the signed event to JSON string
        $authEventJson = json_encode([
            'id' => $authEvent->getId(),
            'pubkey' => $authEvent->getPublicKey(),
            'created_at' => $authEvent->getCreatedAt(),
            'kind' => 22242,
            'tags' => $authEvent->getTags(),
            'content' => $authEvent->getContent(),
            'sig' => $authEvent->getSignature()
        ]);
        
        // Send AUTH message in correct NIP-42 format: ["AUTH", <signed-event-json>]
        // Use json_encode to ensure proper JSON formatting
        $authMessage = json_encode(['AUTH', json_decode($authEventJson)]);
        
        $this->logger->log("🔐 Sending auth message: " . substr($authMessage, 0, 100) . "...");
        $client->text($authMessage);
        
        $this->logger->log("🔐 Authentication message sent, waiting for response...");
        
        // Set listener for auth response
        $client->onText(function (\WebSocket\Client $client, \WebSocket\Connection $connection, \WebSocket\Message\Text $message) {
            $this->logger->log("🔐 Auth response: " . $message->getContent());
            $this->responses[] = \swentel\nostr\RelayResponse\RelayResponse::create(json_decode($message->getContent()));
            $client->stop();
        })->start();
        
        // Broadcast the initial message to the relay now the AUTH is done
        $this->payload = $initialMessage;
        $this->logger->log("🔐 Sending original request after auth...");
        $client->text($this->payload);
        $client->onText(function (\WebSocket\Client $client, \WebSocket\Connection $connection, \WebSocket\Message\Text $message) {
            /** @var \swentel\nostr\RelayResponse\RelayResponse $response */
            $response = \swentel\nostr\RelayResponse\RelayResponse::create(json_decode($message->getContent()));
            $this->responses[] = $response;
            $client->stop();
            if ($response->type === 'EOSE') {
                $client->disconnect();
            }
        })->start();
    }

    private function sendCloseMessage(\swentel\nostr\Relay\Relay $relay, string $subscriptionId): void
    {
        $client = $relay->getClient();
        $closeMessage = new \swentel\nostr\Message\CloseMessage($subscriptionId);
        $message = $closeMessage->generate();
        $client->text($message);
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $debugger = new AuthDebugger();
    
    // Get relay URLs from environment variable or use defaults
    $relayUrlsEnv = getenv('RELAY_URLS');
    if ($relayUrlsEnv) {
        // Parse comma-separated or space-separated relay URLs
        $relays = array_filter(array_map('trim', preg_split('/[,\s]+/', $relayUrlsEnv)));
        $debugger->log("Using relays from RELAY_URLS environment variable: " . implode(', ', $relays));
    } else {
        // Default relays for testing
        $relays = [
            'wss://aggr.nostr.land',
            'wss://relay.damus.io',
            'wss://nostr21.com'
        ];
        $debugger->log("Using default test relays (set RELAY_URLS env var to override)");
    }
    
    foreach ($relays as $relay) {
        $debugger->debugRelayAuth($relay);
        echo "\n" . str_repeat("=", 80) . "\n\n";
    }
}
