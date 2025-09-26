#!/bin/bash

# Nostrbots Production Setup Script
# Sets up a production environment with Docker secrets

set -e

# Source common setup functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/setup-common.sh"

# Script-specific configuration
PROJECT_DIR="/opt/nostrbots"
SERVICE_USER="nostrbots"
SYSTEMD_DIR="/etc/systemd/system"
COMPOSE_FILE="docker-compose.production.yml"

# Show help
show_help() {
    echo "Nostrbots Production Setup (Secrets Only)"
    echo "========================================="
    echo ""
    echo "Usage: sudo $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --cleanup           Clean up existing stack and secrets for blank slate testing"
    echo "  --private-key KEY   Use your existing Nostr private key (hex or nsec format)"
    echo "  --help, -h          Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Generate new keys and setup"
    echo "  $0 --private-key abc123...           # Use hex private key"
    echo "  $0 --private-key nsec1abc123...      # Use nsec private key"
    echo "  $0 --cleanup                         # Clean up for blank slate testing"
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

# Setup user and directories
setup_user_and_directories() {
    log_info "Setting up user and directories..."
    
    # Create service user
    if ! id "$SERVICE_USER" &>/dev/null; then
        useradd -r -s /bin/false -d "$PROJECT_DIR" "$SERVICE_USER"
        log_success "Created service user: $SERVICE_USER"
    else
        log_info "Service user already exists: $SERVICE_USER"
    fi
    
    # Create directories
    mkdir -p "$PROJECT_DIR"/{data,backups,scripts}
    chown -R "$SERVICE_USER:$SERVICE_USER" "$PROJECT_DIR"
    
    # Copy project files
    cp -r "$SCRIPT_DIR"/* "$PROJECT_DIR/"
    chown -R "$SERVICE_USER:$SERVICE_USER" "$PROJECT_DIR"
    
    cd "$PROJECT_DIR"
    log_success "User and directories set up"
}

# Docker swarm initialization is now handled by setup-common.sh

# Cleanup function for blank slate testing
cleanup_stack() {
    log_info "Cleaning up Nostrbots stack for blank slate testing..."
    
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
    # docker volume rm nostrbots_backup_data >/dev/null 2>&1 || true
    # log_success "Docker volumes removed"
    
    # Remove any remaining containers
    log_info "Removing any remaining containers..."
    docker container prune -f >/dev/null 2>&1 || true
    
    log_success "🎉 Cleanup completed! You now have a blank slate for testing."
    echo ""
    echo "To start fresh, run: sudo -E ./setup-production.sh"
    echo ""
}

# Generate keys and store as Docker secrets
generate_keys_to_secrets() {
    log_info "Setting up Nostr keys and storing as Docker secrets"
    
    # Get keys from common setup
    local key_result
    key_result=$(setup_nostr_keys "$@")
    
    # Extract the keys
    local encrypted_key=$(echo "$key_result" | grep "ENCRYPTED_KEY=" | cut -d'=' -f2)
    local npub=$(echo "$key_result" | grep "NPUB=" | cut -d'=' -f2)
    
    # Create Docker secrets (handle existing secrets properly)
    if docker secret ls --format "{{.Name}}" | grep -q "nostr_bot_key_encrypted"; then
        log_info "Docker secret nostr_bot_key_encrypted already exists, skipping creation"
    else
        echo "$encrypted_key" | docker secret create nostr_bot_key_encrypted - >/dev/null
        log_success "Created nostr_bot_key_encrypted secret"
    fi
    
    if docker secret ls --format "{{.Name}}" | grep -q "nostr_bot_npub"; then
        log_info "Docker secret nostr_bot_npub already exists, skipping creation"
    else
        echo "$npub" | docker secret create nostr_bot_npub - >/dev/null
        log_success "Created nostr_bot_npub secret"
    fi
    
    log_success "Keys stored as Docker secrets"
    
    # Display keys for user to save using common function
    display_keys_for_user "$encrypted_key" "$npub"
}

# Create production Docker Compose file
create_docker_compose() {
    # Skip if Docker Compose file already exists
    if [ -f "$COMPOSE_FILE" ]; then
        log_info "Docker Compose file already exists, skipping creation"
        return 0
    fi
    
    log_info "Creating production Docker Compose configuration with secrets..."
    
    cat > "$COMPOSE_FILE" << 'EOF'
version: '3.8'

services:
  orly-relay:
    image: silberengel/next-orly:latest
    container_name: nostrbots-orly-relay
    ports:
      - "3334:7777"
    environment:
      - ORLY_LISTEN=0.0.0.0
      - ORLY_PORT=7777
      - ORLY_LOG_LEVEL=info
      - ORLY_MAX_CONNECTIONS=1000
      - ORLY_ACL_MODE=none
    volumes:
      - orly_data:/data
    networks:
      - nostrbots-network
    restart: unless-stopped
    user: "1000:1000"

  jenkins:
    image: jenkins/jenkins:lts
    container_name: nostrbots-jenkins
    ports:
      - "8080:8080"
    environment:
      - JENKINS_OPTS=--httpPort=8080
      - JAVA_OPTS=-Djenkins.install.runSetupWizard=false
      - JENKINS_ADMIN_ID=${JENKINS_ADMIN_ID:-admin}
      - JENKINS_ADMIN_PASSWORD=${JENKINS_ADMIN_PASSWORD:-admin}
    volumes:
      - jenkins_data:/var/jenkins_home
      - $PROJECT_DIR/scripts/jenkins-setup.groovy:/usr/share/jenkins/ref/init.groovy.d/setup.groovy
    secrets:
      - nostr_bot_key_encrypted
      - nostr_bot_npub
    networks:
      - nostrbots-network
    restart: unless-stopped
    user: "1000:1000"
    working_dir: /var/jenkins_home/workspace

  nostrbots-agent:
    image: nostrbots:latest
    container_name: nostrbots-agent
    environment:
      - NOSTR_BOT_KEY_ENCRYPTED_FILE=/run/secrets/nostr_bot_key_encrypted
      - NOSTR_BOT_NPUB_FILE=/run/secrets/nostr_bot_npub
    secrets:
      - nostr_bot_key_encrypted
      - nostr_bot_npub
    networks:
      - nostrbots-network
    restart: unless-stopped
    depends_on:
      - orly-relay
    command: >
      sh -c "
        echo 'Starting Nostrbots agent...' &&
        tail -f /dev/null
      "

  backup-agent:
    image: nostrbots:latest
    container_name: nostrbots-backup-agent
    environment:
      - NOSTR_BOT_KEY_ENCRYPTED_FILE=/run/secrets/nostr_bot_key_encrypted
      - NOSTR_BOT_NPUB_FILE=/run/secrets/nostr_bot_npub
    volumes:
      - jenkins_data:/var/jenkins_home:ro
      - orly_data:/app/data:ro
      - backup_data:/backups
    secrets:
      - nostr_bot_key_encrypted
      - nostr_bot_npub
    networks:
      - nostrbots-network
    restart: unless-stopped
    command: >
      sh -c "
        echo 'Starting backup agent...' &&
        tail -f /dev/null
      "

volumes:
  orly_data:
    driver: local
  jenkins_data:
    driver: local
  backup_data:
    driver: local

secrets:
  nostr_bot_key_encrypted:
    external: true
  nostr_bot_npub:
    external: true

networks:
  nostrbots-network:
    driver: overlay
    attachable: true
EOF
    
    chown "$SERVICE_USER:$SERVICE_USER" "$COMPOSE_FILE"
    log_success "Production Docker Compose file created with secrets"
}

# Create systemd services
create_systemd_services() {
    log_info "Creating systemd service files..."
    
    # Main service
    cat > "$SYSTEMD_DIR/nostrbots.service" << EOF
[Unit]
Description=Nostrbots Production Stack
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
ExecStart=/usr/bin/docker run --rm --network nostrbots_nostrbots -v jenkins_data:/var/jenkins_home:ro -v orly_data:/app/data:ro -v backup_data:/backups nostrbots:latest /opt/nostrbots/scripts/backup-relay-data.sh
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

    # Reload systemd and enable services
    systemctl daemon-reload
    systemctl enable nostrbots.service
    systemctl enable nostrbots-backup.timer
    systemctl start nostrbots.service
    systemctl start nostrbots-backup.timer
    
    log_success "Systemd services created and enabled"
}

# Deploy the stack
deploy_stack() {
    log_info "Deploying Nostrbots stack..."
    
    docker stack deploy -c "$COMPOSE_FILE" nostrbots
    
    # Wait for services to start
    log_info "Waiting for services to start..."
    sleep 30
    
    # Check service status
    local services=("nostrbots_orly-relay" "nostrbots_jenkins" "nostrbots_nostrbots-agent" "nostrbots_backup-agent")
    for service in "${services[@]}"; do
        if docker service ls --format "table {{.Name}}" | grep -q "$service"; then
            log_success "$service is running"
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
    
    echo "🚀 Nostrbots Production Setup (Secrets Only)"
    echo "============================================="
    echo ""
    
    check_root
    install_system_dependencies
    setup_user_and_directories
    init_docker_swarm
    generate_keys_to_secrets "$@"
    create_docker_compose
    create_systemd_services
    deploy_stack
    
    echo ""
    log_success "🎉 Production setup completed!"
    echo ""
    echo "📋 What's been set up:"
    echo "======================"
    echo "✓ System dependencies installed"
    echo "✓ Docker swarm initialized"
    echo "✓ Nostr keys generated and stored as Docker secrets"
    echo "✓ Production Docker Compose configuration created"
    echo "✓ Systemd services created and enabled"
    echo "✓ Nostrbots stack deployed"
    echo ""
    echo "🌐 Access Points:"
    echo "================="
    echo "1. Jenkins: http://localhost:8080"
    echo "2. Orly Relay: ws://localhost:3334"
    echo "3. Check status: systemctl status nostrbots"
    echo ""
    echo "🔧 Management Commands:"
    echo "======================="
    echo "systemctl start|stop|restart nostrbots"
    echo "docker service ls"
    echo "docker service logs nostrbots_jenkins"
    echo ""
    log_warn "⚠  Remember to retrieve and save your nsec securely!"
    log_warn "⚠  Keys are stored securely in Docker secrets (no files)"
    log_warn "⚠  The system will auto-start on boot"
}

# Initialize and run
init_common_setup "$@"
main "$@"
