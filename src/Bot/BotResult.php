<?php

namespace Nostrbots\Bot;

/**
 * Result object for bot execution
 * 
 * Contains information about what the bot accomplished, including
 * published events, errors, and other relevant data.
 */
class BotResult
{
    private bool $success;
    private array $publishedEvents = [];
    private array $errors = [];
    private array $warnings = [];
    private array $metadata = [];
    private float $executionTime;

    public function __construct(bool $success = true)
    {
        $this->success = $success;
        $this->executionTime = microtime(true);
    }

    /**
     * Mark the result as successful
     */
    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    /**
     * Check if the bot execution was successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Add a published event to the result
     */
    public function addPublishedEvent(string $eventId, int $kind, string $relay, array $metadata = []): self
    {
        $this->publishedEvents[] = [
            'event_id' => $eventId,
            'kind' => $kind,
            'relay' => $relay,
            'metadata' => $metadata,
            'timestamp' => time()
        ];
        return $this;
    }

    /**
     * Get all published events
     */
    public function getPublishedEvents(): array
    {
        return $this->publishedEvents;
    }

    /**
     * Add an error to the result
     */
    public function addError(string $error, \Throwable $exception = null): self
    {
        $errorData = ['message' => $error, 'timestamp' => time()];
        if ($exception) {
            $errorData['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }
        $this->errors[] = $errorData;
        $this->success = false;
        return $this;
    }

    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Add a warning to the result
     */
    public function addWarning(string $warning): self
    {
        $this->warnings[] = [
            'message' => $warning,
            'timestamp' => time()
        ];
        return $this;
    }

    /**
     * Get all warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Set metadata for the result
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get metadata
     */
    public function getMetadata(string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }
        return $this->metadata[$key] ?? null;
    }

    /**
     * Finalize the result (calculate execution time)
     */
    public function finalize(): self
    {
        $this->executionTime = microtime(true) - $this->executionTime;
        return $this;
    }

    /**
     * Get execution time in seconds
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Get a summary of the result
     */
    public function getSummary(): array
    {
        return [
            'success' => $this->success,
            'published_events_count' => count($this->publishedEvents),
            'errors_count' => count($this->errors),
            'warnings_count' => count($this->warnings),
            'execution_time' => $this->executionTime,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Convert result to array for serialization
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'published_events' => $this->publishedEvents,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
            'execution_time' => $this->executionTime,
            'summary' => $this->getSummary()
        ];
    }
}
