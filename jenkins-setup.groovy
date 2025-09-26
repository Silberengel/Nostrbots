// Minimal Jenkins setup for Nostrbots production
// This file is mounted into Jenkins container for initial setup

import jenkins.model.*
import hudson.security.*

def instance = Jenkins.getInstance()

// Set up basic security
def hudsonRealm = new HudsonPrivateSecurityRealm(false)
hudsonRealm.createAccount("admin", "admin")
instance.setSecurityRealm(hudsonRealm)

def strategy = new FullControlOnceLoggedInAuthorizationStrategy()
instance.setAuthorizationStrategy(strategy)

// Save configuration
instance.save()
