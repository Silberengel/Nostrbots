# Nostrbots Quick Start Guide

Get up and running with Nostrbots in minutes!

## Prerequisites

- Docker and Docker Compose installed
- Git (to clone the repository)

## 1. Clone and Setup

```bash
git clone <repository-url>
cd Nostrbots
```

## 2. Build Nostrbots

```bash
docker build -t nostrbots .
```

## 3. Test Hello World Bot

```bash
# Run the test script
./test-hello-world.sh
```

This will:
- Generate a test Nostr key
- Create a "Hello World" article
- Publish it to test relays
- Verify everything works

## 4. Set Up Local Jenkins (Optional)

```bash
# Basic Jenkins setup
./scripts/setup-local-jenkins.sh

# Full setup with pipeline
./scripts/setup-local-jenkins.sh --build-nostrbots --setup-pipeline
```

Then visit: http://localhost:8080

## 5. Create Your Own Bot

```bash
# Create a new bot
./scripts/setup-bot.sh my-bot --schedule "06:00,18:00" --relays "wss://relay1.com,wss://relay2.com"

# Test your bot
docker run --rm -v $(pwd)/bots:/app/bots nostrbots run-bot --bot my-bot --dry-run --verbose
```

## 6. Production Setup

1. Generate your own Nostr key: `php generate-key.php`
2. Set environment variable: `export NOSTR_BOT_KEY=your_key`
3. Use production relays
4. Set up proper scheduling

## Troubleshooting

### Docker Issues
- Make sure Docker is running
- Check Docker permissions

### Bot Issues
- Always test with `--dry-run` first
- Check bot configuration in `bots/*/config.json`
- Verify relay URLs are accessible

### Jenkins Issues
- Check Jenkins logs: `docker logs jenkins-nostrbots`
- Verify Jenkins is accessible at http://localhost:8080
- Check Jenkins container status: `docker ps`

## Next Steps

- Read the full documentation in `README.md`
- Explore bot examples in `bots/` directory
- Set up your own content generation logic
- Configure production relays and keys
