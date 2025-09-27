// Minimal Jenkins setup for Nostrbots production
// This file is mounted into Jenkins container for initial setup

import jenkins.model.*
import hudson.security.*

def instance = Jenkins.getInstance()

// Wait for Jenkins to be fully initialized
while (instance.isQuietingDown()) {
    Thread.sleep(1000)
}

// Disable setup wizard FIRST
instance.setInstallState(InstallState.INITIAL_SETUP_COMPLETED)

// Get admin credentials from environment variables
def adminId = System.getenv("JENKINS_ADMIN_ID") ?: "admin"
def adminPassword = System.getenv("JENKINS_ADMIN_PASSWORD") ?: "admin"

println "Setting up Jenkins with admin user: ${adminId}"

// Set up basic security
def hudsonRealm = new HudsonPrivateSecurityRealm(false)
hudsonRealm.createAccount(adminId, adminPassword)
instance.setSecurityRealm(hudsonRealm)

def strategy = new FullControlOnceLoggedInAuthorizationStrategy()
strategy.setAllowAnonymousRead(false)
instance.setAuthorizationStrategy(strategy)

// Save configuration
instance.save()

// Verify the admin account was created
def securityRealm = instance.getSecurityRealm()
if (securityRealm instanceof HudsonPrivateSecurityRealm) {
    def user = securityRealm.getUser(adminId)
    if (user != null) {
        println "✓ Admin account '${adminId}' created successfully"
    } else {
        println "✗ Failed to create admin account '${adminId}'"
    }
} else {
    println "✗ Security realm not properly configured"
}

println "Jenkins setup completed successfully"
println "Admin credentials: ${adminId}/${adminPassword}"
