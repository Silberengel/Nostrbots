#!/bin/bash

# Backup Management Script for Nostrbots
# Provides easy commands for managing efficient backups

set -euo pipefail

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/opt/nostrbots/backups}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging
log() {
    echo -e "${BLUE}[$(date -Iseconds)]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date -Iseconds)]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date -Iseconds)]${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date -Iseconds)]${NC} $1"
}

# Show usage
usage() {
    cat << EOF
Nostrbots Backup Management

Usage: $0 <command> [options]

Commands:
    backup          Create a new backup
    list            List available backups
    restore <file>  Restore from backup file
    cleanup         Clean up old backups
    status          Show backup system status
    size            Show backup directory size
    help            Show this help message

Examples:
    $0 backup                    # Create new backup
    $0 list                      # List all backups
    $0 restore backup.tar.gz     # Restore from backup
    $0 cleanup                   # Clean old backups
    $0 status                    # Show system status

Backup Location: $BACKUP_DIR
EOF
}

# Create backup
create_backup() {
    log "🔄 Creating new backup..."
    
    if [ ! -d "$BACKUP_DIR" ]; then
        mkdir -p "$BACKUP_DIR"
        log "📁 Created backup directory: $BACKUP_DIR"
    fi
    
    # Run the backup script
    if [ -f "$SCRIPT_DIR/backup-essential-data.sh" ]; then
        BACKUP_DIR="$BACKUP_DIR" "$SCRIPT_DIR/backup-essential-data.sh"
        log_success "✅ Backup created successfully"
    else
        log_error "❌ Backup script not found: $SCRIPT_DIR/backup-essential-data.sh"
        exit 1
    fi
}

# List backups
list_backups() {
    log "📋 Available backups:"
    
    if [ ! -d "$BACKUP_DIR" ] || [ -z "$(ls -A "$BACKUP_DIR" 2>/dev/null)" ]; then
        log_warning "⚠  No backups found in $BACKUP_DIR"
        return 0
    fi
    
    echo
    printf "%-50s %-12s %-20s\n" "Backup File" "Size" "Date"
    printf "%-50s %-12s %-20s\n" "-----------" "----" "----"
    
    for backup in "$BACKUP_DIR"/nostrbots-essential-*.tar.gz; do
        if [ -f "$backup" ]; then
            local filename=$(basename "$backup")
            local size=$(du -h "$backup" | cut -f1)
            local date=$(stat -c %y "$backup" | cut -d' ' -f1)
            printf "%-50s %-12s %-20s\n" "$filename" "$size" "$date"
        fi
    done
    
    echo
    local total_size=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
    local backup_count=$(find "$BACKUP_DIR" -name "nostrbots-essential-*.tar.gz" | wc -l)
    log "📊 Total: $backup_count backups, $total_size"
}

# Restore from backup
restore_backup() {
    local backup_file="$1"
    
    if [ -z "$backup_file" ]; then
        log_error "❌ No backup file specified"
        echo "Usage: $0 restore <backup-file>"
        exit 1
    fi
    
    # Convert relative path to absolute if needed
    if [[ "$backup_file" != /* ]]; then
        backup_file="$BACKUP_DIR/$backup_file"
    fi
    
    if [ ! -f "$backup_file" ]; then
        log_error "❌ Backup file not found: $backup_file"
        exit 1
    fi
    
    log "🔄 Restoring from backup: $backup_file"
    
    # Confirm restore
    echo -n "Are you sure you want to restore from this backup? (y/N): "
    read -r confirm
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        log "❌ Restore cancelled"
        exit 0
    fi
    
    # Run the restore script
    if [ -f "$SCRIPT_DIR/restore-essential-data.sh" ]; then
        "$SCRIPT_DIR/restore-essential-data.sh" "$backup_file"
        log_success "✅ Restore completed successfully"
    else
        log_error "❌ Restore script not found: $SCRIPT_DIR/restore-essential-data.sh"
        exit 1
    fi
}

# Cleanup old backups
cleanup_backups() {
    log "🧹 Cleaning up old backups..."
    
    if [ ! -d "$BACKUP_DIR" ]; then
        log_warning "⚠  Backup directory does not exist: $BACKUP_DIR"
        return 0
    fi
    
    # Show current usage before cleanup
    local before_size=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
    local before_count=$(find "$BACKUP_DIR" -name "nostrbots-essential-*.tar.gz" | wc -l)
    log "📊 Before cleanup: $before_count backups, $before_size"
    
    # Remove backups older than 30 days
    find "$BACKUP_DIR" -name "nostrbots-essential-*.tar.gz" -mtime +30 -delete 2>/dev/null || true
    
    # Show usage after cleanup
    local after_size=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
    local after_count=$(find "$BACKUP_DIR" -name "nostrbots-essential-*.tar.gz" | wc -l)
    log "📊 After cleanup: $after_count backups, $after_size"
    
    local removed_count=$((before_count - after_count))
    if [ "$removed_count" -gt 0 ]; then
        log_success "✅ Removed $removed_count old backups"
    else
        log "ℹ  No old backups to remove"
    fi
}

# Show backup system status
show_status() {
    log "📊 Backup System Status"
    echo
    
    # Check backup directory
    if [ -d "$BACKUP_DIR" ]; then
        local total_size=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
        local backup_count=$(find "$BACKUP_DIR" -name "nostrbots-essential-*.tar.gz" | wc -l)
        log_success "✅ Backup directory exists: $BACKUP_DIR"
        log "📊 Current usage: $backup_count backups, $total_size"
    else
        log_warning "⚠  Backup directory does not exist: $BACKUP_DIR"
    fi
    
    # Check backup script
    if [ -f "$SCRIPT_DIR/backup-essential-data.sh" ]; then
        log_success "✅ Backup script available"
    else
        log_error "❌ Backup script missing: $SCRIPT_DIR/backup-essential-data.sh"
    fi
    
    # Check restore script
    if [ -f "$SCRIPT_DIR/restore-essential-data.sh" ]; then
        log_success "✅ Restore script available"
    else
        log_error "❌ Restore script missing: $SCRIPT_DIR/restore-essential-data.sh"
    fi
    
    # Check systemd service
    if systemctl is-enabled nostrbots-backup.timer >/dev/null 2>&1; then
        log_success "✅ Backup timer is enabled"
        if systemctl is-active nostrbots-backup.timer >/dev/null 2>&1; then
            log_success "✅ Backup timer is active"
        else
            log_warning "⚠  Backup timer is not active"
        fi
    else
        log_warning "⚠  Backup timer is not enabled"
    fi
    
    # Show last backup
    local last_backup=$(find "$BACKUP_DIR" -name "nostrbots-essential-*.tar.gz" -printf '%T@ %p\n' 2>/dev/null | sort -n | tail -1 | cut -d' ' -f2-)
    if [ -n "$last_backup" ]; then
        local last_date=$(stat -c %y "$last_backup" | cut -d' ' -f1,2 | cut -d'.' -f1)
        log "📅 Last backup: $(basename "$last_backup") ($last_date)"
    else
        log_warning "⚠  No backups found"
    fi
}

# Show backup directory size
show_size() {
    log "📊 Backup Directory Size Analysis"
    echo
    
    if [ ! -d "$BACKUP_DIR" ]; then
        log_warning "⚠  Backup directory does not exist: $BACKUP_DIR"
        return 0
    fi
    
    # Overall size
    local total_size=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
    log "📁 Total backup directory size: $total_size"
    
    # Individual backup sizes
    echo
    log "📋 Individual backup sizes:"
    printf "%-50s %-12s\n" "Backup File" "Size"
    printf "%-50s %-12s\n" "-----------" "----"
    
    for backup in "$BACKUP_DIR"/nostrbots-essential-*.tar.gz; do
        if [ -f "$backup" ]; then
            local filename=$(basename "$backup")
            local size=$(du -h "$backup" | cut -f1)
            printf "%-50s %-12s\n" "$filename" "$size"
        fi
    done
    
    # Disk usage
    echo
    log "💾 Disk usage:"
    df -h "$BACKUP_DIR" 2>/dev/null || log_warning "⚠  Could not get disk usage"
}

# Main function
main() {
    case "${1:-help}" in
        backup)
            create_backup
            ;;
        list)
            list_backups
            ;;
        restore)
            restore_backup "${2:-}"
            ;;
        cleanup)
            cleanup_backups
            ;;
        status)
            show_status
            ;;
        size)
            show_size
            ;;
        help|--help|-h)
            usage
            ;;
        *)
            log_error "❌ Unknown command: ${1:-}"
            echo
            usage
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
