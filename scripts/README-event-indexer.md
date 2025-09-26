# Event Indexer Scripts

This directory contains scripts for indexing Nostr events from the Orly relay into Elasticsearch.

## Files

### `index-relay-events.php` (NEW - Working Version)
- **Purpose**: Properly indexes Nostr events from multiple relays into Elasticsearch
- **Protocol**: Uses correct Nostr WebSocket protocol via nostr-php library
- **Features**:
  - **Multi-relay support**: Can query from multiple Nostr relays simultaneously
  - **Nostr authentication**: Supports NIP-42 authentication with protected relays
  - **Incremental indexing**: Only processes new events since last run
  - Connects to multiple relays via WebSocket (configurable list)
  - Queries events using timestamp-based filters (`since` parameter)
  - Creates Elasticsearch index with appropriate mappings
  - Bulk indexes events with metadata including relay information
  - **Duplicate prevention**: Uses event ID as document ID to prevent duplicates
  - **State tracking**: Saves last indexed timestamp to `/var/log/nostrbots/event-indexer-state.json`
  - **Relay resilience**: Continues working even if some relays fail
  - **Automatic authentication**: Uses existing key management system for auth
  - Comprehensive logging with detailed progress information per relay
  - Error handling and retry logic

### `test-elasticsearch.php` (NEW - Testing)
- **Purpose**: Tests Elasticsearch connectivity and indexing functionality
- **Features**:
  - Tests connection to Elasticsearch
  - Tests index creation
  - Tests document indexing
  - Tests document retrieval
  - Cleans up test data

## Usage

### Running the Event Indexer

#### Single Relay
```bash
# Set environment variables
export ELASTICSEARCH_URL="http://elasticsearch:9200"
export RELAY_URLS="ws://orly-relay:7777"

# Run the indexer
php scripts/index-relay-events.php
```

#### Multiple Relays
```bash
# Set environment variables
export ELASTICSEARCH_URL="http://elasticsearch:9200"
export RELAY_URLS="ws://orly-relay:7777,wss://aggr.nostr.land,wss://orly-relay.imwald.eu"

# Run the indexer
php scripts/index-relay-events.php
```

#### Alternative Formats
The script supports multiple relay URL formats:
```bash
# Comma-separated
export RELAY_URLS="ws://orly-relay:7777,wss://aggr.nostr.land,wss://orly-relay.imwald.eu"

# Space-separated
export RELAY_URLS="ws://orly-relay:7777 wss://aggr.nostr.land wss://orly-relay.imwald.eu"

# Mixed protocols (ws:// and wss://)
export RELAY_URLS="ws://orly-relay:7777,wss://aggr.nostr.land,wss://orly-relay.imwald.eu"

# Without protocol (will default to ws://)
export RELAY_URLS="orly-relay:7777,aggr.nostr.land,orly-relay.imwald.eu"
```

### Testing Elasticsearch Integration
```bash
# Test Elasticsearch connectivity and functionality
php scripts/test-elasticsearch.php
```

### Docker Usage
The Docker Compose configuration has been updated to use the new PHP script:
- Uses `index-relay-events.php` instead of the broken bash script
- Supports multiple relays via `RELAY_URLS` environment variable
- Default configuration includes: `ws://orly-relay:7777,wss://aggr.nostr.land,wss://orly-relay.imwald.eu`
- Runs every 5 minutes (300 seconds)

## Key Differences from Original Script

| Aspect | Original (Broken) | New (Working) |
|--------|------------------|---------------|
| **Protocol** | HTTP JSON-RPC | Nostr WebSocket |
| **Language** | Bash | PHP |
| **Library** | Raw curl | nostr-php library |
| **Relay URL** | `http://orly-relay:7777` | **Multiple relays (configurable)** |
| **Query Method** | `{"method": "query"}` | Proper Nostr REQ messages |
| **Indexing Strategy** | All recent events (inefficient) | **Incremental (only new events)** |
| **Duplicate Handling** | None | **Event ID as document ID** |
| **State Management** | None | **Persistent timestamp tracking** |
| **Relay Resilience** | None | **Continues if some relays fail** |
| **Authentication** | None | **NIP-42 auth with secret keys** |
| **Error Handling** | Basic | Comprehensive |
| **Logging** | Simple echo | **Structured logging per relay** |

## Environment Variables

- `ELASTICSEARCH_URL`: Elasticsearch endpoint (default: `http://elasticsearch:9200`)
- `RELAY_URLS`: Comma or space-separated list of relay WebSocket URLs (default: `ws://orly-relay:7777`)
- `ORLY_RELAY_URL`: Legacy single relay URL (fallback if `RELAY_URLS` not set)

### Authentication Variables
The script automatically uses the existing key management system for authentication:
- `NOSTR_BOT_KEY`: Hex private key for authentication
- `NOSTR_BOT_KEY_ENCRYPTED`: Encrypted private key (decrypted automatically)
- `CUSTOM_PRIVATE_KEY`: Custom private key for authentication
- Docker secrets: `/run/secrets/nostr_bot_key_encrypted` and `/run/secrets/nostr_bot_npub`

### Performance Variables
Control the performance and resource usage of the indexer:
- `RELAY_BATCH_SIZE`: Number of relays to process simultaneously (default: 5)
- `REQUEST_DELAY_MS`: Delay between batches in milliseconds (default: 1000)
- `CONNECTION_TIMEOUT`: WebSocket connection timeout in seconds (default: 30)
- `MAX_CONCURRENT_CONNECTIONS`: Maximum concurrent connections (default: 3)
- `BACKGROUND_MODE`: Enable background processing optimizations (default: true)
- `MAX_EVENTS`: Maximum events to fetch per relay per run (default: 1000)

## Dependencies

- PHP 7.4+
- nostr-php library (already in vendor/)
- Elasticsearch 7.x+
- Nostr relays accessible via WebSocket (ws:// or wss://)

## Troubleshooting

1. **Connection Issues**: Ensure Elasticsearch and at least one relay are running
2. **WebSocket Issues**: Verify relays are accessible via WebSocket (ws:// or wss://)
3. **Indexing Issues**: Check Elasticsearch logs and cluster health
4. **Permission Issues**: Ensure log directory `/var/log/nostrbots/` is writable
5. **State File Issues**: If incremental indexing stops working, check `/var/log/nostrbots/event-indexer-state.json`
6. **Duplicate Events**: The script uses event IDs as document IDs to prevent duplicates automatically
7. **First Run**: On first run, the script will index events from the last hour to catch recent activity
8. **Relay Failures**: The script continues working even if some relays fail - check logs for specific relay errors
9. **Multiple Relay Issues**: If one relay fails, others will continue to work independently
10. **Authentication Issues**: If authentication fails, check that your private key is properly configured
11. **Protected Relays**: Some relays require authentication - the script will automatically authenticate if a key is available

## Incremental Indexing Behavior

The script now implements **incremental indexing** to be much more efficient:

### How It Works
1. **First Run**: Indexes events from the last hour to catch recent activity
2. **Subsequent Runs**: Only queries events newer than the last indexed timestamp
3. **State Tracking**: Saves the newest event timestamp to `event-indexer-state.json`
4. **Duplicate Prevention**: Uses Nostr event IDs as Elasticsearch document IDs
5. **No New Events**: Updates timestamp to current time to avoid re-checking old events

### State File
The script maintains state in `/var/log/nostrbots/event-indexer-state.json`:
```json
{
    "last_timestamp": 1703123456,
    "last_run": 1703123500,
    "last_run_date": "2023-12-21T10:45:00+00:00"
}
```

### Benefits
- **Efficient**: Only processes new events, not all recent events
- **Fast**: Reduces query time and network traffic
- **Reliable**: Handles duplicates gracefully
- **Resilient**: Recovers from failures by starting from last known timestamp

## Authentication Behavior

The script supports **NIP-42 authentication** for protected relays:

### How It Works
1. **Automatic Detection**: When a relay requires authentication, it sends an "auth-required" response
2. **Key Management**: Uses the existing KeyManager to get the private key from various sources
3. **Challenge Response**: Creates and signs an AuthEvent with the relay's challenge
4. **Seamless Operation**: Authentication happens automatically without user intervention

### Key Sources (in order of preference)
1. **Docker Secrets**: `/run/secrets/nostr_bot_key_encrypted` (production)
2. **Environment Variables**: `NOSTR_BOT_KEY` or `CUSTOM_PRIVATE_KEY`
3. **Encrypted Keys**: `NOSTR_BOT_KEY_ENCRYPTED` (auto-decrypted)
4. **Fallback**: If no key is available, authentication will fail gracefully

### Authentication Logging
- `"Found private key for authentication"` - Key successfully loaded
- `"Authenticating with relay: [URL]"` - Starting authentication process
- `"Authentication message sent to relay: [URL]"` - Auth message sent
- `"No private key available - authentication will not be possible"` - No key found

## Performance Optimization

The event indexer includes several performance optimizations for handling many relays:

### Batch Processing
- **Relay Batching**: Processes relays in configurable batches (default: 3 at a time)
- **Request Delays**: Adds delays between batches to prevent overwhelming relays
- **Connection Timeouts**: Configurable timeouts to prevent hanging connections

### Background Processing
- **Memory Management**: Optimized memory usage with configurable limits
- **Time Limits**: No time limits in background mode for long-running processes
- **Resource Monitoring**: Tracks memory usage and execution times

### CPU Optimization
- **Connection Pooling**: Limits concurrent connections to prevent resource exhaustion
- **Efficient Processing**: Processes events in batches to reduce memory overhead
- **Graceful Degradation**: Continues processing even if some relays fail

### Recommended Settings for Many Relays

**For 10-20 relays:**
```bash
RELAY_BATCH_SIZE=5
REQUEST_DELAY_MS=1000
CONNECTION_TIMEOUT=30
```

**For 20-50 relays:**
```bash
RELAY_BATCH_SIZE=3
REQUEST_DELAY_MS=2000
CONNECTION_TIMEOUT=45
```

**For 50+ relays:**
```bash
RELAY_BATCH_SIZE=2
REQUEST_DELAY_MS=3000
CONNECTION_TIMEOUT=60
MAX_EVENTS=500
```

## Testing

### Basic Tests
Run the test script to verify everything works:
```bash
php scripts/test-elasticsearch.php
```

### Authentication Tests
Test the authentication system:
```bash
# Test key management and authentication components
php scripts/test-authentication.php

# Test relay connectivity with authentication
php scripts/test-relay-connectivity.php

# Test specific relay authentication scenarios
php scripts/test-relay-auth.php
```

### Real-World Testing
Test against actual relays:
```bash
# Test with your configured relays
RELAY_URLS="wss://aggr.nostr.land,wss://relay.damus.io" php scripts/test-relay-connectivity.php

# Test the full indexer (requires Elasticsearch)
RELAY_URLS="wss://aggr.nostr.land,wss://relay.damus.io" php scripts/index-relay-events.php

# Test with performance monitoring
php scripts/monitor-performance.php
```

### Performance Monitoring
Monitor the performance and health of your indexer:
```bash
# Show current performance statistics
php scripts/monitor-performance.php

# Test with custom performance settings
RELAY_BATCH_SIZE=3 REQUEST_DELAY_MS=2000 php scripts/index-relay-events.php
```

This will test:
- ✅ Elasticsearch connectivity
- ✅ Index creation
- ✅ Document indexing
- ✅ Document retrieval
- ✅ Cleanup
