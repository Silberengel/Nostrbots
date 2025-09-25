#!/bin/bash

# 02-configure-orly.sh
# Configures Orly relay and updates Nostrbots configuration
# Can be run independently or as part of the complete setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[02-CONFIGURE-ORLY]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

print_status "Configuring Orly relay and updating Nostrbots configuration..."

# Load port configuration
if [ -f ".orly-ports" ]; then
    source .orly-ports
else
    ORLY_PORT=3334
    print_warning "No port configuration found, using default port 3334"
fi

# Check if Orly is running
if ! curl -s http://localhost:$ORLY_PORT > /dev/null 2>&1; then
    print_error "Orly is not running on localhost:$ORLY_PORT"
    print_error "Please run 01-install-orly.sh first"
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "src/relays.yml" ]; then
    print_error "Please run this script from the Nostrbots project root directory"
    exit 1
fi

# Update relays.yml with the actual Orly port
print_status "Updating relays.yml with Orly port configuration..."
if [ -f "src/relays.yml" ]; then
    # Create backup
    cp src/relays.yml src/relays.yml.backup
    
    # Replace $ORLY_PORT with actual port
    sed -i "s/\$ORLY_PORT/$ORLY_PORT/g" src/relays.yml
    
    print_success "Updated relays.yml with Orly port $ORLY_PORT"
else
    print_error "relays.yml not found"
    exit 1
fi

# Test Orly relay connection
print_status "Testing Orly relay connection..."
ORLY_WS_URL="ws://localhost:$ORLY_PORT"

# Create a simple test to verify the relay is working
print_status "Verifying Orly relay is accepting connections..."
if curl -s http://localhost:$ORLY_PORT | grep -i "orly\|nostr\|relay" > /dev/null 2>&1; then
    print_success "Orly relay is responding correctly"
else
    print_warning "Orly relay may not be fully configured yet"
fi

# Update bot configurations to use local Orly relay
print_status "Updating bot configurations to use local Orly relay..."

# Update hello-world bot config
if [ -f "bots/hello-world/config.json" ]; then
    print_status "Updating hello-world bot configuration..."
    # Create backup
    cp bots/hello-world/config.json bots/hello-world/config.json.backup
    
    # Update the config to use local Orly relay
    cat > bots/hello-world/config.json << EOF
{
  "name": "Hello World Bot (Local Orly Test)",
  "author": "Nostrbots Test",
  "version": "1.0.0",
  "schedule": "*/5 * * * *",
  "relays": [
    "ws://localhost:$ORLY_PORT",
    "wss://freelay.sovbit.host"
  ],
  "content_kind": "30023"
}
EOF
    print_success "Updated hello-world bot configuration"
fi

# Update daily-office bot config
if [ -f "bots/daily-office/config.json" ]; then
    print_status "Updating daily-office bot configuration..."
    # Create backup
    cp bots/daily-office/config.json bots/daily-office/config.json.backup
    
    # Update the config to include local Orly relay
    cat > bots/daily-office/config.json << EOF
{
  "name": "Daily Office Bot",
  "author": "Nostrbots",
  "version": "1.0.0",
  "schedule": "0 6,18 * * *",
  "relays": [
    "ws://localhost:$ORLY_PORT",
    "wss://thecitadel.nostr1.com",
    "wss://orly-relay.imwald.eu"
  ],
  "content_kind": "30023"
}
EOF
    print_success "Updated daily-office bot configuration"
fi

print_success "Orly configuration complete!"
print_status "Orly relay URL: ws://localhost:$ORLY_PORT"
print_status "Bot configurations updated to use local Orly relay"
