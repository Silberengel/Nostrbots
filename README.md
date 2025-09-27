# Nostrbots

A secure, enterprise-grade PHP tool for publishing content to Nostr from AsciiDoc and Markdown documents, with automated bots, Jenkins CI/CD, and comprehensive security features.

## About

Nostrbots enables automated content publishing to the Nostr protocol with enterprise-grade security and monitoring. It supports multiple event kinds (30023, 30040/30041, 30818), features Docker-based deployment with Jenkins CI/CD, and includes optional Elasticsearch integration for advanced monitoring and search capabilities.

Made with ❤️ by [Silberengel](https://github.com/Silberengel).
I'm happy to receive tips: silberengel@minibits.cash

**Key Features:**
- 🔒 **Secure**: AES-256-CBC encryption, Docker secrets, non-root containers
- 🤖 **Automated**: Bot system with scheduled publishing and CI/CD pipelines
- 📊 **Monitored**: Optional Elasticsearch integration with Kibana dashboards
- 🧪 **Tested**: Comprehensive test suite with local development environment
- 🚀 **Production-Ready**: Systemd services, automated backups, security hardening

## 🚀 Quick Start

### Admin Nsec
You can let Nostrbots generate your nsec, pass it as a parameter to the setup script or set it in your environment variables.
```bash
# for local use
export CUSTOM_PRIVATE_KEY="your_key_here"
./setup-local.sh

# for productive use
sudo CUSTOM_PRIVATE_KEY="your_key_here" ./setup-production.sh

# or just take your environment variables with you
$ sudo -E ./setup-production.sh --elk
```

### Production Setup (Recommended)
```bash
# Basic setup (no ELK stack)
sudo ./setup-production.sh

# Complete setup with ELK monitoring stack
sudo ./setup-production.sh --elk
```

### Local Development
```bash
# Setup local development environment
./setup-local-dev.sh
```

### Manual Testing
```bash
# Run unit tests
php run-tests.php

# Test Hello World bot
php bots/hello-world/generate-content.php
php nostrbots.php bots/hello-world/output/hello-world-latest.adoc --dry-run

# Write simple Nostr notes
php write-note.php "Hello, Nostr!"
```

**Note:** The setup script automatically loads environment variables. For new shell sessions, run `source .env` first.

### Using Your Existing Key
```bash
# Use your existing nsec or hex private key
php generate-key.php --key YOUR_NSEC_OR_HEX_KEY --encrypt

# See [Manual NSEC Setup](MANUAL_NSEC_SETUP.md) for detailed instructions
```

## 🔑 Multi-Key Support

Nostrbots supports multiple nsec keys, allowing you to run different bots with different identities. This is useful for:
- **Brand separation**: Different bots for different purposes
- **Security isolation**: Separate keys for different environments
- **Identity management**: Multiple Nostr identities from one system

### Supported Environment Variables
- `NOSTR_BOT_KEY` (default)
- `NOSTR_BOT_KEY1` through `NOSTR_BOT_KEY10` (numbered slots)
- `CUSTOM_PRIVATE_KEY` (fallback when `NOSTR_BOT_KEY` is not set)

### Setting Up Multiple Keys

1. **Generate keys for different bots:**
```bash
# Generate key for bot 1
php generate-key.php --slot 1

# Generate key for bot 2  
php generate-key.php --slot 2

# Generate key for bot 3
php generate-key.php --slot 3
```

2. **Set environment variables:**
```bash
# Set different keys for different bots
export NOSTR_BOT_KEY1="your_first_nsec_or_hex_key"
export NOSTR_BOT_KEY2="your_second_nsec_or_hex_key" 
export NOSTR_BOT_KEY3="your_third_nsec_or_hex_key"
```

3. **Configure each bot to use its specific key:**
```json
{
  "name": "Hello World Bot",
  "environment_variable": "NOSTR_BOT_KEY1",
  "relays": ["wss://freelay.sovbit.host"],
  "content_kind": 30041
}
```

```json
{
  "name": "Daily Office Bot", 
  "environment_variable": "NOSTR_BOT_KEY2",
  "relays": ["wss://thecitadel.nostr1.com"],
  "content_kind": 30023
}
```

### Running Bots with Different Keys
```bash
# Run hello-world bot (uses NOSTR_BOT_KEY1)
php nostrbots.php run-bot --bot hello-world

# Run daily-office bot (uses NOSTR_BOT_KEY2)  
php nostrbots.php run-bot --bot daily-office
```

### Key Management Commands
```bash
# List all configured keys
php generate-key.php --list-keys

# Generate new key for specific slot
php generate-key.php --slot 3 --encrypt

# Validate a specific key
php generate-key.php --validate --slot 2
```

### Docker Multi-Key Setup
```yaml
services:
  bot1:
    environment:
      - NOSTR_BOT_KEY1=your_first_key
      
  bot2:
    environment:
      - NOSTR_BOT_KEY2=your_second_key
```

### Key Priority Order

The system checks for keys in this order:
1. **Docker secrets** (`/run/secrets/nostr_bot_key`)
2. **NOSTR_BOT_KEY** environment variable
3. **CUSTOM_PRIVATE_KEY** environment variable (fallback)
4. **NOSTR_BOT_KEY_ENCRYPTED** (encrypted key)
5. **Generate new key** if none found

### Using CUSTOM_PRIVATE_KEY

The `CUSTOM_PRIVATE_KEY` environment variable serves as a fallback when `NOSTR_BOT_KEY` is not set. This is useful for:
- **Setup scripts**: Pass your existing key during installation
- **Legacy compatibility**: Support for existing deployments
- **Quick testing**: Temporary key assignment

```bash
# Use during setup
export CUSTOM_PRIVATE_KEY="your_nsec_or_hex_key"
./setup-local-dev.sh

# Or pass directly to setup script
sudo CUSTOM_PRIVATE_KEY="your_key" ./setup-production.sh
```

**Note:** Each bot must specify its `environment_variable` in its `config.json` to use the correct key. If not specified, it defaults to `NOSTR_BOT_KEY`. The `CUSTOM_PRIVATE_KEY` is automatically used as a fallback and will be set as `NOSTR_BOT_KEY` for compatibility.

## 🌐 Access Points

### Production Services
- **Jenkins**: http://localhost:8080
- **Orly Relay**: ws://localhost:3334
- **Kibana**: http://localhost:5601 (Elasticsearch setup)
- **Elasticsearch**: http://localhost:9200 (Elasticsearch setup)

### Management Commands
```bash
# Production Stack Management
./scripts/manage-stack.sh status|health|logs|restart
./scripts/manage-stack.sh deploy basic|elk

# Production Service Management
nostrbots status|logs|backup|elasticsearch
./retrieve-nsec-secure.sh

# Local Development
nostrbots-dev status|start|stop|hello-world|test|nsec|cleanup
./note "Your message" [--dry-run]
```

## 💾 Efficient Backup System

### Automatic Backups
- **Location**: `/opt/nostrbots/backups/`
- **Format**: Compressed archives (`nostrbots-essential-YYYYMMDD_HHMMSS.tar.gz`)
- **Size**: Typically <100MB (vs 800GB+ with old system)
- **Schedule**: Daily automated backups with 30-day retention

### Manual Backup Management
```bash
# Create new backup
./scripts/manage-backups.sh backup

# List available backups
./scripts/manage-backups.sh list

# Check backup system status
./scripts/manage-backups.sh status

# Restore from backup
./scripts/manage-backups.sh restore <backup-file>

# Clean up old backups
./scripts/manage-backups.sh cleanup

# Clean up old systemd services
./cleanup-systemd.sh [--dry-run]

# Complete cleanup (removes everything - use with caution!)
./scripts/manage-backups.sh cleanup-all
```

### What's Backed Up
- **Config files**: Jenkins settings, project configuration
- **Relay events**: Essential event data from database
- **Elasticsearch data**: Exported indices and mappings
- **NOT backed up**: Logs, cache, temporary files, entire containers

## 📚 Documentation

- **[Local Development Guide](LOCAL_DEVELOPMENT.md)**: Local development and testing
- **[Manual NSEC Setup](MANUAL_NSEC_SETUP.md)**: Using your existing Nostr private key
- **[Production Setup Guide](PRODUCTION_SETUP.md)**: Complete production deployment guide
- **[Elasticsearch Setup](ELASTICSEARCH_SETUP.md)**: Monitoring and search capabilities
- **[Security Guide](SECURITY_GUIDE.md)**: Security features and hardening
- **[Efficient Backup System](EFFICIENT_BACKUP_SYSTEM.md)**: Backup and restore procedures

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run the test suite: `php run-tests.php`
5. Test locally: `./setup-local-dev.sh`
6. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📧 Contact

**Author**: [Silberengel](https://jumble.imwald.eu/users/npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z) on Nostr
**Repository**: [GitHub](https://github.com/Silberengel/nostrbots)  
**Issues**: [Nostr Issues](https://gitworkshop.dev/npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z/theforest.nostr1.com/Nostrbots)

---

**Ready to start botting?** Run `./setup-local-dev.sh` for local development or `sudo ./setup-production.sh --elk` for production! 🚀