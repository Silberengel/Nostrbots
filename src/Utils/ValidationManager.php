<?php

namespace Nostrbots\Utils;

use swentel\nostr\Event\Event;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Request\Request;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Filter\Filter;

/**
 * Handles post-publish validation and verification
 */
class ValidationManager
{
    private RelayManager $relayManager;
    private RetryManager $retryManager;

    public function __construct(RelayManager $relayManager)
    {
        $this->relayManager = $relayManager;
        $this->retryManager = RetryManager::forValidation();
    }

    /**
     * Validate that an event was published correctly
     */
    public function validateEvent(\swentel\nostr\Event\Event $event, int $waitSeconds = 10): bool
    {
        echo "🔍 Validating published event..." . PHP_EOL;
        
        // Always show event ID
        echo "📝 Event ID: " . $event->getId() . PHP_EOL;
        
        // Check if we're in mock mode
        if (getenv('NOSTR_MOCK_PUBLISH') === 'true') {
            echo "🔧 MOCK MODE: Simulating successful event validation" . PHP_EOL;
            echo "   Public Key: " . $event->getPublicKey() . PHP_EOL;
            return true;
        }
        
        echo "⏳ Waiting {$waitSeconds} seconds for event propagation..." . PHP_EOL;
        sleep($waitSeconds);
        
        $validationResult = $this->retryManager->execute(function() use ($event) {
            return $this->fetchAndValidateEvent($event);
        }, "Event validation");
        
        // Show verification result
        if ($validationResult) {
            echo "✅ Event confirmed stored on relay!" . PHP_EOL;
        } else {
            echo "⚠️  Event validation failed - event may not be properly propagated" . PHP_EOL;
        }
        
        return $validationResult;
    }

    /**
     * Validate that a publication index was updated correctly
     */
    public function validateIndexUpdate(Event $indexEvent, array $expectedContentReferences, int $waitSeconds = 10): bool
    {
        echo "🔍 Validating index update..." . PHP_EOL;
        echo "⏳ Waiting {$waitSeconds} seconds for index propagation..." . PHP_EOL;
        
        sleep($waitSeconds);
        
        return $this->retryManager->execute(function() use ($indexEvent, $expectedContentReferences) {
            return $this->fetchAndValidateIndex($indexEvent, $expectedContentReferences);
        }, "Index validation");
    }

    /**
     * Fetch and validate a specific event
     */
    private function fetchAndValidateEvent(Event $expectedEvent): bool
    {
        $relays = $this->relayManager->getRelays('write');
        $eventId = $expectedEvent->getId();
        
        // Create RelaySet for validation
        $relaySet = new RelaySet();
        foreach ($relays as $relayUrl) {
            $relaySet->addRelay(new Relay($relayUrl));
        }
        
        try {
            // Create subscription and filter for the specific event
            $subscription = new Subscription();
            $subscriptionId = $subscription->setId();
            $filter = new Filter();
            $filter->setIds([$eventId]);
            $requestMessage = new RequestMessage($subscriptionId, [$filter]);
            
            $request = new Request($relaySet, $requestMessage);
            $response = $request->send();
            
            // Process the response to find the event
            foreach ($response as $relayUrl => $relayResponses) {
                foreach ($relayResponses as $responseItem) {
                    if (is_object($responseItem) && isset($responseItem->type) && $responseItem->type === 'EVENT') {
                        $event = $responseItem->event;
                        
                        // Validate key fields
                        if ($this->validateEventFields($expectedEvent, $event)) {
                            echo "✅ Event validation successful on relay: {$relayUrl}" . PHP_EOL;
                            return true;
                        } else {
                            echo "❌ Event validation failed - content mismatch on relay: {$relayUrl}" . PHP_EOL;
                        }
                    }
                }
            }
            
            echo "❌ Event not found on any relay" . PHP_EOL;
            return false;
            
        } catch (\Exception $e) {
            echo "❌ Error validating event: " . $e->getMessage() . PHP_EOL;
            return false;
        }
    }

    /**
     * Fetch and validate a publication index
     */
    private function fetchAndValidateIndex(Event $expectedIndex, array $expectedContentReferences): bool
    {
        $relays = $this->relayManager->getRelays('write');
        $eventId = $expectedIndex->getId();
        
        foreach ($relays as $relayUrl) {
            try {
                $relay = new Relay($relayUrl);
                $relay->connect();
                
                // Query for the specific index event
                $filter = [
                    'ids' => [$eventId]
                ];
                
                $events = $relay->query($filter);
                
                if (!empty($events)) {
                    $fetchedEvent = $events[0];
                    
                    // Validate index structure
                    if ($this->validateIndexStructure($expectedIndex, $fetchedEvent, $expectedContentReferences)) {
                        echo "✅ Index validation successful on relay: {$relayUrl}" . PHP_EOL;
                        $relay->disconnect();
                        return true;
                    } else {
                        echo "❌ Index validation failed - structure mismatch on relay: {$relayUrl}" . PHP_EOL;
                    }
                } else {
                    echo "⚠️  Index not found on relay: {$relayUrl}" . PHP_EOL;
                }
                
                $relay->disconnect();
            } catch (\Exception $e) {
                echo "⚠️  Failed to validate index on relay {$relayUrl}: " . $e->getMessage() . PHP_EOL;
            }
        }
        
        echo "❌ Index validation failed on all relays" . PHP_EOL;
        return false;
    }

    /**
     * Validate that event fields match
     */
    private function validateEventFields(Event $expected, Event $actual): bool
    {
        $checks = [
            'kind' => $expected->getKind() === $actual->getKind(),
            'content' => $expected->getContent() === $actual->getContent(),
            'pubkey' => $expected->getPubkey() === $actual->getPubkey()
        ];
        
        // Validate tags (simplified - just check count and key tags)
        $expectedTags = $expected->getTags();
        $actualTags = $actual->getTags();
        
        if (count($expectedTags) !== count($actualTags)) {
            echo "❌ Tag count mismatch: expected " . count($expectedTags) . ", got " . count($actualTags) . PHP_EOL;
            return false;
        }
        
        // Check critical tags
        $criticalTags = ['d', 'title'];
        foreach ($criticalTags as $tagName) {
            $expectedTag = $this->findTag($expectedTags, $tagName);
            $actualTag = $this->findTag($actualTags, $tagName);
            
            if ($expectedTag !== $actualTag) {
                echo "❌ Critical tag '{$tagName}' mismatch" . PHP_EOL;
                return false;
            }
        }
        
        $allPassed = array_reduce($checks, fn($carry, $check) => $carry && $check, true);
        
        if (!$allPassed) {
            echo "❌ Event field validation failed" . PHP_EOL;
            foreach ($checks as $field => $passed) {
                if (!$passed) {
                    echo "   - {$field}: mismatch" . PHP_EOL;
                }
            }
        }
        
        return $allPassed;
    }

    /**
     * Validate index structure and content references
     */
    private function validateIndexStructure(Event $expected, Event $actual, array $expectedContentReferences): bool
    {
        // First validate basic event fields
        if (!$this->validateEventFields($expected, $actual)) {
            return false;
        }
        
        // Validate content references (a tags)
        $actualTags = $actual->getTags();
        $actualContentRefs = array_filter($actualTags, fn($tag) => $tag[0] === 'a');
        
        if (count($actualContentRefs) !== count($expectedContentReferences)) {
            echo "❌ Content reference count mismatch: expected " . count($expectedContentReferences) . ", got " . count($actualContentRefs) . PHP_EOL;
            return false;
        }
        
        // Validate each content reference
        foreach ($expectedContentReferences as $index => $expectedRef) {
            if (!isset($actualContentRefs[$index])) {
                echo "❌ Missing content reference at index {$index}" . PHP_EOL;
                return false;
            }
            
            $actualRef = $actualContentRefs[$index];
            if ($actualRef[1] !== $expectedRef) {
                echo "❌ Content reference mismatch at index {$index}: expected '{$expectedRef}', got '{$actualRef[1]}'" . PHP_EOL;
                return false;
            }
        }
        
        echo "✅ Index structure validation passed" . PHP_EOL;
        return true;
    }

    /**
     * Find a specific tag in the tags array
     */
    private function findTag(array $tags, string $tagName): ?array
    {
        foreach ($tags as $tag) {
            if ($tag[0] === $tagName) {
                return $tag;
            }
        }
        return null;
    }
}
