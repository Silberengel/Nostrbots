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

        // Content field can contain preamble for root indices, empty for nested indices
        $contentText = '';
        if (isset($config['preamble']) && !empty($config['preamble'])) {
            $contentText = $config['preamble'];
        }
        $event->setContent($contentText);

        // Handle index management (create, update, or append)
        $finalConfig = $this->processIndexManagement($config);

        // Create and set tags
        $tags = $this->createStandardTags($finalConfig);
        
        // Add required publication-specific tags
        $this->addPublicationTags($tags, $finalConfig);
        
        // Add hierarchy tags for nested publications
        $this->addHierarchyTags($tags, $finalConfig);
        
        // Add content references (a tags) with proper ordering
        $this->addContentReferences($tags, $finalConfig);

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
                    if (!is_array($ref) || !isset($ref['kind'], $ref['d_tag'])) {
                        $errors[] = "content_references[{$index}] must have kind and d_tag fields (pubkey is optional)";
                    }
                    
                    // Validate supported event kinds
                    $supportedKinds = [30023, 30040, 30041, 30818];
                    if (isset($ref['kind']) && !in_array($ref['kind'], $supportedKinds)) {
                        $errors[] = "content_references[{$index}] kind must be one of: " . implode(', ', $supportedKinds);
                    }
                }
            }
        }

        // Validate index management options
        if (isset($config['index_management'])) {
            $mgmt = $config['index_management'];
            if (!is_array($mgmt)) {
                $errors[] = 'index_management must be an array';
            } else {
                if (isset($mgmt['mode']) && !in_array($mgmt['mode'], ['create', 'update', 'append'])) {
                    $errors[] = 'index_management.mode must be one of: create, update, append';
                }
                if (isset($mgmt['existing_index']) && !is_string($mgmt['existing_index'])) {
                    $errors[] = 'index_management.existing_index must be a string (d-tag of existing index)';
                }
                if (isset($mgmt['insert_position']) && !in_array($mgmt['insert_position'], ['first', 'last', 'after', 'before'])) {
                    $errors[] = 'index_management.insert_position must be one of: first, last, after, before';
                }
                if (in_array($mgmt['insert_position'] ?? '', ['after', 'before']) && !isset($mgmt['reference_d_tag'])) {
                    $errors[] = 'index_management.reference_d_tag is required when insert_position is "after" or "before"';
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
            'preamble' => 'Preamble content for root indices (markdown format)',
            'content_references' => 'Array of content sections to include (supports kinds 30023, 30040, 30041, 30818)',
            'index_management' => 'Configuration for creating/updating indices with flexible ordering',
            'hierarchy_level' => 'Nesting level for hierarchical publications (0=root, 1=chapter, 2=section, etc.)',
            'parent_index' => 'D-tag of parent index for nested publications',
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
     * Process index management configuration
     */
    private function processIndexManagement(array $config): array
    {
        $finalConfig = $config;
        
        if (!isset($config['index_management'])) {
            return $finalConfig;
        }

        $mgmt = $config['index_management'];
        $mode = $mgmt['mode'] ?? 'create';

        switch ($mode) {
            case 'update':
                // Use existing index d-tag
                if (isset($mgmt['existing_index'])) {
                    $finalConfig['reuse_d_tag'] = $mgmt['existing_index'];
                }
                break;
                
            case 'append':
                // Load existing index and append new content
                if (isset($mgmt['existing_index'])) {
                    $finalConfig = $this->appendToExistingIndex($finalConfig, $mgmt);
                }
                break;
                
            case 'create':
            default:
                // Create new index (default behavior)
                break;
        }

        return $finalConfig;
    }

    /**
     * Append content to existing index
     */
    private function appendToExistingIndex(array $config, array $mgmt): array
    {
        // TODO: In a full implementation, this would fetch the existing index
        // from relays and merge the content references. For now, we'll simulate
        // this by using the provided configuration and adding new content.
        
        $finalConfig = $config;
        $finalConfig['reuse_d_tag'] = $mgmt['existing_index'];
        
        // Handle insertion position
        $position = $mgmt['insert_position'] ?? 'last';
        $newRefs = $config['content_references'] ?? [];
        
        // In a real implementation, we would:
        // 1. Fetch existing index from relays
        // 2. Parse existing content_references
        // 3. Insert new references at specified position
        // 4. Return merged configuration
        
        // For now, we'll add metadata about the insertion
        $finalConfig['_insertion_metadata'] = [
            'position' => $position,
            'reference_d_tag' => $mgmt['reference_d_tag'] ?? null,
            'new_content_count' => count($newRefs)
        ];
        
        return $finalConfig;
    }

    /**
     * Add hierarchy tags for nested publications
     */
    private function addHierarchyTags(array &$tags, array $config): void
    {
        // Add hierarchy level
        if (isset($config['hierarchy_level'])) {
            $tags[] = ['hierarchy_level', (string)$config['hierarchy_level']];
        }
        
        // Add parent index reference
        if (isset($config['parent_index'])) {
            $parentRef = $config['parent_index'];
            if (is_string($parentRef)) {
                // Simple d-tag reference
                $tags[] = ['parent', $parentRef];
            } elseif (is_array($parentRef) && isset($parentRef['d_tag'])) {
                // Full reference with pubkey
                $pubkey = $parentRef['pubkey'] ?? '';
                $relay = $parentRef['relay'] ?? '';
                $tags[] = ['a', "30040:{$pubkey}:{$parentRef['d_tag']}", $relay, 'parent'];
            }
        }
    }

    /**
     * Add content reference tags (a tags) with proper ordering
     */
    private function addContentReferences(array &$tags, array $config): void
    {
        if (!isset($config['content_references']) || !is_array($config['content_references'])) {
            return;
        }

        // Handle ordered content references
        $references = $config['content_references'];
        
        // Sort by order if specified
        if (isset($references[0]['order'])) {
            usort($references, function($a, $b) {
                return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
            });
        }

        foreach ($references as $ref) {
            if (!is_array($ref) || !isset($ref['kind'], $ref['pubkey'], $ref['d_tag'])) {
                continue;
            }

            $aTag = [
                'a',
                $ref['kind'] . ':' . $ref['pubkey'] . ':' . $ref['d_tag'],
                $ref['relay'] ?? '',
                $ref['event_id'] ?? ''
            ];

            // Add order information if specified
            if (isset($ref['order'])) {
                $aTag[] = 'order:' . $ref['order'];
            }

            $tags[] = $aTag;
        }
    }
}
