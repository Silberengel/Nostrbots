#!/bin/bash

# analyze-script-redundancy.sh
# Analyzes which scripts are redundant with the new Docker setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

print_header() { echo -e "${PURPLE}[ANALYSIS]${NC} $1"; }
print_redundant() { echo -e "${RED}[REDUNDANT]${NC} $1"; }
print_useful() { echo -e "${GREEN}[USEFUL]${NC} $1"; }
print_partial() { echo -e "${YELLOW}[PARTIAL]${NC} $1"; }
print_info() { echo -e "${BLUE}[INFO]${NC} $1"; }

print_header "Script Redundancy Analysis for Docker Setup"
echo "=================================================="
echo ""

print_info "Analyzing scripts in relation to the new Docker setup..."
echo ""

# Redundant scripts (functionality covered by Docker)
print_redundant "SCRIPTS THAT ARE NOW REDUNDANT:"
echo "----------------------------------------"
echo "  • 01-install-orly.sh"
echo "    - Docker builds next-orly from v0.8.4 source automatically"
echo "    - No need to install orly separately"
echo ""
echo "  • 02-configure-orly.sh" 
echo "    - Docker startup script configures relays automatically"
echo "    - Updates src/relays.yml and bot configs on startup"
echo ""
echo "  • 03-verify-orly.sh"
echo "    - Docker startup script includes relay verification"
echo "    - Tests connectivity and event publishing"
echo ""
echo "  • setup-orly-complete.sh"
echo "    - Docker handles complete orly setup automatically"
echo "    - All orly setup steps are built into the container"
echo ""

# Partially redundant scripts
print_partial "SCRIPTS THAT ARE PARTIALLY REDUNDANT:"
echo "---------------------------------------------"
echo "  • test-hello-world.sh"
echo "    - Docker startup script runs the same tests automatically"
echo "    - Still useful for testing non-Docker setups"
echo "    - Can be used for debugging Docker containers"
echo ""

# Still useful scripts
print_useful "SCRIPTS THAT ARE STILL USEFUL:"
echo "------------------------------------"
echo "  • 01-generate-key.sh"
echo "    - Key generation for non-Docker setups"
echo "    - Useful for production key management"
echo ""
echo "  • 02-setup-jenkins.sh"
echo "    - Jenkins setup is separate from Docker image"
echo "    - Still needed for CI/CD pipeline setup"
echo ""
echo "  • 02a-create-nostrbots-user.sh"
echo "    - User management for Jenkins"
echo "    - Required for Jenkins security"
echo ""
echo "  • 02b-setup-distributed-builds.sh"
echo "    - Jenkins distributed builds setup"
echo "    - Required for scalable CI/CD"
echo ""
echo "  • 03-verify-environment.sh"
echo "    - Environment verification for non-Docker setups"
echo "    - Useful for debugging and validation"
echo ""
echo "  • 04-create-pipeline.sh"
echo "    - Jenkins pipeline creation"
echo "    - Required for CI/CD automation"
echo ""
echo "  • 05-verify-setup.sh"
echo "    - Complete setup verification for non-Docker setups"
echo "    - Useful for production deployments"
echo ""
echo "  • setup-jenkins-complete.sh"
echo "    - Complete Jenkins setup"
echo "    - Required for CI/CD pipeline"
echo ""
echo "  • quick-test.sh"
echo "    - Fast validation for CI/CD"
echo "    - Useful for automated testing"
echo ""

# New Docker scripts
print_useful "NEW DOCKER SCRIPTS:"
echo "----------------------"
echo "  • build-next-orly-docker.sh"
echo "    - Build and push Docker images"
echo "    - Essential for Docker workflow"
echo ""
echo "  • test-next-orly-docker.sh"
echo "    - Test Docker images"
echo "    - Essential for Docker validation"
echo ""
echo "  • docker-full-setup.sh"
echo "    - Complete Docker setup with Jenkins"
echo "    - Alternative to individual setup scripts"
echo ""

# Recommendations
print_header "RECOMMENDATIONS:"
echo "=================="
echo ""
echo "1. KEEP these scripts (still useful):"
echo "   - All Jenkins-related scripts (02-*, 04-*, setup-jenkins-*)"
echo "   - Key generation script (01-generate-key.sh)"
echo "   - Verification scripts (03-verify-environment.sh, 05-verify-setup.sh)"
echo "   - New Docker scripts (build-*, test-*, docker-*)"
echo ""
echo "2. CONSIDER REMOVING these scripts (redundant with Docker):"
echo "   - 01-install-orly.sh"
echo "   - 02-configure-orly.sh" 
echo "   - 03-verify-orly.sh"
echo "   - setup-orly-complete.sh"
echo ""
echo "3. KEEP BUT MARK as legacy:"
echo "   - test-hello-world.sh (useful for non-Docker setups)"
echo "   - quick-test.sh (useful for CI/CD)"
echo ""
echo "4. UPDATE documentation to reflect:"
echo "   - Docker is now the primary deployment method"
echo "   - Individual scripts are for advanced users"
echo "   - Jenkins scripts are still required for CI/CD"
echo ""

# Usage patterns
print_header "USAGE PATTERNS:"
echo "================"
echo ""
echo "🐳 DOCKER USERS (Recommended):"
echo "   bash scripts/build-next-orly-docker.sh"
echo "   docker run -p 3334:3334 silberengel/next-orly:latest"
echo ""
echo "🔧 ADVANCED USERS (Non-Docker):"
echo "   bash scripts/01-generate-key.sh"
echo "   bash scripts/setup-orly-complete.sh"
echo "   bash scripts/test-hello-world.sh"
echo ""
echo "🏗️  CI/CD USERS (Jenkins + Docker):"
echo "   bash scripts/docker-full-setup.sh"
echo "   # Or individual Jenkins scripts + Docker"
echo ""

print_info "Analysis complete! The Docker setup significantly simplifies deployment"
print_info "while maintaining flexibility for advanced use cases."
