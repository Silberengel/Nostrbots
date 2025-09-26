#!/bin/bash

# Clear Current Session History Script
# This script can be run directly or sourced to get the clear_history function

clear_history() {
    echo "🧹 Clearing Current Session History"
    echo "==================================="
    echo ""
    
    echo "Current history length: $(history | wc -l)"
    echo ""
    
    echo "Clearing current session history..."
    history -c
    
    echo "History after clearing: $(history | wc -l)"
    echo ""
    
    echo "✓ Current session history cleared!"
    echo ""
    echo "📋 What was done:"
    echo "• Cleared current shell session history"
    echo "• Bash history file was already cleaned by cleanup script"
    echo ""
    echo "🔒 Your sensitive commands should no longer appear when pressing the up arrow."
    echo ""
}

# If this script is run directly (not sourced), run the function
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    echo "⚠  NOTE: This script runs in a subshell, so it only clears history within that subshell."
    echo "   For complete history clearing in your current shell, run:"
    echo "   source ./clear-history.sh && clear_history"
    echo "   Or simply: history -c"
    echo ""
    clear_history
fi