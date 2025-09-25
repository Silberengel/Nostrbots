#!/bin/bash

# test-next-orly-docker.sh
# Tests the next-orly Docker image setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[TEST]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

# Default values
IMAGE_NAME="silberengel/next-orly"
TAG="latest"
TEST_MODE="full"

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --tag)
            TAG="$2"
            shift 2
            ;;
        --quick)
            TEST_MODE="quick"
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [--tag TAG] [--quick]"
            echo ""
            echo "Options:"
            echo "  --tag TAG     Docker image tag to test (default: latest)"
            echo "  --quick       Run quick test only (no full container test)"
            echo "  --help, -h    Show this help message"
            echo ""
            echo "Test modes:"
            echo "  full          Full test including container startup and bot execution"
            echo "  quick         Quick test of image build and basic functionality"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

print_status "Testing next-orly Docker image..."
print_status "Image: $IMAGE_NAME:$TAG"
print_status "Test mode: $TEST_MODE"

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again"
    exit 1
fi

# Check if image exists
if ! docker images $IMAGE_NAME:$TAG | grep -q $TAG; then
    print_error "Image $IMAGE_NAME:$TAG not found. Please build it first:"
    print_status "bash scripts/build-next-orly-docker.sh --tag $TAG"
    exit 1
fi

print_success "Image found: $IMAGE_NAME:$TAG"

# Test 1: Basic image functionality
print_status "Test 1: Basic image functionality..."
if docker run --rm $IMAGE_NAME:$TAG php --version > /dev/null 2>&1; then
    print_success "✅ PHP is working"
else
    print_error "❌ PHP test failed"
    exit 1
fi

if docker run --rm $IMAGE_NAME:$TAG next-orly --help > /dev/null 2>&1; then
    print_success "✅ next-orly binary is working"
else
    print_error "❌ next-orly binary test failed"
    exit 1
fi

# Test 2: Nostrbots functionality
print_status "Test 2: Nostrbots functionality..."
if docker run --rm $IMAGE_NAME:$TAG php nostrbots.php --help > /dev/null 2>&1; then
    print_success "✅ Nostrbots CLI is working"
else
    print_error "❌ Nostrbots CLI test failed"
    exit 1
fi

# Test 3: Bot configuration
print_status "Test 3: Bot configuration..."
if docker run --rm $IMAGE_NAME:$TAG test -f bots/hello-world/config.json; then
    print_success "✅ Hello world bot configuration exists"
else
    print_error "❌ Hello world bot configuration missing"
    exit 1
fi

# Quick test mode - exit here
if [ "$TEST_MODE" = "quick" ]; then
    print_success "🎉 Quick test completed successfully!"
    print_status "All basic functionality tests passed"
    exit 0
fi

# Test 4: Full container test with timeout
print_status "Test 4: Full container startup test..."
print_warning "This test will start the full container and may take up to 2 minutes"

# Check if encrypted key is available
if [ -z "$NOSTR_BOT_KEY_ENCRYPTED" ]; then
    print_warning "No encrypted key found in environment"
    print_status "Generating test key..."
    if ! bash scripts/01-generate-key.sh; then
        print_error "Failed to generate test key"
        exit 1
    fi
    # Source the generated key
    eval $(bash scripts/01-generate-key.sh)
fi

# Create a test container that runs for a limited time
CONTAINER_ID=$(docker run -d -p 3334:3334 \
  -e NOSTR_BOT_KEY_ENCRYPTED="$NOSTR_BOT_KEY_ENCRYPTED" \
  --name test-next-orly-$RANDOM $IMAGE_NAME:$TAG)

# Wait for container to start and check logs
print_status "Waiting for container to start..."
timeout=120
counter=0
SUCCESS=false

while [ $counter -lt $timeout ]; do
    if docker logs $CONTAINER_ID 2>&1 | grep -q "next-orly is running"; then
        print_success "✅ next-orly relay started successfully"
        SUCCESS=true
        break
    fi
    
    if docker logs $CONTAINER_ID 2>&1 | grep -q "ERROR\|FAILED\|Failed"; then
        print_error "❌ Container startup failed"
        docker logs $CONTAINER_ID
        break
    fi
    
    sleep 2
    counter=$((counter + 2))
    echo -n "."
done

echo ""

# Test 5: Relay connectivity
if [ "$SUCCESS" = true ]; then
    print_status "Test 5: Relay connectivity..."
    sleep 5  # Give relay time to fully start
    
    if curl -s http://localhost:3334 > /dev/null 2>&1; then
        print_success "✅ Relay is accessible on port 3334"
    else
        print_warning "⚠️  Relay not accessible (this might be expected)"
    fi
fi

# Cleanup
print_status "Cleaning up test container..."
docker stop $CONTAINER_ID > /dev/null 2>&1 || true
docker rm $CONTAINER_ID > /dev/null 2>&1 || true

# Final results
echo ""
if [ "$SUCCESS" = true ]; then
    print_success "🎉 Full test completed successfully!"
    print_status "The Docker image is working correctly"
    echo ""
    print_status "Next steps:"
    print_status "  1. Run the container: docker run -p 3334:3334 $IMAGE_NAME:$TAG"
    print_status "  2. Or use docker-compose: cp docker-compose.example.yml docker-compose.yml && docker-compose up"
    print_status "  3. Access the relay at: http://localhost:3334"
else
    print_error "❌ Full test failed"
    print_status "Check the container logs above for details"
    exit 1
fi
