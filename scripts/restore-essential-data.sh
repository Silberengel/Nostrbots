#!/bin/bash

# Restore Script for Nostrbots Essential Data
# Restores config files and relay events from efficient backups

set -euo pipefail

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/backups}"
DATA_DIR="${DATA_DIR:-/data}"
WORKSPACE_DIR="${WORKSPACE_DIR:-/workspace}"

# Logging
log() {
    echo "[$(date -Iseconds)] $1"
}

# Show usage
usage() {
    cat << EOF
Usage: $0 <backup-file> [options]

Arguments:
    backup-file    Path to the backup archive (.tar.gz file)

Options:
    --dry-run      Show what would be restored without actually restoring
    --config-only  Restore only config files
    --events-only  Restore only relay events
    --help         Show this help message

Examples:
    $0 /backups/nostrbots-essential-20241225_120000.tar.gz
    $0 /backups/nostrbots-essential-20241225_120000.tar.gz --dry-run
    $0 /backups/nostrbots-essential-20241225_120000.tar.gz --config-only
EOF
}

# Parse command line arguments
DRY_RUN=false
CONFIG_ONLY=false
EVENTS_ONLY=false
BACKUP_FILE=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --config-only)
            CONFIG_ONLY=true
            shift
            ;;
        --events-only)
            EVENTS_ONLY=true
            shift
            ;;
        --help)
            usage
            exit 0
            ;;
        -*)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
        *)
            if [ -z "$BACKUP_FILE" ]; then
                BACKUP_FILE="$1"
            else
                echo "Multiple backup files specified"
                usage
                exit 1
            fi
            shift
            ;;
    esac
done

# Validate backup file
if [ -z "$BACKUP_FILE" ]; then
    echo "Error: No backup file specified"
    usage
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "Error: Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Check if it's a valid backup archive
if ! tar -tzf "$BACKUP_FILE" >/dev/null 2>&1; then
    echo "Error: Invalid or corrupted backup archive: $BACKUP_FILE"
    exit 1
fi

log "🔄 Starting restore process from: $BACKUP_FILE"

# Create temporary extraction directory
TEMP_DIR=$(mktemp -d)
trap "rm -rf $TEMP_DIR" EXIT

log "📁 Extracting backup to temporary directory: $TEMP_DIR"

if [ "$DRY_RUN" = true ]; then
    log "🔍 DRY RUN: Would extract backup archive"
    tar -tzf "$BACKUP_FILE" | head -20
    echo "... (showing first 20 files)"
    log "🔍 DRY RUN: Would restore the following components:"
else
    tar -xzf "$BACKUP_FILE" -C "$TEMP_DIR"
    log "✓ Backup extracted successfully"
fi

# Check backup manifest
if [ -f "$TEMP_DIR/manifest.json" ]; then
    log "📋 Backup manifest found:"
    if command -v jq >/dev/null 2>&1; then
        jq . "$TEMP_DIR/manifest.json"
    else
        cat "$TEMP_DIR/manifest.json"
    fi
else
    log "⚠  No backup manifest found, proceeding with standard restore"
fi

# Restore config files
restore_config_files() {
    log "📋 Restoring config files"
    
    if [ "$DRY_RUN" = true ]; then
        log "🔍 DRY RUN: Would restore Jenkins config from $TEMP_DIR/config/jenkins/"
        log "🔍 DRY RUN: Would restore Nostrbots config from $TEMP_DIR/config/nostrbots/"
        return 0
    fi
    
    # Restore Jenkins config
    if [ -d "$TEMP_DIR/config/jenkins" ]; then
        log "📋 Restoring Jenkins configuration"
        
        # Create Jenkins data directory if it doesn't exist
        mkdir -p "$DATA_DIR/jenkins"
        
        # Restore Jenkins config files
        if [ -f "$TEMP_DIR/config/jenkins/config.xml" ]; then
            cp "$TEMP_DIR/config/jenkins/config.xml" "$DATA_DIR/jenkins/"
            log "✓ Jenkins main config restored"
        fi
        
        # Restore job configs
        if [ -d "$TEMP_DIR/config/jenkins/jobs" ]; then
            cp -r "$TEMP_DIR/config/jenkins/jobs" "$DATA_DIR/jenkins/"
            log "✓ Jenkins job configs restored"
        fi
        
        # Restore user configs
        if [ -d "$TEMP_DIR/config/jenkins/users" ]; then
            cp -r "$TEMP_DIR/config/jenkins/users" "$DATA_DIR/jenkins/"
            log "✓ Jenkins user configs restored"
        fi
    fi
    
    # Restore Nostrbots project config
    if [ -d "$TEMP_DIR/config/nostrbots" ]; then
        log "📋 Restoring Nostrbots project configuration"
        
        # Create workspace directory if it doesn't exist
        mkdir -p "$WORKSPACE_DIR"
        
        # Restore config files
        if [ -d "$TEMP_DIR/config/nostrbots" ]; then
            cp -r "$TEMP_DIR/config/nostrbots"/* "$WORKSPACE_DIR/"
            log "✓ Nostrbots project config restored"
        fi
    fi
}

# Restore relay events
restore_relay_events() {
    log "📡 Restoring relay events"
    
    if [ "$DRY_RUN" = true ]; then
        log "🔍 DRY RUN: Would restore relay events from $TEMP_DIR/events/"
        if [ -f "$TEMP_DIR/events/event_count.txt" ]; then
            log "🔍 DRY RUN: Event count: $(cat "$TEMP_DIR/events/event_count.txt")"
        fi
        return 0
    fi
    
    if [ -d "$TEMP_DIR/events" ]; then
        # Create relay data directory if it doesn't exist
        mkdir -p "$DATA_DIR/orly"
        
        # Restore events database
        if [ -f "$TEMP_DIR/events/events.db" ]; then
            cp "$TEMP_DIR/events/events.db" "$DATA_DIR/orly/"
            log "✓ Relay events database restored"
            
            # Show event count if available
            if [ -f "$TEMP_DIR/events/event_count.txt" ]; then
                local event_count=$(cat "$TEMP_DIR/events/event_count.txt")
                log "📊 Restored $event_count events"
            fi
        fi
        
        # Restore relay config files
        for file in "$TEMP_DIR/events"/*.json "$TEMP_DIR/events"/*.conf; do
            if [ -f "$file" ]; then
                cp "$file" "$DATA_DIR/orly/"
                log "✓ Relay config restored: $(basename "$file")"
            fi
        done
    else
        log "⚠  No relay events found in backup"
    fi
}

# Restore Elasticsearch data
restore_elasticsearch_data() {
    log "🔍 Restoring Elasticsearch data"
    
    if [ "$DRY_RUN" = true ]; then
        log "🔍 DRY RUN: Would restore Elasticsearch data from $TEMP_DIR/elasticsearch/"
        return 0
    fi
    
    if [ -d "$TEMP_DIR/elasticsearch" ]; then
        log "📊 Elasticsearch data found in backup"
        log "ℹ  Note: Elasticsearch data restoration requires manual reindexing"
        log "ℹ  Use the exported JSON files to reindex data into Elasticsearch"
        
        # List available indices
        for file in "$TEMP_DIR/elasticsearch"/*_mapping.json; do
            if [ -f "$file" ]; then
                local index_name=$(basename "$file" _mapping.json)
                log "📋 Found index: $index_name"
            fi
        done
    else
        log "⚠  No Elasticsearch data found in backup"
    fi
}

# Main restore function
main() {
    log "🔄 Starting restore process"
    
    # Determine what to restore
    if [ "$CONFIG_ONLY" = true ]; then
        restore_config_files
    elif [ "$EVENTS_ONLY" = true ]; then
        restore_relay_events
    else
        # Restore everything
        restore_config_files
        restore_relay_events
        restore_elasticsearch_data
    fi
    
    if [ "$DRY_RUN" = true ]; then
        log "🔍 DRY RUN completed - no actual changes made"
    else
        log "✅ Restore process completed successfully"
        log "ℹ  You may need to restart services for changes to take effect"
    fi
}

# Run main function
main "$@"
