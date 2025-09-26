<?php
/**
 * Event Indexing Script for Elasticsearch
 * Indexes Nostr events from the relay into Elasticsearch for search and analytics
 * 
 * This script properly uses the Nostr WebSocket protocol to query events from Orly relay
 * and indexes them into Elasticsearch.
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

/**
 * Custom Request class that handles authentication with our private key
 */
class AuthenticatedRequest extends \swentel\nostr\Request\Request
{
    private ?string $privateKey;
    private object $logger;
    private string $payload;

    public function __construct(\swentel\nostr\Relay\Relay|\swentel\nostr\Relay\RelaySet $relay, \swentel\nostr\MessageInterface $message, ?string $privateKey, object $logger)
    {
        parent::__construct($relay, $message);
        $this->privateKey = $privateKey;
        $this->logger = $logger;
        $this->payload = $message->generate();
    }

    /**
     * Override send method to use our custom authentication flow
     */
    public function send(): array
    {
        $this->responses = [];
        
        // Use the parent class's relays property
        foreach ($this->relays->getRelays() as $relay) {
            $this->responses[$relay->getUrl()] = $this->getResponseFromRelay($relay);
        }
        
        return $this->responses;
    }

        /**
         * Override getResponseFromRelay to handle authentication with our private key
         * This implementation handles the various NIP-42 patterns found in the wild
         */
        private function getResponseFromRelay(\swentel\nostr\Relay\Relay $relay): array | \swentel\nostr\RelayResponse\RelayResponse
        {
            $client = $relay->getClient();
            $client->setTimeout(60);
            $originalPayload = $this->payload;

            // Check if this is a legacy relay that needs simple flow
            if ($this->isLegacyRelay($relay->getUrl())) {
                return $this->getResponseFromLegacyRelay($relay, $client, $originalPayload);
            }

            // Use complex flow for modern relays
            return $this->getResponseFromModernRelay($relay, $client, $originalPayload);
        }

        /**
         * Check if a relay URL is a legacy relay that needs simple authentication flow
         */
        private function isLegacyRelay(string $relayUrl): bool
        {
            $legacyRelays = [
                // Add other legacy relays here as needed
            ];
            
            foreach ($legacyRelays as $legacyRelay) {
                if (str_contains($relayUrl, $legacyRelay)) {
                    return true;
                }
            }
            
            return false;
        }

        /**
         * Simple authentication flow for legacy relays
         * This follows the original, straightforward NIP-42 pattern
         */
        private function getResponseFromLegacyRelay(\swentel\nostr\Relay\Relay $relay, $client, string $originalPayload): array
        {
            try {
                // Send the initial request
                $client->text($originalPayload);
            } catch (\Exception $e) {
                throw new \RuntimeException($e->getMessage());
            }
            
            // Simple response loop for legacy relays
            while ($response = $client->receive()) {
                if ($response === null) {
                    $response = [
                        'ERROR',
                        'Invalid response',
                    ];
                    $client->disconnect();
                    return \swentel\nostr\RelayResponse\RelayResponse::create($response);
                } elseif ($response instanceof \WebSocket\Message\Ping) {
                    // Send pong message
                    $pongMessage = new \WebSocket\Message\Pong();
                    $client->text($pongMessage->getPayload());
                } elseif ($response instanceof \WebSocket\Message\Text) {
                    $relayResponse = \swentel\nostr\RelayResponse\RelayResponse::create(json_decode($response->getContent()));
                    $this->responses[] = $relayResponse;
                    
                    // Handle AUTH challenge - authenticate immediately
                    if ($relayResponse->type === 'AUTH') {
                        try {
                            $this->logger->log("Legacy relay AUTH challenge: " . $relayResponse->message);
                        } catch (Exception $e) {
                            // Logger not available
                        }
                        
                        if ($this->privateKey) {
                            $this->handleLegacyAuthentication($relay, $relayResponse->message);
                            // Wait for OK response
                            continue;
                        } else {
                            $client->disconnect();
                            throw new \RuntimeException("Auth required but no private key available");
                        }
                    }
                    
                    // Handle OK response
                    if ($relayResponse->type === 'OK') {
                        if ($relayResponse->status === true) {
                            // Authentication successful, continue processing
                            continue;
                        } else {
                            $client->disconnect();
                            throw new \RuntimeException($relayResponse->message);
                        }
                    }
                    
                    // Handle EVENT responses
                    if ($relayResponse->type === 'EVENT') {
                        // Continue processing events
                        continue;
                    }
                    
                    // Handle EOSE
                    if ($relayResponse->type === 'EOSE') {
                        $subscriptionId = $relayResponse->subscriptionId;
                        $this->sendCloseMessage($relay, $subscriptionId);
                        // Don't disconnect - keep connection alive for subsequent requests
                        break;
                    }
                    
                    // Handle NOTICE
                    if ($relayResponse->type === 'NOTICE') {
                        try {
                            $this->logger->log("Legacy relay NOTICE: " . $relayResponse->message);
                        } catch (Exception $e) {
                            // Logger not available
                        }
                        continue;
                    }
                }
            }
            
            // Keep connection alive for subsequent requests
            // Don't disconnect or close the client
            return $this->responses;
        }

        /**
         * Complex authentication flow for modern relays
         * This handles the various NIP-42 patterns found in the wild
         */
        private function getResponseFromModernRelay(\swentel\nostr\Relay\Relay $relay, $client, string $originalPayload): array
        {
            $authChallengeReceived = false;

            try {
                // Send the initial request
                $client->text($originalPayload);
            } catch (\Exception $e) {
                throw new \RuntimeException($e->getMessage());
            }
            
            // Handle the response loop with proper NIP-42 flow
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
                    $relayResponse = \swentel\nostr\RelayResponse\RelayResponse::create(json_decode($response->getContent()));
                    $this->responses[] = $relayResponse;
                    
                    // NIP-42 - Handle AUTH challenge from relay
                    if ($relayResponse->type === 'AUTH') {
                        try {
                            $this->logger->log("Received AUTH challenge from relay: " . $relay->getUrl());
                            $this->logger->log("Challenge: " . $relayResponse->message);
                        } catch (Exception $e) {
                            // Logger not available or method not accessible
                        }
                        $_SESSION['challenge'] = $relayResponse->message;
                        $authChallengeReceived = true;
                        
                        // Some legacy relays expect immediate authentication
                        if ($this->privateKey && $this->isLegacyRelay($relay->getUrl())) {
                            try {
                                $this->logger->log("Attempting immediate authentication for legacy relay...");
                            } catch (Exception $e) {
                                // Logger not available
                            }
                            $this->handleAuthentication($relay);
                            // For legacy relays, don't resend the original request - wait for OK response
                            continue;
                        }
                        
                        // For other relays, wait for auth-required message
                        continue;
                    }
                    
                    // NIP-01 - Response OK from the relay.
                    if ($relayResponse->type === 'OK') {
                        if (str_starts_with($relayResponse->message, 'auth-required:')) {
                            try {
                                $this->logger->log("Received auth-required OK from relay: " . $relay->getUrl());
                            } catch (Exception $e) {
                                // Logger not available
                            }
                            if ($authChallengeReceived && isset($_SESSION['challenge'])) {
                                $this->handleAuthentication($relay);
                                // After auth, resend the original request
                                $client->text($originalPayload);
                                continue;
                            } else {
                                $client->disconnect();
                                throw new \RuntimeException("Auth required but no challenge received");
                            }
                        }
                        if (str_starts_with($relayResponse->message, 'restricted:')) {
                            $client->disconnect();
                            throw new \RuntimeException($relayResponse->message);
                        }
                        if ($relayResponse->status === false) {
                            $client->disconnect();
                            throw new \RuntimeException($relayResponse->message);
                        }
                        if ($relayResponse->status === true) {
                            // Success - continue processing
                            continue;
                        }
                    }
                    
                    // NIP-01 - Response CLOSED from the relay.
                    if ($relayResponse->type === 'CLOSED') {
                        if (str_starts_with($relayResponse->message, 'auth-required:')) {
                            try {
                                $this->logger->log("Received auth-required CLOSED from relay: " . $relay->getUrl());
                                $this->logger->log("Auth challenge received: " . ($authChallengeReceived ? 'YES' : 'NO'));
                                $this->logger->log("Challenge in session: " . (isset($_SESSION['challenge']) ? 'YES' : 'NO'));
                            } catch (Exception $e) {
                                // Logger not available
                            }
                            
                            // Skip authentication if we already attempted it for legacy relays
                            if ($authChallengeReceived && isset($_SESSION['challenge']) && !$this->isLegacyRelay($relay->getUrl())) {
                                try {
                                    $this->logger->log("Attempting authentication...");
                                } catch (Exception $e) {
                                    // Logger not available
                                }
                                $this->handleAuthentication($relay);
                                // After auth, resend the original request
                                $client->text($originalPayload);
                                continue;
                            } elseif ($this->isLegacyRelay($relay->getUrl())) {
                                try {
                                    $this->logger->log("Skipping duplicate authentication for legacy relay");
                                } catch (Exception $e) {
                                    // Logger not available
                                }
                                // For legacy relays, we already authenticated, just continue
                                continue;
                            } else {
                                $client->disconnect();
                                throw new \RuntimeException("Auth required but no challenge received");
                            }
                        }
                        if (str_starts_with($relayResponse->message, 'restricted:')) {
                            $client->disconnect();
                            throw new \RuntimeException($relayResponse->message);
                        }
                        if (str_starts_with($relayResponse->message, 'blocked:')) {
                            $client->disconnect();
                            throw new \RuntimeException($relayResponse->message);
                        }
                        // Regular CLOSED - end the connection
                        $client->disconnect();
                        break;
                    }
                    
                    // NIP-01 - Response EVENT from the relay.
                    if ($relayResponse->type === 'EVENT') {
                        // Continue processing events
                        continue;
                    }
                    
                    // NIP-01 - Response NOTICE from the relay.
                    if ($relayResponse->type === 'NOTICE') {
                        try {
                            $this->logger->log("NOTICE from relay: " . $relayResponse->message);
                        } catch (Exception $e) {
                            // Logger not available
                        }
                        if (str_starts_with($relayResponse->message, 'ERROR:')) {
                            $client->disconnect();
                            break;
                        }
                        // Continue processing - NOTICE is informational
                        continue;
                    }
                    
                    // NIP-01 - Response EOSE from the relay.
                    if ($relayResponse->type === 'EOSE') {
                        $subscriptionId = $relayResponse->subscriptionId;
                        $this->sendCloseMessage($relay, $subscriptionId);
                        // Don't disconnect - keep connection alive for subsequent requests
                        break;
                    }
                }
            }
            
            // Keep connection alive for subsequent requests
            // Don't disconnect or close the client
            return $this->responses;
        }

        /**
         * Handle legacy authentication with our private key
         * Simple, direct authentication for legacy relays
         */
        private function handleLegacyAuthentication(\swentel\nostr\Relay\Relay $relay, string $challenge): void
        {
            if (!$this->privateKey) {
                throw new \RuntimeException("No private key available for authentication");
            }

            try {
                $this->logger->log("Legacy authentication with relay: " . $relay->getUrl());
                $this->logger->log("Challenge: " . $challenge);
            } catch (Exception $e) {
                // Logger not available
            }
            
            // Create the auth event according to NIP-42 spec
            $authEvent = new AuthEvent($relay->getUrl(), $challenge);
            $signer = new Sign();
            $signer->signEvent($authEvent, $this->privateKey);
            
            try {
                $this->logger->log("Legacy auth event created - ID: " . substr($authEvent->getId(), 0, 16) . "...");
                $this->logger->log("Legacy auth event public key: " . substr($authEvent->getPublicKey(), 0, 16) . "...");
            } catch (Exception $e) {
                // Logger not available
            }
            
            // Create auth message using the correct nostr-tools format
            $authMessage = json_encode(['AUTH', $authEvent->toArray()]);
            
            try {
                $this->logger->log("Legacy auth message: " . substr($authMessage, 0, 200) . "...");
            } catch (Exception $e) {
                // Logger not available
            }
            
            // Send the auth message
            $client = $relay->getClient();
            $client->text($authMessage);
            
            try {
                $this->logger->log("Legacy authentication message sent to relay: " . $relay->getUrl());
            } catch (Exception $e) {
                // Logger not available
            }
        }

        /**
         * Handle authentication with our private key
         * Simplified version that just sends the auth message and lets the main loop handle the response
         */
        private function handleAuthentication(\swentel\nostr\Relay\Relay $relay): void
        {
            if (!$this->privateKey) {
                throw new \RuntimeException("No private key available for authentication");
            }

            if (!isset($_SESSION['challenge'])) {
                throw new \RuntimeException("No challenge available for authentication");
            }

            try {
                $this->logger->log("Authenticating with relay: " . $relay->getUrl());
                $this->logger->log("Challenge: " . $_SESSION['challenge']);
            } catch (Exception $e) {
                // Logger not available
            }
            
            // Create the auth event according to NIP-42 spec
            $authEvent = new AuthEvent($relay->getUrl(), $_SESSION['challenge']);
            $signer = new Sign();
            $signer->signEvent($authEvent, $this->privateKey);
            
            try {
                $this->logger->log("Auth event created - ID: " . substr($authEvent->getId(), 0, 16) . "...");
                $this->logger->log("Auth event public key: " . substr($authEvent->getPublicKey(), 0, 16) . "...");
            } catch (Exception $e) {
                // Logger not available
            }
            
            // Send AUTH message in correct NIP-42 format: ["AUTH", <signed-event-json>]
            // Use the same format as nostr-tools library
            $authMessage = json_encode(['AUTH', $authEvent->toArray()]);
            
            try {
                $this->logger->log("Auth message: " . substr($authMessage, 0, 200) . "...");
            } catch (Exception $e) {
                // Logger not available
            }
            
            // Send the auth message
            $client = $relay->getClient();
            $client->text($authMessage);
            
            try {
                $this->logger->log("Authentication message sent to relay: " . $relay->getUrl());
            } catch (Exception $e) {
                // Logger not available
            }
        }

    /**
     * Send closeMessage to relay and ignore any response.
     */
    private function sendCloseMessage(\swentel\nostr\Relay\Relay $relay, string $subscriptionId): void
    {
        $client = $relay->getClient();
        $closeMessage = new \swentel\nostr\Message\CloseMessage($subscriptionId);
        $message = $closeMessage->generate();
        $client->text($message);
    }
}

class EventIndexer
{
    private string $elasticsearchUrl;
    private array $relayUrls;
    private string $indexName;
    private int $maxEvents;
    private string $logFile;
    private string $stateFile;
    private ?string $privateKey;
    private KeyManager $keyManager;
    
    // Performance and rate limiting settings
    private int $batchSize;
    private int $requestDelay;
    private int $connectionTimeout;
    private int $maxConcurrentConnections;
    private bool $backgroundMode;

    public function __construct()
    {
        $this->elasticsearchUrl = $_ENV['ELASTICSEARCH_URL'] ?? 'http://elasticsearch:9200';
        
        // Parse relay URLs from environment variable
        $relayList = $_ENV['RELAY_URLS'] ?? $_ENV['ORLY_RELAY_URL'] ?? 'ws://orly-relay:7777';
        $this->relayUrls = $this->parseRelayUrls($relayList);
        
        $this->indexName = 'nostr-events';
        $this->maxEvents = (int)($_ENV['MAX_EVENTS'] ?? 1000);
        $this->logFile = '/var/log/nostrbots/event-indexer.log';
        $this->stateFile = '/var/log/nostrbots/event-indexer-state.json';
        
        // Performance settings
        $this->batchSize = (int)($_ENV['RELAY_BATCH_SIZE'] ?? 5); // Process 5 relays at a time
        $this->requestDelay = (int)($_ENV['REQUEST_DELAY_MS'] ?? 2000); // 2 seconds between batches
        $this->connectionTimeout = (int)($_ENV['CONNECTION_TIMEOUT'] ?? 15); // 15 seconds
        $this->maxConcurrentConnections = (int)($_ENV['MAX_CONCURRENT_CONNECTIONS'] ?? 3);
        $this->backgroundMode = filter_var($_ENV['BACKGROUND_MODE'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        
        // Initialize key manager and get private key for authentication
        $this->keyManager = new KeyManager();
        $this->privateKey = $this->getPrivateKey();
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    private function parseRelayUrls(string $relayList): array
    {
        // Support both comma-separated and space-separated lists
        $urls = preg_split('/[,\s]+/', trim($relayList));
        $urls = array_filter($urls, function($url) {
            return !empty(trim($url));
        });
        
        // Ensure all URLs are properly formatted
        $validUrls = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) continue;
            
            // Add ws:// prefix if no protocol specified
            if (!preg_match('/^wss?:\/\//', $url)) {
                $url = 'ws://' . $url;
            }
            
            $validUrls[] = $url;
        }
        
        return array_unique($validUrls);
    }

    private function getPrivateKey(): ?string
    {
        try {
            // Try to get the private key using the key manager
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

    public function log(string $message): void
    {
        $timestamp = date('c');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        echo $logMessage;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    private function errorExit(string $message): void
    {
        $this->log("ERROR: {$message}");
        exit(1);
    }

    private function getLastIndexedTimestamp(): int
    {
        if (!file_exists($this->stateFile)) {
            // First run - start from 1 day ago to catch recent events
            $timestamp = time() - (1 * 24 * 3600);
            $this->log("First run detected, starting from 1 day ago");
            return $timestamp;
        }

        $state = json_decode(file_get_contents($this->stateFile), true);
        if (!$state || !isset($state['last_timestamp'])) {
            // Corrupted state file - start from 1 day ago
            $timestamp = time() - (1 * 24 * 3600);
            $this->log("Corrupted state file, starting from 1 day ago");
            return $timestamp;
        }

        $this->log("Last indexed timestamp: " . date('c', $state['last_timestamp']));
        return $state['last_timestamp'];
    }

    private function saveLastIndexedTimestamp(int $timestamp): void
    {
        $state = [
            'last_timestamp' => $timestamp,
            'last_run' => time(),
            'last_run_date' => date('c')
        ];

        if (file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            $this->log("Warning: Could not save state file");
        } else {
            $this->log("Saved last indexed timestamp: " . date('c', $timestamp));
        }
    }

    private function checkElasticsearch(): void
    {
        $this->log("Checking Elasticsearch connectivity...");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->elasticsearchUrl . '/_cluster/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            $this->errorExit("Cannot connect to Elasticsearch at {$this->elasticsearchUrl}");
        }
        
        $this->log("Elasticsearch is available");
    }

    private function createIndex(): void
    {
        $this->log("Ensuring index {$this->indexName} exists...");
        
        $indexMapping = [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'pubkey' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'kind' => ['type' => 'integer'],
                    'tags' => ['type' => 'keyword'],
                    'content' => [
                        'type' => 'text',
                        'analyzer' => 'standard'
                    ],
                    'sig' => ['type' => 'keyword'],
                    'indexed_at' => ['type' => 'date'],
                    'relay' => ['type' => 'keyword'],
                    'relay_count' => ['type' => 'integer'],
                    'relay_urls' => ['type' => 'keyword']
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->elasticsearchUrl . '/' . $this->indexName);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($indexMapping));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            $this->log("Warning: Could not create index - curl error");
            return;
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['acknowledged']) && $responseData['acknowledged']) {
            $this->log("Index {$this->indexName} created successfully");
        } elseif (isset($responseData['error']['type']) && 
                  strpos($responseData['error']['type'], 'resource_already_exists_exception') !== false) {
            $this->log("Index {$this->indexName} already exists");
        } else {
            $this->log("Warning: Could not create index: " . $response);
        }
    }

    private function queryEvents(): array
    {
        $lastTimestamp = $this->getLastIndexedTimestamp();
        $totalRelays = count($this->relayUrls);
        $this->log("Fetching events from {$totalRelays} relays since " . date('c', $lastTimestamp));
        $this->log("Processing in batches of {$this->batchSize} relays with {$this->requestDelay}ms delay");
        
        $allEvents = [];
        $newestTimestamp = $lastTimestamp;
        $totalEvents = 0;
        $successfulRelays = 0;
        $failedRelays = 0;
        
        // Process relays in batches to avoid overwhelming the system
        $relayBatches = array_chunk($this->relayUrls, $this->batchSize);
        
        foreach ($relayBatches as $batchIndex => $relayBatch) {
            $batchNumber = $batchIndex + 1;
            $totalBatches = count($relayBatches);
            
            $this->log("Processing batch {$batchNumber}/{$totalBatches} with " . count($relayBatch) . " relays");
            
            try {
                $batchEvents = $this->processRelayBatch($relayBatch, $lastTimestamp);
                $allEvents = array_merge($allEvents, $batchEvents);
                
                // Track newest timestamp from this batch
                foreach ($batchEvents as $event) {
                    if ($event->created_at > $newestTimestamp) {
                        $newestTimestamp = $event->created_at;
                    }
                }
                
                $totalEvents += count($batchEvents);
                $successfulRelays += count($relayBatch);
                
                $this->log("Batch {$batchNumber} completed: " . count($batchEvents) . " events from " . count($relayBatch) . " relays");
                
            } catch (Exception $e) {
                $this->log("Batch {$batchNumber} failed: " . $e->getMessage());
                $failedRelays += count($relayBatch);
            }
            
            // Add delay between batches to prevent overwhelming relays
            if ($batchIndex < count($relayBatches) - 1) {
                $this->log("Waiting {$this->requestDelay}ms before next batch...");
                usleep($this->requestDelay * 1000); // Convert ms to microseconds
            }
        }
        
        $this->log("Batch processing completed:");
        $this->log("- Total events found: {$totalEvents}");
        $this->log("- Successful relays: {$successfulRelays}");
        $this->log("- Failed relays: {$failedRelays}");
        $this->log("- Newest timestamp: " . date('c', $newestTimestamp));
        
        // Save the newest timestamp for next run
        if ($totalEvents > 0) {
            $this->saveLastIndexedTimestamp($newestTimestamp);
        } else {
            // No new events, but update the timestamp to current time to avoid re-checking old events
            $this->saveLastIndexedTimestamp(time());
        }
        
        return $allEvents;
    }
    
    private function processRelayBatch(array $relayUrls, int $lastTimestamp): array
    {
        $events = [];
        
        try {
            // Create relay set with batch relays
            $relaySet = new RelaySet();
            foreach ($relayUrls as $relayUrl) {
                try {
                    $relay = new Relay($relayUrl);
                    $relaySet->addRelay($relay);
                    $this->log("Added relay to batch: {$relayUrl}");
                } catch (Exception $e) {
                    $this->log("Warning: Failed to add relay {$relayUrl}: " . $e->getMessage());
                }
            }
            
            if (count($relaySet->getRelays()) === 0) {
                $this->log("Error: No valid relays could be added to batch");
                return [];
            }
            
            // Create filter to get events since last timestamp
            $filter = new Filter();
            $filter->setSince($lastTimestamp);
            $filter->setLimit($this->maxEvents);
            
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
            
            $response = $request->send();
            
            foreach ($response as $relayUrl => $relayResponses) {
                $relayEventCount = 0;
                $this->log("Processing responses from: {$relayUrl}");
                
                foreach ($relayResponses as $responseItem) {
                    if (is_object($responseItem) && isset($responseItem->type) && $responseItem->type === 'EVENT') {
                        $event = $responseItem->event;
                        $events[] = $event;
                        $relayEventCount++;
                    }
                }
                
                $this->log("Relay {$relayUrl}: {$relayEventCount} events");
            }
            
        } catch (Exception $e) {
            $this->log("Failed to process relay batch: " . $e->getMessage());
        }
        
        return $events;
    }

    private function indexEvents(array $events): void
    {
        $count = count($events);
        
        if ($count === 0) {
            $this->log("No events to index");
            return;
        }
        
        $this->log("Indexing {$count} events to Elasticsearch");
        
        // Prepare bulk index request
        $bulkData = '';
        $indexedCount = 0;
        $duplicateCount = 0;
        
        foreach ($events as $event) {
            // Add metadata
            $indexedEvent = [
                'id' => $event->id,
                'pubkey' => $event->pubkey,
                'created_at' => date('c', $event->created_at),
                'kind' => $event->kind,
                'tags' => $event->tags ?? [],
                'content' => $event->content ?? '',
                'sig' => $event->sig,
                'indexed_at' => date('c'),
                'relay' => 'multi-relay', // Updated to reflect multiple relays
                'relay_count' => count($this->relayUrls),
                'relay_urls' => $this->relayUrls
            ];
            
            // Create bulk index entry - using event ID as document ID prevents duplicates
            $indexEntry = [
                'index' => [
                    '_index' => $this->indexName,
                    '_id' => $event->id
                ]
            ];
            
            $bulkData .= json_encode($indexEntry) . "\n";
            $bulkData .= json_encode($indexedEvent) . "\n";
            $indexedCount++;
        }
        
        // Send bulk request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->elasticsearchUrl . '/_bulk');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bulkData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            $this->log("Warning: Failed to send bulk request to Elasticsearch");
            return;
        }
        
        $responseData = json_decode($response, true);
        
        if (isset($responseData['errors']) && $responseData['errors']) {
            $this->log("Warning: Some events failed to index");
            $errorCount = 0;
            $duplicateCount = 0;
            
            // Log specific errors and count duplicates
            if (isset($responseData['items'])) {
                foreach ($responseData['items'] as $item) {
                    if (isset($item['index']['error'])) {
                        $error = $item['index']['error'];
                        if (isset($error['type']) && $error['type'] === 'version_conflict_engine_exception') {
                            $duplicateCount++;
                        } else {
                            $errorCount++;
                            $this->log("Index error: " . json_encode($error));
                        }
                    }
                }
            }
            
            $this->log("Indexing results: {$indexedCount} processed, {$duplicateCount} duplicates, {$errorCount} errors");
        } else {
            $this->log("Successfully indexed {$count} events");
        }
    }

    public function run(): void
    {
        $this->log("Starting incremental event indexing process");
        $this->log("Performance settings:");
        $this->log("- Batch size: {$this->batchSize} relays per batch");
        $this->log("- Request delay: {$this->requestDelay}ms between batches");
        $this->log("- Connection timeout: {$this->connectionTimeout}s");
        $this->log("- Background mode: " . ($this->backgroundMode ? 'enabled' : 'disabled'));
        
        // Set memory and time limits for background processing
        if ($this->backgroundMode) {
            ini_set('memory_limit', '512M');
            set_time_limit(0); // No time limit for background processing
            $this->log("Background mode: Memory limit set to 512M, no time limit");
        }
        
        $this->checkElasticsearch();
        $this->createIndex();
        
        $startTime = microtime(true);
        
        $events = $this->queryEvents();
        if (!empty($events)) {
            $this->log("Processing " . count($events) . " new events");
            $this->indexEvents($events);
        } else {
            $this->log("No new events to process since last run");
        }
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        $this->log("Incremental event indexing process completed");
        $this->log("Execution time: {$executionTime}s");
        $this->log("Memory usage: " . $this->formatBytes(memory_get_peak_usage(true)));
        
        // Add a small delay before exiting to prevent rapid restarts
        if ($this->backgroundMode) {
            $this->log("Background mode: Adding 5s delay before exit");
            sleep(5);
        }
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $indexer = new EventIndexer();
    $indexer->run();
}
