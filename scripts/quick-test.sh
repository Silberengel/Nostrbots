#!/bin/bash

# quick-test.sh
# Quick test script for CI/CD and automated testing
# Runs a simple dry run test to verify the setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Quick validation
if [ -z "$NOSTR_BOT_KEY" ]; then
    print_error "NOSTR_BOT_KEY not set"
    exit 1
fi

if [ ! -f "nostrbots.php" ]; then
    print_error "nostrbots.php not found"
    exit 1
fi

if [ ! -d "bots/hello-world" ]; then
    print_error "Hello world bot not found"
    exit 1
fi

# Run dry run test
if php nostrbots.php publish bots/hello-world --dry-run > /dev/null 2>&1; then
    print_success "Quick test passed - setup is working"
    
    # Optional: Quick Orly verification if websocat is available
    if command -v websocat >/dev/null 2>&1 && [ -n "$NOSTR_BOT_KEY" ]; then
        # Extract public key and do a quick relay check
        pubkey=$(php -r "
            require_once 'vendor/autoload.php';
            \$privateKey = hex2bin('$NOSTR_BOT_KEY');
            \$publicKey = sodium_crypto_scalarmult_base(\$privateKey);
            echo bin2hex(\$publicKey);
        " 2>/dev/null)
        
        if [ -n "$pubkey" ]; then
            # Quick relay check (timeout after 3 seconds)
            if timeout 3 websocat "ws://localhost:3334" <<< "[\"REQ\",\"quick-test\",{\"authors\":[\"$pubkey\"],\"kinds\":[30041],\"limit\":1}]" 2>/dev/null | grep -q '"EVENT"'; then
                print_success "Orly relay verification passed"
            else
                print_success "Quick test passed (Orly relay not available or no recent events)"
            fi
        fi
    fi
    
    exit 0
else
    print_error "Quick test failed - setup has issues"
    exit 1
fi
