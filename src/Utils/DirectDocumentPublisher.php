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

    public function __construct()
    {
        $this->parser = new DocumentParser();
        $this->bot = new NostrBot();
    }

    /**
     * Publish a document directly from file
     * 
     * @param string $documentPath Path to the document file
     * @param int $contentLevel Header level that becomes content sections (1-6)
     * @param string $contentKind Kind of content to generate ('30023', '30041', '30818')
     * @param bool $dryRun If true, don't actually publish
     * @return array Results with published events and any errors
     */
    public function publishDocument(string $documentPath, int $contentLevel, string $contentKind, bool $dryRun = false): array
    {
        echo "📄 Publishing document: " . basename($documentPath) . PHP_EOL;
        echo "📊 Content level: {$contentLevel}, Content kind: {$contentKind}" . PHP_EOL . PHP_EOL;

        try {
            $structure = $this->parseDocument($documentPath, $contentLevel, $contentKind);
            $this->displayParseResults($structure);

            if ($dryRun) {
                echo "🔍 DRY RUN MODE - No events will be published" . PHP_EOL;
                return $this->buildDryRunResults($structure);
            }

            return $this->publishStructure($structure);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            echo "❌ Error: " . $e->getMessage() . PHP_EOL;
            return $this->buildErrorResults();
        }
    }

    /**
     * Parse document into hierarchical structure
     */
    private function parseDocument(string $documentPath, int $contentLevel, string $contentKind): array
    {
        echo "🔍 Parsing document structure..." . PHP_EOL;
        $structure = $this->parser->parseDocumentForDirectPublishing($documentPath, $contentLevel, $contentKind);
        echo "✅ Document parsed successfully!" . PHP_EOL;
        return $structure;
    }

    /**
     * Display parsing results
     */
    private function displayParseResults(array $structure): void
    {
        echo "📋 Title: {$structure['document_title']}" . PHP_EOL;
        echo "📊 Found " . count($structure['content_sections']) . " content sections" . PHP_EOL;
        echo "📊 Found " . count($structure['index_sections']) . " index sections" . PHP_EOL;
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
                $config = $this->buildEventConfig($section, $structure['metadata'], $publishedEventIds);
                $result = $this->publishEvent($config, $section);
                
                if ($result['success']) {
                    $publishedEventIds[$section['d_tag']] = $result['event_id'];
                    $this->publishedEvents[] = $result;
                    echo "✅ Published: {$result['event_id']}" . PHP_EOL;
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
            return [
                'success' => false,
                'error' => 'Failed to publish event'
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
        echo "❌ {$errorMessage}" . PHP_EOL;
    }

    /**
     * Build event configuration from section and metadata
     */
    private function buildEventConfig(array $section, array $metadata, array $publishedEventIds): array
    {
        $config = [
            'bot_name' => 'Direct Document Publisher',
            'bot_description' => 'Published from document: ' . $section['title'],
            'event_kind' => $section['event_kind'],
            'environment_variable' => 'NOSTR_BOT_KEY',
            'relays' => $metadata['relays'],
            'title' => $section['title'],
            'auto_update' => $metadata['auto_update'],
            'summary' => $metadata['summary'],
            'type' => $metadata['type'],
            'hierarchy_level' => $metadata['hierarchy_level'],
            'static_d_tag' => true,
            'd-tag' => $section['d_tag'],
            'content' => $section['content'] ?? '',
            'validate_after_publish' => true
        ];

        // Add content references for index events
        if ($section['event_kind'] === 30040 && isset($section['content_references'])) {
            $config['content_references'] = $this->updateContentReferencesWithEventIds(
                $section['content_references'], 
                $publishedEventIds
            );
        }

        return $config;
    }

    /**
     * Update content references with actual published event IDs
     */
    private function updateContentReferencesWithEventIds(array $contentReferences, array $publishedEventIds): array
    {
        foreach ($contentReferences as &$reference) {
            if (isset($publishedEventIds[$reference['d_tag']])) {
                $reference['event_id'] = $publishedEventIds[$reference['d_tag']];
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
            'total_events' => count($structure['publish_order'])
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