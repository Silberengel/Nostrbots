<?php

namespace Nostrbots\Utils;

use Swentel\Nostr\Event;
use Swentel\Nostr\Relay;

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
    public function validateEvent(Event $event, int $waitSeconds = 10): bool
    {
        echo "🔍 Validating published event..." . PHP_EOL;
        echo "⏳ Waiting {$waitSeconds} seconds for event propagation..." . PHP_EOL;
        
        sleep($waitSeconds);
        
        return $this->retryManager->execute(function() use ($event) {
            return $this->fetchAndValidateEvent($event);
        }, "Event validation");
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
        
        foreach ($relays as $relayUrl) {
            try {
                $relay = new Relay($relayUrl);
                $relay->connect();
                
                // Query for the specific event
                $filter = [
                    'ids' => [$eventId]
                ];
                
                $events = $relay->query($filter);
                
                if (!empty($events)) {
                    $fetchedEvent = $events[0];
                    
                    // Validate key fields
                    if ($this->validateEventFields($expectedEvent, $fetchedEvent)) {
                        echo "✅ Event validation successful on relay: {$relayUrl}" . PHP_EOL;
                        $relay->disconnect();
                        return true;
                    } else {
                        echo "❌ Event validation failed - content mismatch on relay: {$relayUrl}" . PHP_EOL;
                    }
                } else {
                    echo "⚠️  Event not found on relay: {$relayUrl}" . PHP_EOL;
                }
                
                $relay->disconnect();
            } catch (\Exception $e) {
                echo "⚠️  Failed to validate on relay {$relayUrl}: " . $e->getMessage() . PHP_EOL;
            }
        }
        
        echo "❌ Event validation failed on all relays" . PHP_EOL;
        return false;
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
