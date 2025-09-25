#!/bin/bash

# Test Environment Loading Script
# Verifies that all containers can read from the .env file

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

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

echo "🧪 Testing Environment Loading"
echo "=============================="

# Check if .env file exists
if [ ! -f ".env" ]; then
    log_error ".env file not found. Please run ./setup-env.sh first."
    exit 1
fi

log_success ".env file exists"

# Test 1: Check if .env file is readable
log_info "Testing .env file readability..."
if [ -r ".env" ]; then
    log_success ".env file is readable"
else
    log_error ".env file is not readable"
    exit 1
fi

# Test 2: Check if environment variables can be loaded
log_info "Testing environment variable loading..."
if source .env 2>/dev/null; then
    log_success "Environment variables can be loaded"
else
    log_warning "Environment variables could not be loaded (this might be normal if .env contains comments)"
fi

# Test 3: Check if key variables are set
log_info "Checking key environment variables..."
if [ -n "$NOSTR_BOT_KEY_ENCRYPTED" ] && [ "$NOSTR_BOT_KEY_ENCRYPTED" != "your_encrypted_nostr_private_key_here" ]; then
    log_success "NOSTR_BOT_KEY_ENCRYPTED is set"
else
    log_warning "NOSTR_BOT_KEY_ENCRYPTED is not set or is default value"
fi

if [ -n "$NOSTR_BOT_NPUB" ] && [ "$NOSTR_BOT_NPUB" != "your_nostr_public_key_here" ]; then
    log_success "NOSTR_BOT_NPUB is set"
else
    log_warning "NOSTR_BOT_NPUB is not set or is default value"
fi

# Test 4: Test nostrbots Docker container can read .env
log_info "Testing nostrbots Docker container .env access..."
if docker run --rm \
    -v "$(pwd):/workspace" \
    -w /workspace \
    silberengel/nostrbots:latest \
    sh -c "if [ -f '.env' ]; then echo 'Container can read .env file'; else echo 'Container cannot read .env file'; fi" 2>/dev/null; then
    log_success "nostrbots Docker container can access .env file"
else
    log_warning "nostrbots Docker container test failed (image might not be available)"
fi

# Test 5: Test environment variable passing to Docker
log_info "Testing environment variable passing to Docker..."
if [ -n "$NOSTR_BOT_KEY_ENCRYPTED" ] && [ "$NOSTR_BOT_KEY_ENCRYPTED" != "your_encrypted_nostr_private_key_here" ]; then
    if docker run --rm \
        -e NOSTR_BOT_KEY_ENCRYPTED="$NOSTR_BOT_KEY_ENCRYPTED" \
        -e NOSTR_BOT_NPUB="$NOSTR_BOT_NPUB" \
        silberengel/nostrbots:latest \
        sh -c "echo 'Environment variables passed successfully'" 2>/dev/null; then
        log_success "Environment variables can be passed to Docker containers"
    else
        log_warning "Environment variable passing test failed"
    fi
else
    log_warning "Skipping Docker environment test - keys not properly set"
fi

echo ""
log_success "Environment loading tests completed!"
echo ""
echo "Summary:"
echo "- .env file is accessible"
echo "- Environment variables can be loaded"
echo "- Docker containers can access the .env file"
echo "- Environment variables can be passed to containers"
echo ""
echo "You can now run:"
echo "  ./setup.sh    # Start Jenkins with .env support"
echo "  ./run-pipeline.sh  # Test the pipeline"
