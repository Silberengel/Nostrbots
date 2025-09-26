#!/bin/bash

# Event Indexing Script for Elasticsearch
# Indexes Nostr events from the relay into Elasticsearch for search and analytics

set -euo pipefail

# Configuration
ELASTICSEARCH_URL="${ELASTICSEARCH_URL:-http://elasticsearch:9200}"
ORLY_RELAY_URL="${ORLY_RELAY_URL:-http://orly-relay:7777}"
INDEX_NAME="nostr-events"
BATCH_SIZE=100
MAX_EVENTS=1000

# Logging
log() {
    echo "[$(date -Iseconds)] $1" | tee -a /var/log/nostrbots/event-indexer.log
}

# Check if Elasticsearch is available
check_elasticsearch() {
    if ! curl -s "$ELASTICSEARCH_URL/_cluster/health" > /dev/null; then
        log "ERROR: Elasticsearch is not available at $ELASTICSEARCH_URL"
        return 1
    fi
    log "✅ Elasticsearch is available"
}

# Create index mapping if it doesn't exist
create_index_mapping() {
    local mapping='{
        "mappings": {
            "properties": {
                "id": {"type": "keyword"},
                "pubkey": {"type": "keyword"},
                "created_at": {"type": "date"},
                "kind": {"type": "integer"},
                "tags": {"type": "keyword"},
                "content": {
                    "type": "text",
                    "analyzer": "standard"
                },
                "sig": {"type": "keyword"},
                "relay": {"type": "keyword"},
                "indexed_at": {"type": "date"}
            }
        }
    }'
    
    if ! curl -s "$ELASTICSEARCH_URL/$INDEX_NAME" > /dev/null; then
        log "Creating index mapping for $INDEX_NAME"
        curl -s -X PUT "$ELASTICSEARCH_URL/$INDEX_NAME" \
            -H "Content-Type: application/json" \
            -d "$mapping" > /dev/null
        log "✅ Index mapping created"
    else
        log "Index $INDEX_NAME already exists"
    fi
}

# Get events from relay
get_events_from_relay() {
    local since="${1:-0}"
    local limit="${2:-$BATCH_SIZE}"
    
    # Query the relay for events
    local query='{
        "ids": [],
        "authors": [],
        "kinds": [],
        "#e": [],
        "#p": [],
        "since": '$since',
        "until": null,
        "limit": '$limit'
    }'
    
    # Send query to relay
    local response=$(curl -s -X POST "$ORLY_RELAY_URL" \
        -H "Content-Type: application/json" \
        -d "$query" 2>/dev/null || echo '[]')
    
    echo "$response"
}

# Index events to Elasticsearch
index_events() {
    local events="$1"
    local count=$(echo "$events" | jq length)
    
    if [ "$count" -eq 0 ]; then
        log "No new events to index"
        return 0
    fi
    
    log "Indexing $count events to Elasticsearch"
    
    # Prepare bulk index request
    local bulk_data=""
    while IFS= read -r event; do
        if [ -n "$event" ] && [ "$event" != "null" ]; then
            # Add metadata
            local indexed_event=$(echo "$event" | jq '. + {"indexed_at": now, "relay": "orly-relay"}')
            
            # Create bulk index entry
            local index_entry=$(cat <<EOF
{"index": {"_index": "$INDEX_NAME", "_id": "$(echo "$indexed_event" | jq -r '.id')"}}
$indexed_event
EOF
)
            bulk_data="$bulk_data$index_entry"
        fi
    done <<< "$(echo "$events" | jq -c '.[]')"
    
    # Send bulk request
    local response=$(curl -s -X POST "$ELASTICSEARCH_URL/_bulk" \
        -H "Content-Type: application/json" \
        -d "$bulk_data")
    
    # Check for errors
    local errors=$(echo "$response" | jq '.errors')
    if [ "$errors" = "true" ]; then
        log "ERROR: Some events failed to index"
        echo "$response" | jq '.items[] | select(.index.error) | .index.error' | tee -a /var/log/nostrbots/event-indexer.log
        return 1
    fi
    
    log "✅ Successfully indexed $count events"
}

# Get last indexed timestamp
get_last_indexed_timestamp() {
    local response=$(curl -s "$ELASTICSEARCH_URL/$INDEX_NAME/_search" \
        -H "Content-Type: application/json" \
        -d '{
            "size": 1,
            "sort": [{"created_at": {"order": "desc"}}],
            "_source": ["created_at"]
        }')
    
    local last_timestamp=$(echo "$response" | jq -r '.hits.hits[0]._source.created_at // 0')
    echo "$last_timestamp"
}

# Main indexing function
main() {
    log "🔍 Starting event indexing process"
    
    # Check prerequisites
    check_elasticsearch || exit 1
    create_index_mapping
    
    # Get last indexed timestamp
    local since=$(get_last_indexed_timestamp)
    log "Indexing events since timestamp: $since"
    
    # Get and index events
    local events=$(get_events_from_relay "$since" "$MAX_EVENTS")
    index_events "$events"
    
    log "✅ Event indexing completed"
}

# Run main function
main "$@"
