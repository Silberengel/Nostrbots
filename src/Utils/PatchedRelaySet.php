<?php

namespace Nostrbots\Utils;

use swentel\nostr\Relay\RelaySet as BaseRelaySet;
use WebSocket\Message\Text;

/**
 * Patched RelaySet class that fixes WebSocket compatibility issues
 * 
 * This class extends the original RelaySet class and fixes the issue where
 * the WebSocket library returns a string instead of a Message object.
 */
class PatchedRelaySet extends BaseRelaySet
{
    /**
     * Override the send method to fix WebSocket compatibility
     */
    public function send(): array
    {
        $results = [];
        
        // Use reflection to access the private message property from parent class
        $reflection = new \ReflectionClass(parent::class);
        $messageProperty = $reflection->getProperty('message');
        $messageProperty->setAccessible(true);
        $message = $messageProperty->getValue($this);
        
        if (!$message) {
            throw new \RuntimeException('No message set on RelaySet');
        }
        
        foreach ($this->relays as $relay) {
            try {
                $relayUrl = $relay->getUrl();
                $client = $relay->getClient();
                $payload = $message->generate();
                $client->text($payload);
                $response = $client->receive();
                $client->disconnect();
                
                // Fix: Convert string response to Text Message object if needed
                if (is_string($response)) {
                    $response = new Text($response);
                }
                
                if ($response->getOpcode() === 'ping') {
                    continue;
                }
                if ($response === null) {
                    throw new \RuntimeException('Websocket client response is null');
                }
                
                $result = \swentel\nostr\RelayResponse\RelayResponse::create(json_decode($response->getContent()));
                $results[$relayUrl] = $result;
                
            } catch (\Exception $e) {
                $results[$relay->getUrl()] = [
                    'ERROR',
                    '',
                    false,
                    $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
}
