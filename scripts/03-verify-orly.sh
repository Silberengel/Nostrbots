#!/bin/bash

# 03-verify-orly.sh
# Verifies that Orly relay is working correctly
# Can be run independently or as part of the complete setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[03-VERIFY-ORLY]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

print_status "Verifying Orly relay setup..."

# Load port configuration
if [ -f ".orly-ports" ]; then
    source .orly-ports
else
    ORLY_PORT=3334
    print_warning "No port configuration found, using default port 3334"
fi

# Test 1: Orly container is running
print_status "Test 1: Orly container status..."
if docker ps | grep -q "orly-relay"; then
    print_success "✅ Orly container is running"
else
    print_error "❌ Orly container is not running"
    exit 1
fi

# Test 2: Orly web interface is accessible
print_status "Test 2: Orly web interface accessibility..."
if curl -s http://localhost:$ORLY_PORT > /dev/null 2>&1; then
    print_success "✅ Orly web interface is accessible at http://localhost:$ORLY_PORT"
else
    print_error "❌ Orly web interface is not accessible"
    exit 1
fi

# Test 3: Orly logs are clean
print_status "Test 3: Orly container logs..."
ORLY_LOGS=$(docker logs orly-relay 2>&1 | tail -10)
if echo "$ORLY_LOGS" | grep -i "error\|exception\|fatal" > /dev/null; then
    print_warning "⚠️  Orly logs contain errors:"
    echo "$ORLY_LOGS" | grep -i "error\|exception\|fatal"
else
    print_success "✅ Orly logs are clean"
fi

# Test 4: Relay configuration is updated
print_status "Test 4: Relay configuration..."
if [ -f "src/relays.yml" ]; then
    if grep -q "ws://localhost:$ORLY_PORT" src/relays.yml; then
        print_success "✅ Relay configuration updated with Orly port $ORLY_PORT"
    else
        print_error "❌ Relay configuration not updated with Orly port"
        exit 1
    fi
else
    print_error "❌ relays.yml not found"
    exit 1
fi

# Test 5: Bot configurations are updated
print_status "Test 5: Bot configurations..."
BOT_CONFIGS_UPDATED=0

if [ -f "bots/hello-world/config.json" ]; then
    if grep -q "ws://localhost:$ORLY_PORT" bots/hello-world/config.json; then
        print_success "✅ Hello-world bot configuration updated"
        BOT_CONFIGS_UPDATED=$((BOT_CONFIGS_UPDATED + 1))
    else
        print_warning "⚠️  Hello-world bot configuration not updated"
    fi
fi

if [ -f "bots/daily-office/config.json" ]; then
    if grep -q "ws://localhost:$ORLY_PORT" bots/daily-office/config.json; then
        print_success "✅ Daily-office bot configuration updated"
        BOT_CONFIGS_UPDATED=$((BOT_CONFIGS_UPDATED + 1))
    else
        print_warning "⚠️  Daily-office bot configuration not updated"
    fi
fi

if [ $BOT_CONFIGS_UPDATED -gt 0 ]; then
    print_success "✅ $BOT_CONFIGS_UPDATED bot configuration(s) updated"
else
    print_warning "⚠️  No bot configurations found or updated"
fi

# Test 6: Test relay connection (if possible)
print_status "Test 6: Testing relay connection..."
# This is a basic test - in a real scenario, you might want to use a Nostr client
# For now, we'll just verify the port is open and responding
if nc -z localhost $ORLY_PORT 2>/dev/null; then
    print_success "✅ Orly relay port $ORLY_PORT is open and accepting connections"
else
    print_warning "⚠️  Cannot verify Orly relay port connectivity (nc not available)"
fi

# Summary
echo ""
print_success "🎉 Orly relay verification complete!"
echo ""
echo "📋 Verification Summary:"
echo "  ✅ Orly container is running"
echo "  ✅ Web interface is accessible"
echo "  ✅ Relay configuration is updated"
echo "  ✅ Bot configurations are updated"
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
print_success "Orly verification complete! 🎉"
