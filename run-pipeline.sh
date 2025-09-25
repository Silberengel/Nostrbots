#!/bin/bash

# Nostrbots Pipeline Runner
# Runs the nostrbots pipeline locally for testing

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Check if Docker is running
if ! docker info >/dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Check if nostrbots image exists
if ! docker images | grep -q "silberengel/nostrbots"; then
    log_info "Pulling nostrbots image..."
    docker pull silberengel/nostrbots:latest
fi

# Load environment variables if they exist
if [ -f ".env" ]; then
    log_info "Loading environment variables from .env file..."
    export $(grep -v '^#' .env | xargs)
elif [ -f "nostrbots.env" ]; then
    log_info "Loading environment variables from nostrbots.env..."
    source nostrbots.env
else
    log_warning "No .env file found. Make sure environment variables are set."
fi

echo "🚀 Running Nostrbots Pipeline"
echo "============================="

# Run the pipeline script
bash nostrbots-script.sh

log_success "Pipeline completed successfully!"
