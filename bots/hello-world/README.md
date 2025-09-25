# Hello World Bot

A simple test bot that publishes "Hello World" articles to test relays using a throwaway key.

## Purpose

This bot is designed for:
- **Immediate testing** of the Nostrbots system
- **Learning** how bots work
- **Safe experimentation** with test relays
- **No setup required** - works out of the box

## Configuration

- **Schedule**: 12:00 UTC (noon) daily
- **Relays**: Test relays (freelay.sovbit.host, relay.damus.io)
- **Content Kind**: 30041 (Publication Content)
- **Key**: Automatically generated throwaway key

## Usage

### Quick Test

```bash
# Test content generation (dry run)
docker run --rm -v $(pwd)/bots:/app/bots nostrbots run-bot --bot hello-world --dry-run --verbose

# Generate a test key and run the bot
docker run --rm -v $(pwd)/bots:/app/bots -e NOSTR_BOT_KEY=$(docker run --rm nostrbots generate-key | grep "export NOSTR_BOT_KEY" | cut -d'=' -f2) nostrbots run-bot --bot hello-world --verbose
```

### One-liner Test

```bash
# Complete test in one command
docker run --rm -v $(pwd)/bots:/app/bots -e NOSTR_BOT_KEY=$(docker run --rm nostrbots generate-key | tail -1 | cut -d'=' -f2) nostrbots run-bot --bot hello-world --verbose
```

## What It Does

1. **Generates** a simple "Hello World" article
2. **Publishes** to test relays using a throwaway key
3. **Demonstrates** the basic bot functionality
4. **Provides** educational content about Nostrbots

## Content

The bot generates articles that include:
- Welcome message
- Bot information
- Technical details
- Getting started guide
- Next steps for users

## Safety

- Uses **test relays** only
- Generates **throwaway keys** (not your real key)
- **No permanent data** stored
- **Safe for experimentation**

## Customization

You can modify:
- `config.json` - Bot settings and schedule
- `generate-content.php` - Content generation logic
- `templates/hello-world.adoc` - Article template

## Next Steps

After testing this bot:
1. Create your own bot with `./scripts/setup-bot.sh`
2. Use production relays for real content
3. Set up your own Nostr key
4. Customize content generation
