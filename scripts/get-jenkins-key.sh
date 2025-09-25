#!/bin/bash

# Get Nostr Key from Jenkins Script
# Retrieves the Nostr bot key from Jenkins credentials

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

echo "🔑 Jenkins Nostr Key Retrieval"
echo "=============================="
echo ""

# Check if Jenkins is running
if ! curl -s http://localhost:8080 > /dev/null 2>&1; then
    print_error "Jenkins is not running on localhost:8080"
    print_status "Please start Jenkins first:"
    echo "  docker-compose -f docker-compose.jenkins.yml up -d"
    exit 1
fi

print_status "Jenkins is running. Attempting to retrieve Nostr key..."

# Try to get the key from Jenkins API
JENKINS_URL="http://localhost:8080"
CREDENTIAL_ID="nostr-bot-key"

# Method 1: Try Jenkins API (requires authentication)
print_status "Method 1: Trying Jenkins API..."

# Get the key from Jenkins credentials API
KEY_RESPONSE=$(curl -s -u admin:admin \
    "$JENKINS_URL/credentials/store/system/domain/_/credential/$CREDENTIAL_ID/config.xml" 2>/dev/null || echo "")

if [ -n "$KEY_RESPONSE" ] && [[ "$KEY_RESPONSE" == *"<secret>"* ]]; then
    # Extract the key from the XML response
    NOSTR_KEY=$(echo "$KEY_RESPONSE" | grep -o '<secret>[^<]*</secret>' | sed 's/<secret>//g' | sed 's/<\/secret>//g')
    
    if [ -n "$NOSTR_KEY" ]; then
        print_success "Nostr key retrieved from Jenkins!"
        echo ""
        echo "Your Nostr bot key:"
        echo "NOSTR_BOT_KEY=$NOSTR_KEY"
        echo ""
        print_status "To use this key:"
        echo "  export NOSTR_BOT_KEY=$NOSTR_KEY"
        echo ""
        print_status "To test with this key:"
        echo "  ./test-complete-pipeline.sh"
        exit 0
    fi
fi

# Method 2: Check environment variables
print_status "Method 2: Checking environment variables..."
if [ -n "$NOSTR_BOT_KEY" ]; then
    print_success "Nostr key found in environment!"
    echo ""
    echo "Your Nostr bot key:"
    echo "NOSTR_BOT_KEY=$NOSTR_BOT_KEY"
    echo ""
    print_status "This key is already available in your environment."
    exit 0
fi

# Method 3: Manual instructions
print_warning "Could not automatically retrieve the key."
echo ""
print_status "Manual retrieval options:"
echo ""
echo "1. Jenkins Web Interface:"
echo "   - Go to: http://localhost:8080"
echo "   - Login: admin / admin"
echo "   - Navigate: Manage Jenkins → Credentials"
echo "   - Find: 'nostr-bot-key' credential"
echo "   - Click: 'View' to see the key value"
echo ""
echo "2. Jenkins Container:"
echo "   - docker exec -it jenkins-nostrbots bash"
echo "   - Check environment variables or config files"
echo ""
echo "3. Check Jenkins Logs:"
echo "   - docker logs jenkins-nostrbots | grep NOSTR_BOT_KEY"
echo ""
echo "4. Re-run Setup:"
echo "   - ./scripts/setup-local-jenkins.sh --build-nostrbots --setup-pipeline"
echo "   - The key will be displayed during setup"
echo ""
print_status "Once you have the key, set it with:"
echo "  export NOSTR_BOT_KEY=your_key_here"
echo ""
print_status "Then test with:"
echo "  ./test-complete-pipeline.sh"
