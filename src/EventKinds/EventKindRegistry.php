<?php

namespace Nostrbots\EventKinds;

/**
 * Registry for managing available event kinds
 * 
 * Provides a centralized way to register, discover, and instantiate
 * event kind handlers. Makes the system easily extensible for new event kinds.
 */
class EventKindRegistry
{
    /** @var array<int, string> Map of kind numbers to class names */
    private static array $kindMap = [];

    /** @var array<int, EventKindInterface> Cached instances */
    private static array $instances = [];

    /**
     * Register default event kinds
     */
    public static function registerDefaults(): void
    {
        self::register(30023, LongFormContent::class);
        self::register(30040, PublicationIndex::class);
        self::register(30041, PublicationContent::class);
    }

    /**
     * Register an event kind handler
     * 
     * @param int $kind The event kind number
     * @param string $className The handler class name
     * @throws \InvalidArgumentException If class doesn't implement EventKindInterface
     */
    public static function register(int $kind, string $className): void
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class {$className} does not exist");
        }

        if (!is_subclass_of($className, EventKindInterface::class)) {
            throw new \InvalidArgumentException("Class {$className} must implement EventKindInterface");
        }

        self::$kindMap[$kind] = $className;
        
        // Clear cached instance if it exists
        unset(self::$instances[$kind]);
    }

    /**
     * Get an event kind handler instance
     * 
     * @param int $kind The event kind number
     * @return EventKindInterface The handler instance
     * @throws \InvalidArgumentException If kind is not registered
     */
    public static function get(int $kind): EventKindInterface
    {
        if (!isset(self::$kindMap[$kind])) {
            throw new \InvalidArgumentException("Event kind {$kind} is not registered");
        }

        if (!isset(self::$instances[$kind])) {
            $className = self::$kindMap[$kind];
            self::$instances[$kind] = new $className();
        }

        return self::$instances[$kind];
    }

    /**
     * Check if an event kind is registered
     * 
     * @param int $kind The event kind number
     * @return bool True if registered
     */
    public static function isRegistered(int $kind): bool
    {
        return isset(self::$kindMap[$kind]);
    }

    /**
     * Get all registered event kinds
     * 
     * @return array<int, string> Map of kind numbers to class names
     */
    public static function getAllKinds(): array
    {
        return self::$kindMap;
    }

    /**
     * Get information about all registered event kinds
     * 
     * @return array Array of event kind information
     */
    public static function getKindInfo(): array
    {
        $info = [];
        
        foreach (self::$kindMap as $kind => $className) {
            $handler = self::get($kind);
            $info[$kind] = [
                'kind' => $kind,
                'name' => $handler->getName(),
                'description' => $handler->getDescription(),
                'class' => $className,
                'required_fields' => $handler->getRequiredFields(),
                'optional_fields' => $handler->getOptionalFields()
            ];
        }

        return $info;
    }

    /**
     * Clear all registrations (mainly for testing)
     */
    public static function clear(): void
    {
        self::$kindMap = [];
        self::$instances = [];
    }
}
