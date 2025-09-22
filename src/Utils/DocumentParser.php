<?php

namespace Nostrbots\Utils;

/**
 * Document Parser for converting structured documents into Nostr publication hierarchies
 * 
 * Parses Asciidoc (.adoc) and Markdown (.md) files and generates hierarchical
 * structures for direct publishing to Nostr.
 */
class DocumentParser
{
    private string $documentPath;
    private string $documentContent;
    private string $format;
    private int $contentLevel;
    private string $contentKind;
    private array $sections = [];
    private ?string $preamble = null;
    private string $documentTitle = '';
    private string $baseSlug = '';

    /**
     * Parse a document and return hierarchical structure for direct publishing
     * 
     * @param string $documentPath Path to the document file
     * @param int $contentLevel Header level that becomes content sections (1-6)
     * @param string $contentKind Kind of content to generate ('30023', '30041', '30818', 'longform', 'publication', 'wiki')
     * @return array Hierarchical structure ready for publishing
     */
    public function parseDocumentForDirectPublishing(string $documentPath, int $contentLevel, string $contentKind): array
    {
        $this->validateInputs($documentPath, $contentLevel);
        $this->initializeParser($documentPath, $contentLevel, $contentKind);
        $this->loadDocumentContent();
        $this->parseDocumentStructure();
        
        // Build hierarchical structure for direct publishing
        return $this->buildHierarchicalStructure();
    }

    /**
     * Validate input parameters
     */
    private function validateInputs(string $documentPath, int $contentLevel): void
    {
        if ($contentLevel < 1 || $contentLevel > 6) {
            throw new \InvalidArgumentException("Content level must be between 1 and 6, got: {$contentLevel}");
        }

        if (!file_exists($documentPath)) {
            throw new \InvalidArgumentException("Document file not found: {$documentPath}");
        }

        $extension = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['adoc', 'md'])) {
            throw new \InvalidArgumentException("Unsupported file format: {$extension}. Supported formats: adoc, md");
        }
    }

    /**
     * Initialize parser with document parameters
     */
    private function initializeParser(string $documentPath, int $contentLevel, string $contentKind): void
    {
        $this->documentPath = $documentPath;
        $this->contentLevel = $contentLevel;
        $this->contentKind = $this->normalizeContentKind($contentKind);
        $this->format = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION)) === 'adoc' ? 'asciidoc' : 'markdown';
        $this->sections = [];
        $this->preamble = null;
        $this->documentTitle = '';
        $this->baseSlug = '';
    }

    /**
     * Load document content from file
     */
    private function loadDocumentContent(): void
    {
        $this->documentContent = file_get_contents($this->documentPath);
        if ($this->documentContent === false) {
            throw new \RuntimeException("Failed to read document file: {$this->documentPath}");
        }
    }

    /**
     * Parse the document structure
     */
    private function parseDocumentStructure(): void
    {
        $lines = explode("\n", $this->documentContent);
        $documentHeaderCount = 0;
        $currentSection = null;
        $sectionContent = [];

        foreach ($lines as $lineNum => $line) {
            $lineNum++; // 1-based line numbers
            
            if ($this->isDocumentTitle($line)) {
                $documentHeaderCount++;
                if ($documentHeaderCount > 1) {
                    throw new \InvalidArgumentException("Invalid document structure: Found multiple document headers (level 1 headers). AsciiDoc documents can only have one document title. Found at least 2: '{$this->documentTitle}' and '{$this->extractHeaderText($line)}'");
                }
                $this->documentTitle = $this->extractHeaderText($line);
                $this->baseSlug = $this->generateSlug($this->documentTitle);
                
                // Save any preamble content
                if (!empty($sectionContent)) {
                    $this->preamble = trim(implode("\n", $sectionContent));
                }
                $sectionContent = [];
                continue;
            }

            if ($this->isHeader($line)) {
                $this->processCurrentSection($currentSection, $sectionContent);
                
                $headerLevel = $this->getHeaderLevel($line);
                $headerText = $this->extractHeaderText($line);
                $slug = $this->generateSlug($headerText);
                
                $currentSection = [
                    'title' => $headerText,
                    'slug' => $slug,
                    'level' => $headerLevel,
                    'content' => '',
                    'parent_slug' => $this->findParentSlug($headerLevel)
                ];
                
                $sectionContent = [];
                continue;
            }

            $sectionContent[] = $line;
        }

        // Process the last section
        $this->processCurrentSection($currentSection, $sectionContent);

        // Validate document structure
        if (empty($this->documentTitle)) {
            throw new \InvalidArgumentException("Invalid document structure: Document must have exactly one document title (level 1 header). Found 0 document headers.");
        }
    }

    /**
     * Process current section and add to sections array
     */
    private function processCurrentSection(?array $currentSection, array $sectionContent): void
    {
        if ($currentSection !== null) {
            $currentSection['content'] = trim(implode("\n", $sectionContent));
            $this->sections[] = $currentSection;
        }
    }

    /**
     * Build hierarchical structure optimized for direct publishing
     */
    private function buildHierarchicalStructure(): array
    {
        $metadata = $this->extractDocumentMetadata();
        
        $structure = [
            'document_title' => $this->documentTitle,
            'base_slug' => $this->baseSlug,
            'content_level' => $this->contentLevel,
            'content_kind' => $this->contentKind,
            'preamble' => $this->preamble,
            'metadata' => $metadata,
            'publish_order' => [],
            'content_sections' => [],
            'index_sections' => [],
            'main_index' => null
        ];

        $this->organizeSectionsByType($structure);
        $this->buildMainIndex($structure);
        $this->buildPublishOrder($structure);

        return $structure;
    }

    /**
     * Organize sections into content and index sections
     */
    private function organizeSectionsByType(array &$structure): void
    {
        foreach ($this->sections as $section) {
            $sectionData = [
                'title' => $section['title'],
                'slug' => $section['slug'],
                'level' => $section['level'],
                'content' => $section['content'],
                'parent_slug' => $section['parent_slug'],
                'children' => []
            ];

            if ($section['level'] >= $this->contentLevel) {
                // Content section - publish first
                $sectionData['event_kind'] = $this->contentKind;
                $sectionData['d_tag'] = $section['slug'] . '-content';
                $structure['content_sections'][] = $sectionData;
            } else {
                // Index section - publish after content
                $sectionData['event_kind'] = 30040;
                $sectionData['d_tag'] = $section['slug'];
                $structure['index_sections'][] = $sectionData;
            }
        }
    }

    /**
     * Build main index configuration
     */
    private function buildMainIndex(array &$structure): void
    {
        $structure['main_index'] = [
            'title' => $this->documentTitle,
            'slug' => $this->baseSlug,
            'event_kind' => 30040,
            'd_tag' => $this->baseSlug,
            'content_references' => $this->buildContentReferences($structure)
        ];
    }

    /**
     * Build publish order (content first, then indices, then main index)
     */
    private function buildPublishOrder(array &$structure): void
    {
        $structure['publish_order'] = array_merge(
            $structure['content_sections'],
            $structure['index_sections'],
            [$structure['main_index']]
        );
    }

    /**
     * Build content references for the main index
     */
    private function buildContentReferences(array $structure): array
    {
        $references = [];
        $order = 0;

        // Add preamble if exists
        if ($structure['preamble']) {
            $references[] = [
                'kind' => $structure['content_kind'],
                'd_tag' => $structure['base_slug'] . '-preamble-content',
                'relay' => 'wss://thecitadel.nostr1.com',
                'order' => $order++
            ];
        }

        // Add top-level sections (level 2)
        foreach ($structure['index_sections'] as $section) {
            if ($section['level'] === 2) {
                $references[] = [
                    'kind' => 30040,
                    'd_tag' => $section['d_tag'],
                    'relay' => 'wss://thecitadel.nostr1.com',
                    'order' => $order++
                ];
            }
        }

        return $references;
    }

    /**
     * Extract metadata from document header (key-value pairs after title)
     */
    private function extractDocumentMetadata(): array
    {
        $metadata = [];
        $lines = explode("\n", $this->documentContent);
        $foundTitle = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Check if we've found the document title
            if (!$foundTitle && $this->isDocumentTitle($line)) {
                $foundTitle = true;
                continue;
            }
            
            // If we haven't found the title yet, skip
            if (!$foundTitle) {
                continue;
            }
            
            // Stop at first header after title (== or deeper)
            if ($this->isHeader($line) && !$this->isDocumentTitle($line)) {
                break;
            }
            
            // Parse AsciiDoc attributes (format: :key: value) or key-value pairs (format: key: value)
            if (preg_match('/^:?([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.+)$/', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);
                
                $metadata = $this->processMetadataField($metadata, $key, $value);
            }
        }
        
        return $this->applyDefaultMetadata($metadata);
    }

    /**
     * Process individual metadata field
     */
    private function processMetadataField(array $metadata, string $key, string $value): array
    {
        switch ($key) {
            case 'author':
                $metadata['author'] = $value;
                break;
            case 'version':
                $metadata['version'] = $value;
                break;
            case 'relays':
                $metadata['relays'] = $value;
                break;
            case 'auto_update':
                $metadata['auto_update'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
            case 'summary':
                $metadata['summary'] = $value;
                break;
            case 'type':
                $metadata['type'] = $value;
                break;
            case 'hierarchy_level':
                $metadata['hierarchy_level'] = (int)$value;
                break;
            default:
                // Store any custom metadata
                $metadata[$key] = $value;
                break;
        }
        
        return $metadata;
    }

    /**
     * Apply default values for required metadata
     */
    private function applyDefaultMetadata(array $metadata): array
    {
        $metadata['relays'] = $metadata['relays'] ?? 'favorite-relays';
        $metadata['auto_update'] = $metadata['auto_update'] ?? true;
        $metadata['summary'] = $metadata['summary'] ?? 'Generated from document: ' . $this->documentTitle;
        $metadata['type'] = $metadata['type'] ?? 'documentation';
        $metadata['hierarchy_level'] = $metadata['hierarchy_level'] ?? 0;
        
        return $metadata;
    }

    /**
     * Check if line is a document title (level 1 header)
     */
    private function isDocumentTitle(string $line): bool
    {
        return $this->format === 'asciidoc' 
            ? preg_match('/^=\s+(.+)$/', $line)
            : preg_match('/^#\s+(.+)$/', $line);
    }

    /**
     * Check if line is a header
     */
    private function isHeader(string $line): bool
    {
        return $this->format === 'asciidoc'
            ? preg_match('/^={1,6}\s+(.+)$/', $line)
            : preg_match('/^#{1,6}\s+(.+)$/', $line);
    }

    /**
     * Get header level from line
     */
    private function getHeaderLevel(string $line): int
    {
        if ($this->format === 'asciidoc') {
            preg_match('/^(={1,6})\s+(.+)$/', $line, $matches);
            return strlen($matches[1]);
        } else {
            preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches);
            return strlen($matches[1]);
        }
    }

    /**
     * Extract header text from line
     */
    private function extractHeaderText(string $line): string
    {
        if ($this->format === 'asciidoc') {
            preg_match('/^={1,6}\s+(.+)$/', $line, $matches);
            return trim($matches[1] ?? '');
        } else {
            preg_match('/^#{1,6}\s+(.+)$/', $line, $matches);
            return trim($matches[1] ?? '');
        }
    }

    /**
     * Generate slug from title
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Find parent slug for hierarchical structure
     */
    private function findParentSlug(int $level): ?string
    {
        for ($i = count($this->sections) - 1; $i >= 0; $i--) {
            if ($this->sections[$i]['level'] < $level) {
                return $this->sections[$i]['slug'];
            }
        }
        return null;
    }

    /**
     * Normalize content kind to numeric string
     */
    private function normalizeContentKind(string $contentKind): string
    {
        $kindMap = [
            'longform' => '30023',
            'publication' => '30041',
            'wiki' => '30818',
            '30023' => '30023',
            '30041' => '30041',
            '30818' => '30818'
        ];

        return $kindMap[$contentKind] ?? $contentKind;
    }

    /**
     * Reset the parser state for a new document
     */
    public function reset(): void
    {
        $this->documentPath = '';
        $this->documentContent = '';
        $this->format = '';
        $this->contentLevel = 0;
        $this->contentKind = '';
        $this->sections = [];
        $this->preamble = null;
        $this->documentTitle = '';
        $this->baseSlug = '';
    }

    /**
     * Get document title
     */
    public function getDocumentTitle(): string
    {
        return $this->documentTitle;
    }

    /**
     * Get base slug
     */
    public function getBaseSlug(): string
    {
        return $this->baseSlug;
    }

    /**
     * Get sections
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Get preamble content
     */
    public function getPreamble(): ?string
    {
        return $this->preamble;
    }
}
