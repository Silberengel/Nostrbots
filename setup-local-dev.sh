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
    
    # Decrypt the key for immediate use
    local decrypted_key=$(php decrypt-key.php 2>/dev/null)
    
    # Create .env file
    cat > .env << EOF
# Nostrbots Local Development Configuration
NOSTR_BOT_KEY_ENCRYPTED=$encrypted_key
NOSTR_BOT_KEY=$decrypted_key
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
    
    # Check if Docker is in swarm mode and leave it for local development
    if docker info --format '{{.Swarm.LocalNodeState}}' 2>/dev/null | grep -q "active"; then
        log "Docker is in swarm mode. Leaving swarm mode for local development..."
        if docker swarm leave --force 2>/dev/null; then
            log_success "Left Docker swarm mode"
        else
            log_warn "Could not leave swarm mode, but continuing with docker-compose"
        fi
    fi
    
    # Check if already running
    if docker ps --format "table {{.Names}}" | grep -q "orly-relay"; then
        log "Orly relay is already running"
        return 0
    fi
    
    # Create data directory if it doesn't exist
    mkdir -p data/orly
    
    # Start the relay using docker-compose
    if docker-compose up -d orly-relay; then
        log "Waiting for Orly relay to start..."
        sleep 10
        
        # Check if it's running
        if docker ps --format "table {{.Names}}" | grep -q "orly-relay"; then
            log_success "Orly relay started successfully"
        else
            log_warn "Orly relay may not be ready yet. You can check with: docker-compose logs orly-relay"
        fi
    else
        log_error "Failed to start Orly relay. Check Docker and try again."
        return 1
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
        
        // Load .env file
        if (file_exists('.env')) {
            \$lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach (\$lines as \$line) {
                if (strpos(\$line, '=') !== false && !str_starts_with(\$line, '#')) {
                    list(\$key, \$value) = explode('=', \$line, 2);
                    putenv(\$key . '=' . \$value);
                }
            }
        }
        
        \$encryptedKey = getenv('NOSTR_BOT_KEY_ENCRYPTED');
        if (empty(\$encryptedKey)) {
            echo 'Encrypted key not found';
            exit(1);
        }
        
        // Test decryption using the decrypt-key.php script
        \$decryptedKey = shell_exec('php decrypt-key.php 2>/dev/null');
        if (empty(\$decryptedKey)) {
            echo 'Key decryption failed';
            exit(1);
        }
        
        // Validate the decrypted key format
        if (!ctype_xdigit(trim(\$decryptedKey)) || strlen(trim(\$decryptedKey)) !== 64) {
            echo 'Invalid decrypted key format';
            exit(1);
        }
        
        echo 'Key decryption successful';
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

# Cleanup function
cleanup() {
    log "Cleaning up local development environment"
    docker-compose down 2>/dev/null || true
    log_success "Cleanup completed"
}

# Main function
main() {
    log "🚀 Starting Nostrbots local development setup"
    
    check_not_root
    check_dependencies
    install_php_dependencies
    setup_env_file "$@"
    
    if start_orly_relay; then
        test_setup
        
        echo ""
        log_success "🎉 Local development setup completed!"
        echo ""
        echo "📋 What's been set up:"
        echo "======================"
        echo "✅ PHP dependencies installed"
        echo "✅ Nostr keys generated and saved to .env"
        echo "✅ NOSTR_BOT_KEY environment variable configured"
        echo "✅ CUSTOM_PRIVATE_KEY support enabled (if set)"
        echo "✅ Docker swarm mode handled for local development"
        echo "✅ Orly relay running on ws://localhost:3334"
        echo "✅ Local development environment ready"
        echo ""
        echo "🚀 Next steps:"
        echo "=============="
        echo "1. Test the setup: php run-tests.php"
        echo "2. Run Hello World bot: php bots/hello-world/generate-content.php"
        echo "3. Dry run of publishing content: php nostrbots.php bots/hello-world/output/hello-world-latest.adoc --dry-run"
        echo "4. Publish content: php nostrbots.php bots/hello-world/output/hello-world-latest.adoc"
        echo "5. Publish a kind 1 note: php write-note.php '🌍 Hello World! Published from Nostrbots'"
        echo ""
        echo "💡 To use your own key: export CUSTOM_PRIVATE_KEY='your_key_here'"
        echo "🛑 To stop the relay: docker-compose down"
        echo ""
        
        # Ask if user wants to run hello world bot
        echo -n "Would you like to run the Hello World bot test now? (y/N): "
        read -r REPLY
        if [[ ! $REPLY =~ ^[Nn]$ ]]; then
            run_hello_world_bot
        fi
        
        # Source the .env file for the current shell session
        echo ""
        echo "🔧 Automatically loading environment variables..."
        if [ -f ".env" ]; then
            set -a  # automatically export all variables
            source .env
            set +a  # turn off automatic export
            echo "✅ Environment variables loaded automatically!"
            echo "   You can now use nostrbots.php and write-note.php directly"
            echo ""
            echo "💡 For future shell sessions, run: source .env"
        else
            echo "⚠️  .env file not found - please run 'source .env' manually"
        fi
    else
        log_error "Setup failed. Please check the errors above and try again."
        return 1
    fi
}

# Initialize and run
init_common_setup "$@"
main "$@"
