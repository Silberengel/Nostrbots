<?php

namespace Nostrbots\EventKinds;

use swentel\nostr\Event\Event;

/**
 * Abstract base class for event kind implementations
 * 
 * Provides common functionality shared across all event kinds,
 * reducing code duplication and ensuring consistent behavior.
 */
abstract class AbstractEventKind implements EventKindInterface
{
    /**
     * Generate a d-tag for addressable events
     * 
     * @param string $title The title or base identifier
     * @param bool $includeTimestamp Whether to include timestamp for uniqueness
     * @param bool $forceNormalization Whether to apply normalization rules (for wiki articles)
     * @return string The generated d-tag
     */
    protected function generateDTag(string $title, bool $includeTimestamp = true, bool $forceNormalization = false): string
    {
        if ($forceNormalization) {
            // Apply NIP-54 normalization rules for wiki articles
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $title));
            $slug = trim($slug, '-');
        } else {
            // Standard slug generation
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        }
        
        if ($includeTimestamp) {
            $slug .= '-' . time();
        }
        
        return $slug;
    }

    /**
     * Create standard tags that are common across event kinds
     * 
     * @param array $config Configuration data
     * @return array Array of tags
     */
    protected function createStandardTags(array $config): array
    {
        $tags = [];

        // Add d-tag for addressable events
        if (isset($config['d-tag'])) {
            // Use provided d-tag (allows reusing existing article d-tags)
            $tags[] = ['d', $config['d-tag']];
        } elseif (isset($config['reuse_d_tag'])) {
            // Reuse an existing d-tag for replacing content
            $tags[] = ['d', $config['reuse_d_tag']];
        } elseif (isset($config['title'])) {
            // Generate new d-tag from title
            $includeTimestamp = !isset($config['static_d_tag']) || !$config['static_d_tag'];
            $forceNormalization = isset($config['normalize_d_tag']) && $config['normalize_d_tag'];
            $tags[] = ['d', $this->generateDTag($config['title'], $includeTimestamp, $forceNormalization)];
        }

        // Add title tag
        if (isset($config['title'])) {
            $tags[] = ['title', $config['title']];
        }

        // Add summary tag
        if (isset($config['summary'])) {
            $tags[] = ['summary', $config['summary']];
        }

        // Add image tag
        if (isset($config['image'])) {
            $tags[] = ['image', $config['image']];
        }

        // Add topic tags (t tags)
        if (isset($config['topics']) && is_array($config['topics'])) {
            foreach ($config['topics'] as $topic) {
                $tags[] = ['t', $topic];
            }
        }

        // Add published_at tag
        if (isset($config['published_at'])) {
            $tags[] = ['published_at', (string)$config['published_at']];
        }

        // Add custom tags
        if (isset($config['custom_tags']) && is_array($config['custom_tags'])) {
            foreach ($config['custom_tags'] as $tag) {
                if (is_array($tag) && count($tag) >= 2) {
                    $tags[] = $tag;
                }
            }
        }

        return $tags;
    }

    /**
     * Validate common configuration fields
     * 
     * @param array $config Configuration to validate
     * @param array $requiredFields Required field names
     * @return array Validation errors
     */
    protected function validateCommonFields(array $config, array $requiredFields): array
    {
        $errors = [];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                $errors[] = "Required field '{$field}' is missing or empty";
            }
        }

        // Validate title length
        if (isset($config['title']) && strlen($config['title']) > 200) {
            $errors[] = "Title must be 200 characters or less";
        }

        // Validate summary length
        if (isset($config['summary']) && strlen($config['summary']) > 500) {
            $errors[] = "Summary must be 500 characters or less";
        }

        // Validate image URL or local file path
        if (isset($config['image'])) {
            $image = $config['image'];
            // Allow local file paths for testing (starting with /)
            if (!filter_var($image, FILTER_VALIDATE_URL) && !str_starts_with($image, '/')) {
                $errors[] = "Image must be a valid URL or local file path";
            }
        }

        // Validate topics
        if (isset($config['topics'])) {
            if (!is_array($config['topics'])) {
                $errors[] = "Topics must be an array";
            } else {
                foreach ($config['topics'] as $topic) {
                    if (!is_string($topic) || empty(trim($topic))) {
                        $errors[] = "Each topic must be a non-empty string";
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Default post-processing implementation (no additional events)
     * 
     * @param Event $event The created event
     * @param array $config Original configuration
     * @return array Empty array (no additional events)
     */
    public function postProcess(Event $event, array $config): array
    {
        return [];
    }

    /**
     * Get common optional fields available to all event kinds
     * 
     * @return array Array of optional fields with descriptions
     */
    protected function getCommonOptionalFields(): array
    {
        return [
            'summary' => 'Brief description of the content',
            'image' => 'URL to an image associated with the content',
            'topics' => 'Array of topic tags (t tags)',
            'published_at' => 'Unix timestamp of first publication',
            'custom_tags' => 'Array of additional custom tags',
            'd-tag' => 'Custom d-tag identifier (auto-generated if not provided)',
            'reuse_d_tag' => 'Reuse an existing d-tag to replace/update content',
            'static_d_tag' => 'Generate d-tag without timestamp (for wiki articles)',
            'normalize_d_tag' => 'Apply NIP-54 normalization rules to d-tag'
        ];
    }
}
