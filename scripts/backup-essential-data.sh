#!/bin/bash

# Efficient Backup Script for Nostrbots Essential Data
# Only backs up config files and relay events, not entire containers

set -euo pipefail

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/backups}"
DATA_DIR="${DATA_DIR:-/data}"
ELASTICSEARCH_URL="${ELASTICSEARCH_URL:-http://elasticsearch:9200}"
BACKUP_RETENTION_DAYS=30
MAX_BACKUP_SIZE_MB=1000  # Maximum backup size in MB

# Logging
log() {
    # Ensure log directory exists
    mkdir -p /var/log/nostrbots
    echo "[$(date -Iseconds)] $1" | tee -a /var/log/nostrbots/backup.log
}

# Create backup directory
create_backup_dir() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_path="$BACKUP_DIR/$timestamp"
    mkdir -p "$backup_path"
    echo "$backup_path"
}

# Backup essential config files only
backup_config_files() {
    local backup_path="$1"
    local config_dir="$backup_path/config"
    
    log "📁 Backing up essential config files"
    mkdir -p "$config_dir"
    
    # Backup Jenkins config (only essential files)
    if [ -d "$DATA_DIR/jenkins" ]; then
        log "📋 Backing up Jenkins config"
        mkdir -p "$config_dir/jenkins"
        
        # Only backup essential Jenkins files, not the entire workspace
        if [ -f "$DATA_DIR/jenkins/config.xml" ]; then
            cp "$DATA_DIR/jenkins/config.xml" "$config_dir/jenkins/"
        fi
        
        # Backup job configs (these are small but important)
        if [ -d "$DATA_DIR/jenkins/jobs" ]; then
            find "$DATA_DIR/jenkins/jobs" -name "config.xml" -exec cp --parents {} "$config_dir/jenkins/" \; 2>/dev/null || true
        fi
        
        # Backup user configs
        if [ -d "$DATA_DIR/jenkins/users" ]; then
            find "$DATA_DIR/jenkins/users" -name "config.xml" -exec cp --parents {} "$config_dir/jenkins/" \; 2>/dev/null || true
        fi
        
        log "✓ Jenkins config backed up"
    fi
    
    # Backup Nostrbots project config
    if [ -d "/workspace" ]; then
        log "📋 Backing up Nostrbots project config"
        mkdir -p "$config_dir/nostrbots"
        
        # Only backup essential config files, not the entire project
        for file in ".env" "docker-compose.yml" "docker-compose.*.yml" "*.env" "*.key" "*.pem"; do
            find /workspace -maxdepth 2 -name "$file" -exec cp {} "$config_dir/nostrbots/" \; 2>/dev/null || true
        done
        
        # Backup scripts directory
        if [ -d "/workspace/scripts" ]; then
            cp -r /workspace/scripts "$config_dir/nostrbots/"
        fi
        
        log "✓ Nostrbots config backed up"
    fi
}

# Backup relay events (only the essential data)
backup_relay_events() {
    local backup_path="$1"
    local events_dir="$backup_path/events"
    
    log "📡 Backing up relay events"
    mkdir -p "$events_dir"
    
    if [ -d "$DATA_DIR/orly" ]; then
        # Only backup the events database, not logs or cache
        if [ -f "$DATA_DIR/orly/events.db" ]; then
            cp "$DATA_DIR/orly/events.db" "$events_dir/"
            log "✓ Relay events database backed up"
        fi
        
        # Backup any essential relay config
        for file in "config.json" "settings.json" "*.conf"; do
            find "$DATA_DIR/orly" -maxdepth 1 -name "$file" -exec cp {} "$events_dir/" \; 2>/dev/null || true
        done
        
        # Get relay stats for verification
        if command -v sqlite3 >/dev/null 2>&1 && [ -f "$DATA_DIR/orly/events.db" ]; then
            sqlite3 "$DATA_DIR/orly/events.db" "SELECT COUNT(*) as event_count FROM events;" > "$events_dir/event_count.txt" 2>/dev/null || true
        fi
    else
        log "⚠  No relay data directory found at $DATA_DIR/orly"
    fi
}

# Export Elasticsearch data (only essential indices)
backup_elasticsearch_data() {
    local backup_path="$1"
    local es_dir="$backup_path/elasticsearch"
    
    log "🔍 Backing up Elasticsearch data"
    mkdir -p "$es_dir"
    
    # Check if Elasticsearch is available
    if ! curl -s "$ELASTICSEARCH_URL/_cluster/health" >/dev/null 2>&1; then
        log "⚠  Elasticsearch not available, skipping ES backup"
        return 0
    fi
    
    # Export only essential indices
    local indices=("nostr-events" "nostrbots-logs")
    
    for index in "${indices[@]}"; do
        log "📊 Exporting index: $index"
        
        # Get index mapping
        curl -s "$ELASTICSEARCH_URL/$index/_mapping" > "$es_dir/${index}_mapping.json" 2>/dev/null || true
        
        # Export recent data only (last 30 days by default)
        local query='{
            "query": {
                "range": {
                    "created_at": {
                        "gte": "now-30d"
                    }
                }
            },
            "size": 10000
        }'
        
        curl -s -X POST "$ELASTICSEARCH_URL/$index/_search" \
            -H "Content-Type: application/json" \
            -d "$query" > "$es_dir/${index}_data.json" 2>/dev/null || true
        
        # Get index stats
        curl -s "$ELASTICSEARCH_URL/$index/_stats" > "$es_dir/${index}_stats.json" 2>/dev/null || true
    done
    
    log "✓ Elasticsearch data exported"
}

# Create compressed backup archive
create_backup_archive() {
    local backup_path="$1"
    local archive_name="nostrbots-essential-$(basename "$backup_path").tar.gz"
    local archive_path="$BACKUP_DIR/$archive_name"
    
    log "📦 Creating compressed backup archive"
    
    # Create tar archive with compression
    tar -czf "$archive_path" -C "$backup_path" .
    
    # Check archive size
    local archive_size_mb=$(($(stat -c%s "$archive_path") / 1024 / 1024))
    log "📊 Archive size: ${archive_size_mb}MB"
    
    if [ "$archive_size_mb" -gt "$MAX_BACKUP_SIZE_MB" ]; then
        log "⚠  Archive size (${archive_size_mb}MB) exceeds maximum (${MAX_BACKUP_SIZE_MB}MB)"
        log "🔧 Consider adjusting backup retention or excluding more data"
    fi
    
    # Create backup manifest
    cat > "$backup_path/manifest.json" << EOF
{
    "backup_date": "$(date -Iseconds)",
    "backup_type": "essential_data",
    "archive_name": "$archive_name",
    "archive_size_mb": $archive_size_mb,
    "components": {
        "config_files": true,
        "relay_events": true,
        "elasticsearch_data": true
    },
    "retention_days": $BACKUP_RETENTION_DAYS
}
EOF
    
    # Remove temporary directory
    rm -rf "$backup_path"
    
    log "✓ Compressed backup created: $archive_name (${archive_size_mb}MB)"
}

# Cleanup old backups
cleanup_old_backups() {
    log "🧹 Cleaning up old backups (older than $BACKUP_RETENTION_DAYS days)"
    
    # Remove old backup archives
    find "$BACKUP_DIR" -name "nostrbots-essential-*.tar.gz" -mtime +$BACKUP_RETENTION_DAYS -delete 2>/dev/null || true
    
    # Remove old backup directories (if any remain)
    find "$BACKUP_DIR" -type d -name "20*" -mtime +$BACKUP_RETENTION_DAYS -exec rm -rf {} + 2>/dev/null || true
    
    # Log current backup usage
    local total_size_mb=$(($(du -sb "$BACKUP_DIR" 2>/dev/null | cut -f1) / 1024 / 1024))
    local backup_count=$(find "$BACKUP_DIR" -name "nostrbots-essential-*.tar.gz" | wc -l)
    
    log "📊 Current backup usage: ${total_size_mb}MB across $backup_count backups"
    log "✓ Old backups cleaned up"
}

# Verify backup integrity
verify_backup() {
    local archive_path="$1"
    
    log "🔍 Verifying backup integrity"
    
    # Check if archive exists and is readable
    if [ ! -f "$archive_path" ]; then
        log "✗ Backup verification failed: archive not found"
        return 1
    fi
    
    # Test archive integrity
    if ! tar -tzf "$archive_path" >/dev/null 2>&1; then
        log "✗ Backup verification failed: archive is corrupted"
        return 1
    fi
    
    # Check archive size
    local archive_size_mb=$(($(stat -c%s "$archive_path") / 1024 / 1024))
    if [ "$archive_size_mb" -lt 1 ]; then
        log "✗ Backup verification failed: archive is too small (${archive_size_mb}MB)"
        return 1
    fi
    
    log "✓ Backup verification completed (${archive_size_mb}MB)"
}

# Main backup function
main() {
    log "🔄 Starting efficient backup process"
    
    # Create backup directory
    local backup_path=$(create_backup_dir)
    log "📁 Backup directory: $backup_path"
    
    # Perform backups
    backup_config_files "$backup_path"
    backup_relay_events "$backup_path"
    backup_elasticsearch_data "$backup_path"
    
    # Create compressed archive
    create_backup_archive "$backup_path"
    
    # Verify and cleanup
    local archive_path="$BACKUP_DIR/nostrbots-essential-$(basename "$backup_path").tar.gz"
    verify_backup "$archive_path"
    cleanup_old_backups
    
    log "✅ Efficient backup process completed successfully"
}

# Run main function
main "$@"
