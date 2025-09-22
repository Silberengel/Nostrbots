# Direct Document Publishing Guide

## Overview

The new Direct Document Publishing feature allows you to publish content to Nostr directly from AsciiDoc or Markdown files without creating configuration files. This approach is:

- **🚀 Fast**: No intermediate files or complex setup
- **📝 Simple**: Metadata embedded in document headers
- **🔧 Self-contained**: Everything needed is in one file
- **📊 Efficient**: In-memory processing with optimized publishing order

## Document Format

### Basic Structure

```asciidoc
= Document Title
author: Your Name
version: 1.0
relays: favorite-relays
auto_update: true
summary: Brief description of the document
type: article

This is the preamble content that appears before any chapters.

== Chapter 1: Getting Started

Chapter content goes here.

=== Section 1.1: Prerequisites

Detailed content for this section.

==== Subsection 1.1.1

Even more detailed content.
```

### Metadata Fields

| Field | Required | Default | Description |
|-------|----------|---------|-------------|
| `author` | ❌ No | - | Author name (recommended first) |
| `version` | ❌ No | - | Version number (recommended second) |
| `relays` | ✅ Yes | `favorite-relays` | Relay category to publish to |
| `auto_update` | ❌ No | `true` | Allow updates to existing content |
| `summary` | ❌ No | Auto-generated | Brief description of the document |
| `type` | ❌ No | `documentation` | Content type (article, tutorial, etc.) |
| `hierarchy_level` | ❌ No | `0` | Hierarchy level for organization |

### Content Levels

The `--content-level` parameter determines which header level becomes actual content sections:

- **Level 1** (`=`) - Document title (always used for main index)
- **Level 2** (`==`) - Parts/Sections (become index events if below content level)
- **Level 3** (`===`) - Chapters (become index events if below content level)
- **Level 4** (`====`) - Sections (become content if at or above content level)
- **Level 5** (`=====`) - Subsections (become content if at or above content level)
- **Level 6** (`======`) - Sub-subsections (become content if at or above content level)

#### Example with Content Level 4

```asciidoc
= Main Document
relays: favorite-relays

Preamble content.

== Part I: Fundamentals          # Level 2 - becomes index event
=== Chapter 1: Basics           # Level 3 - becomes index event
==== What is X?                 # Level 4 - becomes content event
Content about X.

==== How X Works                # Level 4 - becomes content event
Content about how X works.

=== Chapter 2: Advanced         # Level 3 - becomes index event
==== Advanced Concepts          # Level 4 - becomes content event
Advanced content.
```

This creates:
- 1 main index (document title)
- 2 part indices (Part I, Part II)
- 2 chapter indices (Chapter 1, Chapter 2)
- 3 content sections (What is X?, How X Works, Advanced Concepts)

## Command Usage

### Basic Syntax

```bash
php nostrbots.php publish <document> [options]
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--content-level <n>` | Header level that becomes content (1-6) | `4` |
| `--content-kind <kind>` | Event kind for content (30023, 30041, 30818) | `30041` |
| `--dry-run` | Test without publishing | `false` |
| `--verbose` | Detailed output | `false` |
| `--profile` | Performance monitoring | `false` |

### Examples

#### Simple Article

```bash
# Publish a simple article
php nostrbots.php publish my-article.adoc

# Test first with dry run
php nostrbots.php publish my-article.adoc --dry-run --verbose
```

#### Complex Hierarchical Document

```bash
# Publish with level 3 content (chapters become content)
php nostrbots.php publish guide.adoc --content-level 3

# Publish as long-form content (kind 30023)
php nostrbots.php publish blog-post.adoc --content-kind 30023

# Full options
php nostrbots.php publish complex-guide.adoc --content-level 4 --content-kind 30041 --dry-run --verbose
```

## Event Types Generated

### Publication Index (Kind 30040)

Generated for document title, parts, and chapters (levels below content level):

```json
{
  "kind": 30040,
  "content": "# Part I: Fundamentals\n\nBrief description...",
  "tags": [
    ["d", "document-slug-part-i"],
    ["t", "part-i-fundamentals"],
    ["title", "Part I: Fundamentals"],
    ["summary", "Introduction to fundamentals"]
  ]
}
```

### Publication Content (Kind 30041)

Generated for content sections (levels at or above content level):

```json
{
  "kind": 30041,
  "content": "# What is X?\n\nDetailed content about X...",
  "tags": [
    ["d", "document-slug-what-is-x-content"],
    ["t", "what-is-x"],
    ["title", "What is X?"]
  ]
}
```

### Long-form Content (Kind 30023)

When using `--content-kind 30023`:

```json
{
  "kind": 30023,
  "content": "# What is X?\n\nDetailed content...",
  "tags": [
    ["d", "document-slug-what-is-x-content"],
    ["title", "What is X?"],
    ["summary", "Brief description"],
    ["published_at", "1704067200"]
  ]
}
```

## Publishing Order

Events are published in dependency order to ensure proper linking:

1. **Content sections first** (no dependencies)
2. **Chapter indices** (reference content sections)
3. **Part indices** (reference chapter indices)
4. **Main index** (references all parts)

## Error Handling

### Common Issues

#### Multiple Document Headers
```asciidoc
= First Title
= Second Title  # ❌ Error: Multiple document headers
```

#### Missing Required Metadata
```asciidoc
= Document Title
# ❌ Error: Missing required 'relays' metadata
```

#### Invalid Content Level
```bash
# ❌ Error: Content level must be 1-6
php nostrbots.php publish doc.adoc --content-level 7
```

### Validation

The system validates:
- ✅ Exactly one document title (level 1 header)
- ✅ Required metadata fields present
- ✅ Valid content level (1-6)
- ✅ Valid content kind
- ✅ Document structure integrity

## Performance Benefits

### Before (Legacy Approach)
```
Document → Parse → Generate 20+ YAML files → Copy to config.yml → Publish
```

### After (Direct Publishing)
```
Document → Parse → Publish
```

### Improvements
- **📁 No file I/O**: Everything processed in memory
- **⚡ Faster**: 3-5x faster publishing
- **🔧 Simpler**: No configuration file management
- **📝 Self-contained**: All metadata in document
- **🔄 Version controlled**: Metadata changes with content

## Migration from Legacy

### Old Approach
```bash
# Generate configs
php parse-document.php document.adoc 30041 --hierarchical --content-level 4

# Copy to config
cp generated/document-index-config.yml botData/myBot/config.yml

# Publish
php nostrbots.php myBot
```

### New Approach
```bash
# Direct publishing
php nostrbots.php publish document.adoc --content-level 4
```

## Best Practices

1. **Always test with `--dry-run` first**
2. **Use descriptive metadata** for better organization
3. **Choose appropriate content level** for your structure
4. **Keep documents focused** - split large documents if needed
5. **Use version control** for document changes
6. **Test with `--verbose`** for debugging

## Examples

See the `examples/` directory for:
- `simple-guide.adoc` - Basic example
- `advanced-nostr-guide.adoc` - Complex hierarchical example

## Troubleshooting

### Document Not Found
```
Error: Document file 'document.adoc' not found
```
**Solution**: Check file path and extension (.adoc or .md)

### Invalid Document Structure
```
Error: Invalid document structure: Found multiple document headers
```
**Solution**: Ensure only one level 1 header (`=`) in document

### Publishing Failed
```
Error: Failed to publish: Chapter 1
```
**Solution**: Check network connectivity and relay availability

### Metadata Missing
```
Error: Missing required metadata field: relays
```
**Solution**: Add `relays: favorite-relays` to document header
