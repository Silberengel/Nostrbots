#!/bin/bash

# Enhanced Production Setup Script with Elasticsearch
# Sets up Nostrbots production environment with logging and search capabilities

set -euo pipefail

# Source common setup functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/setup-common.sh"

# Script-specific configuration
PROJECT_DIR="/opt/nostrbots"
COMPOSE_FILE="docker-compose.production-with-elasticsearch.yml"
SERVICE_NAME="nostrbots-production"

# Show help
show_help() {
    echo "Nostrbots Production Setup with Elasticsearch"
    echo "============================================="
    echo ""
    echo "Usage: sudo $0 [OPTIONS]"
    echo ""
    show_common_help
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

# Setup project directories
create_project_structure() {
    log "Creating project structure"
    
    mkdir -p "$PROJECT_DIR/data"/{orly,jenkins,elasticsearch}
    mkdir -p "$PROJECT_DIR/backup"
    mkdir -p "$PROJECT_DIR/scripts"
    
    # Create backup file with proper permissions
    touch "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    chmod 600 "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    
    log_success "Project structure created"
}

# Initialize Docker swarm
init_docker_swarm() {
    log "Initializing Docker swarm"
    
    if docker info --format '{{.Swarm.LocalNodeState}}' 2>/dev/null | grep -q "active"; then
        log "Docker swarm is already active"
        return 0
    fi
    
    docker swarm init --advertise-addr 127.0.0.1
    log_success "Docker swarm initialized"
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
    
    # Create Docker secrets
    echo "$encrypted_key" | docker secret create nostr_bot_key_encrypted - >/dev/null
    echo "$npub" | docker secret create nostr_bot_npub - >/dev/null
    
    # Create backup file
    echo "$encrypted_key" > "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    chmod 600 "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    
    log_success "Keys stored as Docker secrets and backup file"
}

# Create systemd service
create_systemd_service() {
    log "Creating systemd service"
    
    cat > /etc/systemd/system/nostrbots-production.service << EOF
[Unit]
Description=Nostrbots Production with Elasticsearch
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/docker stack deploy -c $COMPOSE_FILE $SERVICE_NAME
ExecStop=/usr/bin/docker stack rm $SERVICE_NAME
ExecReload=/usr/bin/docker stack deploy -c $COMPOSE_FILE $SERVICE_NAME
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
EOF

    # Backup service
    cat > /etc/systemd/system/nostrbots-backup.service << EOF
[Unit]
Description=Nostrbots Backup Service
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/docker run --rm --network ${SERVICE_NAME}_nostrbots -v jenkins_data:/var/jenkins_home:ro -v orly_data:/app/data:ro -v backup_data:/backups nostrbots:latest /opt/nostrbots/scripts/backup-relay-data.sh
EOF

    # Backup timer
    cat > /etc/systemd/system/nostrbots-backup.timer << EOF
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
    systemctl enable nostrbots-production.service
    systemctl enable nostrbots-backup.timer
    systemctl start nostrbots-production.service
    systemctl start nostrbots-backup.timer
    
    log_success "Systemd service created and enabled"
}

# Deploy the stack
deploy_stack() {
    log "Deploying Nostrbots stack with Elasticsearch"
    
    # Copy the compose file to project directory
    cp "$COMPOSE_FILE" "$PROJECT_DIR/"
    
    cd "$PROJECT_DIR"
    docker stack deploy -c "$COMPOSE_FILE" "$SERVICE_NAME"
    
    log_success "Stack deployed successfully"
}

# Show final information
show_final_info() {
    echo ""
    log_success "🎉 Production setup with Elasticsearch completed!"
    echo ""
    echo "📋 What's been set up:"
    echo "======================"
    echo "✅ Docker and Docker Compose installed"
    echo "✅ Project structure created"
    echo "✅ Docker swarm initialized"
    echo "✅ Nostr keys generated and stored as Docker secrets"
    echo "✅ Systemd services created and enabled"
    echo "✅ Nostrbots stack with Elasticsearch deployed"
    echo ""
    echo "🌐 Access Points:"
    echo "================="
    info "  • Jenkins: http://localhost:8080"
    info "  • Orly Relay: ws://localhost:3334"
    info "  • Elasticsearch: http://localhost:9200"
    info "  • Kibana: http://localhost:5601"
    echo ""
    echo "🔧 Management Commands:"
    echo "======================="
    info "  • Use 'nostrbots elasticsearch' to check Elasticsearch health"
    info "  • Use 'nostrbots kibana' to access Kibana dashboard"
    info "  • Use 'nostrbots backup' to run manual backups"
    echo ""
    echo "📊 Monitoring Features:"
    echo "======================"
    info "  • All Nostr events are automatically indexed in Elasticsearch"
    info "  • Logs are automatically indexed in Elasticsearch"
    info "  • Daily backups include Elasticsearch snapshots"
    echo ""
    echo "⚠️  Next Steps:"
    echo "==============="
    warn "  • Change default Jenkins password"
    warn "  • Configure firewall rules"
    warn "  • Set up SSL certificates for production"
    warn "  • Monitor disk space for Elasticsearch data"
}

# Main function
main() {
    log "🚀 Starting Nostrbots production setup with Elasticsearch"
    
    check_root
    install_docker
    create_project_structure
    init_docker_swarm
    generate_keys_to_secrets "$@"
    create_systemd_service
    deploy_stack
    show_final_info
}

# Initialize and run
init_common_setup "$@"
main "$@"
