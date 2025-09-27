#!/usr/bin/env groovy

// Jenkins Setup Script for Nostrbots
// This script configures Jenkins for Nostrbots production use

import jenkins.model.*
import hudson.security.*
import hudson.security.csrf.DefaultCrumbIssuer
import jenkins.security.s2m.AdminWhitelistRule
import jenkins.security.SecurityRealm

// Get Jenkins instance
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

// Set up security realm with admin account
def hudsonRealm = new HudsonPrivateSecurityRealm(false)
hudsonRealm.createAccount(adminId, adminPassword)
instance.setSecurityRealm(hudsonRealm)

// Set up authorization strategy - give admin full control
def strategy = new FullControlOnceLoggedInAuthorizationStrategy()
strategy.setAllowAnonymousRead(false)
instance.setAuthorizationStrategy(strategy)

// Enable CSRF protection
instance.setCrumbIssuer(new DefaultCrumbIssuer(true))

// Disable CLI over remoting for security
instance.getDescriptor("jenkins.CLI").get().setEnabled(false)

// Set up admin whitelist
instance.getInjector().getInstance(AdminWhitelistRule.class).setMasterKillSwitch(false)

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
println "Please change the default password after first login"
