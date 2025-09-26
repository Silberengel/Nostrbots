#!/bin/bash

# Nostrbots Cleanup Script
# Removes all containers, volumes, and temporary files

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Source shared security utilities
source "$SCRIPT_DIR/security-utils.sh"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${YELLOW}ⓘ$1${NC}"
}

log_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

log_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Security cleanup function is now in security-utils.sh

echo "🧹 Nostrbots Cleanup"
echo "==================="
echo ""
echo "This script will:"
echo "• Stop and remove all containers"
echo "• Remove all volumes"
echo "• Clean up temporary files"
echo "• Clear sensitive environment variables"
echo "• Clean bash history of private key commands"
echo "• Restart shell session to clear current session history"
echo ""
echo "With --all flag, it will also:"
echo "• Remove generated content"
echo "• Perform comprehensive Docker cleanup (removes all unused images, containers, networks, and build cache)"
echo ""
echo "⚠  Note: The script will restart your shell session at the end to ensure"
echo "   complete history clearing. You'll be in a fresh shell afterward."
echo ""

# Stop and remove containers
log_info "Stopping containers..."
docker compose -f docker-compose.jenkins.yml down 2>/dev/null || true
docker compose -f docker-compose.yml down 2>/dev/null || true

# Stop and remove any remaining containers
log_info "Stopping all remaining containers..."
docker stop $(docker ps -aq) 2>/dev/null || true
docker rm $(docker ps -aq) 2>/dev/null || true

# Remove volumes
log_info "Removing volumes..."
docker volume rm nostrbots_jenkins_home 2>/dev/null || true
docker volume rm nostrbots_nostrbots_keys 2>/dev/null || true

# Remove all unused volumes
log_info "Removing all unused volumes..."
docker volume prune -f 2>/dev/null || true

# Remove temporary files
log_info "Cleaning up temporary files..."
rm -f /tmp/nostrbots.env 2>/dev/null || true

# Remove generated content (optional)
if [ "${1:-}" = "--all" ]; then
    log_info "Removing generated content..."
    rm -rf bots/*/output/*.adoc 2>/dev/null || true
    log_success "All generated content removed"
    
    # Comprehensive Docker cleanup
    log_info "Performing comprehensive Docker cleanup..."
    docker system prune -af 2>/dev/null || true
    log_success "Docker system cleaned"
fi

# Always perform security cleanup
secure_cleanup_always

log_success "Cleanup completed!"
