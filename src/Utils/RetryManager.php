<?php

namespace Nostrbots\Utils;

/**
 * Handles retry logic with exponential backoff for network operations
 */
class RetryManager
{
    private int $maxRetries;
    private int $baseDelayMs;
    private float $backoffMultiplier;
    private int $maxDelayMs;
    private bool $jitter;

    public function __construct(
        int $maxRetries = 3,
        int $baseDelayMs = 1000,
        float $backoffMultiplier = 2.0,
        int $maxDelayMs = 10000,
        bool $jitter = true
    ) {
        $this->maxRetries = $maxRetries;
        $this->baseDelayMs = $baseDelayMs;
        $this->backoffMultiplier = $backoffMultiplier;
        $this->maxDelayMs = $maxDelayMs;
        $this->jitter = $jitter;
    }

    /**
     * Execute a callable with retry logic
     */
    public function execute(callable $operation, string $operationName = 'operation'): mixed
    {
        $lastException = null;
        
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                
                if ($attempt === $this->maxRetries) {
                    echo "✗ {$operationName} failed after {$this->maxRetries} retries: " . $e->getMessage() . PHP_EOL;
                    throw $e;
                }
                
                $delay = $this->calculateDelay($attempt);
                echo "⚠  {$operationName} failed (attempt " . ($attempt + 1) . "/" . ($this->maxRetries + 1) . "): " . $e->getMessage() . PHP_EOL;
                echo "🔄 Retrying in {$delay}ms..." . PHP_EOL;
                
                usleep($delay * 1000); // Convert to microseconds
            }
        }
        
        throw $lastException;
    }

    /**
     * Calculate delay with exponential backoff and optional jitter
     */
    private function calculateDelay(int $attempt): int
    {
        $delay = $this->baseDelayMs * pow($this->backoffMultiplier, $attempt);
        $delay = min($delay, $this->maxDelayMs);
        
        if ($this->jitter) {
            // Add ±25% jitter to prevent thundering herd
            $jitterRange = $delay * 0.25;
            $delay += rand(-$jitterRange, $jitterRange);
        }
        
        return max(0, (int)$delay);
    }

    /**
     * Create a retry manager optimized for relay operations
     */
    public static function forRelays(): self
    {
        return new self(
            maxRetries: 3,
            baseDelayMs: 2000, // 2 seconds base delay
            backoffMultiplier: 1.5,
            maxDelayMs: 15000, // Max 15 seconds
            jitter: true
        );
    }

    /**
     * Create a retry manager optimized for validation operations
     */
    public static function forValidation(): self
    {
        return new self(
            maxRetries: 2,
            baseDelayMs: 1000,
            backoffMultiplier: 2.0,
            maxDelayMs: 5000,
            jitter: false // No jitter for validation
        );
    }
}
