# Nostrbots

A streamlined PHP tool for publishing content directly to Nostr from AsciiDoc and Markdown documents.

> **🚀 Version 2.0** - Complete rewrite with direct document publishing!

## Features

- 🚀 **Direct Document Publishing**: Publish directly from AsciiDoc/Markdown files - no config files needed!
- ✅ **Self-Contained Documents**: Metadata embedded in document headers
- ✅ **Hierarchical Publications**: Support for complex nested structures
- ✅ **Multiple Event Kinds**: 30023 (Long-form), 30040/30041 (Publications), 30818 (Wiki)
- ✅ **Smart Publishing Order**: Content → indices → main index
- ✅ **Comprehensive Validation**: Document structure validation with detailed error reporting
- ✅ **Rich CLI Interface**: Beautiful output with execution summaries

## Supported Event Kinds

| Kind  | Name | Description | NIP |
|-------|------|-------------|-----|
| 30023 | Long-form Content | Articles and blog posts | NIP-23 |
| 30040 | Publication Index | Table of contents for publications | NKBIP-01 |
| 30041 | Publication Content | Sections/chapters for publications | NKBIP-01 |
| 30818 | Wiki Article | Collaborative wiki articles | NIP-54 |

## Quick Start

### 1. Create Your Document

```bash
cat > my-article.adoc << 'EOF'
= My First Nostr Article
author: Your Name
version: 1.0
relays: favorite-relays
auto_update: true
summary: My first article published to Nostr
type: article

This is the introduction to my article.

== Chapter 1

Chapter content here.

=== Section 1.1

More detailed content.
EOF
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Generate Key

```bash
php manage-keys.php generate
```

### 4. Set Environment Variable

```bash
export NOSTR_BOT_KEY=your_hex_private_key_here
```

### 5. Publish

```bash
# Test first with dry run
php nostrbots.php publish my-article.adoc --dry-run --verbose

# Publish for real
php nostrbots.php publish my-article.adoc --verbose
```

**That's it!** No configuration files, no complex setup - just your content and one command.

## Document Format

Your document should include metadata in the header (after the title):

```asciidoc
= Document Title
author: Your Name               # Author name (optional)
version: 1.0                    # Version number (optional)
relays: favorite-relays          # Relay category (required)
auto_update: true               # Allow updates (optional, default: true)
summary: Brief description      # Short description (optional)
type: article                   # Content type (optional, default: documentation)
hierarchy_level: 0              # Hierarchy level (optional, default: 0)

Your document content starts here...

== Chapter 1
Chapter content...

=== Section 1.1
More content...
```

## Command Options

```bash
# Basic publishing
php nostrbots.php publish document.adoc

# With custom content level (header level that becomes content sections)
php nostrbots.php publish document.adoc --content-level 3

# With custom content kind
php nostrbots.php publish document.adoc --content-kind 30023

# Dry run to test without publishing
php nostrbots.php publish document.adoc --dry-run --verbose

# Full options
php nostrbots.php publish document.adoc --content-level 4 --content-kind 30041 --dry-run --verbose
```

## How It Works

Nostrbots uses a streamlined approach that eliminates configuration files:

1. **Parse Document**: Reads AsciiDoc/Markdown files and extracts metadata from headers
2. **Build Structure**: Creates hierarchical event structure in memory
3. **Publish Events**: Publishes in dependency order (content → indices → main index)

### Document Structure

Documents are organized hierarchically using headers:

```asciidoc
= Main Document Title          # Level 1 - Main index
author: Your Name
version: 1.0
relays: favorite-relays

Preamble content here.

== Part I: Getting Started     # Level 2 - Part index
=== Chapter 1: Basics          # Level 3 - Chapter index
==== Section 1.1: Concepts    # Level 4 - Content section (if content-level=4)
Actual content here.

==== Section 1.2: Examples    # Level 4 - Content section
More content here.
```

### Content Levels

The `--content-level` parameter determines which headers become content sections:

- **Level 4 (default)**: Sections (`====`) become content, chapters and parts become indices
- **Level 3**: Chapters (`===`) become content, parts become indices  
- **Level 2**: Parts (`==`) become content, only main title becomes index

This creates the appropriate mix of content events (30041) and index events (30040).

## Examples

### Simple Article

```asciidoc
= Getting Started with Nostr
author: Nostr Expert
version: 1.0
relays: favorite-relays
summary: A beginner's guide to Nostr

Welcome to Nostr! This guide will help you get started.

== What is Nostr?

Nostr is a simple protocol...

== How to Use Nostr

Here's how to get started...
```

### Hierarchical Guide

```asciidoc
= Complete Nostr Guide
author: Nostr Protocol Team
version: 2.0
relays: favorite-relays
summary: Comprehensive guide to Nostr protocols

This is the most complete guide to Nostr.

== Part I: Fundamentals
=== Chapter 1: Basic Concepts
==== What is Nostr?
Detailed explanation here.

==== Key Concepts
More concepts here.

=== Chapter 2: Getting Started
==== Creating Your First Key
Step-by-step guide.

== Part II: Advanced Topics
=== Chapter 3: Relay Management
==== Choosing Relays
Guide to selecting relays.
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

## Testing

```bash
# Run all tests
php run-tests.php

# Test specific functionality
php src/Tests/DirectDocumentPublisherTest.php

# Test document parsing
php nostrbots.php publish examples/simple-guide.adoc --dry-run --verbose
```

## Development

### Requirements

- PHP 8.1+
- Composer
- YAML extension (`php-yaml`)

### Running Tests

```bash
# Run test suite
php run-tests.php

# Test relay connectivity
php -r "require 'src/bootstrap.php'; \$rm = new Nostrbots\Utils\RelayManager(); var_dump(\$rm->testRelay('wss://thecitadel.nostr1.com'));"
```

## Documentation

- [Complete Direct Publishing Guide](docs/DIRECT_PUBLISHING.md) - Detailed documentation
- [Relay Selection Guide](RELAY_SELECTION.md) - Relay configuration and selection

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Support

- **GitHub**: [Issues and discussions](https://github.com/SilberWitch/Nostrbots)
- **Nostr**: npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z
- **Email**: support@gitcitadel.eu

---

*Built with ❤️ for the Nostr community*