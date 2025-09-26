#!/bin/bash

# Enhanced Backup Script for Relay Data
# Backs up relay data to local storage and Elasticsearch

set -euo pipefail

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/backups}"
DATA_DIR="${DATA_DIR:-/data}"
ELASTICSEARCH_URL="${ELASTICSEARCH_URL:-http://elasticsearch:9200}"
BACKUP_RETENTION_DAYS=30

# Logging
log() {
    echo "[$(date -Iseconds)] $1" | tee -a /var/log/nostrbots/backup.log
}

# Create backup directory
create_backup_dir() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_path="$BACKUP_DIR/$timestamp"
    mkdir -p "$backup_path"
    echo "$backup_path"
}

# Backup relay data files
backup_relay_files() {
    local backup_path="$1"
    
    log "📁 Backing up relay data files"
    
    if [ -d "$DATA_DIR/orly" ]; then
        cp -r "$DATA_DIR/orly" "$backup_path/"
        log "✅ Relay data files backed up"
    else
        log "⚠️  No relay data directory found at $DATA_DIR/orly"
    fi
}

# Backup to Elasticsearch
backup_to_elasticsearch() {
    local backup_path="$1"
    
    log "🔍 Creating Elasticsearch backup"
    
    # Create snapshot repository
    local repo_config='{
        "type": "fs",
        "settings": {
            "location": "'$backup_path'/elasticsearch-snapshot"
        }
    }'
    
    curl -s -X PUT "$ELASTICSEARCH_URL/_snapshot/backup_repo" \
        -H "Content-Type: application/json" \
        -d "$repo_config" > /dev/null || true
    
    # Create snapshot
    local snapshot_name="backup_$(date +%Y%m%d_%H%M%S)"
    local snapshot_config='{
        "indices": "nostr-events*,nostrbots-logs*",
        "ignore_unavailable": true,
        "include_global_state": false
    }'
    
    curl -s -X PUT "$ELASTICSEARCH_URL/_snapshot/backup_repo/$snapshot_name" \
        -H "Content-Type: application/json" \
        -d "$snapshot_config" > /dev/null
    
    log "✅ Elasticsearch snapshot created: $snapshot_name"
}

# Create encrypted backup archive
create_encrypted_archive() {
    local backup_path="$1"
    local archive_name="nostrbots-backup-$(basename "$backup_path").tar.gz"
    local archive_path="$BACKUP_DIR/$archive_name"
    
    log "🔐 Creating encrypted backup archive"
    
    # Create tar archive
    tar -czf "$archive_path" -C "$backup_path" .
    
    # Encrypt with GPG
    gpg --symmetric --cipher-algo AES256 --output "$archive_path.gpg" "$archive_path"
    
    # Remove unencrypted archive
    rm "$archive_path"
    
    log "✅ Encrypted backup created: $archive_name.gpg"
}

# Cleanup old backups
cleanup_old_backups() {
    log "🧹 Cleaning up old backups (older than $BACKUP_RETENTION_DAYS days)"
    
    find "$BACKUP_DIR" -type d -name "20*" -mtime +$BACKUP_RETENTION_DAYS -exec rm -rf {} + 2>/dev/null || true
    find "$BACKUP_DIR" -name "*.gpg" -mtime +$BACKUP_RETENTION_DAYS -delete 2>/dev/null || true
    
    log "✅ Old backups cleaned up"
}

# Verify backup integrity
verify_backup() {
    local backup_path="$1"
    
    log "🔍 Verifying backup integrity"
    
    # Check if backup directory exists and has content
    if [ ! -d "$backup_path" ] || [ -z "$(ls -A "$backup_path" 2>/dev/null)" ]; then
        log "❌ Backup verification failed: empty or missing backup directory"
        return 1
    fi
    
    # Check Elasticsearch snapshot
    local snapshot_status=$(curl -s "$ELASTICSEARCH_URL/_snapshot/backup_repo/_all" | jq -r '.snapshots[0].state // "MISSING"')
    if [ "$snapshot_status" != "SUCCESS" ]; then
        log "⚠️  Elasticsearch snapshot status: $snapshot_status"
    else
        log "✅ Elasticsearch snapshot verified"
    fi
    
    log "✅ Backup verification completed"
}

# Main backup function
main() {
    log "🔄 Starting backup process"
    
    # Create backup directory
    local backup_path=$(create_backup_dir)
    log "📁 Backup directory: $backup_path"
    
    # Perform backups
    backup_relay_files "$backup_path"
    backup_to_elasticsearch "$backup_path"
    create_encrypted_archive "$backup_path"
    
    # Verify and cleanup
    verify_backup "$backup_path"
    cleanup_old_backups
    
    log "✅ Backup process completed successfully"
}

# Run main function
main "$@"
