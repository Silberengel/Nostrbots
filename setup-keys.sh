#!/bin/bash

# Setup script to help users configure their Nostr bot keys
# This script will add the necessary environment variables to the user's shell profile

echo "🔑 Nostrbots Key Setup"
echo "======================"
echo ""

# Check if we have the bot key
if [ -n "$NOSTR_BOT_KEY" ]; then
    echo "✅ Found existing bot key: NOSTR_BOT_KEY"
    echo ""
else
    echo "⚠️  No NOSTR_BOT_KEY environment variable found."
    echo ""
fi

# Generate a new key if requested
if [ "$1" = "--generate" ]; then
    echo "🔧 Generating new key..."
    echo ""
    
    # Generate the key
    output=$(php manage-keys.php generate 2>&1)
    echo "$output"
    echo ""
    
    # Extract the environment variable and value from the output
    env_line=$(echo "$output" | grep "export NOSTR_BOT_KEY=")
    if [ -n "$env_line" ]; then
        echo "📝 To make this key permanent, add this line to your shell profile:"
        echo "   $env_line"
        echo ""
        
        # Ask if user wants to add it automatically
        read -p "🤔 Would you like to add this to your ~/.bashrc automatically? (y/n): " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            echo "$env_line" >> ~/.bashrc
            echo "✅ Added to ~/.bashrc"
            echo "   Run 'source ~/.bashrc' or restart your terminal to use the key."
        fi
    fi
else
    echo "💡 To generate a new key, run:"
    echo "   ./setup-keys.sh --generate"
    echo ""
    echo "💡 To start the Electron app with keys loaded, run:"
    echo "   ./start-electron.sh"
    echo ""
    echo "💡 To test your current keys, run:"
    echo "   ./load-keys.sh"
fi
