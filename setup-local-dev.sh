#!/bin/bash

# Local Development Setup Script
# Sets up a minimal local environment for testing bots

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging
log() {
    echo -e "${GREEN}[$(date -Iseconds)]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[$(date -Iseconds)] WARNING:${NC} $1"
}

error() {
    echo -e "${RED}[$(date -Iseconds)] ERROR:${NC} $1"
}

info() {
    echo -e "${BLUE}[$(date -Iseconds)] INFO:${NC} $1"
}

# Check if running as root
check_root() {
    if [ "$EUID" -eq 0 ]; then
        warn "Running as root. Consider running as regular user for development."
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

# Check dependencies
check_dependencies() {
    log "Checking dependencies"
    
    # Check PHP
    if ! command -v php >/dev/null 2>&1; then
        error "PHP is not installed. Please install PHP 8.0+"
        exit 1
    fi
    
    # Check Composer
    if ! command -v composer >/dev/null 2>&1; then
        error "Composer is not installed. Please install Composer"
        exit 1
    fi
    
    # Check Docker
    if ! command -v docker >/dev/null 2>&1; then
        error "Docker is not installed. Please install Docker"
        exit 1
    fi
    
    log "✅ All dependencies are available"
}

# Install PHP dependencies
install_php_dependencies() {
    log "Installing PHP dependencies"
    
    if [ ! -f "composer.json" ]; then
        error "composer.json not found. Are you in the right directory?"
        exit 1
    fi
    
    composer install --no-dev --optimize-autoloader
    
    log "✅ PHP dependencies installed"
}

# Generate keys for local development
generate_local_keys() {
    log "Generating keys for local development"
    
    # Generate keys
    local key_output
    key_output=$(php generate-key.php 2>/dev/null)
    
    if [ $? -ne 0 ]; then
        error "Failed to generate keys"
        exit 1
    fi
    
    # Extract keys from output
    local encrypted_key
    local npub
    encrypted_key=$(echo "$key_output" | grep "NOSTR_BOT_KEY_ENCRYPTED=" | cut -d'=' -f2- | tr -d '"')
    npub=$(echo "$key_output" | grep "NOSTR_BOT_NPUB=" | cut -d'=' -f2- | tr -d '"')
    
    if [ -z "$encrypted_key" ] || [ -z "$npub" ]; then
        error "Failed to extract keys from generation output"
        exit 1
    fi
    
    # Create .env file for local development
    cat > .env << EOF
# Nostr Bot Configuration (Local Development)
NOSTR_BOT_KEY_ENCRYPTED=$encrypted_key
NOSTR_BOT_NPUB=$npub

# Local Development Settings
NOSTR_RELAYS=ws://localhost:3334
EOF
    
    log "✅ Keys generated and saved to .env"
    log "🔑 Encrypted Private Key: ${encrypted_key:0:10}..."
    log "🔑 Public Key (npub): $npub"
    
    # Secure cleanup
    unset key_output encrypted_key npub
}

# Start Orly relay
start_orly_relay() {
    log "Starting Orly relay for local development"
    
    # Check if Orly is already running
    if docker ps | grep -q "orly-relay"; then
        log "Orly relay is already running"
        return 0
    fi
    
    # Start Orly relay
    docker run -d \
        --name orly-relay \
        --restart unless-stopped \
        -p 127.0.0.1:3334:7777 \
        -v "$(pwd)/data/orly:/data" \
        silberengel/next-orly:latest
    
    # Wait for Orly to start
    log "Waiting for Orly relay to start..."
    sleep 10
    
    # Test connection
    if curl -s http://localhost:3334 >/dev/null 2>&1; then
        log "✅ Orly relay started successfully"
    else
        warn "Orly relay may not be ready yet. You can check with: docker logs orly-relay"
    fi
}

# Test the setup
test_setup() {
    log "Testing local development setup"
    
    # Test environment loading
    if [ -f ".env" ]; then
        log "✅ .env file exists"
    else
        error ".env file not found"
        return 1
    fi
    
    # Test key decryption
    if php decrypt-key.php >/dev/null 2>&1; then
        log "✅ Key decryption works"
    else
        error "Key decryption failed"
        return 1
    fi
    
    # Test Orly connection
    if curl -s http://localhost:3334 >/dev/null 2>&1; then
        log "✅ Orly relay is accessible"
    else
        warn "Orly relay is not accessible. Check with: docker logs orly-relay"
    fi
    
    log "✅ Local development setup test completed"
}

# Run hello world bot
run_hello_world_bot() {
    log "Running Hello World bot"
    
    # Check if bot directory exists
    if [ ! -d "bots/hello-world" ]; then
        error "Hello World bot directory not found"
        return 1
    fi
    
    # Generate content
    log "Generating Hello World content..."
    php bots/hello-world/generate-content.php
    
    # Check if content was generated
    if [ -f "bots/hello-world/output/hello-world.adoc" ]; then
        log "✅ Hello World content generated"
        log "📄 Content file: bots/hello-world/output/hello-world.adoc"
    else
        error "Failed to generate Hello World content"
        return 1
    fi
    
    # Publish to Nostr (dry run first)
    log "Publishing to Nostr (dry run)..."
    if php nostrbots.php bots/hello-world/output/hello-world.adoc --dry-run; then
        log "✅ Dry run successful"
    else
        error "Dry run failed"
        return 1
    fi
    
    # Ask user if they want to publish for real
    echo ""
    info "🎉 Hello World bot test completed successfully!"
    echo ""
    read -p "Do you want to publish the Hello World content to Nostr for real? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        log "Publishing to Nostr..."
        if php nostrbots.php bots/hello-world/output/hello-world.adoc; then
            log "✅ Hello World content published to Nostr!"
            log "🔍 You can verify it at: http://localhost:3334"
        else
            error "Failed to publish to Nostr"
            return 1
        fi
    else
        log "Skipping real publication (dry run only)"
    fi
}

# Create management script
create_management_script() {
    log "Creating local development management script"
    
    cat > "nostrbots-dev" << 'EOF'
#!/bin/bash

# Nostrbots Local Development Management Script

case "$1" in
    start)
        echo "Starting local development environment..."
        docker start orly-relay 2>/dev/null || echo "Orly relay not found. Run setup first."
        ;;
    stop)
        echo "Stopping local development environment..."
        docker stop orly-relay 2>/dev/null || echo "Orly relay not running."
        ;;
    restart)
        echo "Restarting local development environment..."
        docker restart orly-relay 2>/dev/null || echo "Orly relay not found."
        ;;
    status)
        echo "Local development environment status:"
        echo "Orly relay:"
        docker ps | grep orly-relay || echo "  Not running"
        echo ""
        echo "Environment:"
        if [ -f ".env" ]; then
            echo "  .env file: ✅ Present"
        else
            echo "  .env file: ❌ Missing"
        fi
        ;;
    logs)
        echo "Orly relay logs:"
        docker logs orly-relay
        ;;
    test)
        echo "Running local development tests..."
        php run-tests.php
        ;;
    hello-world)
        echo "Running Hello World bot..."
        php bots/hello-world/generate-content.php
        php nostrbots.php bots/hello-world/output/hello-world.adoc --dry-run
        echo "✅ Hello World bot test completed (dry run)"
        echo "Run 'nostrbots-dev hello-world-publish' to publish for real"
        ;;
    hello-world-publish)
        echo "Publishing Hello World content to Nostr..."
        php bots/hello-world/generate-content.php
        php nostrbots.php bots/hello-world/output/hello-world.adoc
        echo "✅ Hello World content published!"
        ;;
    nsec)
        echo "Your nsec (private key):"
        php decrypt-key.php
        ;;
    cleanup)
        echo "Cleaning up local development environment..."
        docker stop orly-relay 2>/dev/null || true
        docker rm orly-relay 2>/dev/null || true
        rm -f .env
        echo "✅ Cleanup completed"
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs|test|hello-world|hello-world-publish|nsec|cleanup}"
        echo ""
        echo "Commands:"
        echo "  start              - Start Orly relay"
        echo "  stop               - Stop Orly relay"
        echo "  restart            - Restart Orly relay"
        echo "  status             - Show environment status"
        echo "  logs               - Show Orly relay logs"
        echo "  test               - Run unit tests"
        echo "  hello-world        - Test Hello World bot (dry run)"
        echo "  hello-world-publish - Publish Hello World content"
        echo "  nsec               - Show your nsec (private key)"
        echo "  cleanup            - Clean up everything"
        exit 1
        ;;
esac
EOF
    
    chmod +x "nostrbots-dev"
    
    log "✅ Local development management script created"
}

# Main setup function
main() {
    log "🚀 Starting Nostrbots local development setup"
    
    check_root
    check_dependencies
    install_php_dependencies
    generate_local_keys
    start_orly_relay
    test_setup
    create_management_script
    
    log "✅ Local development setup completed successfully!"
    echo ""
    info "🎉 Your local development environment is ready!"
    echo ""
    info "📊 Access Points:"
    info "  • Orly Relay: http://localhost:3334"
    info "  • Hello World Bot: bots/hello-world/"
    echo ""
    info "🔧 Management:"
    info "  • Use 'nostrbots-dev status' to check environment"
    info "  • Use 'nostrbots-dev hello-world' to test the bot"
    info "  • Use 'nostrbots-dev nsec' to get your private key"
    echo ""
    info "🧪 Testing:"
    info "  • Run 'nostrbots-dev hello-world' to test the bot"
    info "  • Run 'nostrbots-dev test' to run unit tests"
    echo ""
    warn "⚠️  Remember:"
    warn "  • This is for local development only"
    warn "  • Use production setup for real deployment"
    warn "  • Your keys are stored in .env file"
    echo ""
    
    # Ask if user wants to run hello world bot
    read -p "Do you want to run the Hello World bot test now? (Y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        run_hello_world_bot
    fi
}

# Run main function
main "$@"
