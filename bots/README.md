# Nostrbots Bot Configuration

This directory contains configuration and content for individual Nostr bots. Each bot has its own subdirectory with a standardized structure.

## Bot Directory Structure

```
bots/
├── README.md                    # This file
├── daily-office/               # Example: Daily Office bot
│   ├── config.json            # Bot configuration
│   ├── generate-content.php   # Content generation script
│   ├── templates/             # Content templates
│   │   ├── morning-office.adoc
│   │   └── evening-office.adoc
│   └── output/                # Generated content (auto-created)
│       └── daily-office-2024-01-15-06:00-morning.adoc
└── your-bot/                  # Your custom bot
    ├── config.json
    ├── generate-content.php
    └── output/
```

## Configuration Schema

Each bot must have a `config.json` file with the following structure:

```json
{
  "name": "Bot Display Name",
  "description": "Brief description of what this bot does",
  "version": "1.0.0",
  "author": "Bot Author",
  "schedule": ["06:00", "18:00"],
  "relays": [
    "wss://relay1.example.com",
    "wss://relay2.example.com"
  ],
  "content_kind": "30023",
  "content_level": 0,
  "auto_update": true,
  "settings": {
    "timezone": "UTC",
    "language": "en"
  },
  "templates": {
    "template_name": "templates/template-file.adoc"
  },
  "output": {
    "directory": "output",
    "filename_pattern": "bot-name-{date}-{time}.adoc"
  }
}
```

### Required Fields

- **name**: Display name for the bot
- **relays**: Array of Nostr relay URLs
- **schedule**: Array of times (HH:MM format) when the bot should run

### Optional Fields

- **description**: Human-readable description
- **version**: Bot version
- **author**: Bot author name
- **content_kind**: Nostr event kind (30023, 30041, 30818)
- **content_level**: Content hierarchy level (0-6)
- **auto_update**: Whether to allow content updates
- **settings**: Bot-specific settings
- **templates**: Template file mappings
- **output**: Output configuration

## Content Generation

Each bot should have a `generate-content.php` script that:

1. Reads the bot configuration
2. Generates appropriate content based on the current time/date
3. Saves the content to the output directory
4. Returns appropriate exit codes

### Example Generator Structure

```php
<?php
require_once __DIR__ . '/../../src/bootstrap.php';

class YourBotGenerator
{
    private string $botDir;
    private array $config;
    
    public function __construct(string $botDir)
    {
        $this->botDir = $botDir;
        $this->config = $this->loadConfig();
    }
    
    public function generateContent(): void
    {
        // Your content generation logic here
        $content = $this->buildContent();
        $this->saveContent($content);
    }
    
    private function loadConfig(): array
    {
        // Load and validate config.json
    }
    
    private function buildContent(): string
    {
        // Build your AsciiDoc/Markdown content
    }
    
    private function saveContent(string $content): void
    {
        // Save to output directory
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $botDir = $argv[1] ?? __DIR__;
        $generator = new YourBotGenerator($botDir);
        $generator->generateContent();
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
```

## Scheduling

Bots are scheduled using the `schedule` field in their configuration:

- **Time Format**: HH:MM (24-hour format)
- **Timezone**: All times are in UTC
- **Multiple Times**: Array of times for multiple daily runs

### Examples

```json
{
  "schedule": ["06:00"],           // Once daily at 6am UTC
  "schedule": ["06:00", "18:00"],  // Twice daily at 6am and 6pm UTC
  "schedule": ["00:00", "06:00", "12:00", "18:00"]  // Four times daily
}
```

## Content Types

### Event Kinds

- **30023**: Long-form Content (Markdown format)
- **30041**: Publication Content (AsciiDoc format)
- **30818**: Wiki Article (AsciiDoc format)

### Content Levels

- **0**: Flat article (single content event)
- **1+**: Hierarchical publication (multiple content events with indexes)

## Relay Configuration

Relays can be specified as:

1. **Direct URLs**: `"wss://relay.example.com"`
2. **Relay Categories**: `"favorite-relays"` (from `src/relays.yml`)
3. **Mixed Arrays**: Combination of URLs and categories

## Testing Your Bot

### Local Testing

```bash
# Test bot configuration
docker run --rm -v $(pwd)/bots:/app/bots nostrbots list-bots

# Test content generation (dry run)
docker run --rm -v $(pwd)/bots:/app/bots nostrbots run-bot --bot your-bot --dry-run --verbose

# Test actual publishing
docker run --rm -v $(pwd)/bots:/app/bots -e NOSTR_BOT_KEY=your-key nostrbots run-bot --bot your-bot --verbose
```

### Jenkins Testing

The Jenkins pipeline automatically:

1. Validates bot configurations
2. Runs dry-run tests for all bots
3. Executes scheduled bots at appropriate times
4. Provides detailed logging and error reporting

## Best Practices

1. **Error Handling**: Always include proper error handling in your generator scripts
2. **Logging**: Use the ErrorHandler class for consistent logging
3. **Validation**: Validate all inputs and configurations
4. **Templates**: Use templates for consistent content formatting
5. **Testing**: Always test with `--dry-run` before publishing
6. **Documentation**: Document your bot's purpose and configuration

## Example Bots

### Daily Office Bot

Publishes Catholic Daily Office readings at 6am and 6pm UTC.

**Features:**
- Liturgical calendar integration
- Morning and evening prayer templates
- Seasonal content adaptation
- Multiple relay publishing

**Configuration:**
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

## Creating Your Own Bot

1. Create a new directory under `bots/`
2. Add a `config.json` file with your bot's configuration
3. Create a `generate-content.php` script
4. Add any templates to a `templates/` subdirectory
5. Test your bot with `--dry-run`
6. Deploy and schedule through Jenkins

For more examples and advanced configurations, see the `daily-office/` bot implementation.
