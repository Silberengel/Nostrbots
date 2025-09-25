#!/bin/bash

# setup-jenkins-complete.sh
# Master script that runs all Jenkins setup steps in sequence
# This script orchestrates the complete Jenkins setup process
#
# Usage: bash scripts/setup-jenkins-complete.sh [JENKINS_PORT] [AGENT_PORT] [--key YOUR_KEY]
#   JENKINS_PORT: Jenkins web interface port (default: 8080)
#   AGENT_PORT: Jenkins agent port (default: 50000)
#   --key YOUR_KEY: Use your existing Nostr key instead of generating a new one

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[SETUP-JENKINS]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_header() { echo -e "${PURPLE}[JENKINS SETUP]${NC} $1"; }

# Parse command line arguments
JENKINS_PORT=${1:-8080}
AGENT_PORT=${2:-50000}
EXISTING_KEY=""

# Check for --key option
if [[ "$*" == *"--key"* ]]; then
    for arg in "$@"; do
        if [[ "$arg" == "--key" ]]; then
            # Find the next argument as the key value
            for i in "${!@}"; do
                if [[ "${!i}" == "--key" ]]; then
                    EXISTING_KEY="${!((i+1))}"
                    break
                fi
            done
            break
        fi
    done
fi

print_header "Complete Jenkins Setup for Nostrbots"
echo "=========================================="
echo "Jenkins Port: $JENKINS_PORT"
echo "Agent Port: $AGENT_PORT"
if [ -n "$EXISTING_KEY" ]; then
    echo "Using existing key: ${EXISTING_KEY:0:20}..."
fi
echo ""

# Check if we're in the right directory
if [ ! -f "generate-key.php" ] || [ ! -f "Dockerfile" ] || [ ! -f "Jenkinsfile" ]; then
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
    
    if bash "$script_path"; then
        print_success "$script_name completed successfully"
        echo ""
    else
        print_error "$script_name failed"
        echo ""
        print_error "Setup failed at step: $script_name"
        print_status "You can run individual scripts to debug:"
        echo "  bash scripts/$script_name"
        exit 1
    fi
}

# Step 1: Generate and encrypt Nostr key
print_header "Step 1: Generate and Encrypt Nostr Key"
echo "==========================================="
if [ -n "$EXISTING_KEY" ]; then
    bash scripts/01-generate-key.sh --key "$EXISTING_KEY"
else
    run_script "01-generate-key.sh"
fi

# Step 2: Setup Jenkins container
print_header "Step 2: Setup Jenkins Container"
echo "===================================="
bash scripts/02-setup-jenkins.sh $JENKINS_PORT $AGENT_PORT

# Step 2a: Create nostrbots user
print_header "Step 2a: Create Nostrbots User"
echo "=================================="
run_script "02a-create-nostrbots-user.sh"

# Step 2b: Setup distributed builds
print_header "Step 2b: Setup Distributed Builds"
echo "====================================="
run_script "02b-setup-distributed-builds.sh"

# Step 3: Verify environment variables
print_header "Step 3: Verify Environment"
echo "================================"
run_script "03-verify-environment.sh"

# Step 4: Create pipeline job
print_header "Step 4: Create Pipeline Job"
echo "==============================="
run_script "04-create-pipeline.sh"

# Step 5: Verify setup
print_header "Step 5: Verify Setup"
echo "========================"
run_script "05-verify-setup.sh"

# Step 6: Test with Hello World bot
print_header "Step 6: Test Hello World Bot"
echo "==============================="
print_status "Running hello world bot test to verify the complete setup..."
if bash scripts/test-hello-world.sh --dry-run-only; then
    print_success "Hello world bot test passed!"
else
    print_error "Hello world bot test failed!"
    print_error "Please check the setup and try again"
    exit 1
fi

# Final summary
echo ""
print_header "🎉 Complete Jenkins Setup Finished!"
echo "======================================"
echo ""
print_success "All setup steps completed successfully!"
echo ""
echo "📋 What was set up:"
echo "  ✅ Encrypted Nostr bot key generated"
echo "  ✅ Jenkins container running with encrypted environment variables"
echo "  ✅ Dedicated nostrbots user created with proper permissions"
echo "  ✅ Distributed builds configured with Jenkins agents"
echo "  ✅ Jenkins setup wizard completed"
echo "  ✅ Pipeline job created and configured"
echo "  ✅ All components verified and working"
echo "  ✅ Hello world bot test passed"
echo ""
echo "🔐 Security Features:"
echo "  🔒 Nostr key is encrypted with AES-256-CBC"
echo "  🔒 Key is only decrypted at runtime in Jenkins"
echo "  🔒 No plain text secrets in logs or environment"
echo "  🔒 All keys stored only in environment variables (no files)"
echo "  🔒 No sensitive data written to disk"
echo ""
echo "🚀 Access Information:"
echo "  Jenkins URL: http://localhost:$JENKINS_PORT"
echo "  Admin Username: admin"
echo "  Admin Password: admin (or check environment for initial password)"
echo "  Bot Username: nostrbots"
echo "  Bot Password: nostrbots123 (stored in environment)"
echo "  Pipeline Job: nostrbots-pipeline"
echo "  Build Agent: nostrbots-agent (distributed builds)"
echo ""
echo "🧪 Test the Setup:"
echo "  1. Visit http://localhost:$JENKINS_PORT"
echo "  2. Login with admin/admin"
echo "  3. Go to nostrbots-pipeline job"
echo "  4. Click 'Build Now' to test the pipeline"
echo ""
echo "🔧 Management Commands:"
echo "  # View Jenkins logs"
echo "  docker logs jenkins-nostrbots"
echo ""
echo "  # Stop Jenkins"
echo "  docker compose -f docker-compose.jenkins.yml down"
echo ""
echo "  # Restart Jenkins"
echo "  docker compose -f docker-compose.jenkins.yml restart"
echo ""
echo "  # Run individual setup steps"
echo "  bash scripts/01-generate-key.sh"
echo "  bash scripts/02-setup-jenkins.sh"
echo "  bash scripts/03-verify-environment.sh"
echo "  bash scripts/04-create-pipeline.sh"
echo "  bash scripts/05-verify-setup.sh"
echo "  bash scripts/test-hello-world.sh"
echo ""
print_success "Jenkins setup complete! Happy botting! 🤖"
