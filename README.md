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
content_level: 0              # Hierarchy level (optional, default: 0)

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

The `--content-level` parameter determines the publication structure:

- **Level 0 (default)**: Flat article - single content event, no indexes
- **Level 1+**: Hierarchical publication - multiple content events with index events (30040)

For content-level > 0, the `--content-kind` parameter determines the content event type:
- **30041 (default)**: Publication content (book-style or blog-style)
- **30023**: Long-form content (magazine-style) 
- **30818**: Wiki articles (documentation-style)

Examples:
```bash
# Flat article (default)
php nostrbots.php publish article.adoc
php nostrbots.php publish article.md

# Hierarchical publication with book-style content
php nostrbots.php publish book.adoc --content-level 2

# Hierarchical publication with magazine-style content (30023 requires content-level > 0)
php nostrbots.php publish magazine.adoc --content-level 2 --content-kind 30023

# Hierarchical publication with documentation-style content
php nostrbots.php publish docs.adoc --content-level 3 --content-kind 30818

# Invalid examples (will throw errors):
# php nostrbots.php publish article.md --content-level 2  # Markdown cannot have content-level
# php nostrbots.php publish article.md --content-kind 30023  # Markdown always uses 30023 automatically
# php nostrbots.php publish article.md --content-kind 30041  # 30041 requires AsciiDoc source
# php nostrbots.php publish article.md --content-kind 30818  # 30818 requires AsciiDoc source
# php nostrbots.php publish article.adoc --content-kind 30023  # 30023 requires content-level > 0
```

### Event Kind Requirements

Different event kinds have specific content format requirements:

#### 30023 - Long-form Content (Markdown)
- **Content Format**: Markdown (converted from AsciiDoc source)
- **Source Format**: AsciiDoc only (`.adoc` files)
- **Use Case**: Articles, tutorials, CMS systems
- **Structure**: Flat article (1 content event, 0 indexes)
- **Conversion**: Automatically converts AsciiDoc syntax to GitHub Markdown
- **Example**:
```bash
php nostrbots.php publish article.adoc --content-kind 30023
```

#### 30040 - Publication Index
- **Content Format**: No content (metadata only)
- **Use Case**: Table of contents for hierarchical publications
- **Structure**: Part of publication system (typically used with 30041)
- **Auto-generated**: Created automatically based on document structure

#### 30041 - Publication Content
- **Content Format**: AsciiDoc only
- **Source Format**: AsciiDoc only (`.adoc` files)
- **Use Case**: Individual sections/chapters of publications OR flat articles (called "notes")
- **Structure**: 
  - **Flat article**: 1 content event, 0 indexes (when used alone)
  - **Publication**: Multiple content events with indexes (when used with 30040)
- **Default**: Used when no specific content kind is specified for AsciiDoc files

#### 30818 - Wiki Article
- **Content Format**: AsciiDoc only
- **Source Format**: AsciiDoc only (`.adoc` files)
- **Use Case**: Collaborative wiki pages with wikilinks
- **Structure**: Flat article (1 content event, 0 indexes)
- **Example**:
```bash
php nostrbots.php publish wiki-article.adoc --content-kind 30818
```

### Content Format Detection

Nostrbots automatically detects content format based on file extension:
- `.md` files → Markdown content
- `.adoc` files → AsciiDoc content

The appropriate content format is automatically passed to the event kind handler.

### Format and Content Level Constraints

- **Markdown files (`.md`)**: Always flat articles (content-level 0) with 30023 (Long-form Content), no additional parameters allowed
- **AsciiDoc files (`.adoc`)**: Can use any content-level (0-6) with any content-kind
- **30023 (Long-form Content)**: Always Markdown format (from .md files or converted from .adoc files)
- **30041 (Publication Content)**: Always AsciiDoc format
- **30818 (Wiki Article)**: Always AsciiDoc format

### AsciiDoc to Markdown Conversion

When using 30023 with AsciiDoc source files, the content is automatically converted to GitHub Markdown format:

- Headers: `= Title` → `# Title`
- Bold: `**text**` → `**text**`
- Italic: `__text__` → `*text*`
- Monospace: `+text+` → `` `text` ``
- Images: `image:file.png[alt]` → `![alt](file.png)`
- Links: `[text](url)` → `[text](url)`
- Lists: `- item` → `- item`
- Code blocks: `[source,lang]` → ` ```lang`
- Admonitions: `[NOTE]` → `> **Note:**`

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
- [Relay Selection Guide](docs/RELAY_SELECTION.md) - Relay configuration and selection

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Support

- **GitHub**: [Issues and discussions](https://github.com/SilberWitch/Nostrbots)
- **Nostr**: npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z
- **Email**: support@gitcitadel.eu

---

*Built with ❤️ for the Nostr community*