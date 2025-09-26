<?php

namespace Nostrbots\Utils;

/**
 * Comprehensive error handling and logging system
 */
class ErrorHandler
{
    private array $errors = [];
    private array $warnings = [];
    private array $info = [];
    private bool $verbose = false;
    private ?string $logFile = null;

    public function __construct(bool $verbose = false, ?string $logFile = null)
    {
        $this->verbose = $verbose;
        $this->logFile = $logFile;
        
        // Set up error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', $verbose ? '1' : '0');
        
        // Set up custom error handler
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle PHP errors
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        $error = [
            'type' => 'error',
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->errors[] = $error;
        $this->log($error);

        if ($this->verbose) {
            echo "✗ Error: {$message} in {$file} on line {$line}" . PHP_EOL;
        }

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException(\Throwable $exception): void
    {
        $error = [
            'type' => 'exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->errors[] = $error;
        $this->log($error);

        if ($this->verbose) {
            echo "💥 Uncaught Exception: " . get_class($exception) . PHP_EOL;
            echo "   Message: {$exception->getMessage()}" . PHP_EOL;
            echo "   File: {$exception->getFile()}:{$exception->getLine()}" . PHP_EOL;
        }

        // Exit with error code
        exit(1);
    }

    /**
     * Handle script shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $shutdownError = [
                'type' => 'shutdown_error',
                'severity' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $this->errors[] = $shutdownError;
            $this->log($shutdownError);

            if ($this->verbose) {
                echo "💀 Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}" . PHP_EOL;
            }
        }
    }

    /**
     * Add a custom error
     */
    public function addError(string $message, array $context = []): void
    {
        $error = [
            'type' => 'custom_error',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->errors[] = $error;
        $this->log($error);

        if ($this->verbose) {
            echo "✗ Error: {$message}" . PHP_EOL;
        }
    }

    /**
     * Add a warning
     */
    public function addWarning(string $message, array $context = []): void
    {
        $warning = [
            'type' => 'warning',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->warnings[] = $warning;
        $this->log($warning);

        if ($this->verbose) {
            echo "⚠  Warning: {$message}" . PHP_EOL;
        }
    }

    /**
     * Add an info message
     */
    public function addInfo(string $message, array $context = []): void
    {
        $info = [
            'type' => 'info',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->info[] = $info;
        $this->log($info);

        if ($this->verbose) {
            echo "ⓘInfo: {$message}" . PHP_EOL;
        }
    }

    /**
     * Log a message to file
     */
    private function log(array $entry): void
    {
        if ($this->logFile === null) {
            return;
        }

        $logEntry = json_encode($entry) . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get all info messages
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Check if there are any errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are any warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get error summary
     */
    public function getErrorSummary(): array
    {
        return [
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
            'info_count' => count($this->info),
            'has_errors' => $this->hasErrors(),
            'has_warnings' => $this->hasWarnings()
        ];
    }

    /**
     * Print error summary
     */
    public function printErrorSummary(): void
    {
        $summary = $this->getErrorSummary();
        
        if ($summary['error_count'] > 0) {
            echo "✗ Errors: {$summary['error_count']}" . PHP_EOL;
        }
        
        if ($summary['warning_count'] > 0) {
            echo "⚠  Warnings: {$summary['warning_count']}" . PHP_EOL;
        }
        
        if ($summary['info_count'] > 0) {
            echo "ⓘInfo messages: {$summary['info_count']}" . PHP_EOL;
        }
    }

    /**
     * Clear all messages
     */
    public function clear(): void
    {
        $this->errors = [];
        $this->warnings = [];
        $this->info = [];
    }

    /**
     * Set verbose mode
     */
    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    /**
     * Set log file
     */
    public function setLogFile(?string $logFile): void
    {
        $this->logFile = $logFile;
    }

    /**
     * Validate configuration with detailed error reporting
     */
    public function validateConfig(array $config, array $requiredFields = []): array
    {
        $errors = [];
        
        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                $errors[] = "Required field '{$field}' is missing or empty";
            }
        }
        
        // Validate specific field types
        if (isset($config['event_kind']) && !is_numeric($config['event_kind'])) {
            $errors[] = "Field 'event_kind' must be a number";
        }
        
        if (isset($config['relays']) && !is_string($config['relays']) && !is_array($config['relays'])) {
            $errors[] = "Field 'relays' must be a string or array";
        }
        
        if (isset($config['min_relay_success']) && (!is_numeric($config['min_relay_success']) || $config['min_relay_success'] < 1)) {
            $errors[] = "Field 'min_relay_success' must be a positive number";
        }
        
        return $errors;
    }
}
