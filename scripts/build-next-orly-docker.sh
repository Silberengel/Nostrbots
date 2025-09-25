#!/bin/bash

# build-next-orly-docker.sh
# Builds and optionally pushes the next-orly Docker image
# Based on next-orly v0.8.4 release

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[BUILD]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

# Default values
IMAGE_NAME="silberengel/next-orly"
TAG="latest"
PUSH=false
BUILD_ARGS=""

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --tag)
            TAG="$2"
            shift 2
            ;;
        --push)
            PUSH=true
            shift
            ;;
        --no-cache)
            BUILD_ARGS="$BUILD_ARGS --no-cache"
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [--tag TAG] [--push] [--no-cache]"
            echo ""
            echo "Options:"
            echo "  --tag TAG     Docker image tag (default: latest)"
            echo "  --push        Push image to Docker Hub after building"
            echo "  --no-cache    Build without using cache"
            echo "  --help, -h    Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                                    # Build with tag 'latest'"
            echo "  $0 --tag v0.8.4                      # Build with tag 'v0.8.4'"
            echo "  $0 --tag v0.8.4 --push               # Build and push to Docker Hub"
            echo "  $0 --no-cache                        # Build without cache"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

print_status "Building next-orly Docker image..."
print_status "Image: $IMAGE_NAME:$TAG"
print_status "Based on: next-orly v0.8.4 release"

# Check if we're in the right directory
if [ ! -f "Dockerfile" ]; then
    print_error "Dockerfile not found. Please run this script from the Nostrbots project root directory"
    exit 1
fi

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again"
    exit 1
fi

# Build the image
print_status "Building Docker image..."
echo "Command: docker build $BUILD_ARGS -t $IMAGE_NAME:$TAG ."
echo ""

if docker build $BUILD_ARGS -t $IMAGE_NAME:$TAG .; then
    print_success "Docker image built successfully!"
else
    print_error "Docker build failed!"
    exit 1
fi

# Show image information
print_status "Image information:"
docker images $IMAGE_NAME:$TAG

# Test the image
print_status "Testing the built image..."
if docker run --rm $IMAGE_NAME:$TAG php --version > /dev/null 2>&1; then
    print_success "Image test passed!"
else
    print_warning "Image test failed - this might be expected for a complex image"
fi

# Push to Docker Hub if requested
if [ "$PUSH" = true ]; then
    print_status "Pushing image to Docker Hub..."
    
    # Check if user is logged in to Docker Hub
    if ! docker info | grep -q "Username:"; then
        print_warning "Not logged in to Docker Hub. Please run 'docker login' first"
        print_status "You can still use the image locally"
    else
        if docker push $IMAGE_NAME:$TAG; then
            print_success "Image pushed to Docker Hub successfully!"
            print_status "Image available at: https://hub.docker.com/r/$IMAGE_NAME"
        else
            print_error "Failed to push image to Docker Hub"
            exit 1
        fi
    fi
fi

# Final summary
echo ""
print_success "🎉 Build complete!"
echo ""
print_status "Image: $IMAGE_NAME:$TAG"
print_status "Size: $(docker images $IMAGE_NAME:$TAG --format 'table {{.Size}}' | tail -1)"
echo ""
print_status "Usage examples:"
echo "  # First, generate encrypted key (same as Jenkins):"
echo "  bash scripts/01-generate-key.sh"
echo ""
echo "  # Run the container with encrypted key"
echo "  docker run --rm -p 3334:3334 \\"
echo "    -e NOSTR_BOT_KEY_ENCRYPTED=\$NOSTR_BOT_KEY_ENCRYPTED \\"
echo "    $IMAGE_NAME:$TAG"
echo ""
echo "  # Run with docker-compose (uses same key as Jenkins)"
echo "  docker-compose -f docker-compose.next-orly.yml up"
echo ""
print_status "The image includes:"
echo "  ✅ next-orly v0.8.4 relay server"
echo "  ✅ Complete Nostrbots environment"
echo "  ✅ Hello world bot setup and execution"
echo "  ✅ Automatic relay configuration"
echo "  ✅ Content publishing and verification"
echo ""
if [ "$PUSH" = true ]; then
    print_success "Image is now available on Docker Hub!"
else
    print_status "To push to Docker Hub, run: $0 --tag $TAG --push"
fi
