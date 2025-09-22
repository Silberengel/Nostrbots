<?php

namespace Nostrbots\EventKinds;

use swentel\nostr\Event\Event;

/**
 * Handler for NIP-23 Long-form Content (kind 30023)
 * 
 * Implements support for long-form text content, generally referred to as 
 * "articles" or "blog posts". Content should be in Markdown format.
 */
class LongFormContent extends AbstractEventKind
{
    public function getKind(): int
    {
        return 30023;
    }

    public function getName(): string
    {
        return 'Long-form Content';
    }

    public function getDescription(): string
    {
        return 'Long-form text content like articles or blog posts in Markdown format (NIP-23)';
    }

    public function createEvent(array $config, array $content): Event
    {
        $event = new Event();
        $event->setKind($this->getKind());

        // Set content (should be Markdown)
        if (isset($content['markdown'])) {
            $event->setContent($content['markdown']);
        } elseif (isset($content['content'])) {
            $event->setContent($content['content']);
        } else {
            throw new \InvalidArgumentException('Content must include "markdown" or "content" field');
        }

        // Create and set tags
        $tags = $this->createStandardTags($config);
        
        // Add NIP-27 references if provided
        if (isset($config['references']) && is_array($config['references'])) {
            foreach ($config['references'] as $ref) {
                if (isset($ref['type'], $ref['id'])) {
                    switch ($ref['type']) {
                        case 'event':
                            $tags[] = ['e', $ref['id'], $ref['relay'] ?? ''];
                            break;
                        case 'address':
                            $tags[] = ['a', $ref['id'], $ref['relay'] ?? ''];
                            break;
                        case 'profile':
                            $tags[] = ['p', $ref['id'], $ref['relay'] ?? ''];
                            break;
                    }
                }
            }
        }

        $event->setTags($tags);
        return $event;
    }

    public function validateConfig(array $config): array
    {
        return $this->validateCommonFields($config, $this->getRequiredFields());
    }

    public function getRequiredFields(): array
    {
        return ['title'];
    }

    public function getOptionalFields(): array
    {
        $common = $this->getCommonOptionalFields();
        $specific = [
            'references' => 'Array of NIP-27 references to other events/profiles',
        ];
        
        return array_merge($common, $specific);
    }

    /**
     * For long-form content, we can optionally create comment/notification events
     */
    public function postProcess(Event $event, array $config): array
    {
        $additionalEvents = [];

        // Create notification event if requested
        if (isset($config['create_notification']) && $config['create_notification']) {
            $additionalEvents[] = $this->createNotificationEvent($event, $config);
        }

        return $additionalEvents;
    }

    /**
     * Create a kind 1111 notification event for the article
     */
    private function createNotificationEvent(Event $articleEvent, array $config): Event
    {
        $notification = new Event();
        $notification->setKind(1111);

        // Create naddr reference
        $dTag = '';
        foreach ($articleEvent->getTags() as $tag) {
            if ($tag[0] === 'd') {
                $dTag = $tag[1];
                break;
            }
        }

        $naddr = $this->getKind() . ':' . $articleEvent->getPublicKey() . ':' . $dTag;
        
        // Set notification content
        $title = $config['title'] ?? 'New Article';
        $notificationText = $config['notification_text'] ?? "A new article has been posted: {$title}";
        $notification->setContent($notificationText . "\nnostr:" . $naddr);

        // Set notification tags
        $tags = [
            ['A', $naddr, ''],
            ['K', '1111'],
            ['a', $naddr, ''],
            ['k', '1111']
        ];

        $notification->setTags($tags);
        return $notification;
    }
}
