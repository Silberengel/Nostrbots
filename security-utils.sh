#!/bin/bash

# Nostrbots Security Utilities
# Shared security functions for all setup scripts

# Core security cleanup function (always runs)
_secure_cleanup_core() {
    # Clear sensitive environment variables
    unset CUSTOM_PRIVATE_KEY
    unset NOSTR_BOT_KEY
    unset NOSTR_BOT_KEY_ENCRYPTED
    unset NOSTR_BOT_NPUB
    unset NOSTR_BOT_KEY_HEX
    unset NOSTR_BOT_NSEC
    
    # Clear bash history file if writable
    if [ -w ~/.bash_history ]; then
        # Remove lines containing private key commands
        sed -i '/--private-key/d' ~/.bash_history 2>/dev/null || true
        sed -i '/CUSTOM_PRIVATE_KEY/d' ~/.bash_history 2>/dev/null || true
        sed -i '/NOSTR_BOT_KEY/d' ~/.bash_history 2>/dev/null || true
        sed -i '/nsec1/d' ~/.bash_history 2>/dev/null || true
        sed -i '/setup.*private-key/d' ~/.bash_history 2>/dev/null || true
    fi
    
    # Clear current session history more aggressively
    history -c 2>/dev/null || true
    
    # Try to clear history in different ways depending on shell
    if [ -n "${BASH_VERSION:-}" ]; then
        # For bash, try multiple methods
        history -c 2>/dev/null || true
        unset HISTFILE 2>/dev/null || true
        # Clear the history array
        if [ -n "${HISTSIZE:-}" ]; then
            for ((i=1; i<=HISTSIZE; i++)); do
                history -d 1 2>/dev/null || true
            done
        fi
    fi
    
    # Force clear the history buffer
    printf '\033[2J\033[H' 2>/dev/null || true
}

# Security cleanup function for history only (during script execution)
_secure_cleanup_history_only() {
    # Clear bash history file if writable
    if [ -w ~/.bash_history ]; then
        # Remove lines containing private key commands
        sed -i '/--private-key/d' ~/.bash_history 2>/dev/null || true
        sed -i '/CUSTOM_PRIVATE_KEY/d' ~/.bash_history 2>/dev/null || true
        sed -i '/NOSTR_BOT_KEY/d' ~/.bash_history 2>/dev/null || true
        sed -i '/nsec1/d' ~/.bash_history 2>/dev/null || true
        sed -i '/setup.*private-key/d' ~/.bash_history 2>/dev/null || true
    fi
    
    # Clear current session history more aggressively
    history -c 2>/dev/null || true
    
    # Try to clear history in different ways depending on shell
    if [ -n "${BASH_VERSION:-}" ]; then
        # For bash, try multiple methods
        history -c 2>/dev/null || true
        unset HISTFILE 2>/dev/null || true
        # Clear the history array
        if [ -n "${HISTSIZE:-}" ]; then
            for ((i=1; i<=HISTSIZE; i++)); do
                history -d 1 2>/dev/null || true
            done
        fi
    fi
    
    # Force clear the history buffer
    printf '\033[2J\033[H' 2>/dev/null || true
}

# Security cleanup function (conditional - only runs if private key was used)
secure_cleanup() {
    # Clear bash history if private key was provided via command line
    if [[ "$*" == *"--private-key"* ]]; then
        # Only clear history, not environment variables during script execution
        _secure_cleanup_history_only
        
        # Log the cleanup (if log function is available)
        if command -v log >/dev/null 2>&1; then
            log "🔒 Security cleanup completed - history cleared"
        elif command -v log_info >/dev/null 2>&1; then
            log_info "🔒 Security cleanup completed - history cleared"
        else
            echo "🔒 Security cleanup completed - history cleared"
        fi
    fi
}

# Always perform security cleanup (for cleanup script)
secure_cleanup_always() {
    _secure_cleanup_core
    
    # Log the cleanup (if log function is available)
    if command -v log >/dev/null 2>&1; then
        log "🔒 Security cleanup completed - both file and session history cleared"
    elif command -v log_info >/dev/null 2>&1; then
        log_info "🔒 Security cleanup completed - both file and session history cleared"
    else
        echo "🔒 Security cleanup completed - both file and session history cleared"
    fi
    
    # Provide instructions for complete history clearing
    echo ""
    echo "🔄 To completely clear your current session history:"
    echo "   1. Run: source ./clear-history.sh && clear_history"
    echo "   2. Or run: history -c"
    echo "   3. Or restart your terminal/shell session"
    echo ""
    echo "   The bash history file has been cleaned, but the current"
    echo "   session history may still contain sensitive commands."
    echo ""
}

# Check if private key was provided via command line and warn
check_command_line_security() {
    if [[ "$*" == *"--private-key"* ]]; then
        # Log warning (if log function is available)
        if command -v warn >/dev/null 2>&1; then
            warn "⚠  SECURITY WARNING: Private key provided via command line!"
            warn "   This may be stored in shell history. For better security, use:"
            warn "   export CUSTOM_PRIVATE_KEY='your_key_here'"
            warn "   $0"
        elif command -v log_warn >/dev/null 2>&1; then
            log_warn "⚠  SECURITY WARNING: Private key provided via command line!"
            log_warn "   This may be stored in shell history. For better security, use:"
            log_warn "   export CUSTOM_PRIVATE_KEY='your_key_here'"
            log_warn "   $0"
        else
            echo "⚠  SECURITY WARNING: Private key provided via command line!"
            echo "   This may be stored in shell history. For better security, use:"
            echo "   export CUSTOM_PRIVATE_KEY='your_key_here'"
            echo "   $0"
        fi
        echo ""
    fi
}

# Set up security cleanup trap
setup_security_trap() {
    trap 'secure_cleanup "$@"' EXIT
}
