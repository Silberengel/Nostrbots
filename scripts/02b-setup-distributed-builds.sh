#!/bin/bash

# 02b-setup-distributed-builds.sh
# Sets up distributed builds with Jenkins agents
# Can be run independently or as part of the complete setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[02B-SETUP-DISTRIBUTED-BUILDS]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

print_status "Setting up distributed builds with Jenkins agents..."

# Load port configuration from environment
if [ -z "$JENKINS_PORT" ] || [ -z "$AGENT_PORT" ]; then
    JENKINS_PORT=8080
    AGENT_PORT=50000
    print_warning "No port configuration found in environment, using default ports"
fi

# Check if Jenkins is running
if ! curl -s http://localhost:$JENKINS_PORT > /dev/null 2>&1; then
    print_error "Jenkins is not running on localhost:$JENKINS_PORT"
    print_error "Please run 02-setup-jenkins.sh first"
    exit 1
fi

# Get nostrbots user password from environment
if [ -z "$NOSTRBOTS_PASSWORD" ]; then
    print_error "No nostrbots user password found in environment. Please run 02a-create-nostrbots-user.sh first"
    exit 1
fi

JENKINS_URL="http://localhost:$JENKINS_PORT"
AUTH_USER="nostrbots:$NOSTRBOTS_PASSWORD"

# Get CSRF crumb
print_status "Getting CSRF crumb..."
CRUMB=$(curl -s -u $AUTH_USER "$JENKINS_URL/crumbIssuer/api/xml?xpath=concat(//crumbRequestField,\":\",//crumb)" 2>/dev/null || echo "")

# Create Jenkins agent node
print_status "Creating Jenkins agent node..."

# Create agent configuration XML
cat > /tmp/agent-config.xml << EOF
<?xml version='1.1' encoding='UTF-8'?>
<slave>
  <name>nostrbots-agent</name>
  <description>Nostrbots build agent for distributed builds</description>
  <remoteFS>/app</remoteFS>
  <numExecutors>2</numExecutors>
  <mode>NORMAL</mode>
  <retentionStrategy class="hudson.slaves.RetentionStrategy\$Always"/>
  <launcher class="hudson.slaves.JNLPLauncher">
    <workDirSettings>
      <disabled>false</disabled>
      <workDirPath></workDirPath>
      <internalDir>remoting</internalDir>
      <failIfWorkDirIsMissing>false</failIfWorkDirIsMissing>
    </workDirSettings>
    <webSocket>false</webSocket>
    <credentialsId></credentialsId>
  </launcher>
  <label>nostrbots docker</label>
  <nodeProperties>
    <hudson.slaves.EnvironmentVariablesNodeProperty>
      <env>
        <hudson.slaves.EnvironmentVariablesNodeProperty\$Entry>
          <key>NOSTR_BOT_KEY_ENCRYPTED</key>
          <value>\$NOSTR_BOT_KEY_ENCRYPTED</value>
        </hudson.slaves.EnvironmentVariablesNodeProperty\$Entry>
        <hudson.slaves.EnvironmentVariablesNodeProperty\$Entry>
          <key>NOSTR_BOT_KEY_PASSWORD</key>
          <value>\$NOSTR_BOT_KEY_PASSWORD</value>
        </hudson.slaves.EnvironmentVariablesNodeProperty\$Entry>
      </env>
    </hudson.slaves.EnvironmentVariablesNodeProperty>
  </nodeProperties>
</slave>
EOF

# Create the agent node
CREATE_AGENT_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null \
    -X POST \
    -u $AUTH_USER \
    -H "Content-Type: application/xml" \
    -H "$CRUMB" \
    -d @/tmp/agent-config.xml \
    "$JENKINS_URL/computer/doCreateItem?name=nostrbots-agent&type=hudson.slaves.DumbSlave" 2>/dev/null || echo "000")

if [ "$CREATE_AGENT_RESPONSE" = "200" ] || [ "$CREATE_AGENT_RESPONSE" = "302" ]; then
    print_success "Jenkins agent node created successfully!"
else
    print_warning "Agent creation returned HTTP $CREATE_AGENT_RESPONSE (may already exist)"
fi

# Get agent secret for JNLP connection
print_status "Getting agent connection secret..."
AGENT_SECRET=$(curl -s -u $AUTH_USER "$JENKINS_URL/computer/nostrbots-agent/slave-agent.jnlp" | grep -o 'secret="[^"]*"' | cut -d'"' -f2 || echo "")

if [ -n "$AGENT_SECRET" ]; then
    print_success "Agent secret retrieved: ${AGENT_SECRET:0:10}..."
    export JENKINS_AGENT_SECRET="$AGENT_SECRET"
else
    print_warning "Could not retrieve agent secret automatically"
fi

# Update Docker Compose to include agent with JNLP
print_status "Updating Docker Compose configuration for distributed builds..."

# Check if encrypted key variables are in environment
if [ -z "$NOSTR_BOT_KEY_ENCRYPTED" ] || [ -z "$NOSTR_BOT_KEY_PASSWORD" ]; then
    print_error "No encrypted key found in environment. Please run 01-generate-key.sh first"
    exit 1
fi

# Update docker-compose.jenkins.yml to include agent configuration
cat > docker-compose.jenkins.yml << EOF
services:
  jenkins:
    image: jenkins/jenkins:lts
    container_name: jenkins-nostrbots
    ports:
      - "$JENKINS_PORT:8080"
      - "$AGENT_PORT:50000"
    volumes:
      - ./jenkins-data:/var/jenkins_home
      - /var/run/docker.sock:/var/run/docker.sock
      - $(pwd):/workspace
    environment:
      - JENKINS_OPTS=--httpPort=8080
      - NOSTR_BOT_KEY_ENCRYPTED=$NOSTR_BOT_KEY_ENCRYPTED
      - NOSTR_BOT_KEY_PASSWORD=$NOSTR_BOT_KEY_PASSWORD
    networks:
      - jenkins-network
    restart: unless-stopped

  # Nostrbots build agent
  nostrbots-agent:
    build:
      context: .
      dockerfile: Dockerfile
    image: nostrbots:jenkins
    container_name: nostrbots-agent
    volumes:
      - $(pwd)/bots:/app/bots
      - $(pwd)/logs:/app/logs
      - $(pwd)/tmp:/app/tmp
      - /var/run/docker.sock:/var/run/docker.sock
    environment:
      - NOSTR_BOT_KEY_ENCRYPTED=$NOSTR_BOT_KEY_ENCRYPTED
      - NOSTR_BOT_KEY_PASSWORD=$NOSTR_BOT_KEY_PASSWORD
      - JENKINS_URL=http://jenkins:8080
      - JENKINS_AGENT_NAME=nostrbots-agent
      - JENKINS_AGENT_SECRET=$AGENT_SECRET
    networks:
      - jenkins-network
    depends_on:
      - jenkins
    command: ["tail", "-f", "/dev/null"]
    restart: unless-stopped

networks:
  jenkins-network:
    driver: bridge
EOF

# Start the agent container
print_status "Starting Jenkins agent container..."
docker compose -f docker-compose.jenkins.yml up -d nostrbots-agent

# Wait for agent to start
print_status "Waiting for agent to start..."
sleep 10

# Connect agent to Jenkins via JNLP
print_status "Connecting agent to Jenkins..."
if [ -n "$AGENT_SECRET" ]; then
    # Start JNLP agent connection
    docker exec -d nostrbots-agent java -jar /usr/share/jenkins/agent.jar \
        -jnlpUrl "http://jenkins:8080/computer/nostrbots-agent/jenkins-agent.jnlp" \
        -secret "$AGENT_SECRET" \
        -workDir /app
    
    print_success "Agent connection initiated!"
else
    print_warning "Cannot connect agent - no secret available"
fi

# Cleanup
rm -f /tmp/agent-config.xml

print_success "Distributed builds setup complete!"
print_status "Jenkins agent: nostrbots-agent"
print_status "Agent secret exported to environment"
print_status "Agent will run builds in distributed mode"
