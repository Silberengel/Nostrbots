#!/bin/bash

# Local Development Setup Script
# Sets up a minimal local environment for testing bots

set -euo pipefail

# Source common setup functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/setup-common.sh"

# Script-specific configuration
COMPOSE_FILE="docker-compose.yml"
SERVICE_NAME="nostrbots-local"

# Show help
show_help() {
    echo "Nostrbots Local Development Setup"
    echo "================================="
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    show_common_help
}

# Check if NOT running as root (for local dev)
check_not_root() {
    if [ "$EUID" -eq 0 ]; then
        warn "Running as root. Consider running as regular user for development."
    fi
}

# Setup .env file for local development
setup_env_file() {
    log "Setting up .env file for local development"
    
    # Get keys from common setup
    local key_result
    key_result=$(setup_nostr_keys "$@")
    
    # Extract the keys
    local encrypted_key=$(echo "$key_result" | grep "ENCRYPTED_KEY=" | cut -d'=' -f2)
    local npub=$(echo "$key_result" | grep "NPUB=" | cut -d'=' -f2)
    
    # Create .env file
    cat > .env << EOF
# Nostrbots Local Development Configuration
NOSTR_BOT_KEY_ENCRYPTED=$encrypted_key
NOSTR_BOT_NPUB=$npub

# Local development settings
NOSTR_RELAY_URL=ws://localhost:3334
NOSTR_BOT_DEBUG=true
EOF
    
    log_success "Keys generated and saved to .env"
    log "🔑 Encrypted Private Key: ${encrypted_key:0:20}..."
    log "🔑 Public Key (npub): $npub"
}

# Start Orly relay for local development
start_orly_relay() {
    log "Starting Orly relay for local development"
    
    # Check if already running
    if docker ps --format "table {{.Names}}" | grep -q "orly-relay"; then
        log "Orly relay is already running"
        return 0
    fi
    
    # Start the relay
    docker run -d \
        --name orly-relay \
        -p 3334:3334 \
        -v "$(pwd)/data/orly:/app/data" \
        ghcr.io/silberengel/orly:latest
    
    log "Waiting for Orly relay to start..."
    sleep 10
    
    # Check if it's running
    if docker ps --format "table {{.Names}}" | grep -q "orly-relay"; then
        log_success "Orly relay started successfully"
    else
        log_warn "Orly relay may not be ready yet. You can check with: docker logs orly-relay"
    fi
}

# Test local development setup
test_setup() {
    log "Testing local development setup"
    
    # Check .env file
    if [ -f ".env" ]; then
        log_success ".env file exists"
    else
        log_error ".env file not found"
        return 1
    fi
    
    # Test key decryption
    if php -r "
        require_once 'vendor/autoload.php';
        \$keyManager = new Nostrbots\Utils\KeyManager();
        \$key = \$keyManager->getPrivateKey('NOSTR_BOT_KEY');
        if (\$key) {
            echo 'Key decryption successful';
        } else {
            echo 'Key decryption failed';
            exit(1);
        }
    " 2>/dev/null; then
        log_success "Key decryption successful"
    else
        log_error "Key decryption failed"
        return 1
    fi
    
    log_success "Local development setup is working!"
}

# Run hello world bot test
run_hello_world_bot() {
    log "Running Hello World bot test"
    
    if [ -f "bots/hello-world/generate-content.php" ]; then
        php bots/hello-world/generate-content.php
        log_success "Hello World bot test completed"
    else
        log_warn "Hello World bot not found, skipping test"
    fi
}

# Main function
main() {
    log "🚀 Starting Nostrbots local development setup"
    
    check_not_root
    check_dependencies
    install_php_dependencies
    setup_env_file "$@"
    start_orly_relay
    test_setup
    
    echo ""
    log_success "🎉 Local development setup completed!"
    echo ""
    echo "📋 What's been set up:"
    echo "======================"
    echo "✅ PHP dependencies installed"
    echo "✅ Nostr keys generated and saved to .env"
    echo "✅ Orly relay running on ws://localhost:3334"
    echo "✅ Local development environment ready"
    echo ""
    echo "🚀 Next steps:"
    echo "=============="
    echo "1. Test the setup: php run-tests.php"
    echo "2. Run Hello World bot: php bots/hello-world/generate-content.php"
    echo "3. Publish content: php nostrbots.php bots/hello-world/output/hello-world.adoc --dry-run"
    echo ""
    
    # Ask if user wants to run hello world bot
    echo -n "Would you like to run the Hello World bot test now? (y/N): "
    read -r REPLY
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        run_hello_world_bot
    fi
}

# Initialize and run
init_common_setup "$@"
main "$@"
