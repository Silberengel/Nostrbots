<?php

namespace Nostrbots\EventKinds;

use swentel\nostr\Event\Event;

/**
 * Handler for Publication Index (kind 30040)
 * 
 * Implements support for curated publication indices that serve as
 * table of contents for organized collections of content.
 */
class PublicationIndex extends AbstractEventKind
{
    public function getKind(): int
    {
        return 30040;
    }

    public function getName(): string
    {
        return 'Publication Index';
    }

    public function getDescription(): string
    {
        return 'Curated publication index serving as table of contents for organized content collections';
    }

    public function createEvent(array $config, array $content): Event
    {
        $event = new Event();
        $event->setKind($this->getKind());

        // Content field MUST be empty for publication indices
        $event->setContent('');

        // Create and set tags
        $tags = $this->createStandardTags($config);
        
        // Add required publication-specific tags
        $this->addPublicationTags($tags, $config);
        
        // Add content references (a tags)
        $this->addContentReferences($tags, $config);

        $event->setTags($tags);
        return $event;
    }

    public function validateConfig(array $config): array
    {
        $errors = $this->validateCommonFields($config, $this->getRequiredFields());

        // Validate auto-update field
        if (isset($config['auto_update'])) {
            $validValues = ['true', 'false', true, false];
            if (!in_array($config['auto_update'], $validValues, true)) {
                $errors[] = 'auto_update must be true or false';
            }
        }

        // Validate publication type
        if (isset($config['type'])) {
            $validTypes = ['book', 'illustrated', 'magazine', 'documentation', 'academic', 'blog'];
            if (!in_array($config['type'], $validTypes)) {
                $errors[] = 'type must be one of: ' . implode(', ', $validTypes);
            }
        }

        // Validate content references
        if (isset($config['content_references'])) {
            if (!is_array($config['content_references'])) {
                $errors[] = 'content_references must be an array';
            } else {
                foreach ($config['content_references'] as $index => $ref) {
                    if (!is_array($ref) || !isset($ref['kind'], $ref['pubkey'], $ref['d_tag'])) {
                        $errors[] = "content_references[{$index}] must have kind, pubkey, and d_tag fields";
                    }
                }
            }
        }

        // Validate derivative work fields
        if (isset($config['original_author']) && !isset($config['original_event'])) {
            $errors[] = 'original_event is required when original_author is specified';
        }
        if (isset($config['original_event']) && !isset($config['original_author'])) {
            $errors[] = 'original_author is required when original_event is specified';
        }

        return $errors;
    }

    public function getRequiredFields(): array
    {
        return ['title', 'auto_update'];
    }

    public function getOptionalFields(): array
    {
        $common = $this->getCommonOptionalFields();
        $specific = [
            'author' => 'Author name for display',
            'type' => 'Publication type (book, illustrated, magazine, documentation, academic, blog)',
            'version' => 'Edition or version information',
            'published_by' => 'Publisher information',
            'published_on' => 'Publication date (YYYY-MM-DD)',
            'source' => 'URL to original source',
            'isbn' => 'ISBN identifier (will be formatted as i tag)',
            'content_references' => 'Array of content sections to include',
            'original_author' => 'Original author pubkey (for derivative works)',
            'original_event' => 'Original event reference (for derivative works)',
        ];
        
        return array_merge($common, $specific);
    }

    /**
     * Add publication-specific tags
     */
    private function addPublicationTags(array &$tags, array $config): void
    {
        // Add author tag
        if (isset($config['author'])) {
            $tags[] = ['author', $config['author']];
        }

        // Add auto-update tag (required)
        $autoUpdate = isset($config['auto_update']) ? 
            (is_bool($config['auto_update']) ? ($config['auto_update'] ? 'true' : 'false') : (string)$config['auto_update']) : 
            'false';
        $tags[] = ['auto-update', $autoUpdate];

        // Add optional publication metadata
        $optionalTags = [
            'type' => 'type',
            'version' => 'version',
            'published_by' => 'published_by',
            'published_on' => 'published_on',
            'source' => 'source'
        ];

        foreach ($optionalTags as $configKey => $tagName) {
            if (isset($config[$configKey])) {
                $tags[] = [$tagName, $config[$configKey]];
            }
        }

        // Add ISBN as i tag
        if (isset($config['isbn'])) {
            $tags[] = ['i', 'isbn:' . $config['isbn']];
        }

        // Add derivative work tags
        if (isset($config['original_author'])) {
            $tags[] = ['p', $config['original_author']];
        }
        if (isset($config['original_event'])) {
            $tags[] = ['E', $config['original_event'], '', ''];
        }
    }

    /**
     * Add content reference tags (a tags)
     */
    private function addContentReferences(array &$tags, array $config): void
    {
        if (!isset($config['content_references']) || !is_array($config['content_references'])) {
            return;
        }

        foreach ($config['content_references'] as $ref) {
            if (!is_array($ref) || !isset($ref['kind'], $ref['pubkey'], $ref['d_tag'])) {
                continue;
            }

            $aTag = [
                'a',
                $ref['kind'] . ':' . $ref['pubkey'] . ':' . $ref['d_tag'],
                $ref['relay'] ?? '',
                $ref['event_id'] ?? ''
            ];

            $tags[] = $aTag;
        }
    }
}
