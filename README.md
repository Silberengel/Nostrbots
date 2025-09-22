# Nostrbots

A modern, extensible PHP framework for publishing various types of Nostr events with automation support.

> **🎉 Version 2.0** - Complete rewrite with modular architecture and full NIP support!

## Features

- ✅ **Extensible Event System**: Support for multiple Nostr event kinds with easy extensibility
- ✅ **NIP-23 Long-form Content** (kind 30023): Articles and blog posts with Markdown support
- ✅ **NIP Curated Publications** (kinds 30040/30041): Hierarchical content collections with flexible ordering and mixed content types
- ✅ **NIP-54 Wiki Articles** (kind 30818): Collaborative wiki content with Asciidoc and wikilinks
- ✅ **Content Replacement**: Reuse d-tags to replace/update existing articles
- ✅ **Flexible Configuration**: YAML-based configuration with content file loading
- ✅ **Relay Management**: Smart relay testing and failover with category-based selection
- ✅ **Key Management**: Secure private key handling with validation
- ✅ **Jenkins Integration**: Ready-to-use pipeline examples for automation
- ✅ **Comprehensive Validation**: Configuration validation with detailed error reporting
- ✅ **Rich Output**: Beautiful CLI interface with execution summaries and viewing links

## Supported Event Kinds

| Kind  | Name | Description | NIP |
|-------|------|-------------|-----|
| 30023 | Long-form Content | Articles and blog posts in Markdown | NIP-23 |
| 30040 | Publication Index | Table of contents for curated publications | NKBIP-01 |
| 30041 | Publication Content | Sections/chapters for curated publications | NKBIP-01 |
| 30818 | Wiki Article | Collaborative wiki articles with Asciidoc | NIP-54i |

*More event kinds can be easily added through the extensible architecture.*

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Generate Keys

```bash
php generate-keys.php --env-var NOSTR_BOT_KEY1
```

### 3. Set Environment Variable

You can use either hex or bech32 format:

```bash
# Hex format (recommended for scripts)
export NOSTR_BOT_KEY1=your_hex_private_key_here

# Bech32 format (human-readable)
export NOSTR_BOT_KEY1=nsec1your_bech32_private_key_here
```

### 4. Run a Bot

```bash
# Validate configuration without publishing
php nostrbots.php longFormExample --dry-run

# Publish an article
php nostrbots.php longFormExample

# Verbose output
php nostrbots.php longFormExample --verbose
```

## Configuration

Each bot is configured through a `config.yml` file in its folder under `botData/`. Here's a basic example:

```yaml
# Bot metadata
bot_name: "My Article Bot"
bot_description: "Publishes articles about Nostr"

# Event kind
event_kind: 30023

# Identity
npub:
  environment_variable: "NOSTR_BOT_KEY1"
  public_key: "npub1..."  # Can also use hex format

# Content
title: "My Article Title"
summary: "A brief description"
topics: ["nostr", "article"]

# Load content from file
content_files:
  markdown: "botData/myBot/article.md"

# Relay selection
relays: "favorite-relays"  # or "all" or specific URL
```

## Event Kind Examples

### Long-form Content (30023)

Perfect for articles, blog posts, and documentation:

```yaml
event_kind: 30023
title: "Getting Started with Nostr"
content_files:
  markdown: "path/to/article.md"
create_notification: true  # Creates kind 1111 notification
```

### Publication Index (30040)

Creates a table of contents for organized content with support for all addressable event kinds:

```yaml
event_kind: 30040
title: "The Complete Nostr Guide"
auto_update: true
type: "documentation"
content_references:
  - kind: 30023    # Long-form article
    pubkey: "npub1..."
    d_tag: "introduction-article"
    order: 1
  - kind: 30041    # Publication section
    pubkey: "npub1..."
    d_tag: "chapter-1-basics"
    order: 2
  - kind: 30818    # Wiki article
    pubkey: "npub1..."
    d_tag: "protocol-reference"
    order: 3
  - kind: 30040    # Nested index
    pubkey: "npub1..."
    d_tag: "advanced-topics"
    order: 4
```

#### Hierarchical Publications

Create nested publication structures:

```yaml
# Parent index
event_kind: 30040
title: "Programming Masterclass"
hierarchy_level: 0  # Root level

# Child index
event_kind: 30040
title: "Advanced Techniques"
hierarchy_level: 1
parent_index:
  d_tag: "programming-masterclass"
  pubkey: "npub1..."
```

#### Index Management

Append content to existing indices:

```yaml
event_kind: 30040
title: "Updated Guide"
index_management:
  mode: "append"
  existing_index: "my-guide-d-tag"
  insert_position: "after"  # first, last, after, before
  reference_d_tag: "chapter-3"  # Insert after this item
content_references:
  - kind: 30023
    d_tag: "new-chapter"
    # ... new content to add
```

### Publication Content (30041)

Individual sections/chapters:

```yaml
event_kind: 30041
title: "Chapter 1: Introduction"
content_files:
  content: "path/to/chapter1.md"
wikilinks:
  - term: "relay"
    definition: "Server that stores and forwards messages"
```

### Wiki Articles (30818)

Collaborative wiki content with Asciidoc:

```yaml
event_kind: 30818
title: "Nostr Protocol"
static_d_tag: true      # No timestamp in d-tag
normalize_d_tag: true   # Apply NIP-54 normalization
content_files:
  asciidoc: "path/to/wiki-article.adoc"
```

### Replacing Existing Content

Update any existing article by reusing its d-tag:

```yaml
event_kind: 30023
title: "Updated Article Title"
reuse_d_tag: "original-article-d-tag-here"
content_files:
  markdown: "path/to/updated-content.md"
```

## Relay Configuration

Edit `src/relays.yml` to configure relay categories:

```yaml
favorite-relays:
  - wss://thecitadel.nostr1.com
  - wss://theforest.nostr1.com

more-relays:
  - wss://nostr.wine
  - wss://nostr.land
```

## Jenkins Integration

1. Set up your private key as a Jenkins secret text credential
2. Create a pipeline using the provided Jenkinsfile examples
3. Configure build triggers (cron, webhooks, etc.)

Example Jenkinsfile:

```groovy
pipeline {
    agent any
    environment {
        NOSTR_BOT_KEY1 = credentials('NOSTR_BOT_KEY1')
    }
    stages {
        stage('Install') {
            steps {
                sh 'composer install'
            }
        }
        stage('Publish') {
            steps {
                sh 'php nostrbots.php myBot'
            }
        }
    }
}
```

## Architecture

### Event Kind System

The framework uses a modular event kind system:

- `EventKindInterface`: Contract for all event kinds
- `AbstractEventKind`: Base class with common functionality
- `EventKindRegistry`: Manages and instantiates event handlers

### Bot Framework

- `BotInterface`: Contract for bot implementations
- `NostrBot`: Main bot implementation
- `BotResult`: Rich result object with execution details

### Utilities

- `RelayManager`: Handles relay discovery, testing, and selection
- `KeyManager`: Secure key management and validation

## Extending Nostrbots

### Adding New Event Kinds

1. Create a new class implementing `EventKindInterface`
2. Extend `AbstractEventKind` for common functionality
3. Register your event kind in the registry
4. Create example configurations

```php
class MyEventKind extends AbstractEventKind {
    public function getKind(): int { return 12345; }
    public function getName(): string { return 'My Event'; }
    // ... implement other methods
}

// Register it
EventKindRegistry::register(12345, MyEventKind::class);
```

## Development

### Requirements

- PHP 8.1+
- Composer
- YAML extension (`php-yaml`)
- Optional: `nak` CLI tool for naddr generation

### Running Tests

```bash
# Validate configuration
php nostrbots.php myBot --dry-run

# Test relay connectivity
php -r "require 'src/bootstrap.php'; $rm = new Nostrbots\Utils\RelayManager(); var_dump($rm->testRelay('wss://thecitadel.nostr1.com'));"
```

## Migration from v1

The v2 architecture is completely rewritten. To migrate:

1. Update your bot configurations to the new YAML format
2. Move content to separate files (recommended)
3. Update your command-line calls to use `nostrbots.php`
4. Regenerate keys using the new key generator if needed

## Contributing

Contributions are welcome! Please:

1. Follow PSR-4 autoloading standards
2. Add tests for new event kinds
3. Update documentation
4. Ensure backward compatibility where possible

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Support

- **GitHub**: [Issues and discussions](https://github.com/SilberWitch/Nostrbots)
- **Nostr**: npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z
- **Email**: support@gitcitadel.eu

## Roadmap

- [ ] Additional event kinds (kind 1, kind 7, etc.)
- [ ] Web interface for bot management
- [ ] Database storage for bot history
- [ ] Multi-language content support
- [ ] Advanced scheduling features
- [ ] Plugin system for custom functionality

---

*Built with ❤️ for the Nostr community*
