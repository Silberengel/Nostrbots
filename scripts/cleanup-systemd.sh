#!/bin/bash

# Systemd Services Cleanup Script for Nostrbots
# Removes all systemd services created by the setup script

set -euo pipefail

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
Nostrbots Systemd Services Cleanup

Usage: $0 [options]

Options:
    --dry-run      Show what would be cleaned up without actually doing it
    --force        Skip confirmation prompts
    --help         Show this help message

This script removes all systemd services created by the Nostrbots setup:
- nostrbots.service
- nostrbots-backup.service  
- nostrbots-backup.timer

Examples:
    $0                    # Clean up with confirmation
    $0 --dry-run          # Show what would be cleaned up
    $0 --force            # Clean up without confirmation
EOF
}

# Parse command line arguments
DRY_RUN=false
FORCE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    log_error "❌ This script must be run as root (use sudo)"
    exit 1
fi

# Systemd services to clean up
SERVICES=("nostrbots.service" "nostrbots-backup.service" "nostrbots-backup.timer")
SYSTEMD_DIR="/etc/systemd/system"

# Show what will be cleaned up
show_cleanup_plan() {
    log "📋 Cleanup Plan:"
    echo
    
    local found_services=()
    local found_files=()
    
    # Check for active services
    for service in "${SERVICES[@]}"; do
        if systemctl is-active "$service" >/dev/null 2>&1; then
            found_services+=("$service (active)")
        elif systemctl is-enabled "$service" >/dev/null 2>&1; then
            found_services+=("$service (enabled)")
        fi
        
        local service_file="$SYSTEMD_DIR/$service"
        if [ -f "$service_file" ]; then
            found_files+=("$service_file")
        fi
    done
    
    if [ ${#found_services[@]} -eq 0 ] && [ ${#found_files[@]} -eq 0 ]; then
        log "ℹ  No Nostrbots systemd services found to clean up"
        return 1
    fi
    
    if [ ${#found_services[@]} -gt 0 ]; then
        log "🔧 Services to stop/disable:"
        for service in "${found_services[@]}"; do
            echo "  • $service"
        done
        echo
    fi
    
    if [ ${#found_files[@]} -gt 0 ]; then
        log "🗑️  Service files to remove:"
        for file in "${found_files[@]}"; do
            echo "  • $file"
        done
        echo
    fi
    
    return 0
}

# Stop and disable services
stop_services() {
    log "⏹️  Stopping and disabling services..."
    
    for service in "${SERVICES[@]}"; do
        if systemctl is-active "$service" >/dev/null 2>&1; then
            if [ "$DRY_RUN" = true ]; then
                log "🔍 DRY RUN: Would stop $service"
            else
                log "⏹️  Stopping $service"
                systemctl stop "$service" 2>/dev/null || log_warning "⚠  Failed to stop $service"
            fi
        fi
        
        if systemctl is-enabled "$service" >/dev/null 2>&1; then
            if [ "$DRY_RUN" = true ]; then
                log "🔍 DRY RUN: Would disable $service"
            else
                log "🚫 Disabling $service"
                systemctl disable "$service" 2>/dev/null || log_warning "⚠  Failed to disable $service"
            fi
        fi
    done
}

# Remove service files
remove_service_files() {
    log "🗑️  Removing service files..."
    
    for service in "${SERVICES[@]}"; do
        local service_file="$SYSTEMD_DIR/$service"
        if [ -f "$service_file" ]; then
            if [ "$DRY_RUN" = true ]; then
                log "🔍 DRY RUN: Would remove $service_file"
            else
                log "🗑️  Removing $service_file"
                rm -f "$service_file" 2>/dev/null || log_warning "⚠  Failed to remove $service_file"
            fi
        fi
    done
}

# Reload systemd daemon
reload_systemd() {
    if [ "$DRY_RUN" = true ]; then
        log "🔍 DRY RUN: Would reload systemd daemon"
    else
        log "🔄 Reloading systemd daemon"
        systemctl daemon-reload 2>/dev/null || log_warning "⚠  Failed to reload systemd daemon"
    fi
}

# Main cleanup function
main() {
    log "🧹 Nostrbots Systemd Services Cleanup"
    echo
    
    # Show what will be cleaned up
    if ! show_cleanup_plan; then
        log_success "✅ Nothing to clean up"
        exit 0
    fi
    
    # Confirmation (unless --force or --dry-run)
    if [ "$DRY_RUN" = false ] && [ "$FORCE" = false ]; then
        echo
        echo -n "Are you sure you want to clean up these systemd services? (y/N): "
        read -r confirm
        if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
            log "❌ Cleanup cancelled"
            exit 0
        fi
    fi
    
    echo
    
    # Perform cleanup
    stop_services
    remove_service_files
    reload_systemd
    
    if [ "$DRY_RUN" = true ]; then
        log "🔍 DRY RUN completed - no actual changes made"
    else
        log_success "✅ Systemd services cleanup completed"
        log "ℹ  Note: This only removes systemd services, not Docker resources or data"
        log "ℹ  Use './scripts/manage-backups.sh cleanup-all' for complete cleanup"
    fi
}

# Run main function
main "$@"
