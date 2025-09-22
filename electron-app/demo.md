# Nostrbots Desktop Demo

## What is Nostrbots Desktop?

Nostrbots Desktop is a beautiful GUI application that makes it easy to convert documents into Nostr publications. It's built on top of the powerful Nostrbots document parser.

## Key Features

### 🎨 Beautiful Interface
- Modern, intuitive design
- Responsive layout
- Real-time feedback

### 📄 Document Support
- **Asciidoc** (.adoc, .asciidoc)
- **Markdown** (.md, .markdown)
- Live preview of document content

### ⚙️ Flexible Configuration
- **Content Types**:
  - Long-form Article (30023)
  - Publication Content (30041)
  - Wiki Article (30818)
- **Publication Modes**:
  - Simple Standalone Article (default)
  - Hierarchical Publication (with --hierarchical flag)

### 📁 File Management
- Browse and select input files
- Choose output directory
- View generated files
- Open files directly from the app

## How to Use

1. **Install**: Run `./install.sh` in the electron-app directory
2. **Launch**: Run `npm start` or `npm run dev`
3. **Select Document**: Click "Browse" to choose your .adoc or .md file
4. **Configure**: Choose content type and publication mode
5. **Parse**: Click "Parse Document" to generate Nostr files
6. **View Results**: See generated files and open them

## Example Workflow

### Simple Article
1. Select `test-article.adoc`
2. Choose "Long-form Article (30023)"
3. Select "Simple Standalone Article" mode
4. Click "Parse Document"
5. View the generated 30023 event files

### Hierarchical Publication
1. Select a complex document with multiple headers
2. Choose "Publication Content (30041)"
3. Select "Hierarchical Publication" mode
4. Set content level (e.g., 3 for sections)
5. Click "Parse Document"
6. View the generated index and content files

## Technical Details

- **Frontend**: Electron with HTML/CSS/JavaScript
- **Backend**: PHP document parser (existing Nostrbots code)
- **Communication**: Secure IPC between processes
- **File Handling**: Native file dialogs and system integration

## Benefits Over CLI

- **User-Friendly**: No command line knowledge required
- **Visual Feedback**: See results immediately
- **File Management**: Easy browsing and opening of files
- **Error Handling**: Clear error messages and notifications
- **Cross-Platform**: Works on Windows, macOS, and Linux

## Perfect For

- **Content Creators** who want to publish to Nostr
- **Non-Technical Users** who prefer GUI over CLI
- **Quick Testing** of different content types
- **Documentation Teams** working with structured content
- **Anyone** who wants to convert documents to Nostr publications

The desktop app makes Nostrbots accessible to everyone, not just developers!
