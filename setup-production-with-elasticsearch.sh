#!/bin/bash

# Enhanced Production Setup Script with Elasticsearch
# Sets up Nostrbots production environment with logging and search capabilities

set -euo pipefail

# Source common setup functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/setup-common.sh"

# Script-specific configuration
PROJECT_DIR="/opt/nostrbots"
SERVICE_USER="nostrbots"
SYSTEMD_DIR="/etc/systemd/system"
COMPOSE_FILE="docker-compose.production-with-elasticsearch.yml"

# Show help
show_help() {
    echo "Nostrbots Production Setup with Elasticsearch"
    echo "============================================="
    echo ""
    echo "Usage: sudo $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --cleanup           Clean up existing stack and secrets for blank slate testing"
    echo "  --private-key KEY   Use your existing Nostr private key (hex or nsec format)"
    echo "  --change-keys       Change keys for existing setup (requires --private-key)"
    echo "  --help, -h          Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Generate new keys and setup"
    echo "  $0 --private-key abc123...           # Use hex private key"
    echo "  $0 --private-key nsec1abc123...      # Use nsec private key"
    echo "  $0 --cleanup                         # Clean up for blank slate testing"
    echo "  $0 --change-keys --private-key KEY   # Change keys for existing setup"
    echo ""
    echo "Private Key Formats:"
    echo "  - Hex: 64-character hexadecimal string (e.g., abc123def456...)"
    echo "  - Nsec: Bech32 encoded private key (e.g., nsec1abc123...)"
    echo ""
    echo "⚠  SECURITY WARNING:"
    echo "  Command line arguments may be stored in shell history!"
    echo "  For better security, use environment variables instead:"
    echo "    export CUSTOM_PRIVATE_KEY='your_key_here'"
    echo "    $0"
    echo ""
}

# Install Docker (if not already installed)
install_docker() {
    if command -v docker >/dev/null 2>&1; then
        log "Docker is already installed"
        return 0
    fi
    
    log "Installing Docker..."
    
    # Update package list
    apt-get update
    
    # Install Docker
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
    
    # Start and enable Docker
    systemctl start docker
    systemctl enable docker
    
    log_success "Docker installed and started"
}

# Setup user and directories
setup_user_and_directories() {
    log_info "Setting up user and directories..."
    
    # Create nostrbots user if it doesn't exist
    if ! id "$SERVICE_USER" &>/dev/null; then
        useradd -r -s /bin/false -d "$PROJECT_DIR" "$SERVICE_USER"
        log_success "Created user: $SERVICE_USER"
    else
        log_info "User $SERVICE_USER already exists"
    fi
    
    # Create project directories
    mkdir -p "$PROJECT_DIR/data"/{orly,jenkins,elasticsearch}
    mkdir -p "$PROJECT_DIR/backup"
    mkdir -p "$PROJECT_DIR/scripts"
    mkdir -p "$PROJECT_DIR/config"
    
    # Set ownership
    chown -R "$SERVICE_USER:$SERVICE_USER" "$PROJECT_DIR"
    
    # Create backup file with proper permissions
    touch "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    chmod 600 "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    
    cd "$PROJECT_DIR"
    log_success "User and directories set up"
}

# Docker swarm initialization is now handled by setup-common.sh

# Cleanup function for blank slate testing
cleanup_stack() {
    log_info "Cleaning up Nostrbots stack with Elasticsearch for blank slate testing..."
    
    # Remove the Docker stack
    if docker stack ls --format "{{.Name}}" | grep -q "nostrbots"; then
        log_info "Removing Docker stack..."
        docker stack rm nostrbots
        sleep 10
        log_success "Docker stack removed"
    else
        log_info "No Docker stack found"
    fi
    
    # Remove Docker secrets
    if docker secret ls --format "{{.Name}}" | grep -q "nostr_bot_key_encrypted"; then
        log_info "Removing Docker secrets..."
        docker secret rm nostr_bot_key_encrypted >/dev/null 2>&1 || true
        docker secret rm nostr_bot_npub >/dev/null 2>&1 || true
        log_success "Docker secrets removed"
    else
        log_info "No Docker secrets found"
    fi
    
    # Remove Docker volumes (optional - uncomment if you want to remove data)
    # log_info "Removing Docker volumes..."
    # docker volume rm nostrbots_orly_data >/dev/null 2>&1 || true
    # docker volume rm nostrbots_jenkins_data >/dev/null 2>&1 || true
    # docker volume rm nostrbots_elasticsearch_data >/dev/null 2>&1 || true
    # docker volume rm nostrbots_backup_data >/dev/null 2>&1 || true
    # log_success "Docker volumes removed"
    
    # Remove any remaining containers
    log_info "Removing any remaining containers..."
    docker container prune -f >/dev/null 2>&1 || true
    
    log_success "🎉 Cleanup completed! You now have a blank slate for testing."
    echo ""
    echo "To start fresh, run: sudo -E ./setup-production-with-elasticsearch.sh"
    echo ""
}

# Generate keys and store as Docker secrets
generate_keys_to_secrets() {
    log "Setting up Nostr keys and storing as Docker secrets"
    
    # Get keys from common setup
    local key_result
    key_result=$(setup_nostr_keys "$@")
    
    # Extract the keys
    local encrypted_key=$(echo "$key_result" | grep "ENCRYPTED_KEY=" | cut -d'=' -f2)
    local npub=$(echo "$key_result" | grep "NPUB=" | cut -d'=' -f2)
    
    # Create Docker secrets (handle existing secrets properly)
    if docker secret ls --format "{{.Name}}" | grep -q "nostr_bot_key_encrypted"; then
        log "Docker secret nostr_bot_key_encrypted already exists, skipping creation"
    else
        echo "$encrypted_key" | docker secret create nostr_bot_key_encrypted - >/dev/null
        log_success "Created nostr_bot_key_encrypted secret"
    fi
    
    if docker secret ls --format "{{.Name}}" | grep -q "nostr_bot_npub"; then
        log "Docker secret nostr_bot_npub already exists, skipping creation"
    else
        echo "$npub" | docker secret create nostr_bot_npub - >/dev/null
        log_success "Created nostr_bot_npub secret"
    fi
    
    # Create backup file
    echo "$encrypted_key" > "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    chmod 600 "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    
    log_success "Keys stored as Docker secrets and backup file"
    
    # Display keys for user to save using common function
    display_keys_for_user "$encrypted_key" "$npub"
}

# Verify Docker Compose file exists and is correct
verify_docker_compose() {
    log_info "Verifying Docker Compose file for production with Elasticsearch"
    
    if [[ ! -f "$COMPOSE_FILE" ]]; then
        log_error "Docker Compose file $COMPOSE_FILE not found!"
        log_error "Please ensure the file exists before running this script."
        exit 1
    fi
    
    # Check if the file contains the expected services
    local required_services=("elasticsearch" "kibana" "orly-relay" "jenkins" "backup-agent")
    local missing_services=()
    
    for service in "${required_services[@]}"; do
        if ! grep -q "^  $service:" "$COMPOSE_FILE"; then
            missing_services+=("$service")
        fi
    done
    
    if [[ ${#missing_services[@]} -gt 0 ]]; then
        log_error "Docker Compose file is missing required services: ${missing_services[*]}"
        log_error "Please check the $COMPOSE_FILE file."
        exit 1
    fi
    
    log_success "Docker Compose file verified - all required services found"
}

# Create systemd services
create_systemd_services() {
    log_info "Creating systemd services"
    
    # Main service
    cat > "$SYSTEMD_DIR/nostrbots.service" << EOF
[Unit]
Description=Nostrbots Production with Elasticsearch
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/docker stack deploy -c $COMPOSE_FILE nostrbots
ExecStop=/usr/bin/docker stack rm nostrbots
ExecReload=/usr/bin/docker stack deploy -c $COMPOSE_FILE nostrbots
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
EOF

    # Backup service
    cat > "$SYSTEMD_DIR/nostrbots-backup.service" << EOF
[Unit]
Description=Nostrbots Backup Service
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/docker run --rm --network nostrbots_nostrbots-network -v nostrbots_jenkins_data:/var/jenkins_home:ro -v nostrbots_orly_data:/app/data:ro -v nostrbots_elasticsearch_data:/usr/share/elasticsearch/data:ro -v nostrbots_backup_data:/backups nostrbots:latest /opt/nostrbots/scripts/backup-relay-data.sh
EOF

    # Backup timer
    cat > "$SYSTEMD_DIR/nostrbots-backup.timer" << EOF
[Unit]
Description=Run Nostrbots backup daily
Requires=nostrbots-backup.service

[Timer]
OnCalendar=daily
Persistent=true

[Install]
WantedBy=timers.target
EOF

    systemctl daemon-reload
    systemctl enable nostrbots.service
    systemctl enable nostrbots-backup.timer
    systemctl start nostrbots.service
    systemctl start nostrbots-backup.timer
    
    log_success "Systemd services created and enabled"
}

# Deploy the stack
deploy_stack() {
    log_info "Deploying Nostrbots stack with Elasticsearch"
    
    # Copy the compose file to project directory (if not already there)
    if [[ "$COMPOSE_FILE" != "$PROJECT_DIR/$COMPOSE_FILE" ]]; then
        cp "$COMPOSE_FILE" "$PROJECT_DIR/"
    fi
    
    cd "$PROJECT_DIR"
    log_info "Current directory: $(pwd)"
    log_info "Compose file: $COMPOSE_FILE"
    
    # Remove existing stack if it exists
    if docker stack ls --format "{{.Name}}" | grep -q "nostrbots"; then
        log_info "Removing existing stack..."
        docker stack rm nostrbots
        sleep 10
    fi
    
    # Deploy the stack with error handling
    log_info "Running: docker stack deploy -c $COMPOSE_FILE nostrbots"
    if docker stack deploy -c "$COMPOSE_FILE" nostrbots; then
        log_success "Stack deployed successfully"
    else
        log_error "Failed to deploy stack"
        log_error "Check Docker logs for more details"
        return 1
    fi
    
    # Wait for services to start
    log_info "Waiting for services to start..."
    sleep 30
    
    # Check service status
    log_info "Checking service status..."
    for service in orly-relay jenkins elasticsearch kibana logstash nostrbots-agent backup-agent event-indexer; do
        if docker service ls --format "{{.Name}}" | grep -q "nostrbots_$service"; then
            log_success "$service service is running"
        else
            log_warn "$service may not be running yet"
        fi
    done
}


# Main function
main() {
    # Check for cleanup option
    if [[ "$*" == *"--cleanup"* ]]; then
        cleanup_stack
        exit 0
    fi
    
    # Check for change-keys option
    if [[ "$*" == *"--change-keys"* ]]; then
        if [[ "$*" != *"--private-key"* ]]; then
            log_error "--change-keys requires --private-key to be specified"
            echo "Usage: $0 --change-keys --private-key YOUR_NEW_KEY"
            exit 1
        fi
        change_keys "$@"
        exit 0
    fi
    
    echo "🚀 Nostrbots Production Setup with Elasticsearch"
    echo "==============================================="
    echo ""
    
    check_root
    install_system_dependencies
    setup_user_and_directories
    init_docker_swarm
    generate_keys_to_secrets "$@"
    verify_docker_compose
    create_systemd_services
    
    # Deploy stack (continue even if it fails)
    if deploy_stack; then
        log_success "Stack deployment completed successfully"
    else
        log_warn "Stack deployment had issues, but continuing with setup completion"
    fi
    
    echo ""
    log_info "Reaching final information section..."
    log_success "🎉 Production setup with Elasticsearch completed!"
    echo ""
    echo "📋 What's been set up:"
    echo "======================"
    echo "✓ Docker and Docker Compose installed"
    echo "✓ Project structure created"
    echo "✓ Docker swarm initialized"
    echo "✓ Nostr keys generated and stored as Docker secrets"
    echo "✓ Systemd services created and enabled"
    echo "✓ Nostrbots stack with Elasticsearch deployed"
    echo ""
    echo "🌐 Access Points:"
    echo "================="
    echo "  • Jenkins: http://localhost:8080"
    echo "  • Orly Relay: ws://localhost:3334"
    echo "  • Elasticsearch: http://localhost:9200"
    echo "  • Kibana: http://localhost:5601"
    echo ""
    echo "🔧 Management Commands:"
    echo "======================="
    echo "  • Use 'nostrbots elasticsearch' to check Elasticsearch health"
    echo "  • Use 'nostrbots kibana' to access Kibana dashboard"
    echo "  • Use 'nostrbots backup' to run manual backups"
    echo ""
    echo "📊 Monitoring Features:"
    echo "======================"
    echo "  • Full ELK Stack (Elasticsearch, Kibana, Logstash)"
    echo "  • All Nostr events are automatically indexed in Elasticsearch"
    echo "  • Logs are automatically processed by Logstash"
    echo "  • Daily backups include Elasticsearch snapshots"
    echo "  • Event indexer runs every 5 minutes"
    echo ""
    echo "🚀 Next Steps:"
    echo "=============="
    echo "1. Test the setup: curl http://localhost:9200 (Elasticsearch health)"
    echo "2. Access Kibana: http://localhost:5601"
    echo "3. Access Jenkins: http://localhost:8080 (admin/admin)"
    echo "4. Test Orly relay: curl http://localhost:3334/health"
    echo "5. Publish a hello world note:"
    echo "   php write-note.php '🌍 Hello World! Published from Nostrbots with ELK Stack'"
    echo ""
    echo "💡 To change keys after setup:"
    echo "   Easy way: sudo ./setup-production-with-elasticsearch.sh --change-keys --private-key YOUR_NEW_KEY"
    echo "   Manual way:"
    echo "   1. Stop services: sudo systemctl stop nostrbots.service"
    echo "   2. Remove old secrets: docker secret rm nostr_bot_key_encrypted nostr_bot_npub"
    echo "   3. Run setup again: sudo -E ./setup-production-with-elasticsearch.sh --private-key YOUR_NEW_KEY"
    echo "🛑 To stop services: sudo systemctl stop nostrbots.service"
    echo "🔄 To restart services: sudo systemctl restart nostrbots.service"
    echo ""
    echo "⚠  Production Considerations:"
    echo "============================="
    echo "  • Change default Jenkins password"
    echo "  • Configure firewall rules"
    echo "  • Set up SSL certificates for production"
    echo "  • Monitor disk space for Elasticsearch data"
    echo "  • Configure log retention policies"
    
    # Ask if user wants to test publishing a hello world note
    echo ""
    echo -n "Would you like to test publishing a hello world note now? (y/N): "
    read -r REPLY
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        test_hello_world_note
    fi
}

# Test hello world note publishing
test_hello_world_note() {
    log_info "Testing hello world note publishing..."
    
    if [ -f "write-note.php" ]; then
        echo "🌍 Publishing test note..."
        php write-note.php '🌍 Hello World! Published from Nostrbots with ELK Stack - Production setup complete!'
        log_success "Hello world note published successfully!"
        echo ""
        echo "💡 You can view your note on any Nostr client connected to ws://localhost:3334"
    else
        log_warn "write-note.php not found, skipping test"
        echo "   You can manually test with: php write-note.php 'Your message here'"
    fi
}

# Helper function to change keys after setup
change_keys() {
    log_info "Changing Nostr keys for existing setup..."
    
    # Check if services are running
    if systemctl is-active --quiet nostrbots.service; then
        log_info "Stopping services..."
        systemctl stop nostrbots.service
    fi
    
    # Remove old secrets
    log_info "Removing old Docker secrets..."
    docker secret rm nostr_bot_key_encrypted >/dev/null 2>&1 || true
    docker secret rm nostr_bot_npub >/dev/null 2>&1 || true
    
    # Generate new keys and secrets
    log_info "Generating new keys and secrets..."
    generate_keys_to_secrets "$@"
    
    # Restart services
    log_info "Restarting services..."
    systemctl start nostrbots.service
    
    log_success "Keys changed successfully!"
    echo ""
    echo "💡 Your new keys are now active. Services have been restarted."
}

# Initialize and run
init_common_setup "$@"
main "$@"
