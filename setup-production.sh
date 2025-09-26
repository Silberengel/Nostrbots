#!/bin/bash

# Nostrbots Production Setup Script - Secrets Only
# This script sets up a production-ready Nostrbots environment using Docker secrets
# No .env files - keys go directly into Docker secrets

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="nostrbots"
SERVICE_USER="nostrbots"
BACKUP_DIR="/opt/nostrbots/backups"
DATA_DIR="/opt/nostrbots/data"
LOG_DIR="/var/log/nostrbots"
SYSTEMD_DIR="/etc/systemd/system"

# Logging functions
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}
 
log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "This script must be run as root for production setup"
        log_info "Please run: sudo $0"
        exit 1
    fi
}

# Install required packages
install_dependencies() {
    log_info "Installing system dependencies..."
    
    apt-get update
    
    # Check if Docker is already installed
    if command -v docker >/dev/null 2>&1; then
        log_info "Docker is already installed, skipping Docker installation"
        DOCKER_PACKAGES=""
    else
        log_info "Docker not found, will install Docker"
        DOCKER_PACKAGES="docker.io"
    fi
    
    # Check if docker-compose is already installed
    if command -v docker-compose >/dev/null 2>&1; then
        log_info "docker-compose is already installed, skipping"
        COMPOSE_PACKAGES=""
    else
        log_info "docker-compose not found, will install"
        COMPOSE_PACKAGES="docker-compose"
    fi
    
    # Install packages (only install what's missing)
    PACKAGES="curl jq cron systemd rsync sqlite3 php-cli php-json php-mbstring php-curl $DOCKER_PACKAGES $COMPOSE_PACKAGES"
    
    apt-get install -y $PACKAGES
    
    # Handle Docker service
    if [ -n "$DOCKER_PACKAGES" ]; then
        # Docker was just installed, start it
        systemctl enable docker
        systemctl start docker
        log_success "Docker installed and started"
    else
        # Docker was already installed, just ensure it's running
        if ! systemctl is-active --quiet docker; then
            systemctl start docker
            log_success "Docker started"
        else
            log_success "Docker already running"
        fi
    fi
    
    log_success "Dependencies installed"
}

# Create system user and directories
setup_user_and_directories() {
    log_info "Setting up user and directories..."
    
    # Create nostrbots user if it doesn't exist
    if ! id "$SERVICE_USER" &>/dev/null; then
        useradd -r -s /bin/bash -d "/opt/nostrbots" -m "$SERVICE_USER"
        log_success "Created user: $SERVICE_USER"
    else
        log_info "User $SERVICE_USER already exists"
    fi
    
    # Create directories
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$DATA_DIR"
    mkdir -p "$LOG_DIR"
    mkdir -p "/opt/nostrbots/scripts"
    mkdir -p "/opt/nostrbots/config"
    
    # Set ownership
    chown -R "$SERVICE_USER:$SERVICE_USER" "/opt/nostrbots"
    chown -R "$SERVICE_USER:$SERVICE_USER" "$LOG_DIR"
    
    log_success "Directories created and configured"
}

# Initialize Docker swarm for secrets
init_docker_swarm() {
    log_info "Initializing Docker swarm for secrets..."
    
    # Check current swarm state
    SWARM_STATE=$(docker info --format '{{.Swarm.LocalNodeState}}' 2>/dev/null || echo "inactive")
    log_info "Current swarm state: $SWARM_STATE"
    
    # Check if swarm is already active
    if [ "$SWARM_STATE" = "active" ]; then
        log_success "Docker swarm already active"
        return 0
    fi
    
    # Initialize swarm if not active
    log_info "Initializing Docker swarm..."
    if docker swarm init --advertise-addr 127.0.0.1; then
        log_success "Docker swarm initialized successfully"
    else
        log_error "Failed to initialize Docker swarm"
        exit 1
    fi
    
    # Verify swarm is now active
    if docker info --format '{{.Swarm.LocalNodeState}}' 2>/dev/null | grep -q "active"; then
        log_success "Docker swarm is now active and ready for secrets"
    else
        log_error "Docker swarm initialization failed - swarm is not active"
        exit 1
    fi
}

# Generate keys directly into Docker secrets
generate_keys_to_secrets() {
    log_info "Generating keys directly into Docker secrets..."
    
    # Copy project files to production directory
    cp -r "$SCRIPT_DIR"/* "/opt/nostrbots/"
    chown -R "$SERVICE_USER:$SERVICE_USER" "/opt/nostrbots"
    
    # Generate keys using PHP script
    cd "/opt/nostrbots"
    
    # Generate keys and capture output
    KEY_OUTPUT=$(php generate-key.php --jenkins --quiet 2>/dev/null)
    
    # Extract the keys from output
    NOSTR_BOT_KEY_ENCRYPTED=$(echo "$KEY_OUTPUT" | grep "NOSTR_BOT_KEY_ENCRYPTED=" | cut -d'=' -f2)
    NOSTR_BOT_NPUB=$(echo "$KEY_OUTPUT" | grep "NOSTR_BOT_NPUB=" | cut -d'=' -f2)
    NOSTR_BOT_KEY_HEX=$(echo "$KEY_OUTPUT" | grep "NOSTR_BOT_KEY_HEX=" | cut -d'=' -f2)
    NOSTR_BOT_NSEC=$(echo "$KEY_OUTPUT" | grep "NOSTR_BOT_NSEC=" | cut -d'=' -f2)
    
    if [ -z "$NOSTR_BOT_KEY_ENCRYPTED" ] || [ -z "$NOSTR_BOT_NPUB" ] || [ -z "$NOSTR_BOT_KEY_HEX" ] || [ -z "$NOSTR_BOT_NSEC" ]; then
        log_error "Failed to generate keys"
        exit 1
    fi
    
    # Remove existing secrets if they exist
    docker secret rm nostr_bot_key_encrypted 2>/dev/null || true
    docker secret rm nostr_bot_npub 2>/dev/null || true
    
    # Create Docker secrets directly
    echo "$NOSTR_BOT_KEY_ENCRYPTED" | docker secret create nostr_bot_key_encrypted - >/dev/null
    echo "$NOSTR_BOT_NPUB" | docker secret create nostr_bot_npub - >/dev/null
    
    log_success "Keys generated and stored in Docker secrets"
    
    echo ""
    log_success "🔑 PRODUCTION KEYS GENERATED"
    echo "================================"
    echo "Nostr Public Key (npub): $NOSTR_BOT_NPUB"
    echo "Hex Private Key: ${NOSTR_BOT_KEY_HEX:0:10}..."
    echo "Encrypted Private Key: ${NOSTR_BOT_KEY_ENCRYPTED:0:10}..."
    echo ""
    
    # Display the nsec (bech32 private key) for user to copy
    secure_display_nsec "$NOSTR_BOT_NSEC"
    
    # Create an encrypted key backup
    log_info "Creating encrypted key backup..."
    BACKUP_FILE="$BACKUP_DIR/nostrbots-keys-$(date +%Y%m%d-%H%M%S).gpg"
    
    # Create temporary file with keys
    TEMP_FILE=$(mktemp)
    echo "NOSTR_BOT_KEY_ENCRYPTED=$NOSTR_BOT_KEY_ENCRYPTED" > "$TEMP_FILE"
    echo "NOSTR_BOT_NPUB=$NOSTR_BOT_NPUB" >> "$TEMP_FILE"
    
    # Encrypt the backup file
    gpg --symmetric --cipher-algo AES256 --output "$BACKUP_FILE" "$TEMP_FILE"
    
    # Secure cleanup
    shred -u "$TEMP_FILE"
    chown "$SERVICE_USER:$SERVICE_USER" "$BACKUP_FILE"
    chmod 600 "$BACKUP_FILE"
    
    log_success "Encrypted key backup created: $BACKUP_FILE"
    
    # Securely clear environment variables
    secure_cleanup
}

# Securely clear sensitive environment variables
secure_cleanup() {
    log_info "Securely clearing sensitive environment variables..."
    
    # Clear variables
    unset NOSTR_BOT_KEY_ENCRYPTED
    unset NOSTR_BOT_NPUB
    unset NOSTR_BOT_KEY_HEX
    unset NOSTR_BOT_NSEC
    unset DECRYPTED_NSEC
    unset KEY_OUTPUT
    
    # Overwrite with random data (if variables were set)
    if [ -n "$NOSTR_BOT_KEY_ENCRYPTED" ]; then
        NOSTR_BOT_KEY_ENCRYPTED=$(openssl rand -base64 32)
        unset NOSTR_BOT_KEY_ENCRYPTED
    fi
    
    if [ -n "$NOSTR_BOT_NPUB" ]; then
        NOSTR_BOT_NPUB=$(openssl rand -base64 32)
        unset NOSTR_BOT_NPUB
    fi
    
    if [ -n "$NOSTR_BOT_KEY_HEX" ]; then
        NOSTR_BOT_KEY_HEX=$(openssl rand -base64 32)
        unset NOSTR_BOT_KEY_HEX
    fi
    
    if [ -n "$NOSTR_BOT_NSEC" ]; then
        NOSTR_BOT_NSEC=$(openssl rand -base64 32)
        unset NOSTR_BOT_NSEC
    fi
    
    if [ -n "$DECRYPTED_NSEC" ]; then
        DECRYPTED_NSEC=$(openssl rand -base64 32)
        unset DECRYPTED_NSEC
    fi
    
    log_success "Environment variables securely cleared"
}

# Securely display nsec without logging
secure_display_nsec() {
    local nsec="$1"
    
    echo ""
    echo "🔑 YOUR NOSTR PRIVATE KEY (NSEC)"
    echo "================================="
    echo ""
    echo "⚠️  IMPORTANT: Copy this key now - it will not be shown again!"
    echo ""
    echo "Your nsec: $nsec"
    echo ""
    echo "📋 Instructions:"
    echo "1. Copy the nsec above to your clipboard"
    echo "2. Save it securely (password manager, encrypted storage, etc.)"
    echo "3. This key will NOT be displayed again"
    echo "4. Keys are now stored securely in Docker secrets"
    echo ""
    echo "Press ENTER when you have copied and saved the nsec..."
    read -r
    echo ""
    echo "✅ Thank you! The nsec has been securely handled."
    echo ""
}

# Create production docker-compose file with secrets
create_production_compose() {
    log_info "Creating production Docker Compose configuration with secrets..."
    
    cat > "/opt/nostrbots/docker-compose.production.yml" << 'EOF'
version: '3.8'

services:
  orly-relay:
    image: silberengel/next-orly:latest
    container_name: nostrbots-orly-relay
    restart: unless-stopped
    ports:
      - "127.0.0.1:3334:7777"  # Bind to localhost only
    environment:
      - ORLY_LISTEN=0.0.0.0
      - ORLY_PORT=7777
      - ORLY_LOG_LEVEL=info
      - ORLY_MAX_CONNECTIONS=1000
      - ORLY_ACL_MODE=none
    volumes:
      - /opt/nostrbots/data/orly:/data
    secrets:
      - nostr_bot_key_encrypted
      - nostr_bot_npub
    networks:
      - nostrbots-network
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE
    read_only: true
    tmpfs:
      - /tmp
      - /var/run
    healthcheck:
      test: ["CMD", "nc", "-z", "localhost", "7777"]
      interval: 30s
      timeout: 10s
      retries: 5
      start_period: 10s

  jenkins:
    image: jenkins/jenkins:lts
    container_name: nostrbots-jenkins
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:8080"  # Bind to localhost only
      - "127.0.0.1:50000:50000"
    environment:
      - JENKINS_OPTS=--httpPort=8080
      - NOSTR_BOT_KEY_ENCRYPTED_FILE=/run/secrets/nostr_bot_key_encrypted
      - NOSTR_BOT_NPUB_FILE=/run/secrets/nostr_bot_npub
      - NOSTRBOTS_PASSWORD=${NOSTRBOTS_PASSWORD:-nostrbots123}
      - JENKINS_ADMIN_ID=${JENKINS_ADMIN_ID:-admin}
      - JENKINS_ADMIN_PASSWORD=${JENKINS_ADMIN_PASSWORD:-admin}
    volumes:
      - /opt/nostrbots/data/jenkins:/var/jenkins_home
      - /var/run/docker.sock:/var/run/docker.sock
      - /opt/nostrbots:/workspace
      - /opt/nostrbots/scripts/jenkins-setup.groovy:/usr/share/jenkins/ref/init.groovy.d/setup.groovy
    secrets:
      - nostr_bot_key_encrypted
      - nostr_bot_npub
    networks:
      - nostrbots-network
    user: root
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    cap_add:
      - CHOWN
      - SETGID
      - SETUID
      - DAC_OVERRIDE
    read_only: true
    tmpfs:
      - /tmp
      - /var/run
      - /var/jenkins_home/workspace
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 4G
        reservations:
          cpus: '1.0'
          memory: 2G
    command: >
      bash -c "
        apt-get update &&
        apt-get install -y apt-transport-https ca-certificates curl gnupg lsb-release &&
        curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg &&
        echo 'deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian bookworm stable' | tee /etc/apt/sources.list.d/docker.list > /dev/null &&
        apt-get update &&
        apt-get install -y docker-ce-cli &&
        /usr/local/bin/jenkins.sh
      "
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/login"]
      interval: 30s
      timeout: 10s
      retries: 5

  nostrbots-agent:
    image: jenkins/jenkins:lts
    container_name: nostrbots-agent
    restart: unless-stopped
    environment:
      - JENKINS_AGENT_SSH_PUBKEY=${JENKINS_AGENT_SSH_PUBKEY:-}
      - NOSTR_BOT_KEY_ENCRYPTED_FILE=/run/secrets/nostr_bot_key_encrypted
      - NOSTR_BOT_NPUB_FILE=/run/secrets/nostr_bot_npub
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /opt/nostrbots:/workspace
    secrets:
      - nostr_bot_key_encrypted
      - nostr_bot_npub
    networks:
      - nostrbots-network
    depends_on:
      - jenkins
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    cap_add:
      - CHOWN
      - SETGID
      - SETUID
    read_only: true
    tmpfs:
      - /tmp
      - /var/run

  backup-agent:
    image: silberengel/nostrbots:latest
    container_name: nostrbots-backup-agent
    restart: unless-stopped
    environment:
      - NOSTR_BOT_KEY_ENCRYPTED_FILE=/run/secrets/nostr_bot_key_encrypted
      - NOSTR_BOT_NPUB_FILE=/run/secrets/nostr_bot_npub
      - BACKUP_DIR=/backups
      - DATA_DIR=/data
    volumes:
      - /opt/nostrbots/backups:/backups
      - /opt/nostrbots/data:/data
      - /opt/nostrbots:/workspace
    secrets:
      - nostr_bot_key_encrypted
      - nostr_bot_npub
    networks:
      - nostrbots-network
    depends_on:
      - orly-relay
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    read_only: true
    tmpfs:
      - /tmp
      - /var/run
    command: >
      bash -c "
        echo '🔄 Backup agent started' &&
        while true; do
          echo '⏰ Starting daily backup at $$(date)' &&
          /workspace/scripts/backup-relay-data.sh &&
          echo '✅ Backup completed at $$(date)' &&
          sleep 86400
        done
      "

secrets:
  nostr_bot_key_encrypted:
    external: true
  nostr_bot_npub:
    external: true

networks:
  nostrbots-network:
    driver: bridge
    internal: true  # No external access
EOF

    chown "$SERVICE_USER:$SERVICE_USER" "/opt/nostrbots/docker-compose.production.yml"
    log_success "Production Docker Compose file created with secrets"
}

# Create backup script that reads from secrets
create_backup_script() {
    log_info "Creating backup script that reads from secrets..."
    
    cat > "/opt/nostrbots/scripts/backup-relay-data.sh" << 'EOF'
#!/bin/bash

# Nostrbots Relay Data Backup Script
# Reads keys from Docker secrets

set -e

BACKUP_DIR="/backups"
DATA_DIR="/data"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_FILE="$BACKUP_DIR/relay-backup-$TIMESTAMP.json"

# Load keys from Docker secrets
if [ -f "/run/secrets/nostr_bot_key_encrypted" ]; then
    export NOSTR_BOT_KEY_ENCRYPTED=$(cat /run/secrets/nostr_bot_key_encrypted)
fi

if [ -f "/run/secrets/nostr_bot_npub" ]; then
    export NOSTR_BOT_NPUB=$(cat /run/secrets/nostr_bot_npub)
fi

log_info() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $1"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
}

log_success() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1"
}

# Check if Orly relay is running
check_relay() {
    if ! curl -s http://localhost:3334/health >/dev/null 2>&1; then
        log_error "Orly relay is not accessible at localhost:3334"
        return 1
    fi
    log_success "Orly relay is accessible"
}

# Export relay data using nostr-tools or custom script
export_relay_data() {
    log_info "Exporting relay data..."
    
    # Create a simple export script
    cat > /tmp/export_relay.php << 'PHPEOF'
<?php
/**
 * Export all events from Orly relay
 */

function exportRelayData($relayUrl, $outputFile) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode([
                'jsonrpc' => '2.0',
                'method' => 'REQ',
                'params' => [
                    'subscription_id',
                    ['limit' => 10000] // Adjust as needed
                ]
            ])
        ]
    ]);
    
    $response = file_get_contents($relayUrl, false, $context);
    if ($response === false) {
        throw new Exception("Failed to connect to relay");
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from relay");
    }
    
    file_put_contents($outputFile, json_encode($data, JSON_PRETTY_PRINT));
    return true;
}

try {
    $relayUrl = 'http://localhost:3334';
    $outputFile = $argv[1] ?? '/tmp/relay_export.json';
    
    if (exportRelayData($relayUrl, $outputFile)) {
        echo "Relay data exported to: $outputFile\n";
        exit(0);
    } else {
        echo "Failed to export relay data\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
PHPEOF

    # Run the export
    php /tmp/export_relay.php "$BACKUP_FILE"
    
    if [ -f "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ]; then
        log_success "Relay data exported to $BACKUP_FILE"
        
        # Compress the backup
        gzip "$BACKUP_FILE"
        log_success "Backup compressed: $BACKUP_FILE.gz"
        
        # Create a symlink to the latest backup
        ln -sf "$BACKUP_FILE.gz" "$BACKUP_DIR/latest-relay-backup.json.gz"
        
        # Clean up old backups (keep last 30 days)
        find "$BACKUP_DIR" -name "relay-backup-*.json.gz" -mtime +30 -delete
        log_success "Old backups cleaned up"
        
    else
        log_error "Failed to create backup file"
        return 1
    fi
}

# Main backup function
main() {
    log_info "Starting Nostrbots backup process..."
    
    check_relay || exit 1
    export_relay_data || exit 1
    
    log_success "Backup process completed successfully"
}

main "$@"
EOF

    chmod +x "/opt/nostrbots/scripts/backup-relay-data.sh"
    log_success "Backup script created"
}

# Create recovery script for encrypted backups
create_recovery_script() {
    log_info "Creating recovery script for encrypted backups..."
    
    cat > "/opt/nostrbots/scripts/recover-from-encrypted-backup.sh" << 'EOF'
#!/bin/bash

# Nostrbots Encrypted Backup Recovery Script
# Recovers keys from encrypted backup files

set -e

BACKUP_DIR="/opt/nostrbots/backups"

log_info() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $1"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
}

log_success() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1"
}

# List available encrypted backups
list_backups() {
    log_info "Available encrypted backups:"
    ls -la "$BACKUP_DIR"/*.gpg 2>/dev/null || log_info "No encrypted backups found"
}

# Restore from specific encrypted backup
restore_backup() {
    local backup_file="$1"
    
    if [ ! -f "$backup_file" ]; then
        log_error "Backup file not found: $backup_file"
        return 1
    fi
    
    log_info "Restoring from encrypted backup: $backup_file"
    
    # Decrypt the backup
    TEMP_FILE=$(mktemp)
    if gpg --decrypt --output "$TEMP_FILE" "$backup_file"; then
        log_success "Backup decrypted successfully"
        
        # Extract keys
        NOSTR_BOT_KEY_ENCRYPTED=$(grep "NOSTR_BOT_KEY_ENCRYPTED=" "$TEMP_FILE" | cut -d'=' -f2)
        NOSTR_BOT_NPUB=$(grep "NOSTR_BOT_NPUB=" "$TEMP_FILE" | cut -d'=' -f2)
        
        if [ -n "$NOSTR_BOT_KEY_ENCRYPTED" ] && [ -n "$NOSTR_BOT_NPUB" ]; then
            log_success "Keys extracted from backup"
            echo "NOSTR_BOT_KEY_ENCRYPTED: ${NOSTR_BOT_KEY_ENCRYPTED:0:10}..."
            echo "NOSTR_BOT_NPUB: $NOSTR_BOT_NPUB"
            
            # Create new Docker secrets
            log_info "Creating new Docker secrets..."
            docker secret rm nostr_bot_key_encrypted 2>/dev/null || true
            docker secret rm nostr_bot_npub 2>/dev/null || true
            
            echo "$NOSTR_BOT_KEY_ENCRYPTED" | docker secret create nostr_bot_key_encrypted -
            echo "$NOSTR_BOT_NPUB" | docker secret create nostr_bot_npub -
            
            log_success "Docker secrets recreated from backup"
        else
            log_error "Failed to extract keys from backup"
            return 1
        fi
        
        # Secure cleanup
        shred -u "$TEMP_FILE"
    else
        log_error "Failed to decrypt backup file"
        return 1
    fi
}

# Main recovery function
main() {
    if [ $# -eq 0 ]; then
        list_backups
    else
        restore_backup "$1"
    fi
}

main "$@"
EOF

    chmod +x "/opt/nostrbots/scripts/recover-from-encrypted-backup.sh"
    log_success "Recovery script created"
}

# Create systemd service files
create_systemd_services() {
    log_info "Creating systemd service files..."
    
    # Nostrbots main service
    cat > "$SYSTEMD_DIR/nostrbots.service" << EOF
[Unit]
Description=Nostrbots Production Service
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
User=$SERVICE_USER
Group=$SERVICE_USER
WorkingDirectory=/opt/nostrbots
ExecStart=/usr/bin/docker stack deploy -c /opt/nostrbots/docker-compose.production.yml nostrbots
ExecStop=/usr/bin/docker stack rm nostrbots
ExecReload=/usr/bin/docker stack deploy -c /opt/nostrbots/docker-compose.production.yml nostrbots
TimeoutStartSec=300
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
EOF

    # Backup service
    cat > "$SYSTEMD_DIR/nostrbots-backup.service" << EOF
[Unit]
Description=Nostrbots Daily Backup Service
Requires=nostrbots.service
After=nostrbots.service

[Service]
Type=oneshot
User=$SERVICE_USER
Group=$SERVICE_USER
WorkingDirectory=/opt/nostrbots
ExecStart=/opt/nostrbots/scripts/backup-relay-data.sh
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
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
    
    log_success "Systemd services created and enabled"
}

# Create monitoring script
create_monitoring_script() {
    log_info "Creating monitoring script..."
    
    cat > "/opt/nostrbots/scripts/monitor.sh" << 'EOF'
#!/bin/bash

# Nostrbots Monitoring Script
# Checks the health of all services

set -e

log_info() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $1"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
}

log_success() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1"
}

# Check Docker stack
check_stack() {
    log_info "Checking Docker stack..."
    
    if docker stack ls | grep -q "nostrbots"; then
        log_success "Nostrbots stack is deployed"
    else
        log_error "Nostrbots stack is not deployed"
        return 1
    fi
}

# Check Docker services
check_services() {
    log_info "Checking Docker services..."
    
    local services=("nostrbots_orly-relay" "nostrbots_jenkins" "nostrbots_nostrbots-agent" "nostrbots_backup-agent")
    local all_healthy=true
    
    for service in "${services[@]}"; do
        if docker service ls --format "table {{.Name}}\t{{.Replicas}}" | grep -q "$service.*1/1"; then
            log_success "Service $service is running"
        else
            log_error "Service $service is not running properly"
            all_healthy=false
        fi
    done
    
    if [ "$all_healthy" = true ]; then
        log_success "All services are healthy"
        return 0
    else
        log_error "Some services are not healthy"
        return 1
    fi
}

# Check systemd services
check_systemd_services() {
    log_info "Checking systemd services..."
    
    if systemctl is-active --quiet nostrbots.service; then
        log_success "Nostrbots systemd service is active"
    else
        log_error "Nostrbots systemd service is not active"
        return 1
    fi
    
    if systemctl is-active --quiet nostrbots-backup.timer; then
        log_success "Backup timer is active"
    else
        log_error "Backup timer is not active"
        return 1
    fi
}

# Check relay connectivity
check_relay() {
    log_info "Checking relay connectivity..."
    
    if curl -s http://localhost:3334/health >/dev/null 2>&1; then
        log_success "Orly relay is accessible"
    else
        log_error "Orly relay is not accessible"
        return 1
    fi
}

# Check Jenkins
check_jenkins() {
    log_info "Checking Jenkins..."
    
    if curl -s http://localhost:8080/login >/dev/null 2>&1; then
        log_success "Jenkins is accessible"
    else
        log_error "Jenkins is not accessible"
        return 1
    fi
}

# Check Docker secrets
check_secrets() {
    log_info "Checking Docker secrets..."
    
    if docker secret ls | grep -q "nostr_bot_key_encrypted"; then
        log_success "Nostr bot key secret exists"
    else
        log_error "Nostr bot key secret not found"
        return 1
    fi
    
    if docker secret ls | grep -q "nostr_bot_npub"; then
        log_success "Nostr bot npub secret exists"
    else
        log_error "Nostr bot npub secret not found"
        return 1
    fi
}

# Main monitoring function
main() {
    log_info "Starting Nostrbots health check..."
    
    local exit_code=0
    
    check_stack || exit_code=1
    check_services || exit_code=1
    check_systemd_services || exit_code=1
    check_relay || exit_code=1
    check_jenkins || exit_code=1
    check_secrets || exit_code=1
    
    if [ $exit_code -eq 0 ]; then
        log_success "All systems are healthy"
    else
        log_error "Some systems are not healthy"
    fi
    
    exit $exit_code
}

main "$@"
EOF

    chmod +x "/opt/nostrbots/scripts/monitor.sh"
    log_success "Monitoring script created"
}

# Create management script
create_management_script() {
    log_info "Creating management script..."
    
    cat > "/usr/local/bin/nostrbots" << 'EOF'
#!/bin/bash

# Nostrbots Management Script
# Provides easy commands to manage the Nostrbots production environment

set -e

SCRIPT_DIR="/opt/nostrbots"
SERVICE_NAME="nostrbots"

show_help() {
    echo "Nostrbots Production Management"
    echo "=============================="
    echo ""
    echo "Usage: nostrbots <command>"
    echo ""
    echo "Commands:"
    echo "  start       Start all Nostrbots services"
    echo "  stop        Stop all Nostrbots services"
    echo "  restart     Restart all Nostrbots services"
    echo "  status      Show status of all services"
    echo "  logs        Show logs from all containers"
    echo "  monitor     Run health check"
    echo "  backup      Run manual backup"
    echo "  restore     Restore from encrypted backup (requires backup file)"
    echo "  update      Update to latest version"
    echo "  keys        Show current keys"
    echo "  nsec        Show decrypted private key (nsec)"
    echo "  secrets     Show Docker secrets status"
    echo "  help        Show this help message"
    echo ""
}

start_services() {
    echo "🚀 Starting Nostrbots services..."
    systemctl start "$SERVICE_NAME.service"
    echo "✅ Services started"
}

stop_services() {
    echo "🛑 Stopping Nostrbots services..."
    systemctl stop "$SERVICE_NAME.service"
    echo "✅ Services stopped"
}

restart_services() {
    echo "🔄 Restarting Nostrbots services..."
    systemctl restart "$SERVICE_NAME.service"
    echo "✅ Services restarted"
}

show_status() {
    echo "📊 Nostrbots Service Status"
    echo "=========================="
    systemctl status "$SERVICE_NAME.service" --no-pager
    echo ""
    echo "📊 Docker Stack Status"
    echo "====================="
    docker stack ls
    echo ""
    echo "📊 Docker Services Status"
    echo "========================"
    docker service ls
    echo ""
    echo "📊 Backup Timer Status"
    echo "====================="
    systemctl status "$SERVICE_NAME-backup.timer" --no-pager
}

show_logs() {
    echo "📋 Nostrbots Logs"
    echo "================"
    docker service logs nostrbots_jenkins --tail=50
    docker service logs nostrbots_orly-relay --tail=50
}

run_monitor() {
    echo "🔍 Running health check..."
    "$SCRIPT_DIR/scripts/monitor.sh"
}

run_backup() {
    echo "💾 Running manual backup..."
    "$SCRIPT_DIR/scripts/backup-relay-data.sh"
}

restore_backup() {
    if [ -z "$1" ]; then
        echo "❌ Please specify an encrypted backup file"
        echo "Usage: nostrbots restore <encrypted-backup-file.gpg>"
        echo ""
        echo "Available encrypted backups:"
        ls -la /opt/nostrbots/backups/*.gpg 2>/dev/null || echo "No encrypted backups found"
        exit 1
    fi
    echo "🔄 Restoring from encrypted backup: $1"
    /opt/nostrbots/scripts/recover-from-encrypted-backup.sh "$1"
    echo "✅ Encrypted backup restored"
}

update_services() {
    echo "🔄 Updating Nostrbots..."
    cd "$SCRIPT_DIR"
    git pull origin main
    docker stack deploy -c docker-compose.production.yml nostrbots
    echo "✅ Update completed"
}

show_keys() {
    echo "🔑 Current Nostrbots Keys"
    echo "========================"
    echo "Keys are stored securely in Docker secrets"
    echo "Use 'nostrbots secrets' to view secret status"
    echo "Use 'nostrbots nsec' to retrieve decrypted private key"
}

show_nsec() {
    echo "🔑 Nostrbots Private Key (nsec)"
    echo "==============================="
    echo "To securely retrieve your nsec:"
    echo "1. Run: ./retrieve-nsec-secure.sh"
    echo "2. Copy the nsec when displayed"
    echo "3. Save it securely (password manager, etc.)"
    echo ""
    echo "Note: The nsec is not stored in environment variables"
    echo "It must be retrieved from Docker secrets when needed"
}

show_secrets() {
    echo "🔐 Docker Secrets Status"
    echo "======================="
    docker secret ls
    echo ""
    echo "🔐 Secret Details"
    echo "================"
    docker secret inspect nostr_bot_key_encrypted 2>/dev/null || echo "❌ nostr_bot_key_encrypted secret not found"
    docker secret inspect nostr_bot_npub 2>/dev/null || echo "❌ nostr_bot_npub secret not found"
}

# Main command handling
case "${1:-help}" in
    "start")
        start_services
        ;;
    "stop")
        stop_services
        ;;
    "restart")
        restart_services
        ;;
    "status")
        show_status
        ;;
    "logs")
        show_logs
        ;;
    "monitor")
        run_monitor
        ;;
    "backup")
        run_backup
        ;;
    "restore")
        restore_backup "$2"
        ;;
    "update")
        update_services
        ;;
    "keys")
        show_keys
        ;;
    "nsec")
        show_nsec
        ;;
    "secrets")
        show_secrets
        ;;
    "help"|*)
        show_help
        ;;
esac
EOF

    chmod +x "/usr/local/bin/nostrbots"
    log_success "Management script created at /usr/local/bin/nostrbots"
}

# Start services
start_services() {
    log_info "Starting Nostrbots services..."
    
    # Start the main service
    systemctl start nostrbots.service
    
    # Wait for services to be ready
    log_info "Waiting for services to start..."
    sleep 30
    
    # Check if services are running
    if systemctl is-active --quiet nostrbots.service; then
        log_success "Nostrbots service started successfully"
    else
        log_error "Failed to start Nostrbots service"
        systemctl status nostrbots.service
        exit 1
    fi
    
    # Start backup timer
    systemctl start nostrbots-backup.timer
    log_success "Backup timer started"
}

# Main setup function
main() {
    echo "🚀 Nostrbots Production Setup (Secrets Only)"
    echo "============================================="
    echo ""
    
    check_root
    install_dependencies
    setup_user_and_directories
    init_docker_swarm
    generate_keys_to_secrets
    create_production_compose
    create_backup_script
    create_recovery_script
    create_systemd_services
    create_monitoring_script
    create_management_script
    start_services
    
    echo ""
    log_success "🎉 Production setup completed successfully!"
    echo ""
    echo "📋 Next Steps:"
    echo "=============="
    echo "1. Retrieve your nsec: nostrbots nsec"
    echo "2. Copy and save the nsec securely"
    echo "3. Access Jenkins at: http://localhost:8080"
    echo "4. Login with: admin / admin"
    echo "5. Install the plugins you want to use"
    echo "6. Change the admin password"
    echo "7. Create the 'nostrbots-pipeline' job manually"
    echo "8. Use 'nostrbots status' to check service status"
    echo "9. Use 'nostrbots monitor' to run health checks"
    echo "10. Use 'nostrbots backup' for manual backups"
    echo ""
    echo "🔧 Management Commands:"
    echo "======================"
    echo "nostrbots start     - Start all services"
    echo "nostrbots stop      - Stop all services"
    echo "nostrbots restart   - Restart all services"
    echo "nostrbots status    - Show service status"
    echo "nostrbots logs      - Show container logs"
    echo "nostrbots monitor   - Run health check"
    echo "nostrbots backup    - Manual backup"
    echo "nostrbots keys      - Show current keys"
    echo "nostrbots nsec      - Show decrypted private key"
    echo "nostrbots secrets   - Show Docker secrets status"
    echo ""
    echo "📁 Important Directories:"
    echo "========================"
    echo "Service files: /opt/nostrbots/"
    echo "Backups: $BACKUP_DIR"
    echo "Data: $DATA_DIR"
    echo "Logs: $LOG_DIR"
    echo ""
    log_warning "⚠️  Remember to retrieve and save your nsec securely!"
    log_warning "⚠️  Keys are stored securely in Docker secrets (no files)"
    log_warning "⚠️  The system will auto-start on boot"
}

# Handle command line arguments
case "${1:-}" in
    "help"|"-h"|"--help")
        echo "Nostrbots Production Setup Script (Secrets Only)"
        echo "Usage: sudo $0 [command]"
        echo ""
        echo "Commands:"
        echo "  (none)    Run full production setup with Docker secrets"
        echo "  help      Show this help message"
        ;;
    *)
        main
        ;;
esac
