# Efficient Backup System for Nostrbots

## Overview

The new efficient backup system addresses the issue of excessive backup storage (800GB+) by only backing up essential data instead of entire container volumes. This system focuses on:

- **Config files**: Jenkins configuration, Nostrbots project settings
- **Relay events**: Essential event data from the relay database
- **Elasticsearch data**: Exported indices and mappings (not full snapshots)

## Key Improvements

### Before (Inefficient)
- ❌ Backed up entire container volumes
- ❌ Included logs, cache, and temporary files
- ❌ Created 800GB+ of backup data
- ❌ Slow backup and restore operations

### After (Efficient)
- ✅ Only backs up essential config and data
- ✅ Compressed archives (typically <100MB)
- ✅ Fast backup and restore operations
- ✅ Automatic cleanup of old backups
- ✅ Backup integrity verification

## Backup Components

### 1. Config Files
- **Jenkins**: `config.xml`, job configurations, user settings
- **Nostrbots**: `.env` files, docker-compose files, scripts
- **Relay**: Configuration files and settings

### 2. Relay Events
- **Database**: `events.db` (SQLite database)
- **Stats**: Event count and metadata
- **Config**: Relay configuration files

### 3. Elasticsearch Data
- **Mappings**: Index structure definitions
- **Data**: Recent events (last 30 days by default)
- **Stats**: Index statistics and metadata

## Usage

### Automatic Backups
The system automatically creates daily backups via the backup-agent container:

```bash
# Check backup status
./scripts/manage-backups.sh status

# View available backups
./scripts/manage-backups.sh list
```

### Manual Backups
Create a backup on demand:

```bash
# Create new backup
./scripts/manage-backups.sh backup

# Check backup size
./scripts/manage-backups.sh size
```

### Restore from Backup
Restore from a specific backup:

```bash
# List available backups
./scripts/manage-backups.sh list

# Restore from backup (with confirmation)
./scripts/manage-backups.sh restore nostrbots-essential-20241225_120000.tar.gz

# Dry run restore (see what would be restored)
./scripts/restore-essential-data.sh /backups/nostrbots-essential-20241225_120000.tar.gz --dry-run
```

### Cleanup Options

#### Basic Cleanup (Backups Only)
Remove old backups to free space:

```bash
# Clean up backups older than 30 days
./scripts/manage-backups.sh cleanup
```

#### Complete Cleanup (Everything)
Remove all Nostrbots components including containers, data, and services:

```bash
# Complete cleanup - removes everything (requires confirmation)
./scripts/manage-backups.sh cleanup-all
```

**⚠️ WARNING**: `cleanup-all` removes:
- All Docker containers (Orly, Jenkins, Elasticsearch, etc.)
- All Docker volumes and data
- All backup files
- All systemd services
- All project data and logs

This is equivalent to a complete uninstall and cannot be undone!

## Backup Format

### Archive Structure
```
nostrbots-essential-YYYYMMDD_HHMMSS.tar.gz
├── config/
│   ├── jenkins/
│   │   ├── config.xml
│   │   ├── jobs/
│   │   └── users/
│   └── nostrbots/
│       ├── .env
│       ├── docker-compose.yml
│       └── scripts/
├── events/
│   ├── events.db
│   ├── event_count.txt
│   └── config files
├── elasticsearch/
│   ├── nostr-events_mapping.json
│   ├── nostr-events_data.json
│   ├── nostr-events_stats.json
│   └── nostrbots-logs_*
└── manifest.json
```

### Manifest File
Each backup includes a manifest with metadata:

```json
{
    "backup_date": "2024-12-25T12:00:00+00:00",
    "backup_type": "essential_data",
    "archive_name": "nostrbots-essential-20241225_120000.tar.gz",
    "archive_size_mb": 45,
    "components": {
        "config_files": true,
        "relay_events": true,
        "elasticsearch_data": true
    },
    "retention_days": 30
}
```

## Configuration

### Environment Variables
- `BACKUP_DIR`: Backup storage location (default: `/backups`)
- `DATA_DIR`: Source data location (default: `/data`)
- `ELASTICSEARCH_URL`: Elasticsearch endpoint (default: `http://elasticsearch:9200`)
- `BACKUP_RETENTION_DAYS`: Retention policy (default: 30 days)
- `MAX_BACKUP_SIZE_MB`: Maximum backup size warning (default: 1000MB)

### Backup Schedule
- **Frequency**: Daily at midnight
- **Retention**: 30 days (configurable)
- **Location**: `/opt/nostrbots/backups/`
- **Format**: Compressed tar.gz archives

## Monitoring

### Backup Logs
```bash
# View backup logs
tail -f /var/log/nostrbots/backup.log

# Check systemd backup service
systemctl status nostrbots-backup.service
systemctl status nostrbots-backup.timer
```

### Size Monitoring
```bash
# Check current backup usage
./scripts/manage-backups.sh size

# Monitor disk usage
df -h /opt/nostrbots/backups/
```

## Troubleshooting

### Common Issues

#### Backup Size Too Large
If backups exceed 1GB, check for:
- Large log files in Jenkins data
- Excessive Elasticsearch data
- Unnecessary files in project directory

#### Backup Failures
```bash
# Check backup logs
journalctl -u nostrbots-backup.service

# Run manual backup for debugging
./scripts/backup-essential-data.sh
```

#### Restore Issues
```bash
# Verify backup integrity
tar -tzf /backups/nostrbots-essential-*.tar.gz

# Test restore with dry run
./scripts/restore-essential-data.sh /backups/backup.tar.gz --dry-run
```

### Recovery Procedures

#### Complete System Recovery
1. Stop all services
2. Restore from latest backup
3. Restart services
4. Verify functionality

#### Partial Recovery
```bash
# Restore only config files
./scripts/restore-essential-data.sh backup.tar.gz --config-only

# Restore only relay events
./scripts/restore-essential-data.sh backup.tar.gz --events-only
```

## Migration from Old System

### Before Migration
1. **Stop services**: `docker stack rm nostrbots`
2. **Backup current data**: Copy `/opt/nostrbots` to safe location
3. **Clean old backups**: Remove large backup files

### After Migration
1. **Deploy new system**: Use updated docker-compose files
2. **Verify backups**: Check new backup system works
3. **Test restore**: Verify restore functionality

### Cleanup Old Backups
```bash
# Remove old inefficient backups
rm -rf /opt/nostrbots/backups/20*

# Check space savings
du -sh /opt/nostrbots/backups/
```

## Best Practices

### Regular Maintenance
- Monitor backup sizes weekly
- Test restore procedures monthly
- Clean up old backups regularly
- Verify backup integrity

### Security
- Backups are stored locally (not encrypted by default)
- Consider encrypting sensitive backups
- Restrict access to backup directory
- Regular security updates

### Performance
- Run backups during low-usage periods
- Monitor disk I/O during backups
- Consider backup to external storage
- Implement backup rotation policies

## Support

For issues with the backup system:
1. Check logs: `/var/log/nostrbots/backup.log`
2. Verify configuration: Environment variables
3. Test manually: Run backup scripts directly
4. Check disk space: Ensure sufficient storage
5. Review documentation: This guide and script help

## Scripts Reference

### backup-essential-data.sh
Main backup script that creates efficient backups.

### restore-essential-data.sh
Restore script with options for partial restoration.

### manage-backups.sh
Management interface for backup operations with comprehensive cleanup capabilities.

### cleanup-systemd.sh
Standalone script for cleaning up only systemd services (does not remove data).