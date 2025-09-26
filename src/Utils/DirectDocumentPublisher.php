<?php

namespace Nostrbots\Utils;

use Nostrbots\Bot\NostrBot;
use Nostrbots\Utils\DocumentParser;

/**
 * Direct Document Publisher
 * 
 * Publishes documents directly without generating intermediate configuration files.
 * Uses the new streamlined approach: Document → Parse → Publish
 */
class DirectDocumentPublisher
{
    private DocumentParser $parser;
    private NostrBot $bot;
    private array $publishedEvents = [];
    private array $errors = [];
    private string $currentRelayConfig = '';

    public function __construct()
    {
        $this->parser = new DocumentParser();
        $this->bot = new NostrBot();
    }

    /**
     * Publish a document directly from file
     * 
     * @param string $documentPath Path to the document file
     * @param int|null $contentLevel Header level that becomes content sections (1-6), null to use file/default
     * @param string|null $contentKind Kind of content to generate ('30023', '30041', '30818'), null to use file/default
     * @param bool $dryRun If true, don't actually publish
     * @return array Results with published events and any errors
     */
    public function publishDocument(string $documentPath, ?int $contentLevel = null, ?string $contentKind = null, bool $dryRun = false): array
    {
        echo "📄 Publishing document: " . basename($documentPath) . PHP_EOL;
        $levelDisplay = $contentLevel ?? 'file/default';
        $kindDisplay = $contentKind ?? 'file/default';
        echo "📊 Content level: {$levelDisplay}, Content kind: {$kindDisplay}" . PHP_EOL . PHP_EOL;

        try {
            $structure = $this->parseDocument($documentPath, $contentLevel, $contentKind);
            $this->displayParseResults($structure);

            if ($dryRun) {
                echo "DRY RUN MODE - No events will be published" . PHP_EOL;
                return $this->buildDryRunResults($structure);
            }

            return $this->publishStructure($structure);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            echo "✗ Error: " . $e->getMessage() . PHP_EOL;
            return $this->buildErrorResults();
        }
    }

    /**
     * Parse document into hierarchical structure
     */
    private function parseDocument(string $documentPath, ?int $contentLevel, ?string $contentKind): array
    {
        echo "Parsing document structure..." . PHP_EOL;
        $structure = $this->parser->parseDocumentForDirectPublishing($documentPath, $contentLevel, $contentKind);
        
        // Determine relay configuration with proper priority
        $structure['relays'] = $this->determineRelayConfiguration($this->parser->getRelays());
        
        echo "✓ Document parsed successfully!" . PHP_EOL;
        return $structure;
    }
    
    /**
     * Determine relay configuration with proper priority:
     * 1. Document metadata relays (if specified)
     * 2. Appropriate relays.yml section
     * 3. Default fallback
     */
    private function determineRelayConfiguration(string $documentRelays): string
    {
        // If document specifies actual relay URLs, use them
        if (!empty($documentRelays) && $this->isRelayUrl($documentRelays)) {
            echo "Using document-specified relay URLs: {$documentRelays}" . PHP_EOL;
            $this->currentRelayConfig = $documentRelays;
            return $documentRelays;
        }
        
        // If document specifies a category name, parse the relays from that category
        if (!empty($documentRelays) && !$this->isRelayUrl($documentRelays)) {
            try {
                $relayManager = new \Nostrbots\Utils\RelayManager();
                $relayList = $relayManager->getRelayList($documentRelays);
                
                if (empty($relayList)) {
                    throw new \InvalidArgumentException("Relay category '{$documentRelays}' is empty or not found in relays.yml configuration.");
                }
                
                $relayUrls = implode(',', $relayList);
                echo "Using relays from category '{$documentRelays}': {$relayUrls}" . PHP_EOL;
                $this->currentRelayConfig = $relayUrls;
                return $relayUrls;
                
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Invalid relay category '{$documentRelays}' specified in document. Error: " . $e->getMessage());
            }
        }
        
        // Fallback to default relays
        echo "Using default relay configuration" . PHP_EOL;
        $this->currentRelayConfig = 'document-relays';
        return 'document-relays';
    }

    /**
     * Display parsing results
     */
    private function displayParseResults(array $structure): void
    {
        echo "📋 Title: {$structure['document_title']}" . PHP_EOL;
        echo "📊 Found " . count($structure['content_sections']) . " content sections" . PHP_EOL;
        
        $indexCount = count($structure['index_sections']);
        echo "📊 Found {$indexCount} index sections" . PHP_EOL;
        echo "📊 Total events to publish: " . count($structure['publish_order']) . PHP_EOL . PHP_EOL;
    }

    /**
     * Publish the hierarchical structure
     */
    private function publishStructure(array $structure): array
    {
        echo "🚀 Starting publication process..." . PHP_EOL . PHP_EOL;
        $this->publishInOrder($structure);
        return $this->buildResults($structure);
    }

    /**
     * Publish events in the correct dependency order
     */
    private function publishInOrder(array $structure): void
    {
        $publishedEventIds = [];
        
        foreach ($structure['publish_order'] as $index => $section) {
            echo "📝 Publishing: {$section['title']}" . PHP_EOL;
            
            try {
                $config = $this->buildEventConfig($section, $structure['metadata'], $publishedEventIds, $structure['relays']);
                $result = $this->publishEvent($config, $section);
                
                if ($result['success']) {
                    $publishedEventIds[$section['d_tag']] = $result['event_id'];
                    $this->publishedEvents[] = $result;
                    echo "✓ Published: {$result['event_id']}" . PHP_EOL;
                } else {
                    $this->handlePublishError($section['title'], $result['error']);
                }
                
            } catch (\Exception $e) {
                $this->handlePublishError($section['title'], $e->getMessage());
            }
            
            echo PHP_EOL;
        }
    }

    /**
     * Publish a single event
     */
    private function publishEvent(array $config, array $section): array
    {
        // Format content based on event kind
        $content = $this->formatContentForEventKind($section['content'], $section['event_kind']);
        $config['content'] = $content;
        
        $this->bot->loadConfig($config);
        $result = $this->bot->run(false); // false = not dry run
        
        if ($result->isSuccess()) {
            $eventId = $this->extractEventId($result);
            return [
                'success' => true,
                'event_id' => $eventId,
                'title' => $section['title'],
                'kind' => $section['event_kind'],
                'd_tag' => $section['d_tag']
            ];
        } else {
            // Get the actual error messages from the bot result
            $errors = $result->getErrors();
            $errorMessage = 'Failed to publish event';
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error['message'];
                }
                $errorMessage = implode('; ', $errorMessages);
            }
            
            return [
                'success' => false,
                'error' => $errorMessage
            ];
        }
    }

    /**
     * Handle publish error
     */
    private function handlePublishError(string $title, string $error): void
    {
        $errorMessage = "Failed to publish: {$title} - {$error}";
        $this->errors[] = $errorMessage;
        echo "✗ {$errorMessage}" . PHP_EOL;
    }

    /**
     * Build event configuration from section and metadata
     */
    private function buildEventConfig(array $section, array $metadata, array $publishedEventIds, string $relayConfig = ''): array
    {
        // Handle relay configuration properly - convert comma-separated string back to array if needed
        $relayConfig = $this->currentRelayConfig ?: 'document-relays';
        if ($this->isRelayUrl($relayConfig)) {
            // If it's a comma-separated string of URLs, convert to array
            $relayConfig = array_map('trim', explode(',', $relayConfig));
            $relayConfig = array_filter($relayConfig, function($url) { return !empty(trim($url)); });
        }
        
        $config = [
            'bot_name' => 'Direct Document Publisher',
            'bot_description' => 'Published from document: ' . $section['title'],
            'event_kind' => $section['event_kind'],
            'environment_variable' => 'NOSTR_BOT_KEY',
            'relays' => $relayConfig, // Use properly formatted relay config
            'title' => $section['title'],
            'auto_update' => $metadata['auto_update'] ?? true,
            'summary' => $metadata['summary'] ?? '',
            'type' => $metadata['type'] ?? 'documentation',
            'hierarchy_level' => $metadata['hierarchy_level'] ?? 0,
            'static_d_tag' => true,
            'd-tag' => $section['d_tag'],
            'validate_after_publish' => true
        ];
        
        // Add author metadata if present
        if (isset($metadata['author'])) {
            $config['author'] = $metadata['author'];
        }
        
        // Add publication date metadata if present
        if (isset($metadata['publication_date'])) {
            $config['publication_date'] = $metadata['publication_date'];
        }
        
        // Add content references for index events (30040) with relay hints
        if ($section['event_kind'] === 30040 && isset($section['content_references'])) {
            $config['content_references'] = $this->updateContentReferencesWithEventIds(
                $section['content_references'], 
                $publishedEventIds
            );
        }

        return $config;
    }

    /**
     * Format content based on event kind requirements
     */
    private function formatContentForEventKind(string $content, string $eventKind): array
    {
        switch ($eventKind) {
            case '30023': // Long-form Content (Markdown)
                return ['markdown' => $content];
            case '30040': // Publication Index (no content, just metadata)
                return []; // No content for index events
            case '30041': // Publication Content (AsciiDoc)
                return ['asciidoc' => $content];
            case '30818': // Wiki Article (AsciiDoc)
                return ['asciidoc' => $content];
            default:
                return ['content' => $content];
        }
    }

    /**
     * Update content references with actual published event IDs and relay hints
     */
    private function updateContentReferencesWithEventIds(array $contentReferences, array $publishedEventIds): array
    {
        foreach ($contentReferences as &$reference) {
            if (isset($publishedEventIds[$reference['d_tag']])) {
                $reference['event_id'] = $publishedEventIds[$reference['d_tag']];
            }
            
            // Add relay hints if we have actual relay URLs
            if (!empty($this->currentRelayConfig) && $this->isRelayUrl($this->currentRelayConfig)) {
                $relayUrls = array_map('trim', explode(',', $this->currentRelayConfig));
                $relayUrls = array_filter($relayUrls, function($url) { return !empty(trim($url)); });
                $reference['relay'] = implode(',', $relayUrls);
            }
        }
        return $contentReferences;
    }

    /**
     * Extract event ID from bot result
     */
    private function extractEventId($result): string
    {
        $publishedEvents = $result->getPublishedEvents();
        if (!empty($publishedEvents)) {
            return $publishedEvents[0]['event_id'];
        }
        throw new \RuntimeException("No event ID found in bot result");
    }

    /**
     * Build dry run results
     */
    private function buildDryRunResults(array $structure): array
    {
        return [
            'success' => true,
            'dry_run' => true,
            'document_title' => $structure['document_title'],
            'metadata' => $structure['metadata'],
            'publish_order' => $structure['publish_order'],
            'content_sections' => count($structure['content_sections']),
            'index_sections' => count($structure['index_sections']),
            'total_events' => count($structure['publish_order']),
            'structure' => $structure // Include full structure for testing
        ];
    }

    /**
     * Build final results
     */
    private function buildResults(array $structure): array
    {
        $success = empty($this->errors);
        
        return [
            'success' => $success,
            'document_title' => $structure['document_title'],
            'published_events' => $this->publishedEvents,
            'errors' => $this->errors,
            'total_published' => count($this->publishedEvents),
            'total_expected' => count($structure['publish_order'])
        ];
    }
    
    /**
     * Check if a string contains actual relay URLs (not category names)
     */
    private function isRelayUrl(string $relayConfig): bool
    {
        // If it contains 'wss://' or 'ws://', it's likely a relay URL
        return strpos($relayConfig, 'wss://') !== false || strpos($relayConfig, 'ws://') !== false;
    }

    /**
     * Build error results
     */
    private function buildErrorResults(): array
    {
        return [
            'success' => false,
            'errors' => $this->errors,
            'published_events' => $this->publishedEvents
        ];
    }
}