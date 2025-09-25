#!/bin/bash

# 01-generate-key.sh
# Generates and encrypts a Nostr bot key for Jenkins
# Can be run independently or as part of the complete setup
#
# Usage: bash scripts/01-generate-key.sh [--key YOUR_KEY] [--force]
#   --key YOUR_KEY: Use your existing Nostr key instead of generating a new one
#   --force: Force regeneration even if key exists in environment

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[01-GENERATE-KEY]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

print_status "Generating and encrypting Nostr bot key..."

# Check if we're in the right directory
if [ ! -f "generate-key.php" ]; then
    print_error "Please run this script from the Nostrbots project root directory"
    exit 1
fi

# Parse command line arguments
EXISTING_KEY=""
FORCE_REGENERATE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --key)
            EXISTING_KEY="$2"
            # Validate key format (should be hex string)
            if [[ ! "$EXISTING_KEY" =~ ^[0-9a-fA-F]+$ ]]; then
                print_error "Invalid key format. Must be hexadecimal string."
                exit 1
            fi
            shift 2
            ;;
        --force)
            FORCE_REGENERATE=true
            shift
            ;;
        *)
            print_error "Unknown option: $1"
            print_status "Usage: bash scripts/01-generate-key.sh [--key YOUR_KEY] [--force]"
            exit 1
            ;;
    esac
done

# Check if key already exists in environment
if [ -n "$NOSTR_BOT_KEY_ENCRYPTED" ] && [ "$FORCE_REGENERATE" = false ]; then
    print_warning "Nostr bot key already exists in environment. Use --force to regenerate."
    print_status "Using existing environment variables..."
    print_success "Using existing encrypted key from environment"
    exit 0
fi

# Generate or encrypt key using the PHP script (always uses default password)
if [ -n "$EXISTING_KEY" ]; then
    print_status "Encrypting your existing Nostr key with default password..."
    JENKINS_KEY_OUTPUT=$(php generate-key.php --key "$EXISTING_KEY" --encrypt --quiet 2>/dev/null)
else
    print_status "Generating new encrypted Nostr key with default password..."
    JENKINS_KEY_OUTPUT=$(php generate-key.php --encrypt --quiet 2>/dev/null)
fi

# Parse the output to get the encrypted key
NOSTR_BOT_KEY_ENCRYPTED=$(echo "$JENKINS_KEY_OUTPUT" | grep "NOSTR_BOT_KEY_ENCRYPTED=" | cut -d'=' -f2)

if [ -z "$NOSTR_BOT_KEY_ENCRYPTED" ]; then
    print_error "Failed to generate encrypted key"
    exit 1
fi

print_success "Generated encrypted Nostr bot key"
print_status "Encrypted key: ${NOSTR_BOT_KEY_ENCRYPTED:0:50}..."

# Export only the encrypted key for current session
export NOSTR_BOT_KEY_ENCRYPTED

# Also output it for sourcing by parent scripts
echo "export NOSTR_BOT_KEY_ENCRYPTED=\"$NOSTR_BOT_KEY_ENCRYPTED\""

if [ -n "$EXISTING_KEY" ]; then
    print_success "Key encryption complete! Your existing key is now encrypted and exported to environment"
else
    print_success "Key generation complete! Variables exported to environment"
fi
print_warning "IMPORTANT: Keys are only in environment variables - not saved to files!"
