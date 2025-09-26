# Local Development Guide

This guide explains how to set up and use Nostrbots for local development and testing.

## 🚀 Quick Start

### 1. Setup Local Development Environment

```bash
# Run the local development setup
./setup-local-dev.sh
```

This will:
- ✓ Install PHP dependencies
- ✓ Generate keys for local development
- ✓ Start Orly relay for testing
- ✓ Create .env file for local use
- ✓ Test the setup
- ✓ Run Hello World bot test

### 2. Access Local Services

- **Orly Relay**: http://localhost:3334
- **Hello World Bot**: `bots/hello-world/`
- **Generated Content**: `bots/hello-world/output/`

## 🔧 Local Development Management

### Using the Management Script

```bash
# Check environment status
nostrbots-dev status

# Start/stop services
nostrbots-dev start
nostrbots-dev stop
nostrbots-dev restart

# Test the Hello World bot
nostrbots-dev hello-world

# Publish Hello World content
nostrbots-dev hello-world-publish

# Run unit tests
nostrbots-dev test

# Get your nsec (private key)
nostrbots-dev nsec

# Clean up everything
nostrbots-dev cleanup
```

### Manual Commands

```bash
# Generate Hello World content
php bots/hello-world/generate-content.php

# Test publishing (dry run)
php nostrbots.php bots/hello-world/output/hello-world.adoc --dry-run

# Publish to Nostr
php nostrbots.php bots/hello-world/output/hello-world.adoc

# Run unit tests
php run-tests.php

# Generate new keys
php generate-key.php

# Decrypt keys
php decrypt-key.php
```

## 📁 Local Development Structure

```
Nostrbots/
├── .env                          # Local environment variables
├── bots/                         # Bot configurations
│   ├── hello-world/             # Test bot
│   │   ├── config.json          # Bot configuration
│   │   ├── generate-content.php # Content generator
│   │   ├── output/              # Generated content
│   │   └── templates/           # Content templates
│   └── daily-office/            # Example bot
├── src/                         # Source code
├── vendor/                      # PHP dependencies
├── nostrbots.php               # Main publishing script
├── generate-key.php            # Key generation
├── decrypt-key.php             # Key decryption
├── run-tests.php               # Unit tests
└── setup-local-dev.sh          # Local setup script
```

## 🧪 Testing

### Hello World Bot Test

The Hello World bot is a simple test bot that:

1. **Generates Content**: Creates a test article with current timestamp
2. **Uses Templates**: Fills in placeholders in AsciiDoc templates
3. **Saves Output**: Stores generated content in `output/` directory
4. **Publishes to Nostr**: Can publish to local Orly relay

### Test Process

```bash
# 1. Generate content
php bots/hello-world/generate-content.php

# 2. Check generated content
ls -la bots/hello-world/output/
cat bots/hello-world/output/hello-world-*.adoc

# 3. Test publishing (dry run)
php nostrbots.php bots/hello-world/output/hello-world-*.adoc --dry-run

# 4. Publish to Nostr
php nostrbots.php bots/hello-world/output/hello-world-*.adoc

# 5. Verify on Orly relay
curl http://localhost:3334
```

### Unit Tests

```bash
# Run all unit tests
php run-tests.php

# Run specific test
php run-tests.php --filter AsciiDocHeaderTest
```

## Key Management

### Local Development Keys

Local development uses a `.env` file for key storage:

```bash
# .env file contents
NOSTR_BOT_KEY_ENCRYPTED=<encrypted_private_key>
NOSTR_BOT_NPUB=<public_key>
NOSTR_RELAYS=ws://localhost:3334
```

### Key Operations

```bash
# Generate new keys
php generate-key.php

# Use your existing key (nsec or hex)
php generate-key.php --key YOUR_NSEC_OR_HEX_KEY --encrypt

# Decrypt and view nsec
php decrypt-key.php

# View public key
grep NOSTR_BOT_NPUB .env
```

### Using Your Existing Key

If you want to use your existing Nostr private key instead of generating a new one:

```bash
# Using nsec format
php generate-key.php --key "nsec1abc123def456..." --encrypt

# Using hex format
php generate-key.php --key "a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456" --encrypt

# With custom password
php generate-key.php --key YOUR_KEY --password "my_password" --encrypt
```

See **[Manual NSEC Setup](MANUAL_NSEC_SETUP.md)** for detailed instructions.

## 🌐 Local Services

### Orly Relay

The local Orly relay provides:

- **WebSocket Interface**: `ws://localhost:3334`
- **HTTP Interface**: `http://localhost:3334`
- **Event Storage**: Local SQLite database
- **Testing Environment**: Safe for development

### Docker Container

```bash
# Check Orly status
docker ps | grep orly

# View Orly logs
docker logs orly-relay

# Restart Orly
docker restart orly-relay

# Stop Orly
docker stop orly-relay
```

## 📝 Creating Custom Bots

### 1. Create Bot Directory

```bash
mkdir -p bots/my-bot/{templates,output}
```

### 2. Create Configuration

```json
// bots/my-bot/config.json
{
    "name": "My Custom Bot",
    "description": "A custom bot for my content",
    "version": "1.0.0",
    "author": "Your Name",
    "schedule": "manual",
    "relays": ["ws://localhost:3334"],
    "content_kind": 30041,
    "content_level": 0
}
```

### 3. Create Template

```asciidoc
// bots/my-bot/templates/my-content.adoc
= My Custom Content
author: My Custom Bot
version: 1.0
relays: test-relays
auto_update: true
summary: My custom content
type: article
bot_type: my-bot
generated_at: {timestamp}

**Hello from My Custom Bot!**

This is my custom content generated at {date} {time}.

== Custom Section

This is a custom section with:
- Custom content
- Dynamic timestamps
- Bot-specific information
```

### 4. Create Content Generator

```php
// bots/my-bot/generate-content.php
<?php

require_once __DIR__ . '/../../src/bootstrap.php';

// Your custom content generation logic here
$content = "Your generated content";

// Save to output directory
file_put_contents(__DIR__ . '/output/my-content.adoc', $content);

echo "Content generated successfully\n";
```

### 5. Test Your Bot

```bash
# Generate content
php bots/my-bot/generate-content.php

# Test publishing
php nostrbots.php bots/my-bot/output/my-content.adoc --dry-run

# Publish to Nostr
php nostrbots.php bots/my-bot/output/my-content.adoc
```

## Debugging

### Common Issues

**Orly relay not accessible:**
```bash
# Check if Orly is running
docker ps | grep orly

# Check Orly logs
docker logs orly-relay

# Restart Orly
docker restart orly-relay
```

**Key decryption fails:**
```bash
# Check .env file
cat .env

# Regenerate keys
php generate-key.php

# Test decryption
php decrypt-key.php
```

**Content generation fails:**
```bash
# Check bot directory
ls -la bots/hello-world/

# Check template file
cat bots/hello-world/templates/hello-world.adoc

# Check config file
cat bots/hello-world/config.json
```

**Publishing fails:**
```bash
# Test with dry run first
php nostrbots.php bots/hello-world/output/hello-world.adoc --dry-run

# Check Orly connection
curl http://localhost:3334

# Check generated content
cat bots/hello-world/output/hello-world.adoc
```

### Logs and Output

- **Generated Content**: `bots/*/output/`
- **Orly Logs**: `docker logs orly-relay`
- **PHP Errors**: Check terminal output
- **Test Results**: `php run-tests.php` output

## 🧹 Cleanup

### Clean Up Local Environment

```bash
# Stop and remove Orly
docker stop orly-relay
docker rm orly-relay

# Remove .env file
rm -f .env

# Remove generated content
rm -rf bots/*/output/*

# Remove data directory
rm -rf data/
```

### Full Cleanup

```bash
# Use the management script
nostrbots-dev cleanup

# Or manual cleanup
./cleanup.sh
```

## 🚀 Next Steps

After local development:

1. **Test Your Bots**: Ensure they work correctly
2. **Create Content**: Develop your bot logic
3. **Test Publishing**: Verify Nostr integration
4. **Deploy to Production**: Use production setup scripts
5. **Monitor**: Use Elasticsearch setup for monitoring

## 📚 Additional Resources

- **[Production Setup Guide](PRODUCTION_SETUP.md)**: Deploy to production
- **[Elasticsearch Setup](ELASTICSEARCH_SETUP.md)**: Add monitoring
- **[Security Guide](SECURITY_GUIDE.md)**: Security best practices
- **[README](README.md)**: Main documentation

---

**Happy developing!** 🚀
