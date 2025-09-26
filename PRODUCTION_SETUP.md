# Nostrbots Production Setup Guide

This guide covers setting up Nostrbots in a production environment with automatic startup, persistent data backups, and recovery capabilities. The setup now supports both basic and ELK stack configurations.

## 🚀 Quick Start

```bash
# Clone the repository
git clone <your-repo-url>
cd Nostrbots

# Minimal development setup
./setup-local-dev.sh

# Basic setup (no ELK stack)
sudo ./setup-production.sh

# Complete setup with ELK monitoring stack
sudo ./setup-production.sh --elk
```

## 🎯 Choosing Your Setup

### Basic Setup (Default)
- **Best for**: Simple deployments, development, testing
- **Services**: Orly Relay, Jenkins, Backup Agent
- **Resources**: Lower memory and storage requirements
- **Features**: Core Nostrbots functionality without monitoring

### ELK Stack Setup (--elk option)
- **Best for**: Production deployments, monitoring, analytics
- **Services**: All basic services + Elasticsearch, Kibana, Logstash, Event Indexer
- **Resources**: Higher memory and storage requirements (4GB+ RAM recommended)
- **Features**: Full monitoring, event indexing, log analysis, search capabilities

## 🧹 Cleanup and Testing

### Getting a Blank Slate
For testing and development, you can easily clean up the entire stack and start fresh:

```bash
# Clean up existing stack and secrets
sudo ./setup-production.sh --cleanup

# Start fresh after cleanup
sudo -E ./setup-production.sh
```

### What the Cleanup Does
The `--cleanup` option performs a complete reset:

1. **Removes Docker Stack** - Stops all services and removes the stack
2. **Removes Docker Secrets** - Cleans up nostr_bot_key_encrypted and nostr_bot_npub secrets
3. **Removes Remaining Containers** - Cleans up any orphaned containers
4. **Preserves Data Volumes** - Keeps your data (Jenkins data, Orly data, backups) by default

### Complete Data Cleanup (Optional)
If you want to remove volumes as well (complete cleanup), you can uncomment the volume removal lines in the cleanup function:

```bash
# Edit the setup script to enable volume cleanup
sudo nano setup-production.sh

# Find the cleanup function and uncomment these lines:
# docker volume rm nostrbots_orly_data >/dev/null 2>&1 || true
# docker volume rm nostrbots_jenkins_data >/dev/null 2>&1 || true
# docker volume rm nostrbots_backup_data >/dev/null 2>&1 || true
```

### Testing Workflow
```bash
# 1. Clean slate
sudo ./setup-production.sh --cleanup

# 2. Fresh installation
sudo -E ./setup-production.sh

# 3. Verify services
nostrbots status
nostrbots monitor

# 4. Test cleanup again
sudo ./setup-production.sh --cleanup
```

## 📋 What the Production Setup Includes

### 🔧 Core Services (Basic Setup)
- **Orly Relay**: Local Nostr relay for development and testing
- **Jenkins**: CI/CD pipeline for automated bot content generation
- **Backup Agent**: Automated daily data export and backup
- **Systemd Services**: Auto-start on boot and service management

### 📊 ELK Stack Services (--elk option)
- **Elasticsearch**: Search and analytics engine
- **Kibana**: Data visualization and dashboard
- **Logstash**: Log processing and pipeline
- **Event Indexer**: Automatic indexing of Nostr events

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
│   ├── orly/                  # Orly relay data
│   └── elasticsearch/         # Elasticsearch data (ELK setup)
├── backups/                   # Backup storage
│   ├── relay-backup-*.json.gz # Daily relay backups
│   └── nostrbots-keys-*.env   # Key backups
├── scripts/                   # Management scripts
│   ├── manage-stack.sh        # Docker Stack management
│   ├── backup-relay-data.sh   # Backup script
│   ├── index-relay-events.sh  # Event indexing script
│   ├── recover-from-backup.sh # Recovery script
│   └── monitor.sh             # Health monitoring
├── config/                    # Configuration files
│   ├── logstash/              # Logstash configuration (ELK setup)
│   └── kibana/                # Kibana configuration (ELK setup)
├── docker-compose.basic.yml   # Basic setup configuration
├── docker-compose.stack.yml   # ELK stack configuration
└── .env                       # Environment variables (keys)

/var/log/nostrbots/            # Application logs
/etc/systemd/system/           # Systemd service files
/usr/local/bin/nostrbots       # Management command
```

## 🎮 Management Commands

The production setup includes multiple management tools for easy service control:

### Docker Stack Management

The new `manage-stack.sh` script provides comprehensive Docker Stack management:

```bash
# Stack deployment
./scripts/manage-stack.sh deploy basic    # Deploy basic setup
./scripts/manage-stack.sh deploy elk      # Deploy with ELK stack

# Stack management
./scripts/manage-stack.sh status          # Show stack status
./scripts/manage-stack.sh health          # Check service health
./scripts/manage-stack.sh restart basic   # Restart basic stack
./scripts/manage-stack.sh restart elk     # Restart ELK stack
./scripts/manage-stack.sh stop            # Stop the stack

# Logs and monitoring
./scripts/manage-stack.sh logs event-indexer  # View specific service logs
./scripts/manage-stack.sh logs jenkins        # View Jenkins logs
./scripts/manage-stack.sh ps                  # Show running services

# Maintenance
./scripts/manage-stack.sh cleanup         # Remove stack and clean up
./scripts/manage-stack.sh update elk      # Update and redeploy
```

### Service Management

The traditional management command for service control:

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

### Setup Script Options

The setup script supports multiple configuration options:

```bash
# Basic setup (no ELK stack)
sudo ./setup-production.sh                    # Generate new keys and setup
sudo ./setup-production.sh --private-key KEY  # Use existing private key

# ELK stack setup (with monitoring)
sudo ./setup-production.sh --elk              # Setup with ELK stack
sudo ./setup-production.sh --elk --private-key KEY  # ELK with existing key

# Management options
sudo ./setup-production.sh --cleanup          # Clean up for blank slate testing
sudo ./setup-production.sh --change-keys --private-key NEW_KEY  # Change keys

# Help
sudo ./setup-production.sh --help             # Show setup script help
```

## Key Management

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
- ⚠ **Save your keys securely** - they are displayed only once during setup
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

## 🔧 Manual Script Execution

### Backup Script
The enhanced backup script provides comprehensive data backup including relay data and Elasticsearch snapshots:

```bash
# Run the backup script (requires sudo)
sudo /opt/nostrbots/scripts/backup-relay-data.sh
```

**What it does:**
- Backs up relay data files from `/data/orly` to `/backups/`
- Creates Elasticsearch snapshots for search data
- Generates encrypted backup archives
- Verifies backup integrity
- Cleans up old backups (30-day retention)

**Configuration:**
- `BACKUP_DIR`: Backup storage location (default: `/backups`)
- `DATA_DIR`: Source data location (default: `/data`)
- `ELASTICSEARCH_URL`: Elasticsearch endpoint (default: `http://elasticsearch:9200`)
- `BACKUP_RETENTION_DAYS`: Retention policy (default: 30 days)

### Event Indexing Script
The indexing script processes Nostr events from the relay into Elasticsearch for search and analytics:

```bash
# Run the indexing script (requires sudo)
sudo /opt/nostrbots/scripts/index-relay-events.sh
```

**What it does:**
- Queries the relay for new events since last index
- Creates Elasticsearch index mappings
- Bulk indexes events for search
- Handles incremental updates
- Provides error handling and logging

**Configuration:**
- `ELASTICSEARCH_URL`: Elasticsearch endpoint (default: `http://elasticsearch:9200`)
- `ORLY_RELAY_URL`: Relay endpoint (default: `http://orly-relay:7777`)
- `INDEX_NAME`: Elasticsearch index name (default: `nostr-events`)
- `BATCH_SIZE`: Events per batch (default: 100)
- `MAX_EVENTS`: Maximum events per run (default: 1000)

### Script Logs
Both scripts log their activities to:
- `/var/log/nostrbots/backup.log` - Backup operations
- `/var/log/nostrbots/event-indexer.log` - Indexing operations

### Prerequisites
Before running the scripts, ensure:
1. **Elasticsearch is running** (for both scripts)
2. **Relay data exists** at `/data/orly` (for backup script)
3. **Proper permissions** - scripts require sudo access
4. **Network connectivity** to Elasticsearch and relay services

**Note**: The scripts automatically create the `/var/log/nostrbots/` directory if it doesn't exist.

## Monitoring and Health Checks

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

### ELK Stack (--elk option)
- **Elasticsearch**: http://localhost:9200
- **Kibana**: http://localhost:5601
- **Event Indexer**: Automatically indexes Nostr events every 5 minutes
- **Logstash**: Processes logs from all services

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
Production configurations are in:
- `/opt/nostrbots/docker-compose.basic.yml` - Basic setup (no ELK)
- `/opt/nostrbots/docker-compose.stack.yml` - Full setup with ELK stack

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
