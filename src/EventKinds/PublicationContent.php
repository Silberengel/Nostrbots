<?php

namespace Nostrbots\EventKinds;

use swentel\nostr\Event\Event;

/**
 * Handler for Publication Content (kind 30041)
 * 
 * Implements support for publication content sections, also known as
 * sections, zettels, episodes, or chapters that make up a publication.
 */
class PublicationContent extends AbstractEventKind
{
    public function getKind(): int
    {
        return 30041;
    }

    public function getName(): string
    {
        return 'Publication Content';
    }

    public function getDescription(): string
    {
        return 'Publication content sections (chapters, episodes, zettels) that make up a curated publication';
    }

    public function createEvent(array $config, array $content): Event
    {
        $event = new Event();
        $event->setKind($this->getKind());

        // Set content (can be AsciiDoc, Markdown, or plain text)
        if (isset($content['content'])) {
            $event->setContent($content['content']);
        } elseif (isset($content['asciidoc'])) {
            $event->setContent($content['asciidoc']);
        } elseif (isset($content['markdown'])) {
            $event->setContent($content['markdown']);
        } else {
            throw new \InvalidArgumentException('Content must include "content", "asciidoc", or "markdown" field');
        }

        // Create and set tags
        $tags = $this->createStandardTags($config);
        
        // Add wikilinks if provided
        $this->addWikilinks($tags, $config);

        $event->setTags($tags);
        return $event;
    }

    public function validateConfig(array $config): array
    {
        $errors = $this->validateCommonFields($config, $this->getRequiredFields());

        // Validate wikilinks
        if (isset($config['wikilinks'])) {
            if (!is_array($config['wikilinks'])) {
                $errors[] = 'wikilinks must be an array';
            } else {
                foreach ($config['wikilinks'] as $index => $link) {
                    if (!is_array($link) || !isset($link['term'])) {
                        $errors[] = "wikilinks[{$index}] must be an array with at least a 'term' field";
                    }
                }
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
            'wikilinks' => 'Array of wikilink definitions for terms in the content',
        ];
        
        return array_merge($common, $specific);
    }

    /**
     * Add wikilink tags for terms referenced in the content
     */
    private function addWikilinks(array &$tags, array $config): void
    {
        if (!isset($config['wikilinks']) || !is_array($config['wikilinks'])) {
            return;
        }

        foreach ($config['wikilinks'] as $link) {
            if (!is_array($link) || !isset($link['term'])) {
                continue;
            }

            $wikilinkTag = [
                'wikilink',
                $link['term'],
                $link['definition'] ?? '',
                $link['relay'] ?? '',
                $link['reference'] ?? ''
            ];

            $tags[] = $wikilinkTag;
        }
    }
}
