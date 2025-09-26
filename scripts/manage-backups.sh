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
    cleanup         Clean up old backups only
    cleanup-all     Complete cleanup (backups + services + Docker + data)
    status          Show backup system status
    size            Show backup directory size
    help            Show this help message

Examples:
    $0 backup                    # Create new backup
    $0 list                      # List all backups
    $0 restore backup.tar.gz     # Restore from backup
    $0 cleanup                   # Clean old backups only
    $0 cleanup-all               # Complete cleanup (removes everything!)
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

# Comprehensive cleanup including systemd services
cleanup_all() {
    log "🧹 Starting comprehensive cleanup (backups + systemd services + Docker resources)..."
    
    # Warning about data loss
    log_warning "⚠  WARNING: This will remove ALL Nostrbots data including:"
    log_warning "   • All Docker containers (Orly, Jenkins, Elasticsearch, etc.)"
    log_warning "   • All Docker volumes and data"
    log_warning "   • All backup files"
    log_warning "   • All systemd services"
    log_warning "   • All project data and logs"
    echo
    echo -n "Are you sure you want to proceed? This action cannot be undone! (type 'DELETE ALL' to confirm): "
    read -r confirm
    if [ "$confirm" != "DELETE ALL" ]; then
        log "❌ Comprehensive cleanup cancelled"
        exit 0
    fi
    
    echo
    log "🚨 Proceeding with complete cleanup..."
    
    # First, clean up old backups
    cleanup_backups
    
    echo
    log "🔧 Cleaning up systemd services..."
    
    # Stop and disable systemd services
    local services=("nostrbots.service" "nostrbots-backup.service" "nostrbots-backup.timer")
    
    for service in "${services[@]}"; do
        if systemctl is-active "$service" >/dev/null 2>&1; then
            log "⏹️  Stopping $service"
            systemctl stop "$service" 2>/dev/null || log_warning "⚠  Failed to stop $service"
        fi
        
        if systemctl is-enabled "$service" >/dev/null 2>&1; then
            log "🚫 Disabling $service"
            systemctl disable "$service" 2>/dev/null || log_warning "⚠  Failed to disable $service"
        fi
    done
    
    # Remove systemd service files
    local systemd_dir="/etc/systemd/system"
    for service in "${services[@]}"; do
        local service_file="$systemd_dir/$service"
        if [ -f "$service_file" ]; then
            log "🗑️  Removing $service_file"
            rm -f "$service_file" 2>/dev/null || log_warning "⚠  Failed to remove $service_file"
        fi
    done
    
    # Reload systemd daemon
    log "🔄 Reloading systemd daemon"
    systemctl daemon-reload 2>/dev/null || log_warning "⚠  Failed to reload systemd daemon"
    
    # Clean up Docker stack and secrets
    log "🐳 Cleaning up Docker resources..."
    
    # Stop and remove Docker stack
    if docker stack ls | grep -q "nostrbots"; then
        log "⏹️  Removing Docker stack 'nostrbots'"
        docker stack rm nostrbots 2>/dev/null || log_warning "⚠  Failed to remove Docker stack"
        
        # Wait for stack removal to complete
        log "⏳ Waiting for stack removal to complete..."
        sleep 10
    fi
    
    # Remove Docker secrets
    local secrets=("nostr_bot_key_encrypted" "nostr_bot_npub")
    for secret in "${secrets[@]}"; do
        if docker secret ls | grep -q "$secret"; then
            log "🗑️  Removing Docker secret '$secret'"
            docker secret rm "$secret" 2>/dev/null || log_warning "⚠  Failed to remove secret '$secret'"
        fi
    done
    
    # Remove all Nostrbots containers (including Orly, Jenkins, etc.)
    log "🗑️  Removing all Nostrbots containers..."
    local all_containers=$(docker ps -aq --filter "name=nostrbots" 2>/dev/null)
    if [ -n "$all_containers" ]; then
        echo "$all_containers" | xargs docker rm -f 2>/dev/null || log_warning "⚠  Failed to remove some containers"
    fi
    
    # Remove containers by service names
    local service_containers=("orly-relay" "jenkins" "backup-agent" "nostrbots-agent" "elasticsearch" "kibana" "logstash" "event-indexer")
    for service in "${service_containers[@]}"; do
        local containers=$(docker ps -aq --filter "name=$service" 2>/dev/null)
        if [ -n "$containers" ]; then
            log "🗑️  Removing $service containers"
            echo "$containers" | xargs docker rm -f 2>/dev/null || log_warning "⚠  Failed to remove $service containers"
        fi
    done
    
    # Remove Docker volumes (this will remove all data!)
    log "🗑️  Removing Docker volumes..."
    local volumes=("nostrbots_orly_data" "nostrbots_jenkins_data" "nostrbots_backup_data" "nostrbots_elasticsearch_data")
    for volume in "${volumes[@]}"; do
        if docker volume ls | grep -q "$volume"; then
            log "🗑️  Removing volume '$volume'"
            docker volume rm "$volume" 2>/dev/null || log_warning "⚠  Failed to remove volume '$volume'"
        fi
    done
    
    # Clean up Docker networks
    if docker network ls | grep -q "nostrbots"; then
        log "🗑️  Removing Docker networks"
        docker network ls --filter "name=nostrbots" -q | xargs docker network rm 2>/dev/null || log_warning "⚠  Failed to remove some networks"
    fi
    
    # Clean up any remaining images (optional - be careful with this)
    log "🗑️  Removing Nostrbots Docker images..."
    local images=$(docker images --filter "reference=*nostrbots*" -q 2>/dev/null)
    if [ -n "$images" ]; then
        echo "$images" | xargs docker rmi -f 2>/dev/null || log_warning "⚠  Failed to remove some images"
    fi
    
    # Clean up silberengel images
    local silberengel_images=$(docker images --filter "reference=silberengel/*" -q 2>/dev/null)
    if [ -n "$silberengel_images" ]; then
        log "🗑️  Removing Silberengel images"
        echo "$silberengel_images" | xargs docker rmi -f 2>/dev/null || log_warning "⚠  Failed to remove some Silberengel images"
    fi
    
    # Clean up backup logs
    if [ -f "/var/log/nostrbots/backup.log" ]; then
        log "🗑️  Cleaning up backup logs"
        rm -f /var/log/nostrbots/backup.log 2>/dev/null || log_warning "⚠  Failed to remove backup logs"
    fi
    
    # Clean up project data directory (optional - this removes ALL data!)
    if [ -d "/opt/nostrbots/data" ]; then
        log "🗑️  Removing project data directory (/opt/nostrbots/data)"
        rm -rf /opt/nostrbots/data 2>/dev/null || log_warning "⚠  Failed to remove data directory"
    fi
    
    # Clean up project backups directory
    if [ -d "/opt/nostrbots/backups" ]; then
        log "🗑️  Removing project backups directory (/opt/nostrbots/backups)"
        rm -rf /opt/nostrbots/backups 2>/dev/null || log_warning "⚠  Failed to remove backups directory"
    fi
    
    # Clean up project logs directory
    if [ -d "/var/log/nostrbots" ]; then
        log "🗑️  Removing project logs directory (/var/log/nostrbots)"
        rm -rf /var/log/nostrbots 2>/dev/null || log_warning "⚠  Failed to remove logs directory"
    fi
    
    log_success "✅ Comprehensive cleanup completed"
    log "ℹ  All Nostrbots services, containers, volumes, and data have been removed"
    log "ℹ  Project files in /opt/nostrbots/ are preserved for reinstallation"
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
        cleanup-all)
            cleanup_all
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
