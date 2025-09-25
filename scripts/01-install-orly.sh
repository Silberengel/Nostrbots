#!/bin/bash

# 01-install-orly.sh
# Installs and sets up the Orly Nostr relay
# Can be run independently or as part of the complete setup
#
# Usage: bash scripts/01-install-orly.sh [ORLY_PORT]
#   ORLY_PORT: Port for Orly relay (default: 3334)

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[01-INSTALL-ORLY]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

# Parse command line arguments
ORLY_PORT=${1:-3334}

print_status "Installing Orly Nostr relay..."
print_status "Orly port: $ORLY_PORT"

# Check if we're in the right directory
if [ ! -f "src/relays.yml" ]; then
    print_error "Please run this script from the Nostrbots project root directory"
    exit 1
fi

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again"
    exit 1
fi

# Check if Orly is already running
if curl -s http://localhost:$ORLY_PORT > /dev/null 2>&1; then
    print_warning "Orly appears to be already running on port $ORLY_PORT"
    print_status "Stopping existing Orly container..."
    docker stop orly-relay 2>/dev/null || true
    docker rm orly-relay 2>/dev/null || true
fi

# Create Orly data directory
print_status "Creating Orly data directory..."
mkdir -p orly-data

# Create Docker Compose file for Orly
print_status "Creating Orly Docker Compose configuration..."
cat > docker-compose.orly.yml << EOF
services:
  orly:
    image: mleku/orly:latest
    container_name: orly-relay
    ports:
      - "$ORLY_PORT:3334"
    volumes:
      - ./orly-data:/app/data
    environment:
      - PORT=3334
      - NOSTR_RELAY_NAME=Local Orly Relay
      - NOSTR_RELAY_DESCRIPTION=Local test relay for Nostrbots
    networks:
      - orly-network
    restart: unless-stopped

networks:
  orly-network:
    driver: bridge
EOF

# Start Orly
print_status "Starting Orly relay..."
docker compose -f docker-compose.orly.yml up -d orly

# Wait for Orly to start
print_status "Waiting for Orly to start..."
timeout=60
counter=0
while ! curl -s http://localhost:$ORLY_PORT > /dev/null 2>&1; do
    if [ $counter -ge $timeout ]; then
        print_error "Orly failed to start within $timeout seconds"
        print_status "Checking Orly logs..."
        docker logs orly-relay
        exit 1
    fi
    sleep 2
    counter=$((counter + 2))
    echo -n "."
done
echo ""

print_success "Orly is running!"

# Save port configuration for other scripts
echo "ORLY_PORT=$ORLY_PORT" > .orly-ports
chmod 600 .orly-ports

print_success "Orly installation complete!"
print_status "Orly URL: http://localhost:$ORLY_PORT"
print_status "Port configuration saved to .orly-ports"
