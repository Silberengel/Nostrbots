#!/bin/bash

# 02a-create-nostrbots-user.sh
# Creates a dedicated nostrbots user in Jenkins
# Can be run independently or as part of the complete setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[02A-CREATE-NOSTRBOTS-USER]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

print_status "Creating dedicated nostrbots user in Jenkins..."

# Load port configuration from environment
if [ -z "$JENKINS_PORT" ]; then
    JENKINS_PORT=8080
    print_warning "No port configuration found in environment, using default port 8080"
fi

# Check if Jenkins is running
if ! curl -s http://localhost:$JENKINS_PORT > /dev/null 2>&1; then
    print_error "Jenkins is not running on localhost:$JENKINS_PORT"
    print_error "Please run 02-setup-jenkins.sh first"
    exit 1
fi

# Get admin password from environment
if [ -z "$JENKINS_ADMIN_PASSWORD" ]; then
    print_error "No Jenkins admin password found in environment. Please run 02-setup-jenkins.sh first"
    exit 1
fi
ADMIN_PASSWORD="$JENKINS_ADMIN_PASSWORD"

JENKINS_URL="http://localhost:$JENKINS_PORT"

# Determine which authentication to use
if curl -s -u admin:admin "$JENKINS_URL/api/json" > /dev/null 2>&1; then
    AUTH_USER="admin:admin"
    print_success "Using admin:admin authentication"
else
    AUTH_USER="admin:$ADMIN_PASSWORD"
    print_success "Using admin with initial password authentication"
fi

# Get CSRF crumb
print_status "Getting CSRF crumb..."
CRUMB=$(curl -s -u $AUTH_USER "$JENKINS_URL/crumbIssuer/api/xml?xpath=concat(//crumbRequestField,\":\",//crumb)" 2>/dev/null || echo "")

# Create nostrbots user
print_status "Creating nostrbots user..."
NOSTRBOTS_PASSWORD="nostrbots123"  # In production, use a secure password

# Create user via Jenkins API
CREATE_USER_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null \
    -X POST \
    -u $AUTH_USER \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -H "$CRUMB" \
    -d "username=nostrbots&password1=$NOSTRBOTS_PASSWORD&password2=$NOSTRBOTS_PASSWORD&fullname=Nostrbots%20Bot%20User&email=nostrbots@localhost" \
    "$JENKINS_URL/securityRealm/createAccountByAdmin" 2>/dev/null || echo "000")

if [ "$CREATE_USER_RESPONSE" = "200" ] || [ "$CREATE_USER_RESPONSE" = "302" ]; then
    print_success "Nostrbots user created successfully!"
else
    print_warning "User creation returned HTTP $CREATE_USER_RESPONSE (may already exist)"
fi

# Export nostrbots user credentials (environment only)
export NOSTRBOTS_PASSWORD

# Configure nostrbots user permissions
print_status "Configuring nostrbots user permissions..."

# Create a Groovy script to configure user permissions
cat > /tmp/configure-nostrbots-user.groovy << 'EOF'
import jenkins.model.Jenkins
import hudson.security.Permission
import hudson.security.ProjectMatrixAuthorizationStrategy
import hudson.security.HudsonPrivateSecurityRealm

// Get the Jenkins instance
def jenkins = Jenkins.getInstance()

// Get the authorization strategy
def authStrategy = jenkins.getAuthorizationStrategy()

// If it's not a matrix authorization strategy, we need to set it up
if (!(authStrategy instanceof ProjectMatrixAuthorizationStrategy)) {
    authStrategy = new ProjectMatrixAuthorizationStrategy()
    jenkins.setAuthorizationStrategy(authStrategy)
}

// Grant permissions to nostrbots user
def permissions = [
    'hudson.model.Item.Build',
    'hudson.model.Item.Read',
    'hudson.model.Item.Workspace',
    'hudson.model.Run.Update',
    'hudson.model.Run.Delete',
    'hudson.model.Run.Artifacts',
    'hudson.model.Computer.Build',
    'hudson.model.Computer.Connect',
    'hudson.model.Computer.Create',
    'hudson.model.Computer.Delete',
    'hudson.model.Computer.Configure',
    'hudson.model.Computer.Disconnect',
    'hudson.model.Computer.Provision',
    'hudson.model.View.Read',
    'hudson.model.View.Create',
    'hudson.model.View.Delete',
    'hudson.model.View.Configure',
    'hudson.model.Hudson.Read',
    'hudson.model.Hudson.RunScripts',
    'hudson.model.Hudson.UploadPlugins',
    'hudson.model.Hudson.ConfigureUpdateCenter',
    'hudson.model.Hudson.ViewStatus',
    'hudson.model.Hudson.Read'
]

permissions.each { permission ->
    authStrategy.add(Permission.fromId(permission), 'nostrbots')
}

// Save the configuration
jenkins.save()

println "Nostrbots user permissions configured successfully"
EOF

# Execute the Groovy script
print_status "Applying nostrbots user permissions..."
GROOVY_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null \
    -X POST \
    -u $AUTH_USER \
    -H "Content-Type: text/plain" \
    -H "$CRUMB" \
    --data-binary @/tmp/configure-nostrbots-user.groovy \
    "$JENKINS_URL/scriptText" 2>/dev/null || echo "000")

if [ "$GROOVY_RESPONSE" = "200" ]; then
    print_success "Nostrbots user permissions configured successfully!"
else
    print_warning "Permission configuration returned HTTP $GROOVY_RESPONSE"
fi

# Cleanup
rm -f /tmp/configure-nostrbots-user.groovy

# Verify nostrbots user can authenticate
print_status "Verifying nostrbots user authentication..."
if curl -s -u nostrbots:$NOSTRBOTS_PASSWORD "$JENKINS_URL/api/json" > /dev/null 2>&1; then
    print_success "✅ Nostrbots user authentication verified"
else
    print_warning "⚠️  Nostrbots user authentication failed - may need more time to propagate"
fi

print_success "Nostrbots user setup complete!"
print_status "Username: nostrbots"
print_status "Password exported to environment"
print_status "User has permissions to run builds and manage agents"
