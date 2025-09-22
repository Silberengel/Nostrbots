<?php

namespace Nostrbots\Utils;

use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Request\Request;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Relay\Relay;
use Symfony\Component\Yaml\Yaml;

/**
 * Manages Nostr relays for the bot system
 * 
 * Handles relay discovery, testing, and selection based on configuration.
 */
class RelayManager
{
    private const DEFAULT_RELAY = 'wss://thecitadel.nostr1.com';
    private const RELAY_CONFIG_FILE = __DIR__ . '/../relays.yml';

    /**
     * Get active relays based on configuration
     * 
     * @param string|array $relayConfig Relay configuration (category name, URL, or array)
     * @return array Array of working relay URLs
     */
    public function getActiveRelays(string|array $relayConfig): array
    {
        if (is_array($relayConfig)) {
            return $this->testRelayList($relayConfig);
        }

        if (str_starts_with($relayConfig, 'wss://') || str_starts_with($relayConfig, 'ws://')) {
            // Single relay URL
            return $this->testRelayList([$relayConfig]);
        }

        // Relay category or 'all'
        $relays = $this->getRelayList($relayConfig);
        return $this->testRelayList($relays);
    }

    /**
     * Get relay list from configuration file
     * 
     * @param string $category Category name or 'all' for all relays
     * @return array Array of relay URLs
     */
    public function getRelayList(string $category = 'all'): array
    {
        if (!file_exists(self::RELAY_CONFIG_FILE)) {
            echo "Relay configuration file not found, using default relay" . PHP_EOL;
            return [self::DEFAULT_RELAY];
        }

        try {
            $relays = Yaml::parseFile(self::RELAY_CONFIG_FILE);
        } catch (\Exception $e) {
            echo "Invalid relay configuration ({$e->getMessage()}), using default relay" . PHP_EOL;
            return [self::DEFAULT_RELAY];
        }
        
        if (!is_array($relays) || empty($relays)) {
            echo "Invalid relay configuration, using default relay" . PHP_EOL;
            return [self::DEFAULT_RELAY];
        }

        if ($category === 'all') {
            // Flatten all relay categories
            $allRelays = [];
            foreach ($relays as $categoryRelays) {
                if (is_array($categoryRelays)) {
                    $allRelays = array_merge($allRelays, $categoryRelays);
                }
            }
            return array_unique($allRelays);
        }

        if (isset($relays[$category]) && is_array($relays[$category])) {
            return $relays[$category];
        }

        echo "Relay category '{$category}' not found, using all relays" . PHP_EOL;
        return $this->getRelayList('all');
    }

    /**
     * Test a list of relays and return only the working ones
     * 
     * @param array $relays Array of relay URLs to test
     * @return array Array of working relay URLs
     */
    public function testRelayList(array $relays): array
    {
        $workingRelays = [];

        foreach ($relays as $relayUrl) {
            if ($this->testRelay($relayUrl)) {
                $workingRelays[] = $relayUrl;
            }
        }

        if (empty($workingRelays)) {
            echo "No working relays found, using default relay" . PHP_EOL;
            if ($this->testRelay(self::DEFAULT_RELAY)) {
                $workingRelays[] = self::DEFAULT_RELAY;
            } else {
                throw new \RuntimeException("Default relay is also not working. Cannot continue.");
            }
        }

        return $workingRelays;
    }

    /**
     * Test if a relay is working
     * 
     * @param string $relayUrl The relay URL to test
     * @return bool True if the relay is working
     */
    public function testRelay(string $relayUrl): bool
    {
        try {
            echo "Testing relay: {$relayUrl}... ";

            $subscription = new Subscription();
            $subscriptionId = $subscription->setId();
            
            $filter = new Filter();
            $filter->setKinds([1, 30023, 30040, 30041]);
            $filter->setLimit(1);
            
            $requestMessage = new RequestMessage($subscriptionId, [$filter]);
            $relay = new Relay($relayUrl);
            $relay->setMessage($requestMessage);
            
            $request = new Request($relay, $requestMessage);
            $response = $request->send();

            // Check if we got a successful response
            $responseJson = json_encode($response);
            $success = str_contains($responseJson, '"isSuccess":true');
            
            echo $success ? "✓ PASS" . PHP_EOL : "✗ FAIL" . PHP_EOL;
            return $success;

        } catch (\Exception $e) {
            echo "✗ FAIL ({$e->getMessage()})" . PHP_EOL;
            return false;
        }
    }

    /**
     * Get information about available relay categories
     * 
     * @return array Array of category information
     */
    public function getRelayCategories(): array
    {
        if (!file_exists(self::RELAY_CONFIG_FILE)) {
            return [];
        }

        try {
            $relays = Yaml::parseFile(self::RELAY_CONFIG_FILE);
        } catch (\Exception $e) {
            return [];
        }
        
        if (!is_array($relays)) {
            return [];
        }

        $categories = [];
        foreach ($relays as $category => $relayList) {
            if (is_array($relayList)) {
                $categories[$category] = [
                    'name' => $category,
                    'count' => count($relayList),
                    'relays' => $relayList
                ];
            }
        }

        return $categories;
    }

    /**
     * Get relays for a specific operation type
     * 
     * @param string $operation 'read', 'write', or 'both'
     * @return array Array of relay URLs
     */
    public function getRelays(string $operation = 'both'): array
    {
        $relays = $this->getRelayList('all');
        
        // For now, return all relays for all operations
        // In the future, this could be enhanced to filter based on relay capabilities
        return $this->testRelayList($relays);
    }

    /**
     * Publish an event to relays with retry logic
     * 
     * @param \Swentel\Nostr\Event $event The event to publish
     * @param array $relays Array of relay URLs
     * @param int $minSuccessCount Minimum number of successful publications
     * @return array Results of publication attempts
     */
    public function publishWithRetry(\Swentel\Nostr\Event $event, array $relays, int $minSuccessCount = 1): array
    {
        $results = [];
        $successCount = 0;
        $retryManager = RetryManager::forRelays();

        foreach ($relays as $relayUrl) {
            $results[$relayUrl] = $retryManager->execute(
                function() use ($event, $relayUrl) {
                    return $this->publishToRelay($event, $relayUrl);
                },
                "Publishing to {$relayUrl}"
            );

            if ($results[$relayUrl]) {
                $successCount++;
            }
        }

        if ($successCount < $minSuccessCount) {
            throw new \RuntimeException(
                "Failed to publish to minimum required relays. " .
                "Required: {$minSuccessCount}, Successful: {$successCount}"
            );
        }

        return $results;
    }

    /**
     * Publish an event to a single relay
     * 
     * @param \Swentel\Nostr\Event $event The event to publish
     * @param string $relayUrl The relay URL
     * @return bool True if successful
     */
    private function publishToRelay(\Swentel\Nostr\Event $event, string $relayUrl): bool
    {
        try {
            $relay = new Relay($relayUrl);
            $relay->connect();
            
            $result = $relay->publish($event);
            $relay->disconnect();
            
            if ($result) {
                echo "✅ Published to {$relayUrl}" . PHP_EOL;
                return true;
            } else {
                echo "❌ Failed to publish to {$relayUrl}" . PHP_EOL;
                return false;
            }
        } catch (\Exception $e) {
            echo "❌ Error publishing to {$relayUrl}: " . $e->getMessage() . PHP_EOL;
            return false;
        }
    }

    /**
     * Query events from relays with retry logic
     * 
     * @param array $filters Array of filters to apply
     * @param array $relays Array of relay URLs
     * @return array Array of events
     */
    public function queryWithRetry(array $filters, array $relays): array
    {
        $allEvents = [];
        $retryManager = RetryManager::forRelays();

        foreach ($relays as $relayUrl) {
            try {
                $events = $retryManager->execute(
                    function() use ($filters, $relayUrl) {
                        return $this->queryFromRelay($filters, $relayUrl);
                    },
                    "Querying from {$relayUrl}"
                );
                
                $allEvents = array_merge($allEvents, $events);
            } catch (\Exception $e) {
                echo "⚠️  Failed to query from {$relayUrl}: " . $e->getMessage() . PHP_EOL;
            }
        }

        // Remove duplicates based on event ID
        $uniqueEvents = [];
        $seenIds = [];
        
        foreach ($allEvents as $event) {
            $eventId = $event->getId();
            if (!in_array($eventId, $seenIds)) {
                $uniqueEvents[] = $event;
                $seenIds[] = $eventId;
            }
        }

        return $uniqueEvents;
    }

    /**
     * Query events from a single relay
     * 
     * @param array $filters Array of filters to apply
     * @param string $relayUrl The relay URL
     * @return array Array of events
     */
    private function queryFromRelay(array $filters, string $relayUrl): array
    {
        try {
            $relay = new Relay($relayUrl);
            $relay->connect();
            
            $events = $relay->query($filters);
            $relay->disconnect();
            
            echo "📥 Retrieved " . count($events) . " events from {$relayUrl}" . PHP_EOL;
            return $events;
        } catch (\Exception $e) {
            echo "❌ Error querying from {$relayUrl}: " . $e->getMessage() . PHP_EOL;
            throw $e;
        }
    }
}
