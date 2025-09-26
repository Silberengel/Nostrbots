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
$ sudo -E ./setup-production-with-elasticsearch.sh
```

### Production Setup (Recommended)
```bash
# Complete setup with monitoring
sudo ./setup-production-with-elasticsearch.sh

# Or basic setup without Elasticsearch
sudo ./setup-production.sh
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

## 🌐 Access Points

### Production Services
- **Jenkins**: http://localhost:8080
- **Orly Relay**: ws://localhost:3334
- **Kibana**: http://localhost:5601 (Elasticsearch setup)
- **Elasticsearch**: http://localhost:9200 (Elasticsearch setup)

### Management Commands
```bash
# Production
nostrbots status|logs|backup|elasticsearch
./retrieve-nsec-secure.sh

# Local Development
nostrbots-dev status|start|stop|hello-world|test|nsec|cleanup
./note "Your message" [--dry-run]
```

## 📚 Documentation

- **[Local Development Guide](LOCAL_DEVELOPMENT.md)**: Local development and testing
- **[Manual NSEC Setup](MANUAL_NSEC_SETUP.md)**: Using your existing Nostr private key
- **[Production Setup Guide](PRODUCTION_SETUP.md)**: Complete production deployment guide
- **[Elasticsearch Setup](ELASTICSEARCH_SETUP.md)**: Monitoring and search capabilities
- **[Security Guide](SECURITY_GUIDE.md)**: Security features and hardening

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

**Ready to start botting?** Run `./setup-local-dev.sh` for local development or `sudo ./setup-production-with-elasticsearch.sh` for production! 🚀