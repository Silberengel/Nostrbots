# Nostrbots v2.0 Feature Overview

This document provides a comprehensive overview of all features available in Nostrbots v2.0.

## 🎯 Core Philosophy

Nostrbots is designed as a **publishing CLI with automation capabilities**. It prioritizes:

- **Publishing Focus**: Optimized for content creation and distribution
- **Automation Ready**: Built for Jenkins, cron, and other automation systems
- **Content Management**: Support for replacing and updating existing content
- **Collaborative Features**: Wiki articles and publication systems

## 📝 Supported Content Types

### 1. Long-form Articles (NIP-23, kind 30023)
**Perfect for**: Blog posts, articles, documentation, tutorials

**Features**:
- Markdown content support
- Automatic notification generation (kind 1111)
- NIP-27 references to other events/profiles
- Rich metadata (title, summary, image, topics)
- Custom tagging system

**Example Use Cases**:
- Company blog posts
- Technical documentation
- Personal articles
- Tutorial series

### 2. Curated Publications (kinds 30040/30041)
**Perfect for**: Books, magazines, course materials, organized content series

**Features**:
- **Publication Index (30040)**: Table of contents with metadata
- **Publication Content (30041)**: Individual chapters/sections
- Auto-update configuration
- Version tracking support
- Fork/derivative work support
- Multiple publication types (book, magazine, documentation, etc.)

**Example Use Cases**:
- Digital books
- Course materials
- Documentation projects
- Magazine issues
- Academic papers

### 3. Wiki Articles (NIP-54, kind 30818)
**Perfect for**: Collaborative knowledge bases, encyclopedias, reference materials

**Features**:
- Asciidoc content with rich formatting
- Wikilink support for cross-references
- NIP-54 compliant d-tag normalization
- Fork and defer mechanisms
- Collaborative editing workflows
- Static d-tag generation (no timestamps)

**Example Use Cases**:
- Technical wikis
- Knowledge bases
- Reference documentation
- Collaborative encyclopedias

## 🔄 Content Management Features

### Article Replacement System
Replace existing content while maintaining the same identifier:

```yaml
# Replace an existing article
reuse_d_tag: "original-article-d-tag"
title: "Updated Article Title"
```

**Benefits**:
- Maintain consistent URLs/links
- Update content without losing references
- Version control for published content
- Seamless content management workflows

### D-tag Configuration Options

1. **Auto-generated with timestamp** (default)
   ```yaml
   # Results in: "my-article-1640995200"
   title: "My Article"
   ```

2. **Custom d-tag**
   ```yaml
   d-tag: "my-custom-identifier"
   ```

3. **Reuse existing d-tag**
   ```yaml
   reuse_d_tag: "existing-article-d-tag"
   ```

4. **Static d-tag** (no timestamp)
   ```yaml
   title: "Wiki Article"
   static_d_tag: true
   # Results in: "wiki-article"
   ```

5. **Normalized d-tag** (NIP-54 for wikis)
   ```yaml
   title: "Wiki Article!"
   normalize_d_tag: true
   # Results in: "wiki-article"
   ```

## 🔧 Technical Architecture

### Event Kind System
- **Interface-based design**: All event kinds implement `EventKindInterface`
- **Abstract base class**: Common functionality in `AbstractEventKind`
- **Registry pattern**: Central registration and management via `EventKindRegistry`
- **Extensible**: Easy to add new event kinds

### Bot Framework
- **Clean interfaces**: `BotInterface` and `BotResult` for consistent behavior
- **Rich results**: Detailed execution information with metadata
- **Error handling**: Comprehensive validation and error reporting
- **Configuration system**: YAML-based with content file loading

### Utility Systems
- **RelayManager**: Smart relay testing, failover, and category-based selection
- **KeyManager**: Secure key validation and environment variable management
- **Bootstrap system**: PSR-4 autoloading and dependency management

## 🚀 Automation Features

### Jenkins Integration
- **Pipeline examples**: Ready-to-use Jenkinsfiles for each content type
- **Parameter support**: Flexible builds with runtime parameters
- **Secret management**: Secure private key handling
- **Build artifacts**: Viewing links and execution summaries

### Command Line Interface
- **Dry-run mode**: Validate configurations without publishing
- **Verbose output**: Detailed execution information
- **Help system**: Built-in documentation and examples
- **Exit codes**: Proper return codes for automation

### Configuration Management
- **YAML-based**: Human-readable configuration files
- **Content files**: Separate content from configuration
- **Validation**: Comprehensive pre-flight checks
- **Flexibility**: Support for various relay and key configurations

## 🔐 Security Features

### Key Management
- **Environment variables**: Secure private key storage via NOSTR_BOT_KEY
- **Validation**: Automatic key-pair validation
- **Single key approach**: Simplified key management with one environment variable
- **Key generation**: Built-in key generation utility

### Relay Security
- **Relay testing**: Automatic connectivity and functionality testing
- **Fallback systems**: Multiple relay support with failover
- **Category-based selection**: Organize relays by purpose or trust level

## 📊 Output and Reporting

### Rich CLI Output
- **Progress indicators**: Visual feedback during execution
- **Color coding**: Success/warning/error indication
- **Execution summaries**: Detailed results with timing information
- **Viewing links**: Direct links to published content

### Structured Results
- **BotResult objects**: Programmatic access to execution results
- **Metadata tracking**: Additional information about published content
- **Error details**: Comprehensive error reporting with context
- **Performance metrics**: Execution timing and statistics

## 🔗 Integration Capabilities

### Content Linking
- **NIP-27 references**: Link to other Nostr events and profiles
- **Wikilinks**: Cross-reference wiki articles
- **Publication relationships**: Link content within publication systems
- **Fork/defer systems**: Collaborative content workflows

### External Integration
- **Jenkins**: Complete CI/CD pipeline support
- **Cron jobs**: Simple scheduled publishing
- **Git workflows**: Version control integration
- **Content management systems**: API-friendly design

## 🎛️ Configuration Examples

### Basic Article
```yaml
event_kind: 30023
title: "My Article"
content_files:
  markdown: "content/article.md"
```

### Publication System
```yaml
# Index
event_kind: 30040
title: "My Book"
auto_update: true
content_references:
  - kind: 30041
    pubkey: "npub1..."
    d_tag: "chapter-1"

# Chapter
event_kind: 30041
title: "Chapter 1"
content_files:
  content: "chapters/chapter1.md"
```

### Wiki Article
```yaml
event_kind: 30818
title: "Technical Topic"
static_d_tag: true
normalize_d_tag: true
content_files:
  asciidoc: "wiki/topic.adoc"
```

### Content Replacement
```yaml
event_kind: 30023
title: "Updated Article"
reuse_d_tag: "original-article-1640995200"
content_files:
  markdown: "updated-content.md"
```

## 🚀 Getting Started

1. **Install dependencies**: `composer install`
2. **Generate key**: `php manage-keys.php generate`
3. **Configure environment**: `export NOSTR_BOT_KEY=your_hex_key`
4. **Test configuration**: `php nostrbots.php exampleBot --dry-run`
5. **Publish content**: `php nostrbots.php exampleBot`

## 🔮 Future Roadmap

- Additional event kinds (kind 1, reactions, etc.)
- Web interface for content management
- Database storage for content history
- Advanced scheduling and automation
- Plugin system for custom functionality
- Multi-language content support

---

*Nostrbots v2.0 - The complete Nostr publishing solution*
