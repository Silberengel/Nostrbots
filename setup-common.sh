#!/bin/bash

# Nostrbots Common Setup Functions
# Shared functionality for all setup scripts

# Source shared security utilities
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/security-utils.sh"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%dT%H:%M:%S%z')]${NC} $1"
}

log_info() {
    echo -e "${BLUE}[$(date '+%Y-%m-%dT%H:%M:%S%z')]${NC} ℹ️  $1"
}

log_success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%dT%H:%M:%S%z')]${NC} ✅ $1"
}

log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%dT%H:%M:%S%z')]${NC} ❌ $1"
}

log_warn() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%dT%H:%M:%S%z')]${NC} ⚠️  $1"
}

warn() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

error() {
    echo -e "${RED}❌ $1${NC}"
    exit 1
}

info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "This script must be run as root for production setup"
        exit 1
    fi
}

# Check if NOT running as root (for local dev)
check_not_root() {
    if [ "$EUID" -eq 0 ]; then
        warn "Running as root. Consider running as regular user for development."
    fi
}

# Check dependencies
check_dependencies() {
    log "Checking dependencies"
    
    local missing_deps=()
    
    # Check for required commands
    for cmd in curl jq php composer docker; do
        if ! command -v "$cmd" >/dev/null 2>&1; then
            missing_deps+=("$cmd")
        fi
    done
    
    if [ ${#missing_deps[@]} -eq 0 ]; then
        log_success "All dependencies are available"
        return 0
    else
        log_error "Missing dependencies: ${missing_deps[*]}"
        return 1
    fi
}

# Install PHP dependencies
install_php_dependencies() {
    log "Installing PHP dependencies"
    
    if [ ! -f "composer.json" ]; then
        log_error "composer.json not found. Are you in the right directory?"
        exit 1
    fi
    
    composer install --no-dev --optimize-autoloader
    log_success "PHP dependencies installed"
}

# Install system dependencies (for production)
install_system_dependencies() {
    log_info "Installing system dependencies..."
    
    # Update package list
    apt-get update
    
    # Check if Docker is already installed
    if command -v docker >/dev/null 2>&1; then
        log_info "Docker is already installed"
        DOCKER_PACKAGES=""
    else
        log_info "Docker not found, will install"
        DOCKER_PACKAGES="docker-ce docker-ce-cli containerd.io"
    fi
    
    # Check if docker-compose is already installed
    if command -v docker-compose >/dev/null 2>&1; then
        log_info "docker-compose is already installed, skipping"
        COMPOSE_PACKAGES=""
    else
        log_info "docker-compose not found, will install"
        COMPOSE_PACKAGES="docker-compose"
    fi
    
    # Install packages
    PACKAGES="curl jq cron systemd rsync sqlite3 php-cli php-json php-mbstring php-curl $DOCKER_PACKAGES $COMPOSE_PACKAGES"
    apt-get install -y $PACKAGES
    
    # Enable and start Docker if it was just installed
    if [ -n "$DOCKER_PACKAGES" ]; then
        systemctl enable docker
        systemctl start docker
        log_success "Docker installed and started"
    elif systemctl is-active --quiet docker; then
        log_success "Docker is already running"
    else
        systemctl start docker
        log_success "Docker started"
    fi
    
    log_success "System dependencies installed"
}

# Setup Nostr keys
setup_nostr_keys() {
    log "Setting up Nostr keys"
    
    local encrypted_key
    local npub
    
    # Check if custom private key was provided
    if [ -n "${CUSTOM_PRIVATE_KEY:-}" ]; then
        # Check security and warn if needed
        check_command_line_security "$@"
        log "Using provided custom private key"
        
        # Validate the private key format (should be hex or nsec)
        if [[ "$CUSTOM_PRIVATE_KEY" =~ ^[0-9a-fA-F]{64}$ ]] || [[ "$CUSTOM_PRIVATE_KEY" =~ ^nsec1 ]]; then
            # Use the generate-key.php script with the custom key
            local key_output
            key_output=$(php generate-key.php --key "$CUSTOM_PRIVATE_KEY" --jenkins --quiet 2>/dev/null)
            
            if [ $? -ne 0 ]; then
                error "Failed to process custom private key"
            fi
            
            # Extract keys from output
            encrypted_key=$(echo "$key_output" | grep "NOSTR_BOT_KEY_ENCRYPTED=" | cut -d'=' -f2- | tr -d '"')
            npub=$(echo "$key_output" | grep "NOSTR_BOT_NPUB=" | cut -d'=' -f2- | tr -d '"')
            
            if [ -z "$encrypted_key" ] || [ -z "$npub" ]; then
                error "Failed to extract keys from custom key processing"
            fi
        else
            error "Invalid private key format. Please provide either a 64-character hex key or an nsec1... key"
        fi
        
    else
        # Generate new keys using PHP script
        log "Generating new Nostr keys"
        local key_output
        key_output=$(php generate-key.php --jenkins --quiet 2>/dev/null)
        
        if [ $? -ne 0 ]; then
            error "Failed to generate keys"
        fi
        
        # Extract keys from output
        encrypted_key=$(echo "$key_output" | grep "NOSTR_BOT_KEY_ENCRYPTED=" | cut -d'=' -f2- | tr -d '"')
        npub=$(echo "$key_output" | grep "NOSTR_BOT_NPUB=" | cut -d'=' -f2- | tr -d '"')
        
        if [ -z "$encrypted_key" ] || [ -z "$npub" ]; then
            error "Failed to extract keys from generation output"
        fi
    fi
    
    # Return the keys for the calling script to use
    echo "ENCRYPTED_KEY=$encrypted_key"
    echo "NPUB=$npub"
}

# Parse command line arguments
parse_common_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --private-key)
                CUSTOM_PRIVATE_KEY="$2"
                shift 2
                ;;
            --help|-h)
                show_help
                exit 0
                ;;
            *)
                # Unknown option - let the calling script handle it
                break
                ;;
        esac
    done
}

# Show common help
show_common_help() {
    echo "Options:"
    echo "  --private-key KEY    Use your existing Nostr private key (hex or nsec format)"
    echo "  --help, -h          Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Generate new keys"
    echo "  $0 --private-key abc123...           # Use hex private key"
    echo "  $0 --private-key nsec1abc123...      # Use nsec private key"
    echo ""
    echo "Private Key Formats:"
    echo "  - Hex: 64-character hexadecimal string (e.g., abc123def456...)"
    echo "  - Nsec: Bech32 encoded private key (e.g., nsec1abc123...)"
    echo ""
    echo "⚠️  SECURITY WARNING:"
    echo "  Command line arguments may be stored in shell history!"
    echo "  For better security, use environment variables instead:"
    echo "    export CUSTOM_PRIVATE_KEY='your_key_here'"
    echo "    $0"
    echo ""
}

# Initialize common setup
init_common_setup() {
    # Set up security cleanup trap
    setup_security_trap
    
    # Parse common arguments
    parse_common_arguments "$@"
}
