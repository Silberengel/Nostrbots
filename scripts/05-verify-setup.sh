#!/bin/bash

# 05-verify-setup.sh
# Verifies that the complete Jenkins setup is working correctly
# Can be run independently or as part of the complete setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[05-VERIFY-SETUP]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

print_status "Verifying complete Jenkins setup..."

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

# Get admin password from environment
if [ -z "$JENKINS_ADMIN_PASSWORD" ]; then
    print_error "No Jenkins admin password found in environment. Please run 02-setup-jenkins.sh first"
    exit 1
fi
ADMIN_PASSWORD="$JENKINS_ADMIN_PASSWORD"

JENKINS_URL="http://localhost:$JENKINS_PORT"

# Determine which authentication to use
if curl -s -u admin:admin "$JENKINS_URL/api/json" > /dev/null 2>&1; then
    AUTH_USER="admin:admin"
    print_success "Jenkins authentication: admin:admin"
else
    AUTH_USER="admin:$ADMIN_PASSWORD"
    print_success "Jenkins authentication: admin with initial password"
fi

# Test 1: Jenkins API access
print_status "Test 1: Jenkins API access..."
API_TEST=$(curl -s -u $AUTH_USER "$JENKINS_URL/api/json" | grep -o '"mode":"[^"]*"' || echo "")
if [[ "$API_TEST" == *"NORMAL"* ]]; then
    print_success "✅ Jenkins API is accessible and in NORMAL mode"
else
    print_error "❌ Jenkins API not accessible or not in NORMAL mode"
    exit 1
fi

# Test 2: Environment variables in Jenkins container
print_status "Test 2: Encrypted environment variables in Jenkins container..."
ENCRYPTED_CHECK=$(docker exec jenkins-nostrbots env | grep NOSTR_BOT_KEY_ENCRYPTED || echo "")
if [ -n "$ENCRYPTED_CHECK" ]; then
    print_success "✅ Encrypted environment variables are available in Jenkins container"
    echo "    Encrypted key: ${ENCRYPTED_CHECK:0:50}..."
else
    print_error "❌ Encrypted environment variables are not available in Jenkins container"
    exit 1
fi

# Test 3: Key decryption
print_status "Test 3: Key decryption test..."
if [ -n "$NOSTR_BOT_KEY_ENCRYPTED" ]; then
    # Test decryption using the PHP script with default password
    DECRYPTED_KEY=$(docker exec jenkins-nostrbots php generate-key.php --key "$NOSTR_BOT_KEY_ENCRYPTED" --decrypt --quiet 2>/dev/null | grep "export NOSTR_BOT_KEY=" | cut -d'=' -f2- || echo "")
    
    if [ -n "$DECRYPTED_KEY" ] && [ ${#DECRYPTED_KEY} -eq 64 ]; then
        print_success "✅ Key decryption test successful"
        echo "    Decrypted key: ${DECRYPTED_KEY:0:20}..."
    else
        print_error "❌ Key decryption test failed"
        exit 1
    fi
else
    print_error "❌ No encrypted key found in environment"
    exit 1
fi

# Test 4: Pipeline job exists
print_status "Test 4: Pipeline job exists..."
JOB_TEST=$(curl -s -u $AUTH_USER "$JENKINS_URL/api/json" | grep -o '"nostrbots-pipeline"' || echo "")
if [[ "$JOB_TEST" == *"nostrbots-pipeline"* ]]; then
    print_success "✅ Pipeline job 'nostrbots-pipeline' exists"
else
    print_error "❌ Pipeline job 'nostrbots-pipeline' not found"
    exit 1
fi

# Test 5: Jenkinsfile exists and is valid
print_status "Test 5: Jenkinsfile validation..."
if [ -f "Jenkinsfile" ]; then
    print_success "✅ Jenkinsfile exists"
    
    # Check if Jenkinsfile contains decryption logic
    if grep -q "NOSTR_BOT_KEY_ENCRYPTED" Jenkinsfile && grep -q "openssl enc" Jenkinsfile; then
        print_success "✅ Jenkinsfile contains key decryption logic"
    else
        print_warning "⚠️  Jenkinsfile may not contain key decryption logic"
    fi
else
    print_error "❌ Jenkinsfile not found"
    exit 1
fi

# Test 6: Docker images
print_status "Test 6: Docker images..."
if docker images | grep -q "nostrbots"; then
    print_success "✅ Nostrbots Docker image exists"
else
    print_warning "⚠️  Nostrbots Docker image not found - will be built on first run"
fi

# Test 7: Bot configurations
print_status "Test 7: Bot configurations..."
if [ -d "bots" ] && [ "$(ls -A bots)" ]; then
    BOT_COUNT=$(find bots -name "config.json" | wc -l)
    print_success "✅ Found $BOT_COUNT bot configuration(s)"
else
    print_warning "⚠️  No bot configurations found in bots/ directory"
fi

# Summary
echo ""
print_success "🎉 Jenkins setup verification complete!"
echo ""
echo "📋 Setup Summary:"
echo "  ✅ Jenkins is running and accessible"
echo "  ✅ Encrypted environment variables are configured"
echo "  ✅ Key decryption is working"
echo "  ✅ Pipeline job is created"
echo "  ✅ Jenkinsfile is ready"
echo ""
echo "🚀 Next Steps:"
echo "  1. Visit Jenkins: http://localhost:$JENKINS_PORT"
echo "  2. Login with: admin / admin (or use initial password if setup not complete)"
echo "  3. Go to: nostrbots-pipeline job"
echo "  4. Click 'Build Now' to test the pipeline"
echo ""
echo "🔧 Useful Commands:"
echo "  # View Jenkins logs"
echo "  docker logs jenkins-nostrbots"
echo ""
echo "  # Stop Jenkins"
echo "  docker compose -f docker-compose.jenkins.yml down"
echo ""
echo "  # Restart Jenkins"
echo "  docker compose -f docker-compose.jenkins.yml restart"
echo ""
print_success "Setup verification complete! 🎉"
