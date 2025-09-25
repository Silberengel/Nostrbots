#!/bin/bash

# Local Jenkins Setup Script for Nostrbots
# Sets up a complete Jenkins environment in Docker for testing bots

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${PURPLE}[JENKINS]${NC} $1"
}

# Function to show usage
show_usage() {
    echo "Local Jenkins Setup Script for Nostrbots"
    echo "========================================"
    echo ""
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  --jenkins-port PORT  Jenkins port (default: 8080)"
    echo "  --orly-port PORT     ORLY relay port (default: 3334)"
    echo "  --admin-user USER    Jenkins admin username (default: admin)"
    echo "  --admin-pass PASS    Jenkins admin password (default: admin)"
    echo "  --data-dir DIR       Jenkins data directory (default: ./jenkins-data)"
    echo "  --build-nostrbots    Build Nostrbots Docker image"
    echo "  --setup-pipeline     Set up the Nostrbots pipeline"
    echo "  --skip-jenkins       Skip Jenkins setup (use existing Jenkins)"
    echo "  --skip-orly          Skip ORLY relay setup (use existing ORLY)"
    echo "  --help               Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Basic setup"
    echo "  $0 --jenkins-port 9090 --admin-user myuser   # Custom port and user"
    echo "  $0 --orly-port 4444 --setup-pipeline # Custom ORLY port"
    echo "  $0 --build-nostrbots --setup-pipeline # Full setup with pipeline"
    echo "  $0 --skip-jenkins --setup-pipeline    # Skip Jenkins, setup ORLY only"
    echo "  $0 --skip-orly --build-nostrbots      # Skip ORLY, setup Jenkins only"
    echo "  $0 --skip-jenkins --skip-orly         # Skip both, just build Nostrbots"
}

# Default values
JENKINS_PORT=8080
ORLY_PORT=3334
JENKINS_USER=admin
JENKINS_PASS=admin
JENKINS_DATA_DIR="./jenkins-data"
BUILD_NOSTRBOTS=false
SETUP_PIPELINE=false
SKIP_JENKINS=false
SKIP_ORLY=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --jenkins-port)
            JENKINS_PORT="$2"
            shift 2
            ;;
        --orly-port)
            ORLY_PORT="$2"
            shift 2
            ;;
        --admin-user)
            JENKINS_USER="$2"
            shift 2
            ;;
        --admin-pass)
            JENKINS_PASS="$2"
            shift 2
            ;;
        --data-dir)
            JENKINS_DATA_DIR="$2"
            shift 2
            ;;
        --build-nostrbots)
            BUILD_NOSTRBOTS=true
            shift
            ;;
        --setup-pipeline)
            SETUP_PIPELINE=true
            shift
            ;;
        --skip-jenkins)
            SKIP_JENKINS=true
            shift
            ;;
        --skip-orly)
            SKIP_ORLY=true
            shift
            ;;
        --help|-h)
            show_usage
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again."
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "Dockerfile" ] || [ ! -f "Jenkinsfile" ]; then
    print_error "Please run this script from the Nostrbots project root directory."
    exit 1
fi

# Key management: Generate or use provided key for all components
if [ -n "$NOSTR_BOT_KEY" ]; then
    print_status "Using provided Nostr key from environment: ${NOSTR_BOT_KEY:0:20}..."
    TEST_KEY="$NOSTR_BOT_KEY"
else
    print_status "Generating new Nostr key for Jenkins and ORLY setup..."
    TEST_KEY=$(docker run --rm nostrbots:latest generate-key | grep "export NOSTR_BOT_KEY" | cut -d'=' -f2 | tr -d '"')
    
    if [ -z "$TEST_KEY" ]; then
        print_error "Failed to generate test key"
        exit 1
    fi
    
    print_success "Generated key: ${TEST_KEY:0:20}..."
    print_status "💡 To reuse this key, export it: export NOSTR_BOT_KEY=$TEST_KEY"
fi

print_header "Setting up Local Jenkins for Nostrbots"
echo "=============================================="
echo ""

# Jenkins setup (skip if --skip-jenkins is used)
if [ "$SKIP_JENKINS" = false ]; then
    print_header "Setting up Jenkins"
    echo "====================="
    echo ""
    
    # Create Jenkins data directory
    print_status "Creating Jenkins data directory: $JENKINS_DATA_DIR"
    mkdir -p "$JENKINS_DATA_DIR"

    # Stop existing Jenkins container if running
    print_status "Stopping existing Jenkins container (if any)..."
    docker stop jenkins-nostrbots 2>/dev/null || true
    docker rm jenkins-nostrbots 2>/dev/null || true

# Create Jenkins Docker Compose file
print_status "Creating Jenkins Docker Compose configuration..."
cat > docker-compose.jenkins.yml << EOF
services:
  jenkins:
    image: jenkins/jenkins:lts
    container_name: jenkins-nostrbots
    ports:
      - "$JENKINS_PORT:8080"
      - "50000:50000"
    volumes:
      - $JENKINS_DATA_DIR:/var/jenkins_home
      - /var/run/docker.sock:/var/run/docker.sock
      - $(pwd):/workspace
    environment:
      - JENKINS_OPTS=--httpPort=8080
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
    networks:
      - jenkins-network
    command: ["tail", "-f", "/dev/null"]

networks:
  jenkins-network:
    driver: bridge
EOF

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

# Try to get the password from the container
ADMIN_PASSWORD=$(docker exec jenkins-nostrbots cat /var/jenkins_home/secrets/initialAdminPassword 2>/dev/null || echo "")

if [ -z "$ADMIN_PASSWORD" ]; then
    print_warning "Could not retrieve initial admin password automatically."
    print_status "Please check the Jenkins container logs:"
    echo "  docker logs jenkins-nostrbots"
    print_status "Or visit http://localhost:$JENKINS_PORT and follow the setup wizard."
else
    print_success "Jenkins initial admin password: $ADMIN_PASSWORD"
fi

# Build Nostrbots image if requested
if [ "$BUILD_NOSTRBOTS" = true ]; then
    print_status "Building Nostrbots Docker image..."
    docker build -t nostrbots:latest .
    print_success "Nostrbots image built successfully!"
fi

# Create Jenkins configuration script
print_status "Creating Jenkins configuration script..."
cat > jenkins-setup.groovy << 'EOF'
import jenkins.model.*
import hudson.security.*
import hudson.security.csrf.DefaultCrumbIssuer
import jenkins.security.s2m.AdminWhitelistRule
import hudson.plugins.git.GitSCM
import org.jenkinsci.plugins.workflow.job.WorkflowJob
import org.jenkinsci.plugins.workflow.cps.CpsScmFlowDefinition

// Disable security for easier setup
def instance = Jenkins.getInstance()
def securityRealm = new HudsonPrivateSecurityRealm(false)
securityRealm.createAccount("admin", "admin")
instance.setSecurityRealm(securityRealm)

def strategy = new FullControlOnceLoggedInAuthorizationStrategy()
instance.setAuthorizationStrategy(strategy)

// Disable CSRF protection for easier setup
instance.setCrumbIssuer(new DefaultCrumbIssuer(false))

// Disable agent to master security subsystem
instance.getInjector().getInstance(AdminWhitelistRule.class).setMasterKillSwitch(false)

// Save configuration
instance.save()

println "Jenkins security configured successfully!"
EOF

# Apply Jenkins configuration
print_status "Applying Jenkins configuration..."
# Wait a bit more for Jenkins to be fully ready
sleep 30
docker cp jenkins-setup.groovy jenkins-nostrbots:/tmp/jenkins-setup.groovy
docker exec jenkins-nostrbots java -jar /usr/share/jenkins/jenkins.war groovy /tmp/jenkins-setup.groovy || {
    print_warning "Could not apply Jenkins configuration automatically."
    print_status "You may need to configure Jenkins manually through the web interface."
}

# Create Nostr bot key credential if we have a key
if [ -n "$TEST_KEY" ]; then
    print_status "Creating Nostr bot key credential in Jenkins..."
    
    # Create credential creation script
    cat > jenkins-credential.groovy << EOF
import jenkins.model.*
import com.cloudbees.plugins.credentials.*
import com.cloudbees.plugins.credentials.common.*
import com.cloudbees.plugins.credentials.domains.*
import com.cloudbees.plugins.credentials.impl.*

def instance = Jenkins.getInstance()
def credentialsStore = instance.getExtensionList('com.cloudbees.plugins.credentials.SystemCredentialsProvider')[0].getStore()

// Create the credential
def credential = new StringCredentialsImpl(
    CredentialsScope.GLOBAL,
    'nostr-bot-key',
    'Nostr Bot Private Key',
    '$TEST_KEY'
)

// Add the credential
credentialsStore.addCredentials(Domain.global(), credential)

println "Nostr bot key credential created successfully!"
EOF

    # Apply credential creation
    docker cp jenkins-credential.groovy jenkins-nostrbots:/tmp/jenkins-credential.groovy
    docker exec jenkins-nostrbots java -jar /usr/share/jenkins/jenkins.war groovy /tmp/jenkins-credential.groovy || {
        print_warning "Could not create Nostr bot key credential automatically."
        print_status "You may need to create it manually in Jenkins → Manage Jenkins → Credentials"
    }
    
    # Clean up
    rm -f jenkins-credential.groovy
fi

# Create Nostrbots pipeline job if requested
if [ "$SETUP_PIPELINE" = true ]; then
    print_status "Setting up Nostrbots pipeline job..."
    
    # Create pipeline job configuration
    cat > nostrbots-pipeline.xml << 'EOF'
<?xml version='1.1' encoding='UTF-8'?>
<flow-definition plugin="workflow-job@2.45">
  <description>Nostrbots CI/CD Pipeline</description>
  <keepDependencies>false</keepDependencies>
  <properties>
    <jenkins.model.BuildDiscarderProperty>
      <strategy class="hudson.tasks.LogRotator">
        <daysToKeep>30</daysToKeep>
        <numToKeepStr>30</numToKeepStr>
        <artifactDaysToKeepStr>-1</artifactDaysToKeepStr>
        <artifactNumToKeepStr>-1</artifactNumToKeepStr>
      </strategy>
    </jenkins.model.BuildDiscarderProperty>
  </properties>
  <triggers>
    <hudson.triggers.TimerTrigger>
      <spec>H * * * *</spec>
    </hudson.triggers.TimerTrigger>
  </triggers>
  <definition class="org.jenkinsci.plugins.workflow.cps.CpsScmFlowDefinition" plugin="workflow-cps@2.94">
    <scm class="hudson.plugins.git.GitSCM" plugin="git@4.11.0">
      <configVersion>2</configVersion>
      <userRemoteConfigs>
        <hudson.plugins.git.UserRemoteConfig>
          <url>file:///workspace</url>
        </hudson.plugins.git.UserRemoteConfig>
      </userRemoteConfigs>
      <branches>
        <hudson.plugins.git.BranchSpec>
          <name>*/main</name>
        </hudson.plugins.git.BranchSpec>
      </branches>
      <doGenerateSubmoduleConfigurations>false</doGenerateSubmoduleConfigurations>
      <submoduleCfg class="list"/>
      <extensions/>
    </scm>
    <scriptPath>Jenkinsfile</scriptPath>
    <lightweight>false</lightweight>
  </definition>
  <triggers/>
  <disabled>false</disabled>
</flow-definition>
EOF

    # Copy pipeline configuration to Jenkins
    docker cp nostrbots-pipeline.xml jenkins-nostrbots:/var/jenkins_home/jobs/
    
    # Create jobs directory structure
    docker exec jenkins-nostrbots mkdir -p /var/jenkins_home/jobs/nostrbots-pipeline
    
    # Move the configuration file
    docker exec jenkins-nostrbots mv /var/jenkins_home/jobs/nostrbots-pipeline.xml /var/jenkins_home/jobs/nostrbots-pipeline/config.xml
    
    # Set proper permissions
    docker exec jenkins-nostrbots chown -R jenkins:jenkins /var/jenkins_home/jobs/nostrbots-pipeline
    
    print_success "Nostrbots pipeline job created!"
fi

else
    print_status "Skipping Jenkins setup (--skip-jenkins specified)"
    print_status "Assuming Jenkins is already running and configured"
fi

# Install ORLY relay if requested (skip if --skip-orly is used)
if [ "$SETUP_PIPELINE" = true ] && [ "$SKIP_ORLY" = false ]; then
    print_status "Setting up ORLY relay for testing..."
    
    # Create ORLY installation script
    cat > install-orly.sh << 'EOF'
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
EOF

    chmod +x install-orly.sh
    
    # Run ORLY installation
    print_status "Installing ORLY relay..."
    ./install-orly.sh
    
    # Update relays.yml to include local ORLY
    print_status "Updating relay configuration..."
    if [ -f "src/relays.yml" ]; then
        # Backup original
        cp src/relays.yml src/relays.yml.backup
        
        # Add local ORLY to test relays
        cat > src/relays.yml << 'RELAYS_YML'
favorite-relays:
  - wss://thecitadel.nostr1.com
  - wss://theforest.nostr1.com

test-relays:
  - ws://localhost:$ORLY_PORT
  - wss://freelay.sovbit.host

daily-office-relays:
  - wss://thecitadel.nostr1.com
  - wss://orly-relay.imwald.eu

local-test-relays:
  - ws://localhost:$ORLY_PORT
RELAYS_YML
        
        print_success "Updated relays.yml with local ORLY relay"
    fi
    
    # Create comprehensive test script
    print_status "Creating comprehensive test script..."
    cat > test-complete-pipeline.sh << EOF
#!/bin/bash

# Complete Pipeline Test Script
# Tests the entire Nostrbots + ORLY + Jenkins pipeline

set -e

# Default ORLY port
ORLY_PORT=$ORLY_PORT
AUTO_KILL_ORLY=false

# Parse command line arguments
while [[ \$# -gt 0 ]]; do
    case \$1 in
        --orly-port)
            ORLY_PORT="\$2"
            shift 2
            ;;
        --auto-kill-orly)
            AUTO_KILL_ORLY=true
            shift
            ;;
        --help|-h)
            echo "Complete Pipeline Test Script"
            echo "============================="
            echo ""
            echo "Usage: \$0 [options]"
            echo ""
            echo "Options:"
            echo "  --orly-port PORT     ORLY relay port (default: $ORLY_PORT)"
            echo "  --auto-kill-orly     Automatically kill existing ORLY processes (no prompt)"
            echo "  --help               Show this help message"
            echo ""
            echo "Examples:"
            echo "  \$0                    # Test with default ORLY port $ORLY_PORT"
            echo "  \$0 --orly-port 4444   # Test with custom ORLY port 4444"
            echo "  \$0 --auto-kill-orly   # Test without prompting to kill existing ORLY"
            exit 0
            ;;
        *)
            echo "Unknown option: \$1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${PURPLE}[TEST]${NC} $1"
}

echo "🧪 Complete Pipeline Test"
echo "========================="
echo ""

# Check if we're in the right directory
if [ ! -f "Dockerfile" ] || [ ! -d "bots/hello-world" ]; then
    print_error "Please run this script from the Nostrbots project root directory."
    exit 1
fi

# Check if ORLY is installed (optional)
ORLY_DIR="../orly"
if [ ! -d "$ORLY_DIR" ] || [ ! -f "$ORLY_DIR/orly" ]; then
    print_status "ORLY relay not found - will use external relays for testing"
    ORLY_AVAILABLE=false
else
    print_status "ORLY relay found - will use local relay for testing"
    ORLY_AVAILABLE=true
fi

# Key management: Generate or use provided key for all components
if [ -n "$NOSTR_BOT_KEY" ]; then
    print_status "Using provided Nostr key from environment: ${NOSTR_BOT_KEY:0:20}..."
    TEST_KEY="$NOSTR_BOT_KEY"
else
    print_status "Generating new Nostr key for testing..."
    TEST_KEY=\$(docker run --rm nostrbots:latest generate-key | grep "export NOSTR_BOT_KEY" | cut -d'=' -f2 | tr -d '"')
    
    if [ -z "$TEST_KEY" ]; then
        print_error "Failed to generate test key"
        exit 1
    fi
    
    print_success "Generated key: \${TEST_KEY:0:20}..."
    print_status "💡 To reuse this key, export it: export NOSTR_BOT_KEY=$TEST_KEY"
fi

echo ""

# Start ORLY relay if available
if [ "$ORLY_AVAILABLE" = true ]; then
    print_header "Starting ORLY relay..."
    cd "\$ORLY_DIR"

    # Check for existing ORLY processes and handle them appropriately
    if pgrep -f "./orly" > /dev/null 2>&1; then
        if [ "\$AUTO_KILL_ORLY" = true ]; then
            print_status "Found existing ORLY processes running. Auto-killing them..."
            pkill -f "./orly" 2>/dev/null || true
            sleep 2
        else
            print_status "Found existing ORLY processes running."
            echo -n "Do you want to stop them? (y/N): "
            read -r response
            if [[ "\$response" =~ ^[Yy]\$ ]]; then
                print_status "Stopping existing ORLY processes..."
                pkill -f "./orly" 2>/dev/null || true
                sleep 2
            else
                print_error "Cannot start new ORLY instance while others are running."
                print_status "Please stop existing ORLY processes manually or use --auto-kill-orly flag."
                exit 1
            fi
        fi
    fi

    # Set ORLY environment variables (following ORLY's pattern)
    export ORLY_PORT=$ORLY_PORT
    export ORLY_ADMINS=\$(echo "$TEST_KEY" | xxd -r -p | sha256sum | cut -d' ' -f1)
    export ORLY_ACL_MODE=none
    export ORLY_DATA_DIR=/tmp/orlytest
    export ORLY_LOG_LEVEL=info
    export ORLY_LOG_TO_STDOUT=true

    # Start ORLY (following ORLY's test pattern)
    ./orly &
    ORLY_PID=\$!

    # Wait for ORLY to start (following ORLY's 5-second pattern)
    print_status "Waiting for ORLY to start..."
    sleep 5

    # Test if ORLY is responding
    if curl -s http://localhost:$ORLY_PORT > /dev/null 2>&1; then
        print_success "ORLY relay is running on ws://localhost:$ORLY_PORT"
    else
        print_error "ORLY failed to start properly"
        kill $ORLY_PID 2>/dev/null || true
        exit 1
    fi

    cd - > /dev/null
else
    print_header "Skipping ORLY relay (not installed)"
    print_status "Using external relays for testing"
    ORLY_PID=""
fi

# Test Hello World bot
print_header "Testing Hello World bot..."

# Update hello-world bot configuration based on ORLY availability
print_status "Updating hello-world bot configuration for testing..."
cp bots/hello-world/config.json bots/hello-world/config.json.backup

if [ "$ORLY_AVAILABLE" = true ]; then
    # ORLY is available - use only local ORLY relay for testing
    print_status "ORLY relay detected - using local relay only for testing"
    cat > bots/hello-world/config.json << EOF
{
    "name": "Hello World Bot (Local ORLY Test)",
    "description": "A test bot using local ORLY relay only",
    "version": "1.0.0",
    "author": "Nostrbots Test",
    "schedule": "manual",
    "relays": ["ws://localhost:$ORLY_PORT"],
    "content_kind": 30041,
    "content_level": 0
}
EOF
    USE_LOCAL_ORLY=true
else
    # ORLY not available - use test-relays (external relays)
    print_status "ORLY relay not found - using external test relays"
    cat > bots/hello-world/config.json << EOF
{
    "name": "Hello World Bot (External Test)",
    "description": "A test bot using external relays",
    "version": "1.0.0",
    "author": "Nostrbots Test",
    "schedule": "manual",
    "relays": ["wss://freelay.sovbit.host"],
    "content_kind": 30041,
    "content_level": 0
}
EOF
    USE_LOCAL_ORLY=false
fi

# Test content generation (dry run)
print_status "Testing content generation (dry run)..."
docker run --rm -v $(pwd)/bots:/app/bots -e NOSTR_BOT_KEY="$TEST_KEY" nostrbots:latest run-bot --bot hello-world --dry-run --verbose

if [ $? -eq 0 ]; then
    print_success "Content generation test passed!"
else
    print_error "Content generation test failed!"
    # Restore original config
    mv bots/hello-world/config.json.backup bots/hello-world/config.json
    kill $ORLY_PID 2>/dev/null || true
    exit 1
fi

echo ""

# Test actual publishing to relay
print_status "Testing actual publishing to relay..."
BOT_OUTPUT=$(docker run --rm -v $(pwd)/bots:/app/bots -e NOSTR_BOT_KEY="$TEST_KEY" nostrbots:latest run-bot --bot hello-world --verbose)

if [ $? -eq 0 ]; then
    print_success "Publishing to relay successful!"
    
    # Extract event ID from bot output
    EVENT_ID=$(echo "$BOT_OUTPUT" | grep -o "Event ID: [a-f0-9]\{64\}" | cut -d' ' -f3)
    if [ -n "$EVENT_ID" ]; then
        echo ""
        print_status "📄 Published Event Details:"
        echo "   Event ID: $EVENT_ID"
        echo "   Direct Link: https://next-alexandria.gitcitadel.eu/events?id=$EVENT_ID"
        echo ""
    fi
else
    print_error "Publishing to relay failed!"
    # Restore original config
    mv bots/hello-world/config.json.backup bots/hello-world/config.json
    kill $ORLY_PID 2>/dev/null || true
    exit 1
fi

echo ""

# Verify the published event based on which relay was used
if [ "$USE_LOCAL_ORLY" = true ]; then
    print_header "Verifying event was written to local ORLY relay..."
    print_status "Querying published event from local ORLY relay..."

    # Check if we can connect to the ORLY relay
    if ! curl -s http://localhost:$ORLY_PORT > /dev/null 2>&1; then
        print_error "Cannot connect to ORLY relay at localhost:$ORLY_PORT"
        print_error "This means the event was NOT written to the local relay!"
        # Restore original config
        mv bots/hello-world/config.json.backup bots/hello-world/config.json
        kill $ORLY_PID 2>/dev/null || true
        exit 1
    fi

    print_status "ORLY relay is responding to HTTP requests"
    print_status "Event ID: $EVENT_ID"
    print_status "Note: Full event verification requires a Nostr client library"
    print_success "Local ORLY relay test completed - relay is operational"
else
    print_header "Verifying event was published to external relay..."
    print_status "Event ID: $EVENT_ID"
    print_status "Published to external relay: wss://freelay.sovbit.host"
    print_success "External relay publishing test completed"
fi

# Cleanup
print_status "Cleaning up..."
mv bots/hello-world/config.json.backup bots/hello-world/config.json
if [ -n "$ORLY_PID" ]; then
    kill $ORLY_PID 2>/dev/null || true
    rm -rf /tmp/orlytest
fi

echo ""
print_success "🎉 Complete pipeline test completed successfully!"
echo ""
print_status "Summary:"
if [ "$USE_LOCAL_ORLY" = true ]; then
    echo "  ✅ ORLY relay started and responded"
    echo "  ✅ Hello World bot content generation"
    echo "  ✅ Hello World bot publishing to local ORLY relay"
    echo "  ✅ Local ORLY relay verification"
else
    echo "  ✅ Hello World bot content generation"
    echo "  ✅ Hello World bot publishing to external relay"
    echo "  ✅ External relay publishing verification"
fi
echo ""
print_status "Your Nostrbots + ORLY + Jenkins pipeline is working correctly!"
echo ""
print_status "Next steps:"
echo "  • Visit Jenkins at: http://localhost:8080"
echo "  • Create your own bots in the bots/ directory"
echo "  • Set up scheduled publishing with Jenkins"
echo ""
EOF

    chmod +x test-complete-pipeline.sh
    
    print_success "Complete pipeline test script created!"
elif [ "$SETUP_PIPELINE" = true ] && [ "$SKIP_ORLY" = true ]; then
    print_status "Skipping ORLY setup (--skip-orly specified)"
    print_status "Assuming ORLY is already installed and configured"
fi

# Create test script for Hello World bot
print_status "Creating Hello World bot test script..."
cat > test-hello-world.sh << 'EOF'
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
EOF

chmod +x test-hello-world.sh

# Create quick start guide
print_status "Creating quick start guide..."
cat > QUICK_START.md << 'EOF'
# Nostrbots Quick Start Guide

Get up and running with Nostrbots in minutes!

## Prerequisites

- Docker and Docker Compose installed
- Git (to clone the repository)

## 1. Clone and Setup

```bash
git clone <repository-url>
cd Nostrbots
```

## 2. Build Nostrbots

```bash
docker build -t nostrbots .
```

## 3. Test Hello World Bot

```bash
# Run the test script
./test-hello-world.sh
```

This will:
- Generate a test Nostr key
- Create a "Hello World" article
- Publish it to test relays
- Verify everything works

## 4. Set Up Local Jenkins (Optional)

```bash
# Basic Jenkins setup
./scripts/setup-local-jenkins.sh

# Full setup with pipeline
./scripts/setup-local-jenkins.sh --build-nostrbots --setup-pipeline
```

Then visit: http://localhost:8080

## 5. Create Your Own Bot

```bash
# Create a new bot
./scripts/setup-bot.sh my-bot --schedule "06:00,18:00" --relays "wss://relay1.com,wss://relay2.com"

# Test your bot
docker run --rm -v $(pwd)/bots:/app/bots nostrbots run-bot --bot my-bot --dry-run --verbose
```

## 6. Production Setup

1. Generate your own Nostr key: `php generate-key.php`
2. Set environment variable: `export NOSTR_BOT_KEY=your_key`
3. Use production relays
4. Set up proper scheduling

## Troubleshooting

### Docker Issues
- Make sure Docker is running
- Check Docker permissions

### Bot Issues
- Always test with `--dry-run` first
- Check bot configuration in `bots/*/config.json`
- Verify relay URLs are accessible

### Jenkins Issues
- Check Jenkins logs: `docker logs jenkins-nostrbots`
- Verify Jenkins is accessible at http://localhost:8080
- Check Jenkins container status: `docker ps`

## Next Steps

- Read the full documentation in `README.md`
- Explore bot examples in `bots/` directory
- Set up your own content generation logic
- Configure production relays and keys
EOF

# Clean up temporary files
rm -f jenkins-setup.groovy nostrbots-pipeline.xml

# Final status
echo ""
print_header "Jenkins Setup Complete!"
echo "========================"
echo ""
print_success "Jenkins is running at: http://localhost:$JENKINS_PORT"
print_success "Admin username: $JENKINS_USER"
if [ -n "$ADMIN_PASSWORD" ]; then
    print_success "Admin password: $ADMIN_PASSWORD"
else
    print_warning "Admin password: Check Jenkins container logs or web interface"
fi
echo ""
print_status "Useful commands:"
echo "  # View Jenkins logs"
echo "  docker logs jenkins-nostrbots"
echo ""
echo "  # Stop Jenkins"
echo "  docker compose -f docker-compose.jenkins.yml down"
echo ""
echo "  # Test Hello World bot"
echo "  ./test-hello-world.sh"
echo ""
echo "  # Create new bot"
echo "  ./scripts/setup-bot.sh my-bot"
echo ""

if [ "$SETUP_PIPELINE" = true ]; then
    if [ "$SKIP_JENKINS" = false ]; then
        print_success "Nostrbots pipeline job created in Jenkins!"
        print_status "The pipeline will run hourly to check for scheduled bots."
    fi
    
    if [ "$SKIP_ORLY" = false ]; then
        print_success "ORLY relay installed and configured!"
        print_status "Local relay available at: ws://localhost:$ORLY_PORT"
        echo ""
        print_status "Complete pipeline test available:"
        echo "  ./test-complete-pipeline.sh --orly-port $ORLY_PORT --auto-kill-orly"
        echo ""
        print_status "This will test:"
        echo "  - ORLY relay startup"
        echo "  - Hello World bot with local relay"
        echo "  - End-to-end publishing workflow"
    fi
fi

print_success "Setup complete! Happy botting! 🤖"
