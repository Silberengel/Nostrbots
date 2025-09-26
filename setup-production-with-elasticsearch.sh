#!/bin/bash

# Enhanced Production Setup Script with Elasticsearch
# Sets up Nostrbots production environment with logging and search capabilities

set -euo pipefail

# Configuration
PROJECT_DIR="/opt/nostrbots"
COMPOSE_FILE="docker-compose.production-with-elasticsearch.yml"
SERVICE_NAME="nostrbots-production"

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
    if [ "$EUID" -ne 0 ]; then
        error "This script must be run as root"
        exit 1
    fi
}

# Install dependencies
install_dependencies() {
    log "Installing system dependencies"
    
    apt-get update
    apt-get install -y \
        curl \
        wget \
        gnupg \
        lsb-release \
        ca-certificates \
        apt-transport-https \
        software-properties-common \
        jq \
        gpg \
        netcat-openbsd
    
    log "✅ Dependencies installed"
}

# Install Docker
install_docker() {
    if command -v docker >/dev/null 2>&1; then
        log "Docker is already installed"
        return 0
    fi
    
    log "Installing Docker"
    
    # Add Docker's official GPG key
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
    
    # Set up the stable repository
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    
    # Install Docker Engine
    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
    
    # Start and enable Docker
    systemctl start docker
    systemctl enable docker
    
    log "✅ Docker installed and started"
}

# Initialize Docker Swarm
init_docker_swarm() {
    if docker info --format '{{.Swarm.LocalNodeState}}' 2>/dev/null | grep -q "active"; then
        log "Docker Swarm is already initialized"
        return 0
    fi
    
    log "Initializing Docker Swarm"
    
    # Get the primary network interface IP
    MANAGER_IP=$(ip route get 8.8.8.8 | awk '{print $7; exit}')
    
    # Initialize swarm
    docker swarm init --advertise-addr "$MANAGER_IP"
    
    log "✅ Docker Swarm initialized"
}

# Generate and store keys
generate_keys_to_secrets() {
    log "Generating Nostr keys and storing as Docker secrets"
    
    # Generate keys using PHP script
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
    
    # Create Docker secrets
    echo "$encrypted_key" | docker secret create nostr_bot_key_encrypted -
    echo "$npub" | docker secret create nostr_bot_npub -
    
    # Store encrypted key for backup
    echo "$encrypted_key" > "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    
    log "✅ Keys generated and stored as Docker secrets"
    log "🔑 Encrypted Private Key: ${encrypted_key:0:10}..."
    log "🔑 Public Key (npub): $npub"
    
    # Secure cleanup
    unset key_output encrypted_key npub
}

# Create project directory structure
create_project_structure() {
    log "Creating project directory structure"
    
    mkdir -p "$PROJECT_DIR"/{data,backups,config,scripts,logs}
    mkdir -p "$PROJECT_DIR/data"/{orly,jenkins,elasticsearch}
    mkdir -p "$PROJECT_DIR/config"/{logstash,kibana}
    mkdir -p "$PROJECT_DIR/scripts"
    mkdir -p /var/log/nostrbots
    
    # Copy project files
    cp -r . "$PROJECT_DIR/"
    
    # Set permissions
    chown -R root:root "$PROJECT_DIR"
    chmod -R 755 "$PROJECT_DIR"
    chmod 600 "$PROJECT_DIR/backup/nostr_bot_key_encrypted.txt"
    
    log "✅ Project structure created"
}

# Create systemd service
create_systemd_service() {
    log "Creating systemd service"
    
    cat > /etc/systemd/system/nostrbots-production.service << EOF
[Unit]
Description=Nostrbots Production Stack
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/docker stack deploy -c $COMPOSE_FILE $SERVICE_NAME
ExecStop=/usr/bin/docker stack rm $SERVICE_NAME
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
EOF
    
    systemctl daemon-reload
    systemctl enable nostrbots-production.service
    
    log "✅ Systemd service created and enabled"
}

# Create backup timer
create_backup_timer() {
    log "Creating backup timer"
    
    cat > /etc/systemd/system/nostrbots-backup.service << EOF
[Unit]
Description=Nostrbots Daily Backup
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/docker exec nostrbots-backup-agent /workspace/scripts/backup-relay-data.sh
EOF
    
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
    systemctl enable nostrbots-backup.timer
    systemctl start nostrbots-backup.timer
    
    log "✅ Backup timer created and started"
}

# Deploy the stack
deploy_stack() {
    log "Deploying Nostrbots production stack"
    
    cd "$PROJECT_DIR"
    
    # Deploy the stack
    docker stack deploy -c "$COMPOSE_FILE" "$SERVICE_NAME"
    
    # Wait for services to be ready
    log "Waiting for services to start..."
    sleep 30
    
    # Check service status
    docker service ls --filter name="$SERVICE_NAME"
    
    log "✅ Stack deployed successfully"
}

# Create management script
create_management_script() {
    log "Creating management script"
    
    cat > "$PROJECT_DIR/nostrbots" << 'EOF'
#!/bin/bash

# Nostrbots Management Script

case "$1" in
    start)
        echo "Starting Nostrbots production stack..."
        systemctl start nostrbots-production
        ;;
    stop)
        echo "Stopping Nostrbots production stack..."
        systemctl stop nostrbots-production
        ;;
    restart)
        echo "Restarting Nostrbots production stack..."
        systemctl restart nostrbots-production
        ;;
    status)
        echo "Nostrbots production stack status:"
        systemctl status nostrbots-production
        echo ""
        echo "Docker services:"
        docker service ls --filter name=nostrbots-production
        ;;
    logs)
        service="${2:-all}"
        if [ "$service" = "all" ]; then
            docker service logs nostrbots-production_orly-relay
            docker service logs nostrbots-production_jenkins
            docker service logs nostrbots-production_backup-agent
            docker service logs nostrbots-production_event-indexer
        else
            docker service logs "nostrbots-production_$service"
        fi
        ;;
    elasticsearch)
        echo "Elasticsearch status:"
        curl -s http://localhost:9200/_cluster/health | jq
        ;;
    kibana)
        echo "Kibana is available at: http://localhost:5601"
        ;;
    backup)
        echo "Running manual backup..."
        systemctl start nostrbots-backup
        ;;
    secrets)
        echo "Docker secrets:"
        docker secret ls
        ;;
    nsec)
        echo "Use './retrieve-nsec-secure.sh' to securely retrieve your nsec"
        ;;
    restore)
        echo "Use './recover-from-encrypted-backup.sh' to restore from backup"
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs|elasticsearch|kibana|backup|secrets|nsec|restore}"
        echo ""
        echo "Commands:"
        echo "  start       - Start the production stack"
        echo "  stop        - Stop the production stack"
        echo "  restart     - Restart the production stack"
        echo "  status      - Show stack status"
        echo "  logs [svc]  - Show logs (all services or specific service)"
        echo "  elasticsearch - Show Elasticsearch health"
        echo "  kibana      - Show Kibana access info"
        echo "  backup      - Run manual backup"
        echo "  secrets     - List Docker secrets"
        echo "  nsec        - Instructions for retrieving nsec"
        echo "  restore     - Instructions for restoring from backup"
        exit 1
        ;;
esac
EOF
    
    chmod +x "$PROJECT_DIR/nostrbots"
    ln -sf "$PROJECT_DIR/nostrbots" /usr/local/bin/nostrbots
    
    log "✅ Management script created"
}

# Main setup function
main() {
    log "🚀 Starting Nostrbots production setup with Elasticsearch"
    
    check_root
    install_dependencies
    install_docker
    init_docker_swarm
    create_project_structure
    generate_keys_to_secrets
    create_systemd_service
    create_backup_timer
    deploy_stack
    create_management_script
    
    log "✅ Nostrbots production setup completed successfully!"
    echo ""
    info "🎉 Your Nostrbots production environment is ready!"
    echo ""
    info "📊 Access Points:"
    info "  • Jenkins: http://localhost:8080"
    info "  • Kibana: http://localhost:5601"
    info "  • Elasticsearch: http://localhost:9200"
    info "  • Orly Relay: ws://localhost:3334"
    echo ""
    info "🔧 Management:"
    info "  • Use 'nostrbots status' to check system status"
    info "  • Use 'nostrbots logs' to view logs"
    info "  • Use 'nostrbots elasticsearch' to check Elasticsearch health"
    echo ""
    info "🔑 Key Management:"
    info "  • Use './retrieve-nsec-secure.sh' to get your nsec"
    info "  • Use 'nostrbots secrets' to list Docker secrets"
    echo ""
    info "📈 Monitoring:"
    info "  • Logs are automatically indexed in Elasticsearch"
    info "  • Events are indexed every 5 minutes"
    info "  • Daily backups include Elasticsearch snapshots"
    echo ""
    warn "⚠️  Remember to:"
    warn "  • Change default Jenkins password"
    warn "  • Configure firewall rules"
    warn "  • Set up SSL certificates for production"
    warn "  • Monitor disk space for Elasticsearch data"
}

# Run main function
main "$@"
