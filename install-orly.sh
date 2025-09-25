#!/bin/bash

# ORLY Relay Installation Script
# Installs and configures ORLY relay for Nostrbots testing

set -e

echo "🔧 Installing ORLY Relay"
echo "========================"

# Check if we're in the right directory
if [ ! -f "Dockerfile" ] || [ ! -f "Jenkinsfile" ]; then
    echo "❌ Please run this script from the Nostrbots project root directory."
    exit 1
fi

# Create orly directory
ORLY_DIR="../orly"
if [ ! -d "$ORLY_DIR" ]; then
    echo "📁 Cloning ORLY repository..."
    git clone https://github.com/mleku/next.orly.dev.git "$ORLY_DIR"
else
    echo "📁 ORLY directory already exists, updating..."
    cd "$ORLY_DIR"
    git pull
    cd - > /dev/null
fi

# Build ORLY
echo "🔨 Building ORLY..."
cd "$ORLY_DIR"

# Check if Go is installed
if ! command -v go &> /dev/null; then
    echo "❌ Go is not installed. Please install Go 1.21+ and try again."
    exit 1
fi

# Build ORLY
echo "Building ORLY binary..."
go build -o orly

# Create ORLY configuration
echo "⚙️  Creating ORLY configuration..."
cat > .env << ORLY_ENV
# ORLY Configuration for Nostrbots Testing
ORLY_PORT=$ORLY_PORT
ORLY_HOST=localhost
ORLY_DB_PATH=./orly.db
ORLY_ACL_MODE=follows
ORLY_SPIDER_MODE=follow
ORLY_SPIDER_FREQUENCY=30m
ORLY_LOG_LEVEL=info
ORLY_MAX_EVENTS=10000
ORLY_MAX_TAGS=2000
ORLY_MAX_CONTENT=100000
ORLY_ENV

# Get the Nostr key from environment or generate one
if [ -n "$NOSTR_BOT_KEY" ]; then
    echo "🔑 Using provided Nostr key for ORLY admin..."
    # Convert hex key to npub format (simplified - in real implementation you'd use proper conversion)
    NPUB_KEY="npub1$(echo "$NOSTR_BOT_KEY" | xxd -r -p | base64 -w 0 | tr -d '=' | tr '+/' '-_' | cut -c1-32)"
    echo "ORLY_ADMINS=$NPUB_KEY" >> .env
    echo "✅ ORLY admin set to: $NPUB_KEY"
else
    echo "⚠️  No NOSTR_BOT_KEY provided. ORLY will run without admin configuration."
fi

# Create startup script
cat > start-orly.sh << 'ORLY_START'
#!/bin/bash
echo "🚀 Starting ORLY relay..."
echo "Relay will be available at: ws://localhost:$ORLY_PORT"
echo "Admin key: $ORLY_ADMINS"
echo ""
./orly
ORLY_START

chmod +x start-orly.sh

cd - > /dev/null

echo "✅ ORLY relay installation complete!"
echo "📁 ORLY directory: $ORLY_DIR"
echo "🚀 To start ORLY: cd $ORLY_DIR && ./start-orly.sh"
