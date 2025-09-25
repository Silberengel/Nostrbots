#!/bin/bash

# 02-setup-jenkins.sh
# Sets up Jenkins container with encrypted environment variables
# Can be run independently or as part of the complete setup
#
# Usage: bash scripts/02-setup-jenkins.sh [JENKINS_PORT] [AGENT_PORT]
#   JENKINS_PORT: Jenkins web interface port (default: 8080)
#   AGENT_PORT: Jenkins agent port (default: 50000)

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[02-SETUP-JENKINS]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

# Parse command line arguments
JENKINS_PORT=${1:-8080}
AGENT_PORT=${2:-50000}

# Validate port numbers
if ! [[ "$JENKINS_PORT" =~ ^[0-9]+$ ]] || [ "$JENKINS_PORT" -lt 1024 ] || [ "$JENKINS_PORT" -gt 65535 ]; then
    print_error "Invalid Jenkins port: $JENKINS_PORT. Must be a number between 1024-65535"
    exit 1
fi

if ! [[ "$AGENT_PORT" =~ ^[0-9]+$ ]] || [ "$AGENT_PORT" -lt 1024 ] || [ "$AGENT_PORT" -gt 65535 ]; then
    print_error "Invalid agent port: $AGENT_PORT. Must be a number between 1024-65535"
    exit 1
fi

print_status "Setting up Jenkins container with encrypted environment..."
print_status "Jenkins port: $JENKINS_PORT"
print_status "Agent port: $AGENT_PORT"

# Check if we're in the right directory
if [ ! -f "Dockerfile" ] || [ ! -f "Jenkinsfile" ]; then
    print_error "Please run this script from the Nostrbots project root directory"
    exit 1
fi

# Check if encrypted key variables are in environment
if [ -z "$NOSTR_BOT_KEY_ENCRYPTED" ]; then
    print_error "No encrypted key found in environment. Please run 01-generate-key.sh first"
    exit 1
fi

print_status "Using encrypted key variables from environment..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again"
    exit 1
fi

# Stop existing Jenkins container if running
print_status "Stopping existing Jenkins container (if any)..."
docker stop jenkins-nostrbots 2>/dev/null || true
docker rm jenkins-nostrbots 2>/dev/null || true

# Create Jenkins data directory
print_status "Creating Jenkins data directory..."
mkdir -p jenkins-data
chmod 755 jenkins-data

# Create Docker Compose file with encrypted environment variables
print_status "Creating Jenkins Docker Compose configuration..."
cat > docker-compose.jenkins.yml << EOF
services:
  jenkins:
    image: jenkins/jenkins:lts
    container_name: jenkins-nostrbots
    user: jenkins
    ports:
      - "127.0.0.1:$JENKINS_PORT:8080"
      - "127.0.0.1:$AGENT_PORT:50000"
    volumes:
      - ./jenkins-data:/var/jenkins_home
      - /var/run/docker.sock:/var/run/docker.sock
      - $(pwd):/workspace
    environment:
      - JENKINS_OPTS=--httpPort=8080
      - NOSTR_BOT_KEY_ENCRYPTED=$NOSTR_BOT_KEY_ENCRYPTED
    networks:
      - jenkins-network
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
    read_only: false
    tmpfs:
      - /tmp
      - /var/run
    deploy:
      resources:
        limits:
          memory: 2G
          cpus: '1.0'
        reservations:
          memory: 512M
          cpus: '0.5'

  # Nostrbots build agent
  nostrbots-agent:
    build:
      context: .
      dockerfile: Dockerfile
    image: nostrbots:jenkins
    container_name: nostrbots-agent
    user: jenkins
    volumes:
      - $(pwd)/bots:/app/bots
      - $(pwd)/logs:/app/logs
      - $(pwd)/tmp:/app/tmp
      - /var/run/docker.sock:/var/run/docker.sock
    environment:
      - NOSTR_BOT_KEY_ENCRYPTED=$NOSTR_BOT_KEY_ENCRYPTED
    networks:
      - jenkins-network
    command: ["tail", "-f", "/dev/null"]
    security_opt:
      - no-new-privileges:true
    read_only: false
    tmpfs:
      - /tmp
      - /var/run
    deploy:
      resources:
        limits:
          memory: 1G
          cpus: '0.5'
        reservations:
          memory: 256M
          cpus: '0.25'

networks:
  jenkins-network:
    driver: bridge
EOF

# Set secure permissions on generated files
print_status "Setting secure file permissions..."
chmod 600 docker-compose.jenkins.yml
chmod 755 jenkins-data

# Start Jenkins
print_status "Starting Jenkins container..."
docker compose -f docker-compose.jenkins.yml up -d jenkins

# Wait for Jenkins to start
print_status "Waiting for Jenkins to start (this may take a few minutes)..."
timeout=300
counter=0
while ! curl -s http://localhost:$JENKINS_PORT > /dev/null 2>&1; do
    if [ $counter -ge $timeout ]; then
        print_error "Jenkins failed to start within $timeout seconds"
        exit 1
    fi
    sleep 5
    counter=$((counter + 5))
    echo -n "."
done
echo ""

print_success "Jenkins is running!"

# Get initial admin password
print_status "Getting Jenkins initial admin password..."
sleep 10  # Give Jenkins time to fully initialize

ADMIN_PASSWORD=$(docker exec jenkins-nostrbots cat /var/jenkins_home/secrets/initialAdminPassword 2>/dev/null || echo "")

if [ -z "$ADMIN_PASSWORD" ]; then
    print_warning "Could not retrieve initial admin password automatically"
    print_status "Please check the Jenkins container logs:"
    echo "  docker logs jenkins-nostrbots"
    print_status "Or visit http://localhost:$JENKINS_PORT and follow the setup wizard"
else
    print_success "Jenkins initial admin password: $ADMIN_PASSWORD"
fi

# Export admin password for other scripts (environment only)
export JENKINS_ADMIN_PASSWORD="$ADMIN_PASSWORD"

print_success "Jenkins setup complete!"
print_status "Jenkins URL: http://localhost:$JENKINS_PORT"
print_status "Admin password exported to environment"

# Export port configuration for other scripts (environment only)
export JENKINS_PORT
export AGENT_PORT
