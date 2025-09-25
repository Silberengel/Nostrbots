<?php

/**
 * Hello World Content Generator
 * 
 * Simple bot that generates Hello World articles for testing
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Nostrbots\Utils\ErrorHandler;

class HelloWorldGenerator
{
    private string $botDir;
    private array $config;
    private ErrorHandler $errorHandler;
    
    public function __construct(string $botDir)
    {
        $this->botDir = $botDir;
        $this->config = $this->loadConfig();
        $this->errorHandler = new ErrorHandler(true);
    }
    
    private function loadConfig(): array
    {
        $configFile = $this->botDir . '/config.json';
        if (!file_exists($configFile)) {
            throw new \Exception("Configuration file not found: $configFile");
        }
        
        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in configuration file: " . json_last_error_msg());
        }
        
        return $config;
    }
    
    public function generateContent(): void
    {
        $currentTime = new DateTime('now', new DateTimeZone('UTC'));
        $date = $currentTime->format('Y-m-d');
        $time = $currentTime->format('H-i');
        
        echo "Generating Hello World content for $date at $time UTC\n";
        
        // Generate simple content
        $content = $this->buildContent($currentTime);
        
        // Save to output directory
        $this->saveContent($content, $date, $time);
        
        echo "Hello World content generated successfully\n";
    }
    
    private function buildContent(DateTime $dateTime): string
    {
        $templateFile = $this->botDir . '/templates/hello-world.adoc';
        
        if (!file_exists($templateFile)) {
            throw new \Exception("Template file not found: $templateFile");
        }
        
        // Read the template
        $template = file_get_contents($templateFile);
        
        // Prepare replacement values
        $replacements = [
            '{date}' => $dateTime->format('l, F j, Y'),
            '{time}' => $dateTime->format('H-i-s'),
            '{timestamp}' => $dateTime->format('c'),
            '{bot_name}' => $this->config['name'] ?? 'Hello World Bot',
            '{author}' => $this->config['author'] ?? 'Nostrbots Test',
            '{version}' => $this->config['version'] ?? '1.0.0',
            '{content_kind}' => $this->config['content_kind'] ?? '30041',
            '{relays}' => implode(', ', $this->config['relays'] ?? []),
            '{php_version}' => PHP_VERSION,
            '{nostrbots_version}' => '2.0',
        ];
        
        // Replace placeholders
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    private function saveContent(string $content, string $date, string $time): void
    {
        $outputDir = $this->botDir . '/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $filename = "hello-world-{$date}-{$time}.adoc";
        $filepath = $outputDir . '/' . $filename;
        
        if (file_put_contents($filepath, $content) === false) {
            throw new \Exception("Failed to save content to: $filepath");
        }
        
        echo "Content saved to: $filename\n";
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $botDir = $argv[1] ?? __DIR__;
        $generator = new HelloWorldGenerator($botDir);
        $generator->generateContent();
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
