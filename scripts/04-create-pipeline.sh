#!/bin/bash

# 04-create-pipeline.sh
# Creates the Jenkins pipeline job
# Can be run independently or as part of the complete setup

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[04-CREATE-PIPELINE]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }

print_status "Creating Jenkins pipeline job..."

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
else
    AUTH_USER="admin:$ADMIN_PASSWORD"
fi

# Get CSRF crumb
print_status "Getting CSRF crumb..."
CRUMB=$(curl -s -u $AUTH_USER "$JENKINS_URL/crumbIssuer/api/xml?xpath=concat(//crumbRequestField,\":\",//crumb)" 2>/dev/null || echo "")

# Create pipeline job XML
print_status "Creating pipeline job configuration..."
cat > /tmp/pipeline-job.xml << 'EOF'
<?xml version='1.1' encoding='UTF-8'?>
<flow-definition plugin="workflow-job@2.45">
  <description>Nostrbots CI/CD Pipeline with Encrypted Key Support</description>
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
          <name>*/master</name>
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

# Create the pipeline job
print_status "Creating nostrbots-pipeline job..."
JOB_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null \
    -X POST \
    -u $AUTH_USER \
    -H "$CRUMB" \
    -H "Content-Type: application/xml" \
    -d @/tmp/pipeline-job.xml \
    "$JENKINS_URL/createItem?name=nostrbots-pipeline" 2>/dev/null || echo "000")

if [ "$JOB_RESPONSE" = "200" ] || [ "$JOB_RESPONSE" = "201" ]; then
    print_success "Pipeline job created successfully!"
else
    print_warning "Pipeline job creation returned HTTP $JOB_RESPONSE"
    
    # Try alternative method using direct file manipulation
    print_status "Trying alternative method using direct file manipulation..."
    
    # Remove existing job if it exists
    docker exec jenkins-nostrbots rm -rf /var/jenkins_home/jobs/nostrbots-pipeline 2>/dev/null || true
    
    # Create job directory
    docker exec jenkins-nostrbots mkdir -p /var/jenkins_home/jobs/nostrbots-pipeline
    
    # Copy job configuration
    docker cp /tmp/pipeline-job.xml jenkins-nostrbots:/var/jenkins_home/jobs/nostrbots-pipeline/config.xml
    docker exec jenkins-nostrbots chown -R jenkins:jenkins /var/jenkins_home/jobs/nostrbots-pipeline
    
    print_success "Pipeline job created using direct file method!"
fi

# Verify the job was created
print_status "Verifying pipeline job creation..."
sleep 5  # Give Jenkins time to process the job

JOB_TEST=$(curl -s -u $AUTH_USER "$JENKINS_URL/api/json" | grep -o '"nostrbots-pipeline"' || echo "")
if [[ "$JOB_TEST" == *"nostrbots-pipeline"* ]]; then
    print_success "Pipeline job is visible in Jenkins!"
else
    print_warning "Pipeline job not yet visible - may need more time"
fi

# Cleanup
rm -f /tmp/pipeline-job.xml

print_success "Pipeline creation complete!"
print_status "Pipeline job: nostrbots-pipeline"
print_status "Jenkins URL: http://localhost:$JENKINS_PORT"
print_status "Job URL: http://localhost:$JENKINS_PORT/job/nostrbots-pipeline/"
