# Nostrbots Production Setup Guide

This guide covers setting up Nostrbots in a production environment with automatic startup, persistent data backups, and recovery capabilities.

## 🚀 Quick Start

```bash
# Clone the repository
git clone <your-repo-url>
cd Nostrbots

# Run the production setup (requires root)
sudo ./setup-production.sh
```

## 📋 What the Production Setup Includes

### 🔧 Core Services
- **Orly Relay**: Local Nostr relay for development and testing
- **Jenkins**: CI/CD pipeline for automated bot content generation
- **Backup Agent**: Automated daily data export and backup
- **Systemd Services**: Auto-start on boot and service management

### 💾 Data Persistence
- **Persistent Storage**: All data stored in `/opt/nostrbots/data/`
- **Daily Backups**: Automatic relay data export to `/opt/nostrbots/backups/`
- **Recovery System**: Restore from backup files when needed
- **Key Management**: Secure encrypted key storage

### 🔄 Automation Features
- **Auto-start**: Services start automatically on system boot
- **Health Monitoring**: Built-in health checks and monitoring
- **Backup Scheduling**: Daily automated backups with retention policy
- **Service Management**: Easy start/stop/restart commands

## 🏗️ System Requirements

- **OS**: Ubuntu 20.04+ or Debian 11+
- **RAM**: Minimum 2GB, recommended 4GB+
- **Storage**: Minimum 10GB free space
- **Network**: Ports 8080 (Jenkins) and 3334 (Orly relay) available
- **Privileges**: Root access for initial setup

## 📁 Directory Structure

After setup, the following directories are created:

```
/opt/nostrbots/                 # Main application directory
├── data/                       # Persistent data storage
│   ├── jenkins/               # Jenkins home directory
│   └── orly/                  # Orly relay data
├── backups/                   # Backup storage
│   ├── relay-backup-*.json.gz # Daily relay backups
│   └── nostrbots-keys-*.env   # Key backups
├── scripts/                   # Management scripts
│   ├── backup-relay-data.sh   # Backup script
│   ├── recover-from-backup.sh # Recovery script
│   └── monitor.sh             # Health monitoring
├── config/                    # Configuration files
└── .env                       # Environment variables (keys)

/var/log/nostrbots/            # Application logs
/etc/systemd/system/           # Systemd service files
/usr/local/bin/nostrbots       # Management command
```

## 🎮 Management Commands

The production setup includes a management command for easy service control:

```bash
# Service management
nostrbots start       # Start all services
nostrbots stop        # Stop all services
nostrbots restart     # Restart all services
nostrbots status      # Show service status

# Monitoring and maintenance
nostrbots monitor     # Run health check
nostrbots logs        # Show container logs
nostrbots backup      # Run manual backup
nostrbots keys        # Show current keys
nostrbots nsec        # Show decrypted private key (nsec)

# Recovery and updates
nostrbots restore <backup-file>  # Restore from backup
nostrbots update                 # Update to latest version
nostrbots help                   # Show all commands
```

## 🔑 Key Management

### Generated Keys
The setup script generates and displays:
- **Nostr Public Key (npub)**: Your bot's public identifier
- **Encrypted Private Key**: Securely encrypted private key
- **Decrypted Private Key (nsec)**: Available as environment variable `NOSTR_BOT_NSEC` for copying (not saved to files)

### Key Storage
- Encrypted keys are stored in `/opt/nostrbots/.env`
- Automatic backups created in `/opt/nostrbots/backups/`
- Keys are loaded automatically by all services
- **Decrypted nsec is NOT saved to files** - only available as environment variable for copying

### Security Notes
- ⚠️ **Save your keys securely** - they are displayed only once during setup
- 🔒 Private keys are encrypted using AES-256-CBC with PBKDF2
- 🗂️ Key backups are created automatically with timestamps

### Retrieving the Private Key (nsec)
The decrypted private key is available for copying (not saved to files):

```bash
# Using the management command (recommended)
nostrbots nsec

# From current shell environment (if available)
echo $NOSTR_BOT_NSEC

# Decrypt on-demand from encrypted key
source /opt/nostrbots/.env && php /opt/nostrbots/decrypt-key.php
```

**Note**: The nsec is only available as an environment variable for copying. It is NOT saved to any files for security reasons.

## 💾 Backup System

### Automatic Backups
- **Schedule**: Daily at midnight
- **Location**: `/opt/nostrbots/backups/`
- **Format**: Compressed JSON files (`relay-backup-YYYYMMDD-HHMMSS.json.gz`)
- **Retention**: 30 days (configurable)

### Manual Backups
```bash
# Run immediate backup
nostrbots backup

# List available backups
ls -la /opt/nostrbots/backups/
```

### Recovery Process
```bash
# List available backups
nostrbots restore

# Restore from specific backup
nostrbots restore /opt/nostrbots/backups/relay-backup-20241225-000000.json.gz
```

## 🔍 Monitoring and Health Checks

### Health Check Components
- **Docker Containers**: All containers running and healthy
- **Systemd Services**: Services active and enabled
- **Relay Connectivity**: Orly relay accessible on port 3334
- **Jenkins Access**: Jenkins accessible on port 8080

### Running Health Checks
```bash
# Full health check
nostrbots monitor

# Check service status
nostrbots status

# View logs
nostrbots logs
```

## 🌐 Service Access

### Jenkins Web Interface
- **URL**: http://localhost:8080
- **Username**: admin
- **Password**: admin
- **Pipeline Job**: nostrbots-pipeline (create manually)

### Orly Relay
- **WebSocket**: ws://localhost:3334
- **HTTP**: http://localhost:3334
- **Health Check**: http://localhost:3334/health

## 🔧 Configuration

### Environment Variables
Key configuration is stored in `/opt/nostrbots/.env`:

```bash
# Nostr Bot Configuration
NOSTR_BOT_KEY_ENCRYPTED=your_encrypted_private_key
NOSTR_BOT_NPUB=your_public_key
# Note: NOSTR_BOT_NSEC is NOT saved to files - only available as environment variable

# Jenkins Configuration
JENKINS_ADMIN_ID=admin
JENKINS_ADMIN_PASSWORD=admin
NOSTRBOTS_PASSWORD=nostrbots123
```

### Docker Compose
Production configuration is in `/opt/nostrbots/docker-compose.production.yml`

### Systemd Services
- `nostrbots.service`: Main service management
- `nostrbots-backup.service`: Backup execution
- `nostrbots-backup.timer`: Daily backup scheduling

## 🚨 Troubleshooting

### Common Issues

#### Services Not Starting
```bash
# Check service status
systemctl status nostrbots.service

# Check container logs
docker logs nostrbots-orly-relay
docker logs nostrbots-jenkins
```

#### Backup Failures
```bash
# Check backup logs
journalctl -u nostrbots-backup.service

# Run manual backup
nostrbots backup
```

#### Key Issues
```bash
# Check if keys are loaded
nostrbots keys

# Verify .env file
cat /opt/nostrbots/.env
```

### Log Locations
- **Systemd Logs**: `journalctl -u nostrbots.service`
- **Container Logs**: `nostrbots logs`
- **Application Logs**: `/var/log/nostrbots/`

## 🔄 Updates and Maintenance

### Updating the System
```bash
# Update to latest version
nostrbots update
```

### Manual Maintenance
```bash
# Stop services for maintenance
nostrbots stop

# Perform maintenance tasks
# ... your maintenance tasks ...

# Restart services
nostrbots start
```

## 🛡️ Security Considerations

### Network Security
- Services bind to localhost by default
- Consider firewall rules for production use
- Use reverse proxy for external access

### Data Security
- Regular backup verification
- Secure key storage and rotation
- Monitor access logs

### System Security
- Keep system updated
- Use strong passwords
- Regular security audits

## 📞 Support

### Getting Help
1. Check the health status: `nostrbots monitor`
2. Review logs: `nostrbots logs`
3. Check service status: `nostrbots status`
4. Review this documentation

### Emergency Recovery
1. Stop services: `nostrbots stop`
2. Restore from backup: `nostrbots restore <backup-file>`
3. Restart services: `nostrbots start`

## 🎯 Next Steps After Setup

1. **Access Jenkins**: Go to http://localhost:8080
2. **Create Pipeline**: Set up the 'nostrbots-pipeline' job
3. **Test Backup**: Run `nostrbots backup` to verify
4. **Monitor Health**: Use `nostrbots monitor` regularly
5. **Configure Alerts**: Set up monitoring for production use

---

**Note**: This production setup is designed for development and testing environments. For production use, consider additional security hardening, monitoring, and backup strategies.
