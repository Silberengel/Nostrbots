<?php

namespace Nostrbots\Bot;

/**
 * Interface for Nostr bots
 * 
 * Defines the contract that all bot implementations must follow.
 * Provides a standardized way to configure, validate, and run bots.
 */
interface BotInterface
{
    /**
     * Get the bot's name
     * 
     * @return string The bot name
     */
    public function getName(): string;

    /**
     * Get the bot's description
     * 
     * @return string Brief description of what the bot does
     */
    public function getDescription(): string;

    /**
     * Load configuration from a file or array
     * 
     * @param string|array $config Path to config file or config array
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function loadConfig(string|array $config): void;

    /**
     * Validate the current configuration
     * 
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfig(): array;

    /**
     * Run the bot
     * 
     * @return BotResult The result of the bot execution
     * @throws \Exception If bot execution fails
     */
    public function run(): BotResult;

    /**
     * Get the bot's current configuration
     * 
     * @return array The configuration array
     */
    public function getConfig(): array;

    /**
     * Set a configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    public function setConfig(string $key, mixed $value): void;
}
