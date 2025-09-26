<?php
/**
 * Performance monitoring script for the event indexer
 */

require_once __DIR__ . '/../vendor/autoload.php';

class PerformanceMonitor
{
    private string $logFile;
    private string $stateFile;

    public function __construct()
    {
        $this->logFile = '/var/log/nostrbots/event-indexer.log';
        $this->stateFile = '/var/log/nostrbots/event-indexer-state.json';
    }

    private function log(string $message): void
    {
        $timestamp = date('c');
        echo "[{$timestamp}] {$message}" . PHP_EOL;
    }

    public function showStats(): void
    {
        $this->log("=== Event Indexer Performance Statistics ===");
        
        // Check if state file exists
        if (file_exists($this->stateFile)) {
            $state = json_decode(file_get_contents($this->stateFile), true);
            if ($state) {
                $this->log("Last run: " . ($state['last_run_date'] ?? 'Unknown'));
                $this->log("Last indexed timestamp: " . date('c', $state['last_timestamp'] ?? 0));
                $this->log("Time since last run: " . $this->formatDuration(time() - ($state['last_run'] ?? 0)));
            }
        } else {
            $this->log("No state file found - indexer may not have run yet");
        }
        
        // Check log file for recent activity
        if (file_exists($this->logFile)) {
            $this->analyzeLogFile();
        } else {
            $this->log("No log file found");
        }
        
        // System resource usage
        $this->showSystemStats();
    }

    private function analyzeLogFile(): void
    {
        $this->log("\n=== Recent Activity Analysis ===");
        
        $logContent = file_get_contents($this->logFile);
        $lines = explode("\n", $logContent);
        
        // Get last 100 lines
        $recentLines = array_slice($lines, -100);
        
        $stats = [
            'total_runs' => 0,
            'events_processed' => 0,
            'relays_processed' => 0,
            'errors' => 0,
            'execution_times' => []
        ];
        
        foreach ($recentLines as $line) {
            if (strpos($line, 'Starting incremental event indexing process') !== false) {
                $stats['total_runs']++;
            }
            if (preg_match('/Processing (\d+) new events/', $line, $matches)) {
                $stats['events_processed'] += (int)$matches[1];
            }
            if (preg_match('/Processing batch (\d+)\/(\d+) with (\d+) relays/', $line, $matches)) {
                $stats['relays_processed'] += (int)$matches[3];
            }
            if (strpos($line, 'ERROR:') !== false || strpos($line, 'Failed') !== false) {
                $stats['errors']++;
            }
            if (preg_match('/Execution time: ([\d.]+)s/', $line, $matches)) {
                $stats['execution_times'][] = (float)$matches[1];
            }
        }
        
        $this->log("Recent runs: {$stats['total_runs']}");
        $this->log("Events processed: {$stats['events_processed']}");
        $this->log("Relays processed: {$stats['relays_processed']}");
        $this->log("Errors encountered: {$stats['errors']}");
        
        if (!empty($stats['execution_times'])) {
            $avgTime = array_sum($stats['execution_times']) / count($stats['execution_times']);
            $maxTime = max($stats['execution_times']);
            $minTime = min($stats['execution_times']);
            $this->log("Average execution time: " . round($avgTime, 2) . "s");
            $this->log("Max execution time: " . round($maxTime, 2) . "s");
            $this->log("Min execution time: " . round($minTime, 2) . "s");
        }
    }

    private function showSystemStats(): void
    {
        $this->log("\n=== System Resource Usage ===");
        
        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $this->log("Current memory usage: " . $this->formatBytes($memoryUsage));
        $this->log("Peak memory usage: " . $this->formatBytes($memoryPeak));
        
        // CPU load
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->log("System load: " . implode(', ', array_map(function($l) { return round($l, 2); }, $load)));
        }
        
        // Disk usage
        $diskFree = disk_free_space('.');
        $diskTotal = disk_total_space('.');
        if ($diskFree !== false && $diskTotal !== false) {
            $diskUsed = $diskTotal - $diskFree;
            $this->log("Disk usage: " . $this->formatBytes($diskUsed) . " / " . $this->formatBytes($diskTotal) . 
                      " (" . round(($diskUsed / $diskTotal) * 100, 1) . "%)");
        }
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . "m";
        } else {
            return round($seconds / 3600, 1) . "h";
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function showRecommendations(): void
    {
        $this->log("\n=== Performance Recommendations ===");
        
        // Check if state file exists and analyze
        if (file_exists($this->stateFile)) {
            $state = json_decode(file_get_contents($this->stateFile), true);
            if ($state) {
                $timeSinceLastRun = time() - ($state['last_run'] ?? 0);
                
                if ($timeSinceLastRun > 3600) {
                    $this->log("⚠️  Indexer hasn't run in over an hour - check if it's running");
                }
                
                if ($timeSinceLastRun < 60) {
                    $this->log("⚠️  Indexer is running very frequently - consider increasing delays");
                }
            }
        }
        
        // Check log file for patterns
        if (file_exists($this->logFile)) {
            $logContent = file_get_contents($this->logFile);
            
            if (substr_count($logContent, 'Failed to add relay') > 10) {
                $this->log("⚠️  Many relay connection failures - check relay URLs and network");
            }
            
            if (substr_count($logContent, 'ERROR:') > 5) {
                $this->log("⚠️  Multiple errors detected - check logs for details");
            }
            
            if (strpos($logContent, 'Execution time:') !== false) {
                preg_match_all('/Execution time: ([\d.]+)s/', $logContent, $matches);
                if (!empty($matches[1])) {
                    $times = array_map('floatval', $matches[1]);
                    $avgTime = array_sum($times) / count($times);
                    
                    if ($avgTime > 300) {
                        $this->log("⚠️  Average execution time is high ({$avgTime}s) - consider reducing batch size");
                    }
                    
                    if ($avgTime < 10) {
                        $this->log("💡 Execution time is very fast ({$avgTime}s) - you could increase batch size");
                    }
                }
            }
        }
        
        $this->log("\n💡 Performance tuning tips:");
        $this->log("- Increase RELAY_BATCH_SIZE for faster processing (if system can handle it)");
        $this->log("- Decrease REQUEST_DELAY_MS for faster processing (but be respectful to relays)");
        $this->log("- Increase CONNECTION_TIMEOUT if you have slow relays");
        $this->log("- Monitor memory usage and adjust MAX_EVENTS if needed");
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $monitor = new PerformanceMonitor();
    $monitor->showStats();
    $monitor->showRecommendations();
}
