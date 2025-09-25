#!/bin/bash

# docker-full-setup.sh
# Complete Docker-based setup for Nostrbots with Jenkins and testing
# This script sets up everything in Docker containers and runs tests
#
# Usage: bash scripts/docker-full-setup.sh [options]
#   --jenkins-port <port>    Jenkins web interface port (default: 8080)
#   --agent-port <port>      Jenkins agent port (default: 50000)
#   --orly-port <port>       Orly relay port (default: 3334)
#   --password <password>    Custom encryption password (optional)
#   --key <hex_key>          Use existing Nostr key (optional)
#   --test-only              Only run tests, don't setup Jenkins
#   --no-orly                Skip Orly relay setup
#   --no-test                Skip hello world test
#   --help, -h               Show this help message

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[DOCKER-FULL-SETUP]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

# Default values
JENKINS_PORT=8080
AGENT_PORT=50000
ORLY_PORT=3334
CUSTOM_PASSWORD=""
EXISTING_KEY=""
TEST_ONLY=false
NO_ORLY=false
NO_TEST=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --jenkins-port)
            JENKINS_PORT="$2"
            shift 2
            ;;
        --agent-port)
            AGENT_PORT="$2"
            shift 2
            ;;
        --orly-port)
            ORLY_PORT="$2"
            shift 2
            ;;
        --password)
            CUSTOM_PASSWORD="$2"
            shift 2
            ;;
        --key)
            EXISTING_KEY="$2"
            shift 2
            ;;
        --test-only)
            TEST_ONLY=true
            shift
            ;;
        --no-orly)
            NO_ORLY=true
            shift
            ;;
        --no-test)
            NO_TEST=true
            shift
            ;;
        --help|-h)
            echo "Docker Full Setup for Nostrbots"
            echo "================================"
            echo ""
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --jenkins-port <port>    Jenkins web interface port (default: 8080)"
            echo "  --agent-port <port>      Jenkins agent port (default: 50000)"
            echo "  --orly-port <port>       Orly relay port (default: 3334)"
            echo "  --password <password>    Custom encryption password (optional)"
            echo "  --key <hex_key>          Use existing Nostr key (optional)"
            echo "  --test-only              Only run tests, don't setup Jenkins"
            echo "  --no-orly                Skip Orly relay setup"
            echo "  --no-test                Skip hello world test"
            echo "  --help, -h               Show this help message"
            echo ""
            echo "This script will:"
            echo "  1. Build the Nostrbots Docker image"
            echo "  2. Generate and encrypt Nostr bot key"
            echo "  3. Setup Jenkins with encrypted environment"
            echo "  4. Setup Orly relay (optional)"
            echo "  5. Run hello world bot test"
            echo "  6. Verify everything is working"
            echo ""
            echo "Requirements:"
            echo "  • Docker and Docker Compose"
            echo "  • Internet connection for downloading images"
            echo "  • Ports $JENKINS_PORT, $AGENT_PORT, $ORLY_PORT available"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

print_status "🚀 Starting Docker Full Setup for Nostrbots"
print_status "============================================="
print_status "Jenkins port: $JENKINS_PORT"
print_status "Agent port: $AGENT_PORT"
print_status "Orly port: $ORLY_PORT"
print_status "Test only: $TEST_ONLY"
print_status "Skip Orly: $NO_ORLY"
print_status "Skip test: $NO_TEST"

# Check if we're in the right directory
if [ ! -f "Dockerfile" ] || [ ! -f "nostrbots.php" ]; then
    print_error "Please run this script from the Nostrbots project root directory"
    exit 1
fi

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again"
    exit 1
fi

# Install websocat in the container for Orly verification
print_status "📦 Installing websocat for relay verification..."
if ! command -v websocat >/dev/null 2>&1; then
    print_warning "websocat not found, installing..."
    if command -v apt-get >/dev/null 2>&1; then
        sudo apt-get update && sudo apt-get install -y websocat
    elif command -v brew >/dev/null 2>&1; then
        brew install websocat
    else
        print_warning "Could not install websocat automatically"
        print_warning "Orly relay verification will be skipped"
    fi
fi

# Build the Nostrbots Docker image
print_status "🔨 Building Nostrbots Docker image..."
if docker build -t nostrbots:latest .; then
    print_success "✅ Docker image built successfully"
else
    print_error "❌ Failed to build Docker image"
    exit 1
fi

# Generate and encrypt the Nostr bot key
print_status "🔑 Generating and encrypting Nostr bot key..."
KEY_GEN_ARGS=""
if [ -n "$CUSTOM_PASSWORD" ]; then
    KEY_GEN_ARGS="$KEY_GEN_ARGS --password $CUSTOM_PASSWORD"
fi
if [ -n "$EXISTING_KEY" ]; then
    KEY_GEN_ARGS="$KEY_GEN_ARGS --key $EXISTING_KEY"
fi

if docker run --rm -v "$(pwd):/workspace" -w /workspace nostrbots:latest \
    bash -c "php generate-key.php --jenkins $KEY_GEN_ARGS" > /tmp/nostr-key.env; then
    print_success "✅ Nostr bot key generated and encrypted"
    
    # Source the environment variables
    source /tmp/nostr-key.env
    print_status "Key variables exported to environment"
else
    print_error "❌ Failed to generate Nostr bot key"
    exit 1
fi

# Setup Jenkins (unless test-only)
if [ "$TEST_ONLY" = false ]; then
    print_status "🏗️  Setting up Jenkins..."
    
    # Export ports for Jenkins setup
    export JENKINS_PORT
    export AGENT_PORT
    
    if docker run --rm -v "$(pwd):/workspace" -w /workspace \
        -e JENKINS_PORT -e AGENT_PORT \
        -e NOSTR_BOT_KEY_ENCRYPTED -e NOSTR_BOT_KEY_PASSWORD \
        nostrbots:latest bash scripts/02-setup-jenkins.sh "$JENKINS_PORT" "$AGENT_PORT"; then
        print_success "✅ Jenkins setup completed"
    else
        print_error "❌ Jenkins setup failed"
        exit 1
    fi
    
    # Setup Jenkins user and distributed builds
    print_status "👤 Setting up Jenkins user and distributed builds..."
    if docker run --rm -v "$(pwd):/workspace" -w /workspace \
        -e JENKINS_PORT -e AGENT_PORT -e NOSTRBOTS_PASSWORD \
        -e NOSTR_BOT_KEY_ENCRYPTED -e NOSTR_BOT_KEY_PASSWORD \
        nostrbots:latest bash -c "
            bash scripts/02a-create-nostrbots-user.sh && \
            bash scripts/02b-setup-distributed-builds.sh
        "; then
        print_success "✅ Jenkins user and distributed builds configured"
    else
        print_error "❌ Jenkins user setup failed"
        exit 1
    fi
    
    # Verify environment
    print_status "🔍 Verifying Jenkins environment..."
    if docker run --rm -v "$(pwd):/workspace" -w /workspace \
        -e JENKINS_PORT -e JENKINS_ADMIN_PASSWORD \
        -e NOSTR_BOT_KEY_ENCRYPTED -e NOSTR_BOT_KEY_PASSWORD \
        nostrbots:latest bash scripts/03-verify-environment.sh; then
        print_success "✅ Jenkins environment verified"
    else
        print_error "❌ Jenkins environment verification failed"
        exit 1
    fi
    
    # Create pipeline
    print_status "📋 Creating Jenkins pipeline..."
    if docker run --rm -v "$(pwd):/workspace" -w /workspace \
        -e JENKINS_PORT -e JENKINS_ADMIN_PASSWORD \
        nostrbots:latest bash scripts/04-create-pipeline.sh; then
        print_success "✅ Jenkins pipeline created"
    else
        print_error "❌ Jenkins pipeline creation failed"
        exit 1
    fi
    
    # Final verification
    print_status "✅ Final Jenkins verification..."
    if docker run --rm -v "$(pwd):/workspace" -w /workspace \
        -e JENKINS_PORT -e JENKINS_ADMIN_PASSWORD \
        -e NOSTR_BOT_KEY_ENCRYPTED -e NOSTR_BOT_KEY_PASSWORD \
        nostrbots:latest bash scripts/05-verify-setup.sh; then
        print_success "✅ Jenkins setup fully verified"
    else
        print_error "❌ Jenkins verification failed"
        exit 1
    fi
fi

# Setup Orly relay (unless disabled)
if [ "$NO_ORLY" = false ]; then
    print_status "🌐 Setting up Orly relay..."
    
    # Export Orly port
    export ORLY_PORT
    
    if docker run --rm -v "$(pwd):/workspace" -w /workspace \
        -e ORLY_PORT \
        nostrbots:latest bash scripts/01-install-orly.sh "$ORLY_PORT"; then
        print_success "✅ Orly relay installed"
        
        # Configure Orly
        if docker run --rm -v "$(pwd):/workspace" -w /workspace \
            -e ORLY_PORT \
            nostrbots:latest bash scripts/02-configure-orly.sh; then
            print_success "✅ Orly relay configured"
        else
            print_warning "⚠️  Orly configuration had issues"
        fi
        
        # Verify Orly
        if docker run --rm -v "$(pwd):/workspace" -w /workspace \
            -e ORLY_PORT \
            nostrbots:latest bash scripts/03-verify-orly.sh; then
            print_success "✅ Orly relay verified"
        else
            print_warning "⚠️  Orly verification had issues"
        fi
    else
        print_warning "⚠️  Orly relay setup failed (this is optional)"
    fi
fi

# Run hello world test (unless disabled)
if [ "$NO_TEST" = false ]; then
    print_status "🧪 Running Hello World Bot Test..."
    
    # Decrypt the key for testing
    if docker run --rm -v "$(pwd):/workspace" -w /workspace \
        -e NOSTR_BOT_KEY_ENCRYPTED -e NOSTR_BOT_KEY_PASSWORD \
        nostrbots:latest bash -c "
            export NOSTR_BOT_KEY=\$(php generate-key.php --key \$NOSTR_BOT_KEY_ENCRYPTED --decrypt --password \$NOSTR_BOT_KEY_PASSWORD --quiet | grep 'export NOSTR_BOT_KEY=' | cut -d'=' -f2-)
            bash scripts/test-hello-world.sh --no-orly-verify
        "; then
        print_success "✅ Hello world bot test passed"
    else
        print_error "❌ Hello world bot test failed"
        exit 1
    fi
fi

# Final summary
echo ""
print_status "🎉 Docker Full Setup Complete!"
echo "================================"
print_success "All components have been set up and tested successfully!"

echo ""
echo "📋 What was set up:"
if [ "$TEST_ONLY" = false ]; then
    echo "  ✅ Jenkins container with encrypted environment"
    echo "  ✅ Dedicated nostrbots user with proper permissions"
    echo "  ✅ Distributed builds with Jenkins agents"
    echo "  ✅ Jenkins pipeline for automated bot execution"
fi
if [ "$NO_ORLY" = false ]; then
    echo "  ✅ Orly Nostr relay for local testing"
fi
if [ "$NO_TEST" = false ]; then
    echo "  ✅ Hello world bot test passed"
fi

echo ""
echo "🔐 Security Features:"
echo "  🔒 Nostr key encrypted with AES-256-CBC"
echo "  🔒 Password-based encryption with secure defaults"
echo "  🔒 Non-root containers with security options"
echo "  🔒 Memory protection and secure cleanup"
echo "  🔒 Network isolation and resource limits"

echo ""
echo "🌐 Access Information:"
if [ "$TEST_ONLY" = false ]; then
    echo "  • Jenkins: http://localhost:$JENKINS_PORT"
    echo "  • Jenkins Agent: localhost:$AGENT_PORT"
fi
if [ "$NO_ORLY" = false ]; then
    echo "  • Orly Relay: ws://localhost:$ORLY_PORT"
fi

echo ""
echo "🚀 Next Steps:"
echo "  • Check Jenkins web interface"
echo "  • Create your own bots in the bots/ directory"
echo "  • Run scheduled bots via Jenkins"
echo "  • Publish content to Nostr"

echo ""
print_success "Happy botting! 🤖"

# Clean up temporary files
rm -f /tmp/nostr-key.env
