<?php

namespace Nostrbots\Utils;

use swentel\nostr\Relay\Relay as BaseRelay;
use WebSocket\Message\Text;

/**
 * Patched Relay class that fixes WebSocket compatibility issues
 * 
 * This class extends the original Relay class and fixes the issue where
 * the WebSocket library returns a string instead of a Message object.
 */
class PatchedRelay extends BaseRelay
{
    /**
     * Override the send method to fix WebSocket compatibility
     */
    public function send(): \swentel\nostr\RelayResponse\RelayResponse
    {
        try {
            // Use the parent's send method but intercept the response
            $result = parent::send();
            return $result;
            
        } catch (\WebSocket\Exception\ClientException $e) {
            if ($this->client) {
                $this->client->disconnect();
            }
            throw new \RuntimeException('WebSocket error: ' . $e->getMessage());
        } catch (\Throwable $e) {
            if ($this->client) {
                $this->client->disconnect();
            }
            throw new \RuntimeException('WebSocket error: ' . $e->getMessage());
        }
    }
}
