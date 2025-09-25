# Nostrbots

A PHP tool for publishing content to Nostr from AsciiDoc and Markdown documents, with support for automated bots and scheduled publishing.

## Features

- **Direct Publishing**: Publish from AsciiDoc/Markdown files with embedded metadata
- **Multiple Event Kinds**: 30023 (Long-form), 30040/30041 (Publications), 30818 (Wiki)
- **Bot System**: Automated content generation and scheduled publishing
- **Docker Support**: Containerized deployment with Jenkins CI/CD
- **Test Environment**: Safe testing with throwaway keys and test relays

## Supported Event Kinds

| Kind  | Name | Description |
|-------|------|-------------|
| 30023 | Long-form Content | Articles and blog posts (Markdown) |
| 30040 | Publication Index | Table of contents for publications |
| 30041 | Publication Content | Sections/chapters (AsciiDoc) |
| 30818 | Wiki Article | Collaborative wiki articles |

## Installation

### With Docker (Recommended)

```bash
# Clone and build
git clone <repository-url>
cd Nostrbots
docker build -t nostrbots .

# Test immediately
./test-hello-world.sh
```

### Without Docker

```bash
# Install dependencies
composer install

# Generate Nostr key (or use your own)
php generate-key.php --export

# Set environment variable
export NOSTR_BOT_KEY=your_private_key_here

# Test publishing
php nostrbots.php publish examples/simple-guide.adoc --dry-run --verbose
```

## Quick Test

Try the Hello World bot immediately:

```bash
./test-hello-world.sh
```

This will:
- Generate a test Nostr key
- Create a "Hello World" article
- Publish to test relays
- Verify everything works

## Bot System

### Create a Bot

```bash
# Create a new bot
./scripts/setup-bot.sh my-bot --schedule "06:00,18:00" --relays "wss://relay1.com,wss://relay2.com"

# Test your bot
docker run --rm -v $(pwd)/bots:/app/bots nostrbots run-bot --bot my-bot --dry-run --verbose
```

### Bot Structure

```
bots/
├── hello-world/             # Test bot
│   ├── config.json          # Bot configuration
│   ├── generate-content.php # Content generator
│   └── output/              # Generated articles
└── daily-office/            # Catholic Daily Office bot
    ├── config.json
    ├── generate-content.php
    └── templates/
```

## Jenkins CI/CD

Set up automated bot execution with local ORLY relay:

```bash
# Basic Jenkins setup
./scripts/setup-local-jenkins.sh

# Full setup with pipeline + ORLY relay
./scripts/setup-local-jenkins.sh --build-nostrbots --setup-pipeline
```

This will:
- Install and configure Jenkins
- Build Nostrbots Docker image
- Install ORLY relay from [next.orly.dev](https://github.com/mleku/next.orly.dev)
- Configure ORLY with the same key as your bot
- Set up complete CI/CD pipeline
- Create comprehensive test scripts

Then visit: http://localhost:8080 (admin/admin)

### Complete Pipeline Test

Test the entire system with one command:

```bash
./test-complete-pipeline.sh
```

This tests:
- ORLY relay startup and configuration
- Hello World bot publishing to local relay
- End-to-end workflow verification

## Catholic Daily Office Bot

The Daily Office bot demonstrates a real-world application, publishing Catholic liturgical content twice daily.

### Configuration

```json
{
  "name": "Daily Office Bot",
  "schedule": ["06:00", "18:00"],
  "relays": [
    "wss://thecitadel.nostr1.com",
    "wss://orly-relay.imwald.eu"
  ],
  "content_kind": "30023"
}
```

### How It Works

1. **Liturgical Calendar**: Tracks Catholic seasons (Advent, Christmas, Lent, Easter, Ordinary Time)
2. **Time-Based Content**: Generates different content for morning (6am) vs evening (6pm) prayers
3. **Dynamic Content**: Includes daily psalms, scripture readings, and intercessions
4. **Seasonal Adaptation**: Content varies based on liturgical season and color

### Content Structure

**Morning Prayer (6am UTC)**:
- Opening prayer and Gloria
- Psalm of the day
- Scripture reading
- Intercessions
- Closing prayer and blessing

**Evening Prayer (6pm UTC)**:
- Opening prayer and Gloria
- Psalm of thanksgiving
- Scripture reading
- Magnificat (Mary's song)
- Intercessions
- Closing prayer and blessing

### Example Output

```asciidoc
= Morning Prayer - Monday, January 15, 2024
author: Daily Office Bot
relays: daily-office-relays
summary: Catholic Daily Office - Morning Prayer for Monday, January 15, 2024
liturgical_season: Ordinary Time
liturgical_color: Green
prayer_time: 6:00 AM UTC

**Monday of the Second Week in Ordinary Time**
*Liturgical Season: Ordinary Time*
*Liturgical Color: Green*

== Opening Prayer

O God, come to my assistance.
O Lord, make haste to help me.

Glory to the Father, and to the Son, and to the Holy Spirit,
as it was in the beginning, is now, and will be for ever. Amen.

== Psalm of the Day

Psalm 95: Come, let us sing to the Lord; let us shout for joy to the Rock of our salvation.

== Scripture Reading

Matthew 5:14-16: 'You are the light of the world. A city built on a hill cannot be hid.'

== Intercessions

Let us pray to the Lord, who is our light and our salvation:

- For the Church, that she may be a beacon of hope in the world
- For all who are suffering, that they may find comfort and strength
- For our families and communities, that we may grow in love and unity
- For peace in our world, that all conflicts may be resolved through justice

== Closing Prayer

Almighty and eternal God,
you have brought us safely to this new day.
Preserve us now by your mighty power,
that we may not fall into sin,
nor be overcome by adversity;
and in all we do,
direct us to the fulfilling of your purpose;
through Jesus Christ our Lord. Amen.

== Blessing

May the Lord bless us, protect us from all evil,
and bring us to everlasting life. Amen.
```

## Command Line Usage

```bash
# Basic publishing
php nostrbots.php publish document.adoc

# With options
php nostrbots.php publish document.adoc --content-level 3 --content-kind 30023 --dry-run --verbose

# Bot management
docker run --rm -v $(pwd)/bots:/app/bots nostrbots list-bots
docker run --rm -v $(pwd)/bots:/app/bots nostrbots run-bot --bot daily-office --dry-run
```

## Document Format

Include metadata in your document header:

```asciidoc
= Document Title
author: Your Name
relays: favorite-relays
summary: Brief description
type: article

Your content here...

== Chapter 1
Chapter content...
```

## Testing

```bash
# Run test suite
php run-tests.php

# Test Hello World bot
./test-hello-world.sh
```

## License

MIT License - see [LICENSE](./LICENSE) file for details.

## Contact

This is brought to you by [Silberengel](https://jumble.imwald.eu/users/npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z).

Check me out on [GitHub](https://github.com/Silberengel) and [GitWorkshop](https://gitworkshop.dev/silberengel@gitcitadel.com).

You can zap me some Bitcoin on Lightning: silberengel@minibits.cash