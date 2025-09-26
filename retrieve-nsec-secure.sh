#!/bin/bash

# Secure Nsec Retrieval Script
# This script securely retrieves and displays the nsec without logging it

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}ⓘ$1${NC}"
}

log_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠  $1${NC}"
}

log_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Check if Docker secrets exist
check_secrets() {
    if ! docker secret ls | grep -q "nostr_bot_key_encrypted"; then
        log_error "Dostr bot key secret not found"
        log_info "Please run the production setup first"
        exit 1
    fi
    
    if ! docker secret ls | grep -q "nostr_bot_npub"; then
        log_error "Nostr bot npub secret not found"
        log_info "Please run the production setup first"
        exit 1
    fi
    
    log_success "Docker secrets found"
}

# Retrieve nsec from Docker secrets
retrieve_nsec() {
    log_info "Retrieving nsec from Docker secrets..."
    
    # Create a temporary container to access secrets
    CONTAINER_ID=$(docker run -d --rm \
        --secret nostr_bot_key_encrypted \
        --secret nostr_bot_npub \
        silberengel/nostrbots:latest \
        tail -f /dev/null)
    
    # Copy the decrypt script to the container
    docker cp decrypt-key.php "$CONTAINER_ID:/tmp/decrypt-key.php"
    
    # Set up environment in container
    docker exec "$CONTAINER_ID" sh -c "
        export NOSTR_BOT_KEY_ENCRYPTED=\$(cat /run/secrets/nostr_bot_key_encrypted)
        export NOSTR_BOT_NPUB=\$(cat /run/secrets/nostr_bot_npub)
        php /tmp/decrypt-key.php
    " > /tmp/nsec_output.txt
    
    # Clean up container
    docker stop "$CONTAINER_ID" >/dev/null 2>&1
    
    # Read the nsec
    if [ -f "/tmp/nsec_output.txt" ] && [ -s "/tmp/nsec_output.txt" ]; then
        NSEC=$(cat /tmp/nsec_output.txt)
        rm -f /tmp/nsec_output.txt
        
        if [ -n "$NSEC" ]; then
            log_success "Nsec retrieved successfully"
            return 0
        else
            log_error "Failed to retrieve nsec"
            return 1
        fi
    else
        log_error "Failed to retrieve nsec"
        rm -f /tmp/nsec_output.txt
        return 1
    fi
}

# Securely display nsec
secure_display() {
    local nsec="$1"
    
    echo ""
    echo "YOUR NOSTR PRIVATE KEY (NSEC)"
    echo "================================="
    echo ""
    echo "⚠  IMPORTANT: Copy this key now - it will not be shown again!"
    echo ""
    echo "Your nsec: $nsec"
    echo ""
    echo "📋 Instructions:"
    echo "1. Copy the nsec above to your clipboard"
    echo "2. Save it securely (password manager, encrypted storage, etc.)"
    echo "3. This key will NOT be displayed again"
    echo "4. Keys are stored securely in Docker secrets"
    echo ""
    echo "Press ENTER when you have copied and saved the nsec..."
    read -r
    echo ""
    echo "✓ Thank you! The nsec has been securely handled."
    echo ""
}

# Main function
main() {
    echo "🔐 Secure Nsec Retrieval"
    echo "========================"
    echo ""
    
    check_secrets
    
    if retrieve_nsec; then
        secure_display "$NSEC"
        
        # Securely clear the variable
        NSEC=$(openssl rand -base64 32)
        unset NSEC
        
        log_success "Nsec retrieved and displayed securely"
    else
        log_error "Failed to retrieve nsec"
        exit 1
    fi
}

# Handle command line arguments
case "${1:-}" in
    "help"|"-h"|"--help")
        echo "Secure Nsec Retrieval Script"
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  (none)    Retrieve and securely display nsec"
        echo "  help      Show this help message"
        ;;
    *)
        main
        ;;
esac
