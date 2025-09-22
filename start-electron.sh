#!/bin/bash

# Start Electron app with proper environment variables loaded
# This script ensures that the Electron app has access to all NOSTR_BOT_KEY variables

echo "🚀 Starting Nostrbots Desktop App..."

# Source the user's shell configuration to get environment variables
if [ -f ~/.bashrc ]; then
    source ~/.bashrc
fi

if [ -f ~/.bash_profile ]; then
    source ~/.bash_profile
fi

if [ -f ~/.profile ]; then
    source ~/.profile
fi

# Export the NOSTR_BOT_KEY variable if it's set
if [ -n "$NOSTR_BOT_KEY" ]; then
    export NOSTR_BOT_KEY
    echo "✅ NOSTR_BOT_KEY loaded"
else
    echo "⚠️  NOSTR_BOT_KEY environment variable not found."
    echo "   Generate a key with: php manage-keys.php generate"
    echo "   Then set the environment variable: export NOSTR_BOT_KEY=your_key_here"
    echo "   And restart the app."
    echo ""
fi

echo "🔑 Bot key status: $([ -n "$NOSTR_BOT_KEY" ] && echo "Configured" || echo "Not configured")"
echo ""

# Change to the electron-app directory and start the app
cd "$(dirname "$0")/electron-app"

# Start Electron with the loaded environment variables
if [ "$1" = "--dev" ]; then
    echo "🔧 Starting in development mode..."
    npm run dev
else
    echo "🚀 Starting in production mode..."
    npm start
fi
