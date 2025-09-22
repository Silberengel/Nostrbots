<?php

namespace Nostrbots\Bot;

use Nostrbots\EventKinds\EventKindRegistry;
use Nostrbots\Utils\RelayManager;
use Nostrbots\Utils\KeyManager;
use Nostrbots\Utils\ValidationManager;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Nip19\Nip19Helper;
use Symfony\Component\Yaml\Yaml;

/**
 * Main bot implementation for publishing Nostr events
 * 
 * Handles the complete flow of creating, signing, and publishing events
 * based on configuration and content data.
 */
class NostrBot implements BotInterface
{
    private array $config = [];
    private RelayManager $relayManager;
    private KeyManager $keyManager;
    private ValidationManager $validationManager;

    public function __construct()
    {
        // Initialize registry with default event kinds
        EventKindRegistry::registerDefaults();
        
        $this->relayManager = new RelayManager();
        $this->keyManager = new KeyManager();
        $this->validationManager = new ValidationManager($this->relayManager);
    }

    public function getName(): string
    {
        return $this->config['bot_name'] ?? 'Nostrbots';
    }

    public function getDescription(): string
    {
        return $this->config['bot_description'] ?? 'A Nostr bot for publishing various event kinds';
    }

    public function loadConfig(string|array $config): void
    {
        if (is_string($config)) {
            if (!file_exists($config)) {
                throw new \InvalidArgumentException("Configuration file not found: {$config}");
            }
            
            $extension = pathinfo($config, PATHINFO_EXTENSION);
            if ($extension === 'yml' || $extension === 'yaml') {
                try {
                    $this->config = Yaml::parseFile($config);
                } catch (\Exception $e) {
                    throw new \InvalidArgumentException("Failed to parse YAML configuration file: {$config}. Error: " . $e->getMessage());
                }
            } else {
                throw new \InvalidArgumentException("Unsupported configuration file format: {$extension}");
            }
        } else {
            $this->config = $config;
        }

        // Load content files if specified
        $this->loadContentFiles();
    }

    public function validateConfig(): array
    {
        $errors = [];

        // Check required fields
        $requiredFields = ['event_kind'];
        foreach ($requiredFields as $field) {
            if (!isset($this->config[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }
        
        // Check for environment variable (required for key management)
        if (!isset($this->config['environment_variable'])) {
            $errors[] = "environment_variable is required for key management";
        }

        // Validate event kind
        if (isset($this->config['event_kind'])) {
            $kind = (int)$this->config['event_kind'];
            if (!EventKindRegistry::isRegistered($kind)) {
                $errors[] = "Event kind {$kind} is not supported";
            } else {
                // Validate event-specific configuration
                $handler = EventKindRegistry::get($kind);
                $eventErrors = $handler->validateConfig($this->config);
                $errors = array_merge($errors, $eventErrors);
            }
        }


        return $errors;
    }

    public function run(bool $dryRun = false): BotResult
    {
        $result = new BotResult();

        try {
            // Validate configuration
            $errors = $this->validateConfig();
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $result->addError($error);
                }
                return $result->finalize();
            }

            echo "Starting {$this->getName()}..." . PHP_EOL;

            // Get event kind handler
            $kind = (int)$this->config['event_kind'];
            $handler = EventKindRegistry::get($kind);

            echo "Creating {$handler->getName()} event..." . PHP_EOL;

            // Create the main event
            $content = $this->config['content'] ?? [];
            
            // If content is a string, wrap it in an array with 'content' key
            if (is_string($content)) {
                $content = ['content' => $content];
            }
            
            // Special handling for publication indices - create content sections first
            // But only if this is not already a child event creation (prevent infinite recursion)
            if ($this->config['event_kind'] === 30040 && 
                isset($this->config['content_references']) && 
                !($this->config['_is_child_event'] ?? false)) {
                echo "📚 Creating content sections for publication index..." . PHP_EOL;
                $createdEventIds = $this->createContentSections($this->config['content_references']);
                
                // Update the main index configuration with actual event IDs
                $this->updateContentReferencesWithEventIds($createdEventIds);
            }
            
            $event = $handler->createEvent($this->config, $content);

            // Handle dry run vs actual publishing
            if ($dryRun) {
                echo "🔍 Dry run mode - configuration is valid, no events will be published" . PHP_EOL;
                $result->setSuccess(true);
                return $result->finalize();
            }

            // Sign the event (only for actual publishing)
            $this->signEvent($event);

            // Publish the event with retry logic
            $relays = $this->getRelays();
            $minSuccessCount = $this->config['min_relay_success'] ?? 1;
            
            echo "📡 Publishing to " . count($relays) . " relays (minimum {$minSuccessCount} required)..." . PHP_EOL;
            
            $publishResults = $this->relayManager->publishWithRetry($event, $relays, $minSuccessCount);
            
            $publishedCount = 0;
            foreach ($publishResults as $relayUrl => $success) {
                if ($success) {
                    $result->addPublishedEvent(
                        $event->getId(),
                        $event->getKind(),
                        $relayUrl,
                        ['title' => $this->config['title'] ?? 'Untitled']
                    );
                    $publishedCount++;
                } else {
                    $result->addWarning("Failed to publish to {$relayUrl}");
                }
            }

            if ($publishedCount === 0) {
                $result->addError("Failed to publish to any relays");
                return $result->finalize();
            }

            // Validate the published event if enabled
            if ($this->config['validate_after_publish'] ?? true) {
                echo "🔍 Validating published event..." . PHP_EOL;
                $validationSuccess = $this->validationManager->validateEvent($event);
                if (!$validationSuccess) {
                    $result->addWarning("Event validation failed - event may not be properly propagated");
                }
            }

            // Handle post-processing (additional events)
            $additionalEvents = $handler->postProcess($event, $this->config);
            foreach ($additionalEvents as $additionalEvent) {
                $this->signEvent($additionalEvent);
                
                echo "📡 Publishing additional event (kind {$additionalEvent->getKind()})..." . PHP_EOL;
                $additionalResults = $this->relayManager->publishWithRetry($additionalEvent, $relays, $minSuccessCount);
                
                foreach ($additionalResults as $relayUrl => $success) {
                    if ($success) {
                        $result->addPublishedEvent(
                            $additionalEvent->getId(),
                            $additionalEvent->getKind(),
                            $relayUrl,
                            ['type' => 'additional_event']
                        );
                    } else {
                        $result->addWarning("Failed to publish additional event to {$relayUrl}");
                    }
                }
                
                // Validate additional events if enabled
                if ($this->config['validate_after_publish'] ?? true) {
                    $validationSuccess = $this->validationManager->validateEvent($additionalEvent);
                    if (!$validationSuccess) {
                        $result->addWarning("Additional event validation failed");
                    }
                }
            }

            // Generate viewing links
            $this->generateViewingLinks($event, $relays[0] ?? '', $result);

            echo "Bot execution completed successfully!" . PHP_EOL;

        } catch (\Exception $e) {
            $result->addError("Bot execution failed: " . $e->getMessage(), $e);
        }

        return $result->finalize();
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Load content files referenced in configuration
     */
    private function loadContentFiles(): void
    {
        if (!isset($this->config['content_files'])) {
            return;
        }

        foreach ($this->config['content_files'] as $key => $filePath) {
            if (is_string($filePath) && file_exists($filePath)) {
                $this->config['content'][$key] = file_get_contents($filePath);
            }
        }
    }

    /**
     * Sign an event with the configured private key
     */
    private function signEvent($event): void
    {
        $envVar = $this->config['environment_variable'];

        $privateKey = $this->keyManager->getPrivateKey($envVar);
        
        $signer = new Sign();
        $signer->signEvent($event, $privateKey);
    }

    /**
     * Get the list of relays to publish to
     */
    private function getRelays(): array
    {
        $relayConfig = $this->config['relays'] ?? 'default';
        
        // Check if we're in test mode
        if ($this->config['test_mode'] ?? false) {
            // Only force test relays if no specific relays are configured
            if ($relayConfig === 'default' || $relayConfig === 'all') {
                return $this->relayManager->getTestRelays();
            }
            // If specific relays are configured, use them even in test mode
            return $this->relayManager->getActiveRelays($relayConfig, 'test');
        }
        
        return $this->relayManager->getActiveRelays($relayConfig, 'production');
    }


    /**
     * Generate viewing links for the published event
     */
    private function generateViewingLinks($event, string $relay, BotResult $result): void
    {
        try {
            // Find the d-tag for addressable events
            $dTag = '';
            foreach ($event->getTags() as $tag) {
                if ($tag[0] === 'd') {
                    $dTag = $tag[1];
                    break;
                }
            }

            if ($dTag) {
                // Generate naddr for addressable events using NIP-19
                try {
                    $nip19Helper = new Nip19Helper();
                    $naddr = $nip19Helper->encodeAddr($event, $dTag, $event->getKind(), $event->getPublicKey(), [$relay]);
                    $viewUrl = "https://next-alexandria.gitcitadel.eu/events?id={$naddr}";
                    echo "View at: {$viewUrl}" . PHP_EOL;
                    $result->setMetadata('view_url', $viewUrl);
                    $result->setMetadata('naddr', $naddr);
                } catch (\Exception $e) {
                    echo "Warning: Could not generate naddr: " . $e->getMessage() . PHP_EOL;
                }
            }

            // Also provide direct event link
            $eventId = $event->getId();
            $directUrl = "https://next-alexandria.gitcitadel.eu/events?id={$eventId}";
            echo "Direct link: {$directUrl}" . PHP_EOL;
            $result->setMetadata('direct_url', $directUrl);

        } catch (\Exception $e) {
            $result->addWarning("Could not generate viewing links: " . $e->getMessage());
        }
    }

    /**
     * Create hierarchical publication structure
     * Creates content sections first, then indices in dependency order
     * Returns array of created event IDs keyed by item ID
     */
    private function createContentSections(array $contentReferences): array
    {
        // Build dependency graph and create events in correct order
        $dependencyGraph = $this->buildDependencyGraph($contentReferences);
        $creationOrder = $this->topologicalSort($dependencyGraph);
        
        echo "📋 Creation order: " . implode(' → ', $creationOrder) . PHP_EOL;
        
        // Create a lookup map from item ID to actual item
        $itemMap = [];
        $currentPubkey = $this->getCurrentPublicKeyHex();
        foreach ($contentReferences as $ref) {
            if (!isset($ref['pubkey'])) {
                $ref['pubkey'] = $currentPubkey;
            }
            $itemId = $this->getItemId($ref);
            $itemMap[$itemId] = $ref;
        }
        
        $createdEventIds = [];
        foreach ($creationOrder as $itemId) {
            if (isset($itemMap[$itemId])) {
                $eventId = $this->createSingleEvent($itemMap[$itemId]);
                if ($eventId) {
                    $createdEventIds[$itemId] = $eventId;
                }
            } else {
                echo "⚠️  Warning: Item not found in map: {$itemId}" . PHP_EOL;
            }
        }
        
        return $createdEventIds;
    }

    /**
     * Build dependency graph from content references
     */
    private function buildDependencyGraph(array $contentReferences): array
    {
        $graph = [];
        $allItems = [];
        
        // First pass: collect all items and set pubkeys
        $currentPubkey = $this->getCurrentPublicKeyHex();
        foreach ($contentReferences as $ref) {
            // Set pubkey if not provided
            if (!isset($ref['pubkey'])) {
                $ref['pubkey'] = $currentPubkey;
            }
            $itemId = $this->getItemId($ref);
            $allItems[$itemId] = $ref;
            $graph[$itemId] = [];
        }
        
        // Second pass: build dependencies
        foreach ($contentReferences as $ref) {
            $itemId = $this->getItemId($ref);
            
            // If this is an index (30040), find what it depends on
            if ($ref['kind'] === 30040) {
                // Look for other items that reference this one
                foreach ($contentReferences as $otherRef) {
                    if ($otherRef['kind'] === 30040 && isset($otherRef['content_references'])) {
                        foreach ($otherRef['content_references'] as $subRef) {
                            if ($this->getItemId($subRef) === $itemId) {
                                $otherId = $this->getItemId($otherRef);
                                $graph[$otherId][] = $itemId; // other depends on this
                            }
                        }
                    }
                }
            }
        }
        
        return $graph;
    }

    /**
     * Topological sort to determine creation order
     */
    private function topologicalSort(array $graph): array
    {
        $visited = [];
        $temp = [];
        $result = [];
        
        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $this->topologicalSortVisit($node, $graph, $visited, $temp, $result);
            }
        }
        
        return array_reverse($result); // Reverse to get creation order (dependencies first)
    }

    private function topologicalSortVisit($node, array $graph, array &$visited, array &$temp, array &$result): void
    {
        if (isset($temp[$node])) {
            throw new \Exception("Circular dependency detected involving: " . $node);
        }
        
        if (isset($visited[$node])) {
            return;
        }
        
        $temp[$node] = true;
        
        foreach ($graph[$node] as $neighbor) {
            $this->topologicalSortVisit($neighbor, $graph, $visited, $temp, $result);
        }
        
        unset($temp[$node]);
        $visited[$node] = true;
        $result[] = $node;
    }

    /**
     * Get unique identifier for an item
     */
    private function getItemId(array $ref): string
    {
        $pubkey = $ref['pubkey'] ?? $this->getCurrentPublicKeyHex();
        return $ref['kind'] . ':' . $pubkey . ':' . $ref['d_tag'];
    }

    /**
     * Create a single event (content section or index)
     * Returns the event ID if successful, null if failed
     */
    private function createSingleEvent(array $item): ?string
    {
        $itemId = $this->getItemId($item);
        echo "📝 Creating: {$itemId}" . PHP_EOL;
        
        try {
            // Create a new bot instance for this specific item
            $itemConfig = $this->createItemConfig($item);
            echo "   📋 Item config event_kind: " . ($itemConfig['event_kind'] ?? 'not set') . PHP_EOL;
            $itemBot = new NostrBot();
            $itemBot->loadConfig($itemConfig);
            
            // Run the bot to create and publish this item
            $result = $itemBot->run(false); // false = not dry run
            
            if ($result->isSuccess()) {
                echo "✅ Successfully created: {$itemId}" . PHP_EOL;
                // Get the event ID from the published events
                $publishedEvents = $result->getPublishedEvents();
                if (!empty($publishedEvents)) {
                    $eventId = $publishedEvents[0]['event_id']; // Get the first published event ID
                    return $eventId;
                } else {
                    echo "   ⚠️  Warning: No published events found for {$itemId}" . PHP_EOL;
                    return null;
                }
            } else {
                echo "❌ Failed to create: {$itemId}" . PHP_EOL;
                foreach ($result->getErrors() as $error) {
                    if (is_array($error)) {
                        echo "   Error: " . implode(', ', $error) . PHP_EOL;
                    } else {
                        echo "   Error: {$error}" . PHP_EOL;
                    }
                }
                foreach ($result->getWarnings() as $warning) {
                    echo "   Warning: {$warning}" . PHP_EOL;
                }
                return null;
            }
        } catch (\Exception $e) {
            echo "❌ Error creating {$itemId}: " . $e->getMessage() . PHP_EOL;
            return null;
        }
    }

    /**
     * Create configuration for a specific item
     */
    private function createItemConfig(array $item): array
    {
        $config = $this->config;
        $config['event_kind'] = $item['kind'];
        $config['d-tag'] = $item['d_tag'];
        
        // Mark this as a child event to prevent infinite recursion
        $config['_is_child_event'] = true;
        
        // Get the current bot's public key (hex format for a tags)
        $currentPubkey = $this->getCurrentPublicKeyHex();
        
        // For content sections (30041), we need to create content
        if ($item['kind'] === 30041) {
            $config['bot_name'] = 'Publication Content Bot';
            $config['title'] = $this->generateContentTitle($item['d_tag']);
            $config['summary'] = $this->generateContentSummary($item['d_tag']);
            $config['content'] = $this->generateContentFile($item['d_tag']);
        }
        
        // For indices (30040), copy the content_references and update pubkeys
        if ($item['kind'] === 30040 && isset($item['content_references'])) {
            $config['content_references'] = $item['content_references'];
            // Update all pubkeys in content_references to use current bot's pubkey
            foreach ($config['content_references'] as &$ref) {
                $ref['pubkey'] = $currentPubkey;
            }
        }
        
        return $config;
    }

    /**
     * Get the current bot's public key
     */
    private function getCurrentPublicKey(): string
    {
        $envVar = $this->config['environment_variable'];
        $privateKey = getenv($envVar);
        
        if ($privateKey === false || empty($privateKey)) {
            throw new \InvalidArgumentException("Environment variable '{$envVar}' is not set or is empty");
        }
        
        // Convert bech32 nsec to hex if needed
        if (str_starts_with($privateKey, 'nsec')) {
            $key = new \swentel\nostr\Key\Key();
            $privateKey = $key->convertToHex($privateKey);
        }
        
        // Get the public key
        $key = new \swentel\nostr\Key\Key();
        $hexPublicKey = $key->getPublicKey($privateKey);
        return $key->convertPublicKeyToBech32($hexPublicKey);
    }

    /**
     * Get the current bot's public key in hex format (for a tags)
     */
    private function getCurrentPublicKeyHex(): string
    {
        $envVar = $this->config['environment_variable'];
        $privateKey = getenv($envVar);
        
        if ($privateKey === false || empty($privateKey)) {
            throw new \InvalidArgumentException("Environment variable '{$envVar}' is not set or is empty");
        }
        
        // Convert bech32 nsec to hex if needed
        if (str_starts_with($privateKey, 'nsec')) {
            $key = new \swentel\nostr\Key\Key();
            $privateKey = $key->convertToHex($privateKey);
        }
        
        // Get the public key in hex format
        $key = new \swentel\nostr\Key\Key();
        return $key->getPublicKey($privateKey);
    }

    /**
     * Generate content title based on d-tag
     */
    private function generateContentTitle(string $dTag): string
    {
        $titleMap = [
            'nostr-guide-introduction' => 'Introduction to Nostr',
            'nostr-guide-getting-started' => 'Getting Started with Nostr',
            'nostr-guide-advanced-features' => 'Advanced Nostr Features',
            'nostr-guide-development' => 'Nostr Development Guide'
        ];
        
        return $titleMap[$dTag] ?? ucwords(str_replace('-', ' ', $dTag));
    }

    /**
     * Generate content summary based on d-tag
     */
    private function generateContentSummary(string $dTag): string
    {
        $summaryMap = [
            'nostr-guide-introduction' => 'An introduction to the Nostr protocol and its core concepts',
            'nostr-guide-getting-started' => 'Learn how to get started with Nostr clients and basic usage',
            'nostr-guide-advanced-features' => 'Explore advanced features and capabilities of the Nostr protocol',
            'nostr-guide-development' => 'Guide for developers building on the Nostr protocol'
        ];
        
        return $summaryMap[$dTag] ?? 'Content section for ' . $dTag;
    }

    /**
     * Generate content file based on d-tag
     */
    private function generateContentFile(string $dTag): string
    {
        $contentMap = [
            'nostr-guide-introduction' => "# Introduction to Nostr\n\nNostr is a simple, open protocol that enables global, decentralized, and uncensorable social media.\n\n## Key Concepts\n\n- **Relays**: Servers that store and forward messages\n- **Keys**: Cryptographic identities (npub/nsec)\n- **Events**: Signed messages with different kinds\n- **Clients**: Applications that interact with the protocol\n\n## Why Nostr?\n\nNostr provides a foundation for building censorship-resistant social applications.",
            'nostr-guide-getting-started' => "# Getting Started with Nostr\n\n## Choosing a Client\n\nPopular Nostr clients include:\n\n- **Damus**: iOS client\n- **Amethyst**: Android client\n- **Iris**: Web client\n- **Snort**: Web client\n\n## Creating Your Identity\n\n1. Download a Nostr client\n2. Generate your keypair\n3. Backup your private key (nsec)\n4. Start following people and posting!",
            'nostr-guide-advanced-features' => "# Advanced Nostr Features\n\n## NIPs (Nostr Implementation Possibilities)\n\nNIPs extend the base protocol with new features:\n\n- **NIP-04**: Encrypted direct messages\n- **NIP-19**: Bech32-encoded entities\n- **NIP-23**: Long-form content\n- **NIP-25**: Reactions\n\n## Relay Management\n\n- Use multiple relays for redundancy\n- Choose relays based on your needs\n- Consider relay policies and moderation",
            'nostr-guide-development' => "# Nostr Development Guide\n\n## Building Nostr Applications\n\n### Client Development\n\n- Implement the Nostr protocol\n- Handle WebSocket connections\n- Manage keypairs and signing\n- Parse and validate events\n\n### Relay Development\n\n- Store and serve events\n- Implement filtering\n- Handle subscriptions\n- Manage rate limiting\n\n## Libraries and Tools\n\n- **nostr-php**: PHP library\n- **nostr-tools**: JavaScript utilities\n- **nostr-sdk**: Rust SDK"
        ];
        
        return $contentMap[$dTag] ?? "# Content for {$dTag}\n\nThis is placeholder content for the {$dTag} section.";
    }

    /**
     * Update content references with actual event IDs
     */
    private function updateContentReferencesWithEventIds(array $createdEventIds): void
    {
        if (!isset($this->config['content_references'])) {
            return;
        }

        echo "🔗 Updating content references with actual event IDs..." . PHP_EOL;
        
        $currentPubkey = $this->getCurrentPublicKeyHex();
        
        foreach ($this->config['content_references'] as &$ref) {
            // Ensure pubkey is set for the item ID calculation
            if (!isset($ref['pubkey'])) {
                $ref['pubkey'] = $currentPubkey;
            }
            
            $itemId = $this->getItemId($ref);
            
            if (isset($createdEventIds[$itemId])) {
                $ref['event_id'] = $createdEventIds[$itemId];
                echo "   ✓ Updated {$ref['d_tag']} with event ID: {$createdEventIds[$itemId]}" . PHP_EOL;
            } else {
                echo "   ⚠️  No event ID found for {$ref['d_tag']} (item ID: {$itemId})" . PHP_EOL;
            }
        }
    }
}
