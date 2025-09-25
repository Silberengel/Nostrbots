#!/bin/bash

# Test Hello World Bot Script
# Generates a test key and runs the Hello World bot

set -e

echo "🧪 Testing Hello World Bot"
echo "=========================="
echo ""

# Use the key generated earlier in the script
echo "🔑 Using Nostr key for testing: ${TEST_KEY:0:20}..."
echo ""

# Test content generation (dry run)
echo "📝 Testing content generation (dry run)..."
docker run --rm -v $(pwd)/bots:/app/bots nostrbots:latest run-bot --bot hello-world --dry-run --verbose

if [ $? -eq 0 ]; then
    echo "✅ Content generation test passed!"
else
    echo "❌ Content generation test failed!"
    exit 1
fi

echo ""

# Test actual publishing
echo "🚀 Testing actual publishing to test relays..."
BOT_OUTPUT=$(docker run --rm -v $(pwd)/bots:/app/bots -e NOSTR_BOT_KEY="$TEST_KEY" nostrbots:latest run-bot --bot hello-world --verbose)

if [ $? -eq 0 ]; then
    echo "✅ Publishing test passed!"
    
    # Extract event ID from bot output
    EVENT_ID=$(echo "$BOT_OUTPUT" | grep -o "Event ID: [a-f0-9]\{64\}" | cut -d' ' -f3)
    if [ -n "$EVENT_ID" ]; then
        echo ""
        echo "📄 Published Event Details:"
        echo "   Event ID: $EVENT_ID"
        echo "   Direct Link: https://next-alexandria.gitcitadel.eu/events?id=$EVENT_ID"
        echo ""
    fi
    
    echo "🎉 Hello World bot test completed successfully!"
    echo "Check the test relays to see your published article:"
    echo "- wss://freelay.sovbit.host"
    echo "- wss://relay.damus.io"
else
    echo "❌ Publishing test failed!"
    exit 1
fi
