#!/bin/bash

# setup-orly-complete.sh
# Master script that runs all Orly setup steps in sequence
# This script orchestrates the complete Orly relay setup process
#
# Usage: bash scripts/setup-orly-complete.sh [ORLY_PORT]
#   ORLY_PORT: Port for Orly relay (default: 3334)

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[SETUP-ORLY]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_header() { echo -e "${PURPLE}[ORLY SETUP]${NC} $1"; }

# Parse command line arguments
ORLY_PORT=${1:-3334}

print_header "Complete Orly Relay Setup for Nostrbots"
echo "============================================="
echo "Orly Port: $ORLY_PORT"
echo ""

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

# Function to run a script and handle errors
run_script() {
    local script_name="$1"
    local script_path="scripts/$script_name"
    
    if [ ! -f "$script_path" ]; then
        print_error "Script not found: $script_path"
        exit 1
    fi
    
    print_status "Running $script_name..."
    echo "----------------------------------------"
    
    if bash "$script_path" $ORLY_PORT; then
        print_success "$script_name completed successfully"
        echo ""
    else
        print_error "$script_name failed"
        echo ""
        print_error "Setup failed at step: $script_name"
        print_status "You can run individual scripts to debug:"
        echo "  bash scripts/$script_name $ORLY_PORT"
        exit 1
    fi
}

# Step 1: Install Orly relay
print_header "Step 1: Install Orly Relay"
echo "=============================="
run_script "01-install-orly.sh"

# Step 2: Configure Orly and update Nostrbots
print_header "Step 2: Configure Orly and Update Nostrbots"
echo "================================================"
run_script "02-configure-orly.sh"

# Step 3: Verify Orly setup
print_header "Step 3: Verify Orly Setup"
echo "============================="
run_script "03-verify-orly.sh"

# Final summary
echo ""
print_header "🎉 Complete Orly Setup Finished!"
echo "===================================="
echo ""
print_success "All Orly setup steps completed successfully!"
echo ""
echo "📋 What was set up:"
echo "  ✅ Orly Nostr relay installed and running"
echo "  ✅ Relay configuration updated in Nostrbots"
echo "  ✅ Bot configurations updated to use local relay"
echo "  ✅ All components verified and working"
echo ""
echo "🔗 Orly Information:"
echo "  Web Interface: http://localhost:$ORLY_PORT"
echo "  WebSocket URL: ws://localhost:$ORLY_PORT"
echo "  Container: orly-relay"
echo ""
echo "🧪 Test the Setup:"
echo "  1. Visit http://localhost:$ORLY_PORT to see the relay interface"
echo "  2. Run a bot to test publishing to the local relay"
echo "  3. Check the relay for published events"
echo ""
echo "🔧 Management Commands:"
echo "  # View Orly logs"
echo "  docker logs orly-relay"
echo ""
echo "  # Stop Orly"
echo "  docker compose -f docker-compose.orly.yml down"
echo ""
echo "  # Restart Orly"
echo "  docker compose -f docker-compose.orly.yml restart"
echo ""
echo "  # Run individual setup steps"
echo "  bash scripts/01-install-orly.sh $ORLY_PORT"
echo "  bash scripts/02-configure-orly.sh"
echo "  bash scripts/03-verify-orly.sh"
echo ""
print_success "Orly setup complete! Happy relaying! 📡"
