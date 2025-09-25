#!/bin/bash

# Nostrbots Bot Setup Script
# Creates a new bot with the proper directory structure

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to show usage
show_usage() {
    echo "Nostrbots Bot Setup Script"
    echo "========================="
    echo ""
    echo "Usage: $0 <bot-name> [options]"
    echo ""
    echo "Arguments:"
    echo "  bot-name              Name of the bot (will be used as directory name)"
    echo ""
    echo "Options:"
    echo "  --schedule TIMES      Comma-separated list of times (e.g., '06:00,18:00')"
    echo "  --relays RELAYS       Comma-separated list of relay URLs"
    echo "  --content-kind KIND   Content kind (30023, 30041, 30818)"
    echo "  --author AUTHOR       Bot author name"
    echo "  --description DESC    Bot description"
    echo "  --help               Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 my-bot"
    echo "  $0 daily-news --schedule '06:00,12:00,18:00' --relays 'wss://relay1.com,wss://relay2.com'"
    echo "  $0 tech-blog --content-kind 30023 --author 'Tech Writer'"
}

# Default values
BOT_NAME=""
SCHEDULE="06:00,18:00"
RELAYS="wss://thecitadel.nostr1.com,wss://orly-relay.imwald.eu"
CONTENT_KIND="30023"
AUTHOR="Bot Author"
DESCRIPTION="Automated content bot"

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --schedule)
            SCHEDULE="$2"
            shift 2
            ;;
        --relays)
            RELAYS="$2"
            shift 2
            ;;
        --content-kind)
            CONTENT_KIND="$2"
            shift 2
            ;;
        --author)
            AUTHOR="$2"
            shift 2
            ;;
        --description)
            DESCRIPTION="$2"
            shift 2
            ;;
        --help|-h)
            show_usage
            exit 0
            ;;
        -*)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
        *)
            if [ -z "$BOT_NAME" ]; then
                BOT_NAME="$1"
            else
                print_error "Multiple bot names specified"
                show_usage
                exit 1
            fi
            shift
            ;;
    esac
done

# Validate bot name
if [ -z "$BOT_NAME" ]; then
    print_error "Bot name is required"
    show_usage
    exit 1
fi

# Validate bot name format (alphanumeric and hyphens only)
if [[ ! "$BOT_NAME" =~ ^[a-zA-Z0-9-]+$ ]]; then
    print_error "Bot name must contain only alphanumeric characters and hyphens"
    exit 1
fi

# Check if bots directory exists
if [ ! -d "bots" ]; then
    print_error "bots directory not found. Run this script from the project root."
    exit 1
fi

# Check if bot already exists
BOT_DIR="bots/$BOT_NAME"
if [ -d "$BOT_DIR" ]; then
    print_error "Bot '$BOT_NAME' already exists in $BOT_DIR"
    exit 1
fi

print_status "Creating bot: $BOT_NAME"

# Create bot directory structure
print_status "Creating directory structure..."
mkdir -p "$BOT_DIR/templates"
mkdir -p "$BOT_DIR/output"

# Convert schedule to JSON array format
SCHEDULE_JSON=$(echo "$SCHEDULE" | sed 's/,/","/g' | sed 's/^/"/' | sed 's/$/"/')

# Convert relays to JSON array format
RELAYS_JSON=$(echo "$RELAYS" | sed 's/,/","/g' | sed 's/^/"/' | sed 's/$/"/')

# Create config.json
print_status "Creating configuration file..."
cat > "$BOT_DIR/config.json" << EOF
{
  "name": "$BOT_NAME",
  "description": "$DESCRIPTION",
  "version": "1.0.0",
  "author": "$AUTHOR",
  "schedule": [$SCHEDULE_JSON],
  "relays": [$RELAYS_JSON],
  "content_kind": "$CONTENT_KIND",
  "content_level": 0,
  "auto_update": true,
  "settings": {
    "timezone": "UTC",
    "language": "en"
  },
  "templates": {
    "default": "templates/default.adoc"
  },
  "output": {
    "directory": "output",
    "filename_pattern": "$BOT_NAME-{date}-{time}.adoc"
  }
}
EOF

# Create default template
print_status "Creating default template..."
cat > "$BOT_DIR/templates/default.adoc" << EOF
= {title}
author: $AUTHOR
version: 1.0
relays: $BOT_NAME-relays
auto_update: true
summary: {summary}
type: {type}

{content}
EOF

# Create content generator script
print_status "Creating content generator script..."
cat > "$BOT_DIR/generate-content.php" << 'EOF'
<?php

/**
 * Content Generator for {BOT_NAME}
 * 
 * Generates content based on current time and date
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Nostrbots\Utils\ErrorHandler;

class {BOT_NAME}Generator
{
    private string $botDir;
    private array $config;
    private ErrorHandler $errorHandler;
    
    public function __construct(string $botDir)
    {
        $this->botDir = $botDir;
        $this->config = $this->loadConfig();
        $this->errorHandler = new ErrorHandler(true);
    }
    
    private function loadConfig(): array
    {
        $configFile = $this->botDir . '/config.json';
        if (!file_exists($configFile)) {
            throw new \Exception("Configuration file not found: $configFile");
        }
        
        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in configuration file: " . json_last_error_msg());
        }
        
        return $config;
    }
    
    public function generateContent(): void
    {
        $currentTime = new DateTime('now', new DateTimeZone('UTC'));
        $date = $currentTime->format('Y-m-d');
        $time = $currentTime->format('H:i');
        
        echo "📅 Generating content for $date at $time UTC\n";
        
        // Generate your content here
        $content = $this->buildContent($currentTime);
        
        // Save to output directory
        $this->saveContent($content, $date, $time);
        
        echo "✅ Content generated successfully\n";
    }
    
    private function buildContent(DateTime $dateTime): string
    {
        $date = $dateTime->format('l, F j, Y');
        $time = $dateTime->format('H:i');
        
        // TODO: Implement your content generation logic here
        $content = "= {BOT_NAME} Content - $date\n";
        $content .= "author: {$this->config['author']}\n";
        $content .= "version: 1.0\n";
        $content .= "relays: {$this->config['name']}-relays\n";
        $content .= "auto_update: true\n";
        $content .= "summary: Automated content from {BOT_NAME} bot\n";
        $content .= "type: article\n\n";
        
        $content .= "**Generated on $date at $time UTC**\n\n";
        
        $content .= "== Content\n\n";
        $content .= "This is a placeholder for your bot's content.\n\n";
        $content .= "Replace this with your actual content generation logic.\n\n";
        $content .= "== Information\n\n";
        $content .= "- Bot: {$this->config['name']}\n";
        $content .= "- Author: {$this->config['author']}\n";
        $content .= "- Generated: $dateTime->format('c')\n";
        
        return $content;
    }
    
    private function saveContent(string $content, string $date, string $time): void
    {
        $outputDir = $this->botDir . '/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $filename = "{$this->config['name']}-{$date}-{$time}.adoc";
        $filepath = $outputDir . '/' . $filename;
        
        if (file_put_contents($filepath, $content) === false) {
            throw new \Exception("Failed to save content to: $filepath");
        }
        
        echo "📄 Content saved to: $filename\n";
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $botDir = $argv[1] ?? __DIR__;
        $generator = new {BOT_NAME}Generator($botDir);
        $generator->generateContent();
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
EOF

# Replace placeholder in the generator script
sed -i "s/{BOT_NAME}/$BOT_NAME/g" "$BOT_DIR/generate-content.php"

# Create README for the bot
print_status "Creating bot README..."
cat > "$BOT_DIR/README.md" << EOF
# $BOT_NAME Bot

$DESCRIPTION

## Configuration

- **Author**: $AUTHOR
- **Schedule**: $SCHEDULE (UTC)
- **Relays**: $RELAYS
- **Content Kind**: $CONTENT_KIND

## Usage

### Local Testing

\`\`\`bash
# Test content generation (dry run)
docker run --rm -v \$(pwd)/bots:/app/bots nostrbots run-bot --bot $BOT_NAME --dry-run --verbose

# Test actual publishing
docker run --rm -v \$(pwd)/bots:/app/bots -e NOSTR_BOT_KEY=your-key nostrbots run-bot --bot $BOT_NAME --verbose
\`\`\`

### Content Generation

The bot uses \`generate-content.php\` to create content. Modify this script to implement your specific content generation logic.

### Templates

Templates are stored in the \`templates/\` directory. The default template is \`templates/default.adoc\`.

### Output

Generated content is saved to the \`output/\` directory with the pattern: \`$BOT_NAME-{date}-{time}.adoc\`

## Customization

1. Edit \`config.json\` to modify bot settings
2. Update \`generate-content.php\` to implement your content logic
3. Modify templates in the \`templates/\` directory
4. Test with \`--dry-run\` before publishing
EOF

print_success "Bot '$BOT_NAME' created successfully!"
echo ""
print_status "Bot directory: $BOT_DIR"
print_status "Configuration: $BOT_DIR/config.json"
print_status "Generator script: $BOT_DIR/generate-content.php"
print_status "Templates: $BOT_DIR/templates/"
print_status "Output directory: $BOT_DIR/output/"
echo ""
print_status "Next steps:"
echo "1. Edit $BOT_DIR/generate-content.php to implement your content logic"
echo "2. Customize templates in $BOT_DIR/templates/"
echo "3. Test with: docker run --rm -v \$(pwd)/bots:/app/bots nostrbots run-bot --bot $BOT_NAME --dry-run --verbose"
echo "4. Set up your Nostr key: export NOSTR_BOT_KEY=your_private_key"
echo "5. Run the bot: docker run --rm -v \$(pwd)/bots:/app/bots -e NOSTR_BOT_KEY=\$NOSTR_BOT_KEY nostrbots run-bot --bot $BOT_NAME"
