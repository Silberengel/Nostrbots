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
    # Ensure log directory exists
    mkdir -p /var/log/nostrbots
    echo "[$(date -Iseconds)] $1" | tee -a /var/log/nostrbots/event-indexer.log
}

# Error handling
error_exit() {
    log "ERROR: $1"
    exit 1
}

# Check if Elasticsearch is available
check_elasticsearch() {
    log "Checking Elasticsearch connectivity..."
    if ! curl -s "$ELASTICSEARCH_URL/_cluster/health" > /dev/null; then
        error_exit "Cannot connect to Elasticsearch at $ELASTICSEARCH_URL"
    fi
    log "Elasticsearch is available"
}

# Create index if it doesn't exist
create_index() {
    log "Ensuring index $INDEX_NAME exists..."
    
    local index_mapping='{
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
                "indexed_at": {"type": "date"},
                "relay": {"type": "keyword"}
            }
        }
    }'
    
    local response=$(curl -s -X PUT "$ELASTICSEARCH_URL/$INDEX_NAME" \
        -H "Content-Type: application/json" \
        -d "$index_mapping")
    
    if echo "$response" | jq -e '.acknowledged' > /dev/null; then
        log "Index $INDEX_NAME created successfully"
    elif echo "$response" | jq -e '.error.type' | grep -q "resource_already_exists_exception"; then
        log "Index $INDEX_NAME already exists"
    else
        log "Warning: Could not create index: $response"
    fi
}

# Get events from relay
get_events() {
    log "Fetching events from relay..."
    
    local response=$(curl -s -X POST "$ORLY_RELAY_URL" \
        -H "Content-Type: application/json" \
        -d '{
            "jsonrpc": "2.0",
            "method": "query",
            "params": {
                "limit": '$MAX_EVENTS'
            },
            "id": 1
        }')
    
    if [ -z "$response" ]; then
        error_exit "No response from relay"
    fi
    
    local events=$(echo "$response" | jq -r '.result // empty')
    if [ -z "$events" ] || [ "$events" = "null" ]; then
        log "No events found in relay response"
        return 1
    fi
    
    local count=$(echo "$events" | jq 'length')
    log "Found $count events to index"
    
    if [ "$count" -eq 0 ]; then
        return 1
    fi
    
    echo "$events"
}

# Index events to Elasticsearch
index_events() {
    local events="$1"
    local count=$(echo "$events" | jq 'length')
    
    if [ "$count" -eq 0 ]; then
        log "No events to index"
        return 0
    fi
    
    log "Indexing $count events to Elasticsearch"
    
    # Prepare bulk index request - using sh-compatible syntax
    local bulk_data=""
    local temp_file=$(mktemp)
    echo "$events" | jq -c '.[]' > "$temp_file"
    
    while IFS= read -r event; do
        if [ -n "$event" ] && [ "$event" != "null" ]; then
            # Add metadata
            local indexed_event=$(echo "$event" | jq '. + {"indexed_at": now, "relay": "orly-relay"}')
            
            # Create bulk index entry
            local event_id=$(echo "$indexed_event" | jq -r '.id')
            local index_entry="{\"index\": {\"_index\": \"$INDEX_NAME\", \"_id\": \"$event_id\"}}
$indexed_event"
            bulk_data="$bulk_data$index_entry"
        fi
    done < "$temp_file"
    rm -f "$temp_file"
    
    # Send bulk request
    local response=$(curl -s -X POST "$ELASTICSEARCH_URL/_bulk" \
        -H "Content-Type: application/json" \
        -d "$bulk_data")
    
    if echo "$response" | jq -e '.errors' | grep -q "true"; then
        log "Warning: Some events failed to index"
        echo "$response" | jq '.items[] | select(.index.error) | .index.error' | tee -a /var/log/nostrbots/event-indexer.log
    else
        log "Successfully indexed $count events"
    fi
}

# Main execution
main() {
    log "Starting event indexing process"
    
    check_elasticsearch
    create_index
    
    local events
    if events=$(get_events); then
        index_events "$events"
    else
        log "No events to process"
    fi
    
    log "Event indexing process completed"
}

# Run main function
main "$@"
