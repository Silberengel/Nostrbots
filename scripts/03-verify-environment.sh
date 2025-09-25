#!/bin/bash

# 03-verify-environment.sh
# Verifies that encrypted environment variables are properly set in Jenkins
# Can be run independently or as part of the complete setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[03-VERIFY-ENVIRONMENT]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

print_status "Verifying encrypted environment variables in Jenkins container..."

# Load port configuration from environment
if [ -z "$JENKINS_PORT" ]; then
    JENKINS_PORT=8080
    print_warning "No port configuration found in environment, using default port 8080"
fi

# Check if Jenkins is running
if ! curl -s http://localhost:$JENKINS_PORT > /dev/null 2>&1; then
    print_error "Jenkins is not running on localhost:$JENKINS_PORT"
    print_error "Please run 02-setup-jenkins.sh first"
    exit 1
fi

# Check if encrypted key variables are in environment
if [ -z "$NOSTR_BOT_KEY_ENCRYPTED" ]; then
    print_error "No encrypted key found in environment. Please run 01-generate-key.sh first"
    exit 1
fi

print_status "Using encrypted key variables from environment..."

# Get admin password from environment
if [ -z "$JENKINS_ADMIN_PASSWORD" ]; then
    print_error "No Jenkins admin password found in environment. Please run 02-setup-jenkins.sh first"
    exit 1
fi
ADMIN_PASSWORD="$JENKINS_ADMIN_PASSWORD"

JENKINS_URL="http://localhost:$JENKINS_PORT"

# Wait for Jenkins to be fully ready
print_status "Waiting for Jenkins to be fully ready..."
timeout=60
counter=0
while ! curl -s "$JENKINS_URL" > /dev/null 2>&1; do
    if [ $counter -ge $timeout ]; then
        print_error "Jenkins not ready after $timeout seconds"
        exit 1
    fi
    sleep 2
    counter=$((counter + 2))
    echo -n "."
done
echo ""

print_success "Jenkins is ready!"
print_status "Using environment variable approach - no Jenkins credentials needed"

# Verify environment variables are available in Jenkins container
print_status "Verifying encrypted environment variables in Jenkins container..."
ENCRYPTED_CHECK=$(docker exec jenkins-nostrbots env | grep NOSTR_BOT_KEY_ENCRYPTED || echo "")
if [ -n "$ENCRYPTED_CHECK" ]; then
    print_success "Encrypted environment variables are available in Jenkins container!"
    echo "  Encrypted key: ${ENCRYPTED_CHECK:0:50}..."
else
    print_error "Encrypted environment variables are not available in Jenkins container"
    exit 1
fi

print_success "Environment verification complete!"
print_status "Jenkins will automatically decrypt the Nostr key from environment variables"
