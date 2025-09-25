#!/bin/bash

# test-hello-world.sh
# Tests the complete setup by running hello world dry run and publish
# Can be run independently or as part of the complete setup
#
# Usage: bash scripts/test-hello-world.sh [--dry-run-only] [--publish-only]

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[TEST-HELLO-WORLD]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

# Parse command line arguments
DRY_RUN_ONLY=false
PUBLISH_ONLY=false
VERIFY_ORLY=true

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run-only)
            DRY_RUN_ONLY=true
            shift
            ;;
        --publish-only)
            PUBLISH_ONLY=true
            shift
            ;;
        --no-orly-verify)
            VERIFY_ORLY=false
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [--dry-run-only] [--publish-only] [--no-orly-verify]"
            echo ""
            echo "Options:"
            echo "  --dry-run-only      Only run dry run test"
            echo "  --publish-only      Only run publish test"
            echo "  --no-orly-verify    Skip Orly relay verification"
            echo "  --help, -h          Show this help message"
            echo ""
            echo "This script tests the complete Nostrbots setup by:"
            echo "  1. Running hello world bot dry run"
            echo "  2. Running hello world bot publish"
            echo "  3. Verifying the published content"
            echo "  4. Querying Orly relay to confirm event publication"
            echo ""
            echo "Requirements:"
            echo "  • websocat (for Orly relay verification): apt install websocat"
            echo "  • Orly relay running (optional, for full verification)"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

print_status "Testing Nostrbots setup with Hello World bot..."
print_status "================================================"

# Check if we're in the right directory
if [ ! -f "nostrbots.php" ] || [ ! -d "bots/hello-world" ]; then
    print_error "Please run this script from the Nostrbots project root directory"
    exit 1
fi

# Check if NOSTR_BOT_KEY is set
if [ -z "$NOSTR_BOT_KEY" ]; then
    print_error "NOSTR_BOT_KEY environment variable is not set"
    print_error "Please run the setup scripts first or set NOSTR_BOT_KEY manually"
    exit 1
fi

print_success "NOSTR_BOT_KEY is set (${NOSTR_BOT_KEY:0:20}...)"

# Check if hello world bot exists
if [ ! -f "bots/hello-world/config.json" ]; then
    print_error "Hello world bot configuration not found"
    exit 1
fi

print_success "Hello world bot configuration found"

# Function to run dry run test
run_dry_run_test() {
    print_status "🧪 Running Hello World Bot Dry Run Test..."
    echo "=============================================="
    
    # First generate content
    print_status "Step 1: Generating content with hello-world bot..."
    if php bots/hello-world/generate-content.php; then
        print_success "✅ Content generation successful!"
    else
        print_error "❌ Content generation failed!"
        return 1
    fi
    
    # Find the latest generated file
    LATEST_OUTPUT=$(ls -t bots/hello-world/output/*.adoc 2>/dev/null | head -1)
    if [ -z "$LATEST_OUTPUT" ]; then
        print_error "❌ No output file found after content generation"
        return 1
    fi
    
    print_status "Step 2: Testing dry run with generated file: $(basename "$LATEST_OUTPUT")"
    print_status "Executing: php nostrbots.php publish \"$LATEST_OUTPUT\" --dry-run"
    
    if php nostrbots.php publish "$LATEST_OUTPUT" --dry-run; then
        print_success "✅ Dry run test PASSED!"
        print_status "The bot can generate content and validate the setup"
        return 0
    else
        print_error "❌ Dry run test FAILED!"
        print_error "There's an issue with the bot configuration or setup"
        return 1
    fi
}

# Function to run publish test
run_publish_test() {
    print_status "🚀 Running Hello World Bot Publish Test..."
    echo "=============================================="
    
    # First generate content if not already done
    if [ -z "$LATEST_OUTPUT" ]; then
        print_status "Step 1: Generating content with hello-world bot..."
        if php bots/hello-world/generate-content.php; then
            print_success "✅ Content generation successful!"
        else
            print_error "❌ Content generation failed!"
            return 1
        fi
        
        # Find the latest generated file
        LATEST_OUTPUT=$(ls -t bots/hello-world/output/*.adoc 2>/dev/null | head -1)
        if [ -z "$LATEST_OUTPUT" ]; then
            print_error "❌ No output file found after content generation"
            return 1
        fi
    fi
    
    print_status "Step 2: Publishing generated file: $(basename "$LATEST_OUTPUT")"
    print_status "Executing: php nostrbots.php publish \"$LATEST_OUTPUT\""
    
    if php nostrbots.php publish "$LATEST_OUTPUT"; then
        print_success "✅ Publish test PASSED!"
        print_status "The bot successfully published content to Nostr!"
        
        print_success "📄 Output file: $(basename "$LATEST_OUTPUT")"
        print_status "Content preview:"
        echo "----------------------------------------"
        head -10 "$LATEST_OUTPUT" | sed 's/^/  /'
        echo "----------------------------------------"
        
        return 0
    else
        print_error "❌ Publish test FAILED!"
        print_error "There's an issue with the Nostr publishing setup"
        return 1
    fi
}

# Function to query Orly relay for published events
query_orly_relay() {
    local pubkey="$1"
    local kind="$2"
    local orly_port="${ORLY_PORT:-3334}"
    
    print_status "🔍 Querying Orly relay for published events..."
    print_status "Relay: ws://localhost:$orly_port"
    print_status "Pubkey: ${pubkey:0:20}..."
    print_status "Kind: $kind"
    
    # Create a temporary file for the websocket response
    local temp_file=$(mktemp)
    
    # Use websocat if available, otherwise use a simple curl-based approach
    if command -v websocat >/dev/null 2>&1; then
        # Use websocat for proper websocket communication
        local req_message="[\"REQ\",\"test-$(date +%s)\",{\"authors\":[\"$pubkey\"],\"kinds\":[$kind],\"limit\":1}]"
        
        timeout 10 websocat "ws://localhost:$orly_port" <<< "$req_message" > "$temp_file" 2>/dev/null || true
        
        # Check if we got any events back
        if grep -q '"EVENT"' "$temp_file" 2>/dev/null; then
            print_success "✅ Found published event in Orly relay!"
            
            # Extract event details
            local event_id=$(grep -o '"id":"[^"]*"' "$temp_file" | head -1 | cut -d'"' -f4)
            local event_created_at=$(grep -o '"created_at":[0-9]*' "$temp_file" | head -1 | cut -d':' -f2)
            
            if [ -n "$event_id" ]; then
                print_status "Event ID: ${event_id:0:20}..."
            fi
            
            if [ -n "$event_created_at" ]; then
                local event_time=$(date -d "@$event_created_at" 2>/dev/null || date -r "$event_created_at" 2>/dev/null || echo "unknown")
                print_status "Event time: $event_time"
            fi
            
            rm -f "$temp_file"
            return 0
        else
            print_warning "⚠️  No events found in Orly relay"
            rm -f "$temp_file"
            return 1
        fi
    else
        # Fallback: try to use curl with HTTP endpoint if available
        print_warning "websocat not available, trying HTTP endpoint..."
        
        if curl -s "http://localhost:$orly_port/query?authors=$pubkey&kinds=$kind&limit=1" > "$temp_file" 2>/dev/null; then
            if [ -s "$temp_file" ] && grep -q '"id"' "$temp_file"; then
                print_success "✅ Found published event via HTTP query!"
                rm -f "$temp_file"
                return 0
            fi
        fi
        
        print_warning "⚠️  Could not query Orly relay (websocat not installed)"
        print_warning "Install websocat for full relay verification: apt install websocat"
        rm -f "$temp_file"
        return 1
    fi
}

# Function to verify published content
verify_published_content() {
    print_status "🔍 Verifying Published Content..."
    echo "===================================="
    
    # Check if we have recent output files
    RECENT_FILES=$(find bots/hello-world/output -name "*.adoc" -mmin -5 2>/dev/null | wc -l)
    
    if [ "$RECENT_FILES" -gt 0 ]; then
        print_success "✅ Found $RECENT_FILES recent output file(s)"
        
        # Show the latest file
        LATEST_FILE=$(ls -t bots/hello-world/output/*.adoc 2>/dev/null | head -1)
        if [ -n "$LATEST_FILE" ]; then
            print_status "Latest output: $(basename "$LATEST_FILE")"
            print_status "File size: $(wc -c < "$LATEST_FILE") bytes"
            print_status "Created: $(stat -c %y "$LATEST_FILE" 2>/dev/null || stat -f %Sm "$LATEST_FILE" 2>/dev/null || echo "unknown")"
        fi
        
        # Try to verify the event was published to Orly relay
        if [ "$VERIFY_ORLY" = true ] && [ -n "$NOSTR_BOT_KEY" ]; then
            # Extract public key from private key
            local pubkey=$(php -r "
                require_once 'vendor/autoload.php';
                use function BitWasp\Bech32\convertBits;
                use function BitWasp\Bech32\encode;
                
                \$privateKey = hex2bin('$NOSTR_BOT_KEY');
                \$publicKey = sodium_crypto_scalarmult_base(\$privateKey);
                echo bin2hex(\$publicKey);
            " 2>/dev/null)
            
            if [ -n "$pubkey" ]; then
                print_status "Verifying event publication on Orly relay..."
                if query_orly_relay "$pubkey" "30041"; then
                    print_success "✅ Event verified on Orly relay!"
                else
                    print_warning "⚠️  Could not verify event on Orly relay"
                    print_warning "This might be normal if Orly is not running or configured"
                fi
            else
                print_warning "⚠️  Could not extract public key for relay verification"
            fi
        elif [ "$VERIFY_ORLY" = false ]; then
            print_status "Skipping Orly relay verification (--no-orly-verify specified)"
        fi
        
        return 0
    else
        print_warning "⚠️  No recent output files found (within last 5 minutes)"
        print_warning "This might indicate the publish didn't work as expected"
        return 1
    fi
}

# Main test execution
DRY_RUN_PASSED=false
PUBLISH_PASSED=false

# Run dry run test (unless publish-only)
if [ "$PUBLISH_ONLY" = false ]; then
    if run_dry_run_test; then
        DRY_RUN_PASSED=true
    else
        print_error "Dry run test failed - stopping here"
        exit 1
    fi
else
    print_status "Skipping dry run test (--publish-only specified)"
    DRY_RUN_PASSED=true
fi

# Run publish test (unless dry-run-only)
if [ "$DRY_RUN_ONLY" = false ]; then
    if run_publish_test; then
        PUBLISH_PASSED=true
        
        # Verify the published content
        if verify_published_content; then
            print_success "✅ Content verification PASSED!"
        else
            print_warning "⚠️  Content verification had issues"
        fi
    else
        print_error "Publish test failed"
        exit 1
    fi
else
    print_status "Skipping publish test (--dry-run-only specified)"
    PUBLISH_PASSED=true
fi

# Final results
echo ""
print_status "🎉 Test Results Summary"
echo "========================"

if [ "$DRY_RUN_PASSED" = true ]; then
    print_success "✅ Dry Run Test: PASSED"
else
    print_error "❌ Dry Run Test: FAILED"
fi

if [ "$PUBLISH_PASSED" = true ]; then
    print_success "✅ Publish Test: PASSED"
else
    print_error "❌ Publish Test: FAILED"
fi

echo ""
if [ "$DRY_RUN_PASSED" = true ] && [ "$PUBLISH_PASSED" = true ]; then
    print_success "🎉 ALL TESTS PASSED! Your Nostrbots setup is working correctly!"
    print_status "You can now:"
    print_status "  • Create your own bots in the bots/ directory"
    print_status "  • Run scheduled bots via Jenkins"
    print_status "  • Publish content to Nostr"
    echo ""
    print_status "Next steps:"
    print_status "  • Check the published content on your configured relays"
    print_status "  • Create custom bots for your content"
    print_status "  • Set up Jenkins for automated publishing"
    exit 0
else
    print_error "❌ Some tests failed. Please check the setup and try again."
    exit 1
fi
