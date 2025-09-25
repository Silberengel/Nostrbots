#!/bin/bash

# 01-generate-key.sh
# Generates and encrypts a Nostr bot key for Jenkins
# Can be run independently or as part of the complete setup
#
# Usage: bash scripts/01-generate-key.sh [--key YOUR_KEY] [--password PASSWORD] [--force]
#   --key YOUR_KEY: Use your existing Nostr key instead of generating a new one
#   --password PASSWORD: Use custom password for encryption (default: secure default)
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
CUSTOM_PASSWORD=""
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
        --password)
            CUSTOM_PASSWORD="$2"
            # Validate password length
            if [ ${#CUSTOM_PASSWORD} -lt 8 ]; then
                print_error "Password must be at least 8 characters long."
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
            print_status "Usage: bash scripts/01-generate-key.sh [--key YOUR_KEY] [--password PASSWORD] [--force]"
            exit 1
            ;;
    esac
done

# Check if key already exists in environment
if [ -n "$NOSTR_BOT_KEY_ENCRYPTED" ] && [ -n "$NOSTR_BOT_KEY_PASSWORD" ] && [ "$FORCE_REGENERATE" = false ]; then
    print_warning "Nostr bot key already exists in environment. Use --force to regenerate."
    print_status "Using existing environment variables..."
    print_success "Using existing encrypted key from environment"
    exit 0
fi

# Generate or encrypt key using the PHP script
if [ -n "$EXISTING_KEY" ]; then
    print_status "Encrypting your existing Nostr key..."
    if [ -n "$CUSTOM_PASSWORD" ]; then
        JENKINS_KEY_OUTPUT=$(php generate-key.php --key "$EXISTING_KEY" --password "$CUSTOM_PASSWORD" --encrypt --jenkins --quiet)
    else
        JENKINS_KEY_OUTPUT=$(php generate-key.php --key "$EXISTING_KEY" --encrypt --jenkins --quiet)
    fi
else
    print_status "Generating new encrypted Nostr key..."
    if [ -n "$CUSTOM_PASSWORD" ]; then
        JENKINS_KEY_OUTPUT=$(php generate-key.php --password "$CUSTOM_PASSWORD" --jenkins --quiet)
    else
        JENKINS_KEY_OUTPUT=$(php generate-key.php --jenkins --quiet)
    fi
fi

# Parse the output to get the variables
NOSTR_BOT_KEY_ENCRYPTED=$(echo "$JENKINS_KEY_OUTPUT" | grep "NOSTR_BOT_KEY_ENCRYPTED=" | cut -d'=' -f2)
NOSTR_BOT_KEY_PASSWORD=$(echo "$JENKINS_KEY_OUTPUT" | grep "NOSTR_BOT_KEY_PASSWORD=" | cut -d'=' -f2)

if [ -z "$NOSTR_BOT_KEY_ENCRYPTED" ] || [ -z "$NOSTR_BOT_KEY_PASSWORD" ]; then
    print_error "Failed to generate encrypted key"
    exit 1
fi

print_success "Generated encrypted Nostr bot key"
print_status "Encrypted key: ${NOSTR_BOT_KEY_ENCRYPTED:0:50}..."
print_status "Password: ${NOSTR_BOT_KEY_PASSWORD:0:20}..."

# Export variables for current session
export NOSTR_BOT_KEY_ENCRYPTED
export NOSTR_BOT_KEY_PASSWORD

if [ -n "$EXISTING_KEY" ]; then
    print_success "Key encryption complete! Your existing key is now encrypted and exported to environment"
else
    print_success "Key generation complete! Variables exported to environment"
fi
print_warning "IMPORTANT: Keys are only in environment variables - not saved to files!"
