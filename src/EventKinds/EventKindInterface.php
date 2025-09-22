<?php

namespace Nostrbots\EventKinds;

use swentel\nostr\Event\Event;

/**
 * Interface for all Nostr event kinds supported by Nostrbots
 * 
 * This interface defines the contract that all event kind handlers must implement.
 * It provides a standardized way to create, validate, and process different types
 * of Nostr events while maintaining extensibility for future event kinds.
 */
interface EventKindInterface
{
    /**
     * Get the numeric kind identifier for this event type
     * 
     * @return int The event kind number (e.g., 30023, 30040, 30041)
     */
    public function getKind(): int;

    /**
     * Get a human-readable name for this event kind
     * 
     * @return string The display name (e.g., "Long-form Content", "Publication Index")
     */
    public function getName(): string;

    /**
     * Get a description of what this event kind is used for
     * 
     * @return string A brief description of the event kind's purpose
     */
    public function getDescription(): string;

    /**
     * Create a new event of this kind from configuration data
     * 
     * @param array $config Configuration data from YAML or other source
     * @param array $content Content data (markdown, text, etc.)
     * @return Event The created and configured event
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function createEvent(array $config, array $content): Event;

    /**
     * Validate that the provided configuration is valid for this event kind
     * 
     * @param array $config Configuration data to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfig(array $config): array;

    /**
     * Get the required configuration fields for this event kind
     * 
     * @return array Array of required field names
     */
    public function getRequiredFields(): array;

    /**
     * Get the optional configuration fields for this event kind
     * 
     * @return array Array of optional field names with their descriptions
     */
    public function getOptionalFields(): array;

    /**
     * Process any post-creation tasks (e.g., generating related events)
     * 
     * @param Event $event The created event
     * @param array $config Original configuration
     * @return array Array of additional events to publish (if any)
     */
    public function postProcess(Event $event, array $config): array;
}
