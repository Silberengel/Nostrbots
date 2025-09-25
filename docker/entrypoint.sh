#!/bin/bash
set -e

# Security: Create and switch to non-root user
create_user() {
    # Create jenkins user with UID 1000 (common non-root UID)
    if ! id -u jenkins >/dev/null 2>&1; then
        useradd -m -u 1000 -s /bin/bash jenkins
        echo "Created jenkins user (UID: 1000)"
    fi
    
    # Create app directory and set ownership
    mkdir -p /app
    chown -R jenkins:jenkins /app
    
    # Create necessary directories with proper permissions
    mkdir -p /app/bots /app/logs /app/tmp
    chown -R jenkins:jenkins /app/bots /app/logs /app/tmp
    chmod 755 /app/bots /app/logs /app/tmp
    
    echo "Set up jenkins user and directories"
}

# Security: Drop root privileges and run as jenkins user
drop_privileges() {
    echo "Dropping root privileges, switching to jenkins user..."
    
    # Switch to jenkins user and execute the command
    exec su-exec jenkins "$@"
}

# Main execution
main() {
    echo "🔒 Nostrbots Security Entrypoint"
    echo "================================"
    
    # Only create user if running as root
    if [ "$(id -u)" = "0" ]; then
        create_user
        drop_privileges "$@"
    else
        echo "Already running as non-root user: $(whoami)"
        exec "$@"
    fi
}

# Run main function with all arguments
main "$@"
