<?php

namespace Nostrbots\Utils;

/**
 * Manages performance optimization and resource monitoring
 */
class PerformanceManager
{
    private array $timers = [];
    private array $memorySnapshots = [];
    private int $maxMemoryUsage = 0;
    private bool $profilingEnabled = false;

    public function __construct(bool $enableProfiling = false)
    {
        $this->profilingEnabled = $enableProfiling;
        $this->startTimer('total_execution');
        $this->takeMemorySnapshot('start');
    }

    /**
     * Start a performance timer
     */
    public function startTimer(string $name): void
    {
        if ($this->profilingEnabled) {
            $this->timers[$name] = [
                'start' => microtime(true),
                'end' => null,
                'duration' => null
            ];
        }
    }

    /**
     * End a performance timer
     */
    public function endTimer(string $name): float
    {
        if (!$this->profilingEnabled || !isset($this->timers[$name])) {
            return 0.0;
        }

        $this->timers[$name]['end'] = microtime(true);
        $this->timers[$name]['duration'] = $this->timers[$name]['end'] - $this->timers[$name]['start'];
        
        return $this->timers[$name]['duration'];
    }

    /**
     * Take a memory snapshot
     */
    public function takeMemorySnapshot(string $name): void
    {
        if ($this->profilingEnabled) {
            $memoryUsage = memory_get_usage(true);
            $this->memorySnapshots[$name] = [
                'memory' => $memoryUsage,
                'peak' => memory_get_peak_usage(true),
                'timestamp' => microtime(true)
            ];
            
            if ($memoryUsage > $this->maxMemoryUsage) {
                $this->maxMemoryUsage = $memoryUsage;
            }
        }
    }

    /**
     * Get performance report
     */
    public function getPerformanceReport(): array
    {
        if (!$this->profilingEnabled) {
            return ['profiling_disabled' => true];
        }

        $this->endTimer('total_execution');
        $this->takeMemorySnapshot('end');

        return [
            'timers' => $this->timers,
            'memory_snapshots' => $this->memorySnapshots,
            'max_memory_usage' => $this->maxMemoryUsage,
            'current_memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Print performance report
     */
    public function printPerformanceReport(): void
    {
        if (!$this->profilingEnabled) {
            echo "📊 Performance profiling is disabled" . PHP_EOL;
            return;
        }

        $report = $this->getPerformanceReport();
        
        echo "📊 Performance Report" . PHP_EOL;
        echo "===================" . PHP_EOL;
        
        // Timer information
        echo "⏱️  Execution Times:" . PHP_EOL;
        foreach ($report['timers'] as $name => $timer) {
            if ($timer['duration'] !== null) {
                $duration = round($timer['duration'] * 1000, 2);
                echo "   {$name}: {$duration}ms" . PHP_EOL;
            }
        }
        
        // Memory information
        echo PHP_EOL . "💾 Memory Usage:" . PHP_EOL;
        echo "   Current: " . $this->formatBytes($report['current_memory_usage']) . PHP_EOL;
        echo "   Peak: " . $this->formatBytes($report['peak_memory_usage']) . PHP_EOL;
        echo "   Max during execution: " . $this->formatBytes($report['max_memory_usage']) . PHP_EOL;
        echo "   Limit: " . $report['memory_limit'] . PHP_EOL;
        
        // Memory snapshots
        if (!empty($report['memory_snapshots'])) {
            echo PHP_EOL . "📸 Memory Snapshots:" . PHP_EOL;
            foreach ($report['memory_snapshots'] as $name => $snapshot) {
                echo "   {$name}: " . $this->formatBytes($snapshot['memory']) . PHP_EOL;
            }
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Optimize memory usage by forcing garbage collection
     */
    public function optimizeMemory(): void
    {
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            if ($this->profilingEnabled && $collected > 0) {
                echo "🧹 Garbage collected {$collected} cycles" . PHP_EOL;
            }
        }
    }

    /**
     * Check if memory usage is approaching limits
     */
    public function isMemoryUsageHigh(): bool
    {
        $currentUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($memoryLimit === -1) {
            return false; // No limit
        }
        
        $usagePercent = ($currentUsage / $memoryLimit) * 100;
        return $usagePercent > 80; // Warning at 80%
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return -1; // No limit
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;
        
        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }

    /**
     * Enable or disable profiling
     */
    public function setProfilingEnabled(bool $enabled): void
    {
        $this->profilingEnabled = $enabled;
    }

    /**
     * Get profiling status
     */
    public function isProfilingEnabled(): bool
    {
        return $this->profilingEnabled;
    }
}
