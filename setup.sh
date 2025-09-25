#!/bin/bash

# Nostrbots Complete Setup Script
# This script handles the entire setup process for Nostrbots

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    log_success "Docker is installed"
}

# Generate keys if they don't exist
generate_keys() {
    log_info "Checking for existing keys..."
    if [ ! -f ".env" ]; then
        log_info "Creating .env file from template..."
        cp env.example .env
        log_info "Generating new keys..."
        php generate-key.php --jenkins --quiet
        log_success "Keys generated and saved to .env file"
        log_warning "Please review and update the .env file with your actual keys"
    else
        log_success ".env file already exists"
        # Check if keys are properly set
        if grep -q "your_encrypted_nostr_private_key_here" .env; then
            log_warning "Please update the .env file with your actual keys"
        fi
    fi
}

# Setup Jenkins with Docker support
setup_jenkins() {
    log_info "Setting up Jenkins with Docker support..."
    
    # Load environment variables from .env file
    if [ -f ".env" ]; then
        log_info "Loading environment variables from .env file..."
        export $(grep -v '^#' .env | xargs)
    else
        log_warning "No .env file found. Using default values."
    fi
    
    # Stop existing containers
    docker compose -f docker-compose.jenkins.yml down 2>/dev/null || true
    docker compose -f docker-compose.yml down 2>/dev/null || true
    
    # Remove old volumes for clean start
    docker volume rm nostrbots_jenkins_home 2>/dev/null || true
    
    # Start Orly relay first
    log_info "Starting Orly relay..."
    docker compose -f docker-compose.yml up -d orly-relay
    
    # Wait for Orly relay to be ready
    log_info "Waiting for Orly relay to be ready..."
    local retries=0
    while [ $retries -lt 30 ]; do
        if curl -s http://localhost:3334/health >/dev/null 2>&1; then
            log_success "Orly relay is ready"
            break
        fi
        sleep 2
        retries=$((retries + 1))
    done
    
    if [ $retries -eq 30 ]; then
        log_warning "Orly relay may not be fully ready, but continuing..."
    fi
    
    # Start Jenkins
    log_info "Starting Jenkins..."
    docker compose -f docker-compose.jenkins.yml up -d
    
    # Wait for Jenkins to be ready
    log_info "Waiting for Jenkins to start..."
    sleep 30
    
    # Check if Jenkins is running
    if curl -f http://localhost:8080/login >/dev/null 2>&1; then
        log_success "Jenkins is running at http://localhost:8080"
        log_info "Login with: admin / admin"
    else
        log_error "Jenkins failed to start. Check logs with:"
        echo "docker compose -f docker-compose.jenkins.yml logs jenkins"
        exit 1
    fi
}

# Create Jenkins pipeline job (interactive)
create_jenkins_pipeline() {
    log_info "Setting up Jenkins pipeline job..."
    
    # Wait a bit for Jenkins to be fully ready
    sleep 10
    
    # Check if the job already exists
    if curl -s -f "http://admin:admin@localhost:8080/job/nostrbots-pipeline/api/json" >/dev/null 2>&1; then
        log_success "Jenkins pipeline job 'nostrbots-pipeline' already exists"
        return 0
    fi
    
    echo ""
    log_info "🔧 MANUAL STEP REQUIRED: Create Jenkins Pipeline Job"
    echo "=================================================="
    echo ""
    echo "The Jenkins pipeline job needs to be created manually due to security restrictions."
    echo ""
    echo "📋 INSTRUCTIONS:"
    echo "1. Open your web browser and go to: http://localhost:8080"
    echo "2. Login with username: admin"
    echo "3. Login with password: admin"
    echo "4. Click 'New Item' on the left sidebar"
    echo "5. Enter name: 'nostrbots-pipeline'"
    echo "6. Select 'Pipeline' and click 'OK'"
    echo "7. In the Pipeline section:"
    echo "   - Definition: Pipeline script from SCM"
    echo "   - SCM: Git"
    echo "   - Repository URL: file:///workspace"
    echo "   - Branch: */main"
    echo "   - Script Path: Jenkinsfile"
    echo "8. Click 'Save'"
    echo ""
    echo "⏳ Waiting for you to create the pipeline job..."
    echo "   (This script will check every 10 seconds)"
    echo ""
    
    # Wait for user to create the pipeline job
    local retries=0
    while [ $retries -lt 60 ]; do  # Wait up to 10 minutes
        if curl -s -f "http://admin:admin@localhost:8080/job/nostrbots-pipeline/api/json" >/dev/null 2>&1; then
            log_success "Jenkins pipeline job 'nostrbots-pipeline' found!"
            return 0
        fi
        
        if [ $((retries % 6)) -eq 0 ]; then  # Every minute
            echo "⏳ Still waiting... (${retries}/60 checks)"
        fi
        
        sleep 10
        retries=$((retries + 1))
    done
    
    log_error "Timeout waiting for pipeline job creation"
    log_error "Please create the pipeline job manually and run this script again"
    exit 1
}

# Check if Jenkins pipeline job exists and works
check_jenkins_pipeline() {
    log_info "Verifying Jenkins pipeline job..."
    
    # Check if the nostrbots-pipeline job exists
    if curl -s -f "http://admin:admin@localhost:8080/job/nostrbots-pipeline/api/json" >/dev/null 2>&1; then
        log_success "Jenkins pipeline job 'nostrbots-pipeline' exists and is accessible"
    else
        log_error "Jenkins pipeline job 'nostrbots-pipeline' NOT FOUND!"
        log_error "Pipeline creation failed or job is not accessible."
        exit 1
    fi
}

# Test the setup
test_setup() {
    log_info "Testing environment loading..."
    if [ -f "test-env-loading.sh" ]; then
        bash test-env-loading.sh
    else
        log_warning "Environment test script not found, skipping environment test"
    fi
    
    log_info "Testing nostrbots Docker image..."
    
    # Pull the image if needed
    if ! docker images | grep -q "silberengel/nostrbots"; then
        log_info "Pulling nostrbots image..."
        docker pull silberengel/nostrbots:latest
    fi
    
    # Test basic functionality
    docker run --rm silberengel/nostrbots:latest php --version >/dev/null
    log_success "nostrbots Docker image is working"
    
    # Test content generation with environment variables
    log_info "Testing content generation with environment variables..."
    if [ -f ".env" ]; then
        export $(grep -v '^#' .env | xargs)
    fi
    
    docker run --rm \
        -e NOSTR_BOT_KEY_ENCRYPTED="$NOSTR_BOT_KEY_ENCRYPTED" \
        -e NOSTR_BOT_NPUB="$NOSTR_BOT_NPUB" \
        -v "$(pwd):/workspace" \
        -w /workspace \
        silberengel/nostrbots:latest \
        php bots/hello-world/generate-content.php >/dev/null
    
    if [ -d "bots/hello-world/output" ] && [ "$(ls -A bots/hello-world/output/*.adoc 2>/dev/null)" ]; then
        log_success "Content generation with environment variables is working"
    else
        log_warning "Content generation test failed"
    fi
}

# Main setup function
main() {
    echo "🚀 Nostrbots Complete Setup"
    echo "=========================="
    
    check_docker
    generate_keys
    setup_jenkins
    create_jenkins_pipeline
    check_jenkins_pipeline
    test_setup
    
    echo ""
    log_success "Setup completed successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Go to http://localhost:8080"
    echo "2. Login with admin/admin"
    echo "3. Go to 'nostrbots-pipeline' job"
    echo "4. Click 'Build Now' to test the pipeline"
    echo ""
    echo "To run the pipeline manually: ./run-pipeline.sh"
}

# Handle command line arguments
case "${1:-}" in
    "jenkins")
        check_docker
        setup_jenkins
        ;;
    "test")
        check_docker
        test_setup
        ;;
    "keys")
        generate_keys
        ;;
    "create-pipeline")
        create_jenkins_pipeline
        check_jenkins_pipeline
        ;;
    "check-pipeline")
        check_jenkins_pipeline
        ;;
    *)
        main
        ;;
esac
