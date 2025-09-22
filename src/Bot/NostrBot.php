<?php

namespace Nostrbots\Bot;

use Nostrbots\EventKinds\EventKindRegistry;
use Nostrbots\Utils\RelayManager;
use Nostrbots\Utils\KeyManager;
use Nostrbots\Utils\ValidationManager;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;
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
        $requiredFields = ['event_kind', 'npub'];
        foreach ($requiredFields as $field) {
            if (!isset($this->config[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
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

        // Validate npub configuration
        if (isset($this->config['npub'])) {
            if (!isset($this->config['npub']['environment_variable'])) {
                $errors[] = "npub.environment_variable is required";
            }
            if (!isset($this->config['npub']['public_key'])) {
                $errors[] = "npub.public_key is required";
            }
        }

        return $errors;
    }

    public function run(): BotResult
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
            $event = $handler->createEvent($this->config, $this->config['content'] ?? []);

            // Sign the event
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
        $envVar = $this->config['npub']['environment_variable'];
        $expectedPubkey = $this->config['npub']['public_key'];

        $privateKey = $this->keyManager->getPrivateKey($envVar, $expectedPubkey);
        
        $signer = new Sign();
        $signer->signEvent($event, $privateKey);
    }

    /**
     * Get the list of relays to publish to
     */
    private function getRelays(): array
    {
        // Check if we're in test mode
        if ($this->config['test_mode'] ?? false) {
            return $this->relayManager->getTestRelays();
        }
        
        $relayConfig = $this->config['relays'] ?? 'all';
        return $this->relayManager->getActiveRelays($relayConfig);
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
                // Generate naddr for addressable events
                $kind = $event->getKind();
                $pubkey = $event->getPublicKey();
                
                // Use nak to generate naddr if available
                $naddr = shell_exec("nak encode naddr -d '{$dTag}' --author {$pubkey} --kind {$kind} --relay {$relay}");
                if ($naddr) {
                    $naddr = trim($naddr);
                    $viewUrl = "https://njump.me/{$naddr}";
                    echo "View at: {$viewUrl}" . PHP_EOL;
                    $result->setMetadata('view_url', $viewUrl);
                    $result->setMetadata('naddr', $naddr);
                }
            }

            // Also provide direct event link
            $eventId = $event->getId();
            $directUrl = "https://njump.me/{$eventId}";
            echo "Direct link: {$directUrl}" . PHP_EOL;
            $result->setMetadata('direct_url', $directUrl);

        } catch (\Exception $e) {
            $result->addWarning("Could not generate viewing links: " . $e->getMessage());
        }
    }
}
