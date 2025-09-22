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
}
