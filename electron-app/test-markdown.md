# Markdown Test Document

This is a **Markdown** document to test the rendering capabilities of the Nostrbots Desktop app.

## Features

The app now supports:

- **Markdown rendering** with proper formatting
- **Asciidoc rendering** with full syntax support
- **Tabbed preview** - switch between raw and rendered views
- **Syntax highlighting** for code blocks

## Code Example

Here's a code block with syntax highlighting:

```javascript
function parseDocument(content, type) {
    if (type === 'markdown') {
        return marked.parse(content);
    } else if (type === 'asciidoc') {
        return asciidoctor.convert(content);
    }
}
```

## Lists and Formatting

### Unordered List
- Item 1
- Item 2
- Item 3

### Ordered List
1. First item
2. Second item
3. Third item

## Blockquote

> This is a blockquote example. It should be styled nicely with a left border and italic text.

## Table

| Feature | Status | Notes |
|---------|--------|-------|
| Markdown | ✅ | Full support |
| Asciidoc | ✅ | Full support |
| Preview | ✅ | Tabbed interface |

## Conclusion

The enhanced preview makes it much easier to see how your document will look when converted to Nostr publications!
