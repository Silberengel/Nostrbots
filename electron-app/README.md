# Nostrbots Desktop

A beautiful desktop application for converting documents to Nostr publications using the Nostrbots document parser.

## Features

- 🎨 **Beautiful GUI** - Modern, intuitive interface
- 📄 **Drag & Drop** - Easy file selection
- 👀 **Live Preview** - See your document content before parsing
- ⚙️ **Flexible Configuration** - Choose content types and publication modes
- 📁 **File Management** - View and open generated files
- 🚀 **Simple & Hierarchical** - Support for both standalone articles and complex publications

## Installation

### Prerequisites

- Node.js 16+ and npm
- PHP 8.1+ with the Nostrbots project in the parent directory

### Setup

1. Install dependencies:
```bash
cd electron-app
npm install
```

2. Make sure the Nostrbots PHP project is in the parent directory:
```
Nostrbots/
├── electron-app/          # This Electron app
├── parse-document.php     # PHP parser script
├── src/                   # PHP source code
└── ...
```

## Running the Application

### Development Mode
```bash
npm run dev
```

### Production Mode
```bash
npm start
```

## Building

### Build for Current Platform
```bash
npm run build
```

### Create Distributables
```bash
npm run dist
```

## Usage

1. **Select Document** - Click "Browse" to select an Asciidoc or Markdown file
2. **Choose Content Type** - Select from Long-form (30023), Publication (30041), or Wiki (30818)
3. **Select Mode**:
   - **Simple Standalone Article** - Creates a single article with all content
   - **Hierarchical Publication** - Creates complex structure with chapters/sections
4. **Parse** - Click "Parse Document" to generate the Nostr publication files
5. **View Results** - See generated files and open them directly

## Supported Document Formats

- **Asciidoc** (.adoc, .asciidoc)
- **Markdown** (.md, .markdown)

## Content Types

- **Long-form Article (30023)** - Perfect for blog posts and articles
- **Publication Content (30041)** - Sections and chapters for publications
- **Wiki Article (30818)** - Collaborative wiki content

## Architecture

- **Main Process** - Handles file operations and PHP execution
- **Renderer Process** - UI and user interactions
- **Preload Script** - Secure IPC communication
- **PHP Backend** - Uses the existing Nostrbots document parser

## Development

The app uses Electron with a clean separation between main and renderer processes for security.

### File Structure
```
electron-app/
├── main.js          # Main process
├── preload.js       # Preload script
├── index.html       # UI
├── styles.css       # Styling
├── renderer.js      # Frontend logic
└── package.json     # Dependencies
```

## Troubleshooting

### PHP Not Found
Make sure PHP is installed and available in your PATH.

### Parse Errors
Check that the Nostrbots project is properly set up in the parent directory.

### File Permissions
Ensure the app has permission to read input files and write to output directories.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

MIT License - see the main Nostrbots project for details.
