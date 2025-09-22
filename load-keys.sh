#!/bin/bash

# Load keys script that reads the NOSTR_BOT_KEY environment variable and exports it
# This ensures that the Electron app can access the key

# Source the user's shell configuration
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
    echo "⚠️  NOSTR_BOT_KEY not found"
fi

# Run the PHP script with the exported environment variable
php manage-keys.php list
