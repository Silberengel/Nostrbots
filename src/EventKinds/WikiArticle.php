<?php

namespace Nostrbots\EventKinds;

use swentel\nostr\Event\Event;

/**
 * Handler for NIP-54 Wiki Articles (kind 30818)
 * 
 * Implements support for wiki articles with Asciidoc content,
 * wikilinks, and proper d-tag normalization according to NIP-54.
 */
class WikiArticle extends AbstractEventKind
{
    public function getKind(): int
    {
        return 30818;
    }

    public function getName(): string
    {
        return 'Wiki Article';
    }

    public function getDescription(): string
    {
        return 'Collaborative wiki articles with Asciidoc content and wikilinks (NIP-54)';
    }

    public function createEvent(array $config, array $content): Event
    {
        $event = new Event();
        $event->setKind($this->getKind());

        // Set content (should be Asciidoc)
        if (isset($content['asciidoc'])) {
            $event->setContent($content['asciidoc']);
        } elseif (isset($content['content'])) {
            $event->setContent($content['content']);
        } else {
            throw new \InvalidArgumentException('Content must include "asciidoc" or "content" field');
        }

        // Create and set tags with NIP-54 specific handling
        $tags = $this->createWikiTags($config);

        $event->setTags($tags);
        return $event;
    }

    public function validateConfig(array $config): array
    {
        $errors = $this->validateCommonFields($config, $this->getRequiredFields());

        // Validate d-tag normalization for wiki articles
        if (isset($config['d-tag'])) {
            $normalizedDTag = $this->normalizeWikiDTag($config['d-tag']);
            if ($config['d-tag'] !== $normalizedDTag) {
                $errors[] = "d-tag '{$config['d-tag']}' should be normalized to '{$normalizedDTag}' according to NIP-54 rules";
            }
        }

        // Validate fork references
        if (isset($config['fork_from'])) {
            if (!is_array($config['fork_from']) || !isset($config['fork_from']['event_id'], $config['fork_from']['address'])) {
                $errors[] = 'fork_from must be an array with event_id and address fields';
            }
        }

        // Validate defer references
        if (isset($config['defer_to'])) {
            if (!is_array($config['defer_to']) || !isset($config['defer_to']['event_id'], $config['defer_to']['address'])) {
                $errors[] = 'defer_to must be an array with event_id and address fields';
            }
        }

        return $errors;
    }

    public function getRequiredFields(): array
    {
        return ['title'];
    }

    public function getOptionalFields(): array
    {
        $common = $this->getCommonOptionalFields();
        $specific = [
            'fork_from' => 'Reference to the original article this was forked from (array with event_id and address)',
            'defer_to' => 'Reference to a "better" version of this article (array with event_id and address)',
        ];
        
        return array_merge($common, $specific);
    }

    /**
     * Create tags specific to wiki articles
     */
    private function createWikiTags(array $config): array
    {
        $tags = [];

        // Handle d-tag with NIP-54 normalization
        if (isset($config['d-tag'])) {
            // Use provided d-tag (should already be normalized)
            $tags[] = ['d', $config['d-tag']];
        } elseif (isset($config['reuse_d_tag'])) {
            // Reuse an existing d-tag for replacing content
            $tags[] = ['d', $config['reuse_d_tag']];
        } elseif (isset($config['title'])) {
            // Generate normalized d-tag from title
            $dTag = $this->normalizeWikiDTag($config['title']);
            $tags[] = ['d', $dTag];
        }

        // Add title tag
        if (isset($config['title'])) {
            $tags[] = ['title', $config['title']];
        }

        // Add summary tag
        if (isset($config['summary'])) {
            $tags[] = ['summary', $config['summary']];
        }

        // Add topic tags (t tags)
        if (isset($config['topics']) && is_array($config['topics'])) {
            foreach ($config['topics'] as $topic) {
                $tags[] = ['t', $topic];
            }
        }

        // Add fork reference tags
        if (isset($config['fork_from']) && is_array($config['fork_from'])) {
            $tags[] = ['a', $config['fork_from']['address'], '', 'fork'];
            $tags[] = ['e', $config['fork_from']['event_id'], '', 'fork'];
        }

        // Add defer reference tags
        if (isset($config['defer_to']) && is_array($config['defer_to'])) {
            $tags[] = ['a', $config['defer_to']['address'], '', 'defer'];
            $tags[] = ['e', $config['defer_to']['event_id'], '', 'defer'];
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
     * Normalize d-tag according to NIP-54 rules
     * 
     * - Any non-letter character MUST be converted to a `-`
     * - All letters MUST be converted to lowercase
     */
    private function normalizeWikiDTag(string $input): string
    {
        // Convert non-letter characters to dashes
        $normalized = preg_replace('/[^a-zA-Z]/', '-', $input);
        
        // Convert to lowercase
        $normalized = strtolower($normalized);
        
        // Remove leading/trailing dashes and collapse multiple dashes
        $normalized = trim($normalized, '-');
        $normalized = preg_replace('/-+/', '-', $normalized);
        
        return $normalized;
    }
}
