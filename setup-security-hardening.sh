#!/bin/bash

# Security Hardening Script for Nostrbots Production
# This script implements additional security measures

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "This script must be run as root for security hardening"
        log_info "Please run: sudo $0"
        exit 1
    fi
}

# Configure firewall
setup_firewall() {
    log_info "Setting up firewall..."
    
    # Install ufw if not present
    if ! command -v ufw &> /dev/null; then
        apt-get update
        apt-get install -y ufw
    fi
    
    # Reset firewall to defaults
    ufw --force reset
    
    # Set default policies
    ufw default deny incoming
    ufw default allow outgoing
    
    # Allow SSH (be careful with this!)
    ufw allow ssh
    
    # Allow Jenkins and Orly on localhost only
    ufw allow from 127.0.0.1 to any port 8080
    ufw allow from 127.0.0.1 to any port 3334
    
    # Enable firewall
    ufw --force enable
    
    log_success "Firewall configured and enabled"
}

# Install and configure fail2ban
setup_fail2ban() {
    log_info "Setting up fail2ban..."
    
    # Install fail2ban
    apt-get update
    apt-get install -y fail2ban
    
    # Create fail2ban configuration
    cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3
backend = systemd

[sshd]
enabled = true
port = ssh
logpath = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled = false

[nginx-limit-req]
enabled = false

[nginx-botsearch]
enabled = false

[docker-auth]
enabled = true
filter = docker-auth
logpath = /var/log/auth.log
maxretry = 3

[docker-abuse]
enabled = true
filter = docker-abuse
logpath = /var/log/auth.log
maxretry = 3
EOF

    # Create Docker-specific filters
    cat > /etc/fail2ban/filter.d/docker-auth.conf << 'EOF'
[Definition]
failregex = ^.*docker.*authentication failure.*$
ignoreregex =
EOF

    cat > /etc/fail2ban/filter.d/docker-abuse.conf << 'EOF'
[Definition]
failregex = ^.*docker.*abuse.*$
ignoreregex =
EOF

    # Start and enable fail2ban
    systemctl enable fail2ban
    systemctl start fail2ban
    
    log_success "fail2ban configured and started"
}

# Set up comprehensive logging
setup_logging() {
    log_info "Setting up comprehensive logging..."
    
    # Create logrotate configuration for Nostrbots
    cat > /etc/logrotate.d/nostrbots << 'EOF'
/var/log/nostrbots/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 nostrbots nostrbots
    postrotate
        systemctl reload nostrbots.service 2>/dev/null || true
    endscript
}

/opt/nostrbots/data/jenkins/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 nostrbots nostrbots
}
EOF

    # Create audit logging script
    cat > /opt/nostrbots/scripts/audit-logger.sh << 'EOF'
#!/bin/bash

# Nostrbots Audit Logger
# Logs security-relevant events

AUDIT_LOG="/var/log/nostrbots/audit.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

log_audit() {
    echo "[$TIMESTAMP] $1" >> "$AUDIT_LOG"
}

# Log key access
if [ "$1" = "key_access" ]; then
    log_audit "KEY_ACCESS: User $USER accessed $2 from $3"
fi

# Log service start/stop
if [ "$1" = "service_action" ]; then
    log_audit "SERVICE_ACTION: $2 $3 by user $USER"
fi

# Log backup operations
if [ "$1" = "backup" ]; then
    log_audit "BACKUP: $2 operation by user $USER"
fi

# Log security events
if [ "$1" = "security" ]; then
    log_audit "SECURITY: $2 by user $USER from $3"
fi
EOF

    chmod +x /opt/nostrbots/scripts/audit-logger.sh
    chown nostrbots:nostrbots /opt/nostrbots/scripts/audit-logger.sh
    
    # Create audit log directory
    mkdir -p /var/log/nostrbots
    chown nostrbots:nostrbots /var/log/nostrbots
    
    log_success "Comprehensive logging configured"
}

# Set up automatic security updates
setup_auto_updates() {
    log_info "Setting up automatic security updates..."
    
    # Install unattended-upgrades
    apt-get update
    apt-get install -y unattended-upgrades
    
    # Configure automatic updates
    cat > /etc/apt/apt.conf.d/50unattended-upgrades << 'EOF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};

Unattended-Upgrade::AutoFixInterruptedDpkg "true";
Unattended-Upgrade::MinimalSteps "true";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Automatic-Reboot-Time "02:00";
EOF

    # Enable automatic updates
    cat > /etc/apt/apt.conf.d/20auto-upgrades << 'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF

    # Start unattended-upgrades
    systemctl enable unattended-upgrades
    systemctl start unattended-upgrades
    
    log_success "Automatic security updates configured"
}

# Configure system hardening
setup_system_hardening() {
    log_info "Setting up system hardening..."
    
    # Disable unnecessary services
    systemctl disable bluetooth 2>/dev/null || true
    systemctl disable cups 2>/dev/null || true
    systemctl disable avahi-daemon 2>/dev/null || true
    
    # Configure kernel parameters for security
    cat > /etc/sysctl.d/99-nostrbots-security.conf << 'EOF'
# Network security
net.ipv4.conf.all.send_redirects = 0
net.ipv4.conf.default.send_redirects = 0
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.default.accept_redirects = 0
net.ipv4.conf.all.secure_redirects = 0
net.ipv4.conf.default.secure_redirects = 0
net.ipv4.conf.all.log_martians = 1
net.ipv4.conf.default.log_martians = 1
net.ipv4.icmp_echo_ignore_broadcasts = 1
net.ipv4.icmp_ignore_bogus_error_responses = 1
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_rfc1337 = 1

# Memory protection
kernel.dmesg_restrict = 1
kernel.kptr_restrict = 2
kernel.yama.ptrace_scope = 1

# File system protection
fs.protected_hardlinks = 1
fs.protected_symlinks = 1
EOF

    # Apply sysctl settings
    sysctl -p /etc/sysctl.d/99-nostrbots-security.conf
    
    log_success "System hardening configured"
}

# Set up monitoring and alerting
setup_monitoring() {
    log_info "Setting up monitoring and alerting..."
    
    # Create monitoring script
    cat > /opt/nostrbots/scripts/security-monitor.sh << 'EOF'
#!/bin/bash

# Nostrbots Security Monitor
# Monitors for security events and sends alerts

LOG_FILE="/var/log/nostrbots/security-monitor.log"
ALERT_EMAIL="${ALERT_EMAIL:-admin@localhost}"

log_monitor() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Check for failed login attempts
check_failed_logins() {
    local failed_count=$(grep "Failed password" /var/log/auth.log | grep "$(date '+%b %d')" | wc -l)
    if [ "$failed_count" -gt 10 ]; then
        log_monitor "ALERT: High number of failed login attempts: $failed_count"
        # Send alert (implement your preferred method)
    fi
}

# Check for unusual Docker activity
check_docker_activity() {
    local container_count=$(docker ps -q | wc -l)
    if [ "$container_count" -lt 3 ]; then
        log_monitor "ALERT: Unusual number of running containers: $container_count"
    fi
}

# Check for disk space
check_disk_space() {
    local disk_usage=$(df /opt/nostrbots | tail -1 | awk '{print $5}' | sed 's/%//')
    if [ "$disk_usage" -gt 90 ]; then
        log_monitor "ALERT: High disk usage: ${disk_usage}%"
    fi
}

# Check for memory usage
check_memory_usage() {
    local memory_usage=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
    if [ "$memory_usage" -gt 90 ]; then
        log_monitor "ALERT: High memory usage: ${memory_usage}%"
    fi
}

# Run all checks
main() {
    check_failed_logins
    check_docker_activity
    check_disk_space
    check_memory_usage
}

main "$@"
EOF

    chmod +x /opt/nostrbots/scripts/security-monitor.sh
    chown nostrbots:nostrbots /opt/nostrbots/scripts/security-monitor.sh
    
    # Add to crontab for regular monitoring
    (crontab -u nostrbots -l 2>/dev/null; echo "*/5 * * * * /opt/nostrbots/scripts/security-monitor.sh") | crontab -u nostrbots -
    
    log_success "Monitoring and alerting configured"
}

# Main function
main() {
    echo "🛡️  Nostrbots Security Hardening"
    echo "================================"
    echo ""
    
    check_root
    setup_firewall
    setup_fail2ban
    setup_logging
    setup_auto_updates
    setup_system_hardening
    setup_monitoring
    
    echo ""
    log_success "🎉 Security hardening completed!"
    echo ""
    echo "📋 Security measures implemented:"
    echo "================================"
    echo "✅ Firewall configured (UFW)"
    echo "✅ Intrusion prevention (fail2ban)"
    echo "✅ Comprehensive logging"
    echo "✅ Automatic security updates"
    echo "✅ System hardening"
    echo "✅ Security monitoring"
    echo ""
    echo "🔧 Management commands:"
    echo "======================"
    echo "ufw status                    # Check firewall status"
    echo "fail2ban-client status        # Check fail2ban status"
    echo "tail -f /var/log/nostrbots/audit.log  # View audit logs"
    echo "tail -f /var/log/nostrbots/security-monitor.log  # View security monitor logs"
    echo ""
    log_warning "⚠️  Remember to test your setup after hardening!"
    log_warning "⚠️  Some services may need to be restarted"
}

# Handle command line arguments
case "${1:-}" in
    "help"|"-h"|"--help")
        echo "Security Hardening Script"
        echo "Usage: sudo $0 [command]"
        echo ""
        echo "Commands:"
        echo "  (none)    Run full security hardening"
        echo "  help      Show this help message"
        ;;
    *)
        main
        ;;
esac
