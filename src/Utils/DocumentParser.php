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
    private string $relays = '';
    private array $metadataLines = [];

    /**
     * Parse a document and return hierarchical structure for direct publishing
     * 
     * @param string $documentPath Path to the document file
     * @param int $contentLevel Header level that becomes content sections (1-6)
     * @param string $contentKind Kind of content to generate ('30023', '30041', '30818', 'longform', 'publication', 'wiki')
     * @return array Hierarchical structure ready for publishing
     */
    public function parseDocumentForDirectPublishing(string $documentPath, ?int $contentLevel = null, ?string $contentKind = null): array
    {
        $this->validateInputs($documentPath, $contentLevel, $contentKind);
        $this->initializeParser($documentPath);
        $this->loadDocumentContent();
        $this->parseDocumentStructure();
        
        // Apply priority: command line > file headers > defaults
        $this->applyContentLevelAndKind($contentLevel, $contentKind);
        
        return $this->buildHierarchicalStructure();
    }

    /**
     * Validate input parameters
     */
    private function validateInputs(string $documentPath, ?int $contentLevel, ?string $contentKind): void
    {
        // Validate file exists
        if (!file_exists($documentPath)) {
            throw new \InvalidArgumentException("Document file not found: {$documentPath}");
        }
        
        // Validate file extension
        $extension = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['adoc', 'md'])) {
            throw new \InvalidArgumentException("Unsupported file format: {$extension}. Only .adoc and .md files are supported.");
        }
        
        // Validate content level rangevalidateInputs
        if ($contentLevel !== null && ($contentLevel < 0 || $contentLevel > 6)) {
            throw new \InvalidArgumentException("Content level must be between 0 and 6, got: {$contentLevel}");
        }
        
        // Validate content kind
        if ($contentKind !== null) {
            $validKinds = ['30023', '30041', '30818', 'longform', 'publication', 'wiki'];
            if (!in_array($contentKind, $validKinds)) {
                throw new \InvalidArgumentException("Invalid content kind: {$contentKind}. Valid options are: " . implode(', ', $validKinds));
            }
        }
        
        // Validate Markdown file constraints - no additional parameters allowed
        if ($extension === 'md') {
            if ($contentLevel !== null) {
                throw new \InvalidArgumentException("Markdown files cannot have content-level parameters. They are always flat articles (content-level 0).");
            }
            if ($contentKind !== null) {
                throw new \InvalidArgumentException("Markdown files cannot have content-kind parameters. They always use 30023 (Long-form Content).");
            }
        }
        
        // Validate content kind constraints
        if ($contentKind !== null) {
            $normalizedKind = $this->normalizeContentKind($contentKind);
            if ($normalizedKind === '30023') {
                if ($extension !== 'adoc') {
                    throw new \InvalidArgumentException("30023 (Long-form Content) requires AsciiDoc source files (.adoc). Convert your document to .adoc format. It will be transformed to Markdown for publishing.");
                }
                if ($contentLevel === null || $contentLevel === 0) {
                    throw new \InvalidArgumentException("30023 (Long-form Content) requires content-level > 0. Use --content-level 1 or higher for hierarchical publications.");
                }
            } elseif (in_array($normalizedKind, ['30041', '30818'])) {
                if ($extension !== 'adoc') {
                    throw new \InvalidArgumentException("{$normalizedKind} requires AsciiDoc source files (.adoc). Convert your document to .adoc format.");
                }
            }
        }
    }

    /**
     * Initialize parser with document path
     */
    private function initializeParser(string $documentPath): void
    {
        $this->documentPath = $documentPath;
        $this->format = $this->detectFormat($documentPath);
        $this->sections = [];
        $this->preamble = null;
        $this->documentTitle = '';
        $this->baseSlug = '';
        $this->metadataLines = [];
        // contentLevel and contentKind will be set later in applyContentLevelAndKind
    }

    /**
     * Load document content from file
     */
    private function loadDocumentContent(): void
    {
        $this->documentContent = file_get_contents($this->documentPath);
        if ($this->documentContent === false) {
            throw new \RuntimeException("Failed to read document: {$this->documentPath}");
        }
    }

    /**
     * Parse document structure into sections
     */
    private function parseDocumentStructure(): void
    {
        $lines = explode("\n", $this->documentContent);
        $currentSection = null;
        $sectionContent = [];
        $preambleContent = [];
        $inPreamble = true;
        $foundFirstEmptyLine = false;
        $h1Count = 0; // Count H1 headers to ensure exactly one
        $foundH1 = false; // Track if we've found the H1 header
        $lineNumber = 0; // Track line number for better error messages
            
        foreach ($lines as $line) {
            $lineNumber++;
            $trimmedLine = trim($line);
            
            // Skip empty lines at the beginning
            if (!$foundH1 && empty($trimmedLine)) {
                continue;
            }
            
            if ($this->isDocumentTitle($line)) {
                $h1Count++;
                if ($h1Count === 1) {
                    // First H1 header - this is the document title
                    $this->documentTitle = $this->extractHeaderText($line);
                    $this->baseSlug = $this->generateSlug($this->documentTitle);
                    $foundH1 = true;
                } else {
                    // Multiple H1 headers - this is an error
                    $formatName = $this->format === 'asciidoc' ? 'AsciiDoc' : 'Markdown';
                    $headerFormat = $this->format === 'asciidoc' ? '= Title' : '# Title';
                    throw new \InvalidArgumentException(
                        "Invalid {$formatName} document structure: Document must have exactly one H1 level header ({$headerFormat}). " .
                        "Found {$h1Count} H1 headers. Only the first H1 header is used as the document title for generating d-tags."
                    );
                }
                continue;
            }
            
            // If we encounter non-empty content before finding H1, that's an error
            if (!$foundH1 && !empty($trimmedLine)) {
                $formatName = $this->format === 'asciidoc' ? 'AsciiDoc' : 'Markdown';
                $headerFormat = $this->format === 'asciidoc' ? '= Title' : '# Title';
                throw new \InvalidArgumentException(
                    "Invalid {$formatName} document structure: Document must start with an H1 level header ({$headerFormat}). " .
                    "Found content on line {$lineNumber} before the H1 header: '{$trimmedLine}'"
                );
            }

            // Collect metadata lines after document title until first empty line
            if (!$foundFirstEmptyLine) {
                if (trim($line) === '') {
                    $foundFirstEmptyLine = true;
                } else {
                    // This is a metadata line, collect it for later processing
                    $this->metadataLines[] = $line;
                }
                continue;
            }

            if ($this->isHeader($line)) {
                // Process previous section
                if ($currentSection !== null) {
                    $currentSection['content'] = trim(implode("\n", $sectionContent));
                    $this->sections[] = $currentSection;
                }

                // Start new section
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
                $inPreamble = false;
                continue;
            }

            if ($inPreamble) {
                $preambleContent[] = $line;
            } else {
            $sectionContent[] = $line;
            }
        }

        // Process the last section
        if ($currentSection !== null) {
            $currentSection['content'] = trim(implode("\n", $sectionContent));
            $this->sections[] = $currentSection;
        }

        // Save preamble content
        if (!empty($preambleContent)) {
            $this->preamble = trim(implode("\n", $preambleContent));
        }

        // Validate document structure - ensure exactly one H1 header exists
        if (empty($this->documentTitle)) {
            $formatName = $this->format === 'asciidoc' ? 'AsciiDoc' : 'Markdown';
            $headerFormat = $this->format === 'asciidoc' ? '= Title' : '# Title';
            throw new \InvalidArgumentException(
                "Invalid {$formatName} document structure: Document must have exactly one H1 level header ({$headerFormat}) at the beginning. " .
                "The H1 header is required because it provides the document title and base for generating d-tags for replaceable events. " .
                "Found 0 H1 headers."
            );
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

        $this->createContentEvents($structure);
        $this->createIndexEvents($structure);
        $this->buildPublishOrder($structure);

        return $structure;
    }

    /**
     * Create content events based on content level
     */
    private function createContentEvents(array &$structure): void
    {
        if ($this->contentLevel === 0) {
            // Content level 0: Always flat article, create 1 content event with everything
            $allContent = $this->preamble;
            foreach ($this->sections as $section) {
                $allContent .= "\n\n" . str_repeat('#', $section['level']) . ' ' . $section['title'] . "\n\n";
                $allContent .= $section['content'];
            }
            
        // Convert AsciiDoc to Markdown if needed for 30023 content
        if ($this->contentKind === '30023' && $this->format === 'asciidoc') {
            $allContent = $this->convertAsciiDocToMarkdown($allContent);
        }
            
            $structure['content_sections'][] = [
                'title' => $this->documentTitle,
                'slug' => $this->baseSlug . '-content',
                'level' => 0,
                'content' => trim($allContent),
                'parent_slug' => null,
                'children' => [],
                'event_kind' => $this->contentKind,
                'd_tag' => $this->baseSlug . '-content'
            ];
            return;
        }
        
        if ($this->contentLevel === 1) {
            // Content level 1: Create 1 content event with everything under the main title
            $allContent = $this->preamble;
            foreach ($this->sections as $section) {
                $allContent .= "\n\n" . str_repeat('#', $section['level']) . ' ' . $section['title'] . "\n\n";
                $allContent .= $section['content'];
            }
            
            // Convert AsciiDoc to Markdown if needed for 30023 content
            if ($this->contentKind === '30023' && $this->format === 'asciidoc') {
                $allContent = $this->convertAsciiDocToMarkdown($allContent);
            }
            
            $structure['content_sections'][] = [
                'title' => $this->documentTitle,
                'slug' => $this->baseSlug . '-content',
                'level' => 1,
                'content' => trim($allContent),
                'parent_slug' => null,
                'children' => [],
                'event_kind' => $this->contentKind,
                'd_tag' => $this->baseSlug . '-content'
            ];
            return;
        }
        
        // For content levels 1+, create content events from bottom up
        $processedSections = [];
        
        // Step 1: Create content events for the content level (lowest level)
        foreach ($this->sections as $section) {
            if ($section['level'] === $this->contentLevel) {
                $content = $this->buildSectionContent($section);
                $structure['content_sections'][] = [
                    'title' => $section['title'],
                    'slug' => $section['slug'] . '-content',
                    'level' => $section['level'],
                    'content' => $content,
                    'parent_slug' => $section['parent_slug'],
                    'children' => [],
                    'event_kind' => $this->contentKind, // Can be 30023, 30041, or 30818
                    'd_tag' => $section['slug'] . '-content'
                ];
                $processedSections[] = $section['slug'];
            }
        }
        
        // Step 2: Create content events for higher levels, excluding already processed content
        for ($level = $this->contentLevel - 1; $level >= 1; $level--) {
            foreach ($this->sections as $section) {
                if ($section['level'] === $level) {
                    $hasProcessedSubsections = $this->hasProcessedSubsections($section, $processedSections);
                    
                    if ($hasProcessedSubsections) {
                        // This section has processed subsections, so create content for remaining parts
                        $content = $this->buildRemainingSectionContent($section, $processedSections);
                        // Only create content event if there's actual content remaining
                        if (!empty(trim($content))) {
                            $structure['content_sections'][] = [
                                'title' => $section['title'] . ' - Content',
                                'slug' => $section['slug'] . '-content',
                                'level' => $section['level'],
                                'content' => $content,
                                'parent_slug' => $section['parent_slug'],
                                'children' => [],
                                'event_kind' => $this->contentKind, // Can be 30023, 30041, or 30818
                                'd_tag' => $section['slug'] . '-content'
                            ];
                        }
                    } else {
                        // This section has no processed subsections, so it becomes a content event
                        $content = $this->buildSectionContent($section);
                        // Only create content event if there's actual content
                        if (!empty(trim($content))) {
                            $structure['content_sections'][] = [
                                'title' => $section['title'],
                                'slug' => $section['slug'] . '-content',
                                'level' => $section['level'],
                                'content' => $content,
                                'parent_slug' => $section['parent_slug'],
                                'children' => [],
                                'event_kind' => $this->contentKind, // Can be 30023, 30041, or 30818
                                'd_tag' => $section['slug'] . '-content'
                            ];
                        }
                    }
                    $processedSections[] = $section['slug'];
                }
            }
        }
        
        // Step 3: Create preamble content event (what's left under = level)
        if (!empty($this->preamble)) {
            $structure['content_sections'][] = [
                'title' => $this->documentTitle . ' - Preamble',
                'slug' => $this->baseSlug . '-preamble',
                'level' => 0,
                'content' => $this->preamble,
                'parent_slug' => null,
                'children' => [],
                'event_kind' => $this->contentKind, // Can be 30023, 30041, or 30818
                'd_tag' => $this->baseSlug . '-preamble'
            ];
        }
    }

    /**
     * Create index events based on content level
     */
    private function createIndexEvents(array &$structure): void
    {
        if ($this->contentLevel === 0) {
            // Content level 0: No index events (flat articles)
            return;
        }
        
        if ($this->contentLevel === 1) {
            // Content level 1: Create main index for the document title
            $structure['index_sections'][] = [
                'title' => $this->documentTitle,
                'slug' => $this->baseSlug,
                'level' => 1,
                'content' => '',
                'parent_slug' => null,
                'children' => [],
                'event_kind' => 30040,
                'd_tag' => $this->baseSlug,
                'content_references' => $this->buildMainIndexReferences($structure)
            ];
            
            $structure['main_index'] = $structure['index_sections'][0];
            return;
        }
        
        // Create index events for levels 1 through contentLevel
        for ($level = 1; $level <= $this->contentLevel; $level++) {
            if ($level === 1) {
                // Create main index for document title
                $structure['index_sections'][] = [
                    'title' => $this->documentTitle,
                    'slug' => $this->baseSlug,
                    'level' => 1,
                    'content' => '',
                    'parent_slug' => null,
                    'children' => [],
                    'event_kind' => 30040,
                    'd_tag' => $this->baseSlug,
                    'content_references' => $this->buildMainIndexReferences($structure)
                ];
            } else {
                // Create index events for sections at this level
                foreach ($this->sections as $section) {
                    if ($section['level'] === $level) {
                        $structure['index_sections'][] = [
                'title' => $section['title'],
                'slug' => $section['slug'],
                'level' => $section['level'],
                'content' => $section['content'],
                'parent_slug' => $section['parent_slug'],
                'children' => [],
                            'event_kind' => 30040,
                            'd_tag' => $section['slug'],
                            'content_references' => $this->buildSectionIndexReferences($section, $structure)
                        ];
                    }
                }
            }
        }
        
        // Set the main index (level 1 section)
        foreach ($structure['index_sections'] as $indexSection) {
            if ($indexSection['level'] === 1) {
                $structure['main_index'] = $indexSection;
                break;
            }
        }
    }

    /**
     * Build publish order for events
     */
    private function buildPublishOrder(array &$structure): void
    {
        $publishOrder = [];
        
        // Add content events first
        foreach ($structure['content_sections'] as $contentSection) {
            $publishOrder[] = $contentSection;
        }
        
        // Add index events (main index last)
        foreach ($structure['index_sections'] as $indexSection) {
            if ($indexSection['level'] !== 1) {
                $publishOrder[] = $indexSection;
            }
        }
        
        // Add main index last
        if ($structure['main_index'] !== null) {
            $publishOrder[] = $structure['main_index'];
        }
        
        $structure['publish_order'] = $publishOrder;
    }

    /**
     * Check if a section has any subsections that have been processed
     */
    private function hasProcessedSubsections(array $section, array $processedSections): bool
    {
        foreach ($this->sections as $subsection) {
            if ($subsection['level'] > $section['level'] && 
                $subsection['parent_slug'] === $section['slug'] &&
                in_array($subsection['slug'], $processedSections)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build content for a section including all its subsections
     */
    private function buildSectionContent(array $section): string
    {
        $content = $section['content'];
        
        // Add all subsections
        foreach ($this->sections as $subsection) {
            if ($subsection['level'] > $section['level'] && 
                $subsection['parent_slug'] === $section['slug']) {
                $content .= "\n\n" . str_repeat('#', $subsection['level']) . ' ' . $subsection['title'] . "\n\n";
                $content .= $subsection['content'];
            }
        }
        
        // Convert AsciiDoc to Markdown if needed for 30023 content
        if ($this->contentKind === '30023' && $this->format === 'asciidoc') {
            $content = $this->convertAsciiDocToMarkdown($content);
        }
        
        return trim($content);
    }

    /**
     * Build content for a section excluding already processed subsections
     */
    private function buildRemainingSectionContent(array $section, array $processedSections): string
    {
        $content = $section['content'];
        
        // Add subsections that haven't been processed yet
        foreach ($this->sections as $subsection) {
            if ($subsection['level'] > $section['level'] && 
                $subsection['parent_slug'] === $section['slug'] &&
                !in_array($subsection['slug'], $processedSections)) {
                $content .= "\n\n" . str_repeat('#', $subsection['level']) . ' ' . $subsection['title'] . "\n\n";
                $content .= $subsection['content'];
            }
        }
        
        // Convert AsciiDoc to Markdown if needed for 30023 content
        if ($this->contentKind === '30023' && $this->format === 'asciidoc') {
            $content = $this->convertAsciiDocToMarkdown($content);
        }
        
        return trim($content);
    }

    /**
     * Build references for main index
     */
    private function buildMainIndexReferences(array $structure): array
    {
        $references = [];
        $order = 0;

        if ($this->contentLevel === 1) {
            // For content level 1, only reference the main content
            $references[] = [
                'kind' => (int)$this->contentKind,
                'd_tag' => $this->baseSlug . '-content',
                'order' => $order++
            ];
        } else {
            // For higher content levels, reference preamble and child indexes
            if (!empty($this->preamble)) {
                $references[] = [
                    'kind' => (int)$this->contentKind,
                    'd_tag' => $this->baseSlug . '-preamble',
                    'order' => $order++
                ];
            }
            
            // Add references to child indexes
            foreach ($this->sections as $section) {
                if ($section['level'] === 2) { // Direct children of main index
                    $references[] = [
                        'kind' => 30040,
                        'd_tag' => $section['slug'],
                        'order' => $order++
                    ];
                }
            }
        }

        return $references;
    }

    /**
     * Build content references for a specific section
     */
    private function buildSectionIndexReferences(array $section, array $structure): array
    {
        $references = [];
        $order = 0;

        // Find direct children of this section
        foreach ($this->sections as $childSection) {
            if ($childSection['parent_slug'] === $section['slug']) {
                if ($childSection['level'] >= $this->contentLevel) {
                    // Direct content child
                    $references[] = [
                        'kind' => (int)$this->contentKind,
                        'd_tag' => $childSection['slug'] . '-content',
                        'order' => $order++
                    ];
                } else {
                    // Direct index child
                    $references[] = [
                        'kind' => 30040,
                        'd_tag' => $childSection['slug'],
                        'order' => $order++
                    ];
                }
            }
        }

        return $references;
    }

    /**
     * Apply content level and kind with proper priority: command line > file headers > defaults
     */
    private function applyContentLevelAndKind(?int $commandLineContentLevel, ?string $commandLineContentKind): void
    {
        // Extract metadata from file headers
        $fileMetadata = $this->extractDocumentMetadata();
        
        // Set defaults based on file format
        if ($this->format === 'markdown') {
            $this->contentLevel = 0; // Markdown files are always flat articles
            $this->contentKind = '30023'; // Markdown files always use 30023 (Long-form Content)
        } else {
            $this->contentLevel = 0; // Default: flat article
            $this->contentKind = '30041'; // Default: publication content
        }
        
        // Apply file header overrides (only for AsciiDoc files)
        if ($this->format === 'asciidoc') {
            if (isset($fileMetadata['content_level'])) {
                $this->contentLevel = (int)$fileMetadata['content_level'];
            }
            if (isset($fileMetadata['content_kind'])) {
                $this->contentKind = $this->normalizeContentKind($fileMetadata['content_kind']);
            }
        }
        
        // Apply command line overrides (highest priority, but not for Markdown files)
        if ($this->format === 'asciidoc') {
            if ($commandLineContentLevel !== null) {
                $this->contentLevel = $commandLineContentLevel;
            }
            if ($commandLineContentKind !== null) {
                $this->contentKind = $this->normalizeContentKind($commandLineContentKind);
            }
        }
        
        // Validate constraints
        $this->validateContentLevelAndKindConstraints();
    }
    
    /**
     * Validate content level and kind constraints
     * Note: Most validation is now done upfront in validateInputs()
     */
    private function validateContentLevelAndKindConstraints(): void
    {
        // Additional runtime validation can be added here if needed
        // Most constraints are now validated upfront in validateInputs()
    }

    /**
     * Extract document metadata from content
     */
    private function extractDocumentMetadata(): array
    {
        
        $metadata = [
            'auto_update' => true,
            'summary' => 'Generated from document: ' . $this->documentTitle,
            'type' => 'documentation'
        ];
        
        // Store relay information separately for later use
        $this->relays = 'favorite-relays';
        
        // Parse the collected metadata lines
        $inHeader = true;
        $documentTitle = $this->documentTitle; // Use the already extracted title
        $authorLine = null;
        $revisionLine = null;
        $foundH1 = true; // We already found H1 in parseDocumentStructure
        
        
        // Process the collected metadata lines
        foreach ($this->metadataLines as $line) {
            $originalLine = $line;
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '//') === 0) {
                continue;
            }
            
            // Skip document title lines since we already extracted the title
            if ($this->isDocumentTitle($line)) {
                continue;
            }
            
            // Parse Markdown format metadata (**Key:** Value) - accept any key
            if (preg_match('/^\*\*([^*]+):\*\*\s*(.+)$/', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);
                
                
                // Handle special cases that need to be stored separately (like relays)
                if ($key === 'relays' || $key === 'relay') {
                    $this->relays = $value;
                    continue;
                }
                
                // Handle comma-separated values by splitting them into separate entries
                if (strpos($value, ',') !== false) {
                    $values = array_map('trim', explode(',', $value));
                    $values = array_filter($values, function($v) { return !empty($v); });
                    foreach ($values as $singleValue) {
                        if (!isset($metadata[$key])) {
                            $metadata[$key] = [];
                        }
                        $metadata[$key][] = $singleValue;
                    }
                } else {
                    // Single value
                    if (!isset($metadata[$key])) {
                        $metadata[$key] = [];
                    }
                    $metadata[$key][] = $value;
                }
                continue; // Skip to next line after processing Markdown metadata
            }
            
            // Check for author line (Name <email> or Name)
            if ($documentTitle && !$authorLine && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\s*:/', $line) && !preg_match('/^:/', $line)) {
                $authorLine = $line;
                continue;
            }
            
            // Check for revision line (v1.0, 2024-01-01, etc.)
            if ($authorLine && !$revisionLine && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\s*:/', $line) && !preg_match('/^:/', $line)) {
                $revisionLine = $line;
                continue;
            }
            
            // Parse AsciiDoc attribute entries (:key: value)
            if (preg_match('/^:([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.*)$/', $line, $matches)) {
                $key = strtolower($matches[1]);
                $value = trim($matches[2]);
                $metadata = $this->mapAsciiDocAttribute($key, $value, $metadata);
                continue;
            }
            
            // Parse key-value pairs (key: value)
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.+)$/', $line, $matches)) {
                $key = strtolower($matches[1]);
                $value = trim($matches[2]);
                $metadata = $this->mapAsciiDocAttribute($key, $value, $metadata);
                continue;
            }
        }
        
        // Process author line
        if ($authorLine) {
            $metadata = $this->parseAuthorLine($authorLine, $metadata);
        }
        
        // Process revision line
        if ($revisionLine) {
            $metadata = $this->parseRevisionLine($revisionLine, $metadata);
        }
        
        
        return $metadata;
    }
    
    /**
     * Map AsciiDoc attributes to standard metadata keys
     */
    private function mapAsciiDocAttribute(string $key, string $value, array $metadata): array
    {
        $value = trim($value);
        
        // Handle flexible key mapping - if it's not a known key, store it as-is
        switch ($key) {
            // Content structure
            case 'content_level':
            case 'contentlevel':
                $metadata['content_level'] = $value;
                break;
            case 'content_kind':
            case 'contentkind':
                $metadata['content_kind'] = $value;
                break;
                
            // Author information - handle multiple authors as individual tags
            case 'author':
            case 'authors':
                $authors = array_map('trim', explode(',', $value));
                $authors = array_filter($authors, function($author) { return !empty(trim($author)); });
                foreach ($authors as $author) {
                    $metadata['author'][] = trim($author);
                }
                break;
            case 'email':
            case 'author_email':
                $metadata['email'] = $value;
                break;
            case 'firstname':
            case 'first_name':
                $metadata['firstname'] = $value;
                $metadata = $this->buildAuthorFromParts($metadata);
                break;
            case 'lastname':
            case 'last_name':
                $metadata['lastname'] = $value;
                $metadata = $this->buildAuthorFromParts($metadata);
                break;
            case 'middlename':
            case 'middle_name':
                $metadata['middlename'] = $value;
                $metadata = $this->buildAuthorFromParts($metadata);
                break;
            case 'authorinitials':
            case 'author_initials':
                $metadata['authorinitials'] = $value;
                break;
                
            // Version/revision information
            case 'version':
            case 'revnumber':
            case 'revision':
                $metadata['version'] = $value;
                break;
            case 'revdate':
            case 'date':
            case 'revision_date':
            case 'publication_date':
                $metadata['publication_date'] = $value;
                break;
            case 'revremark':
            case 'revision_remark':
                $metadata['revremark'] = $value;
                break;
                
            // Document description
            case 'description':
            case 'summary':
            case 'abstract':
                $metadata['summary'] = $value;
                break;
                
            // Keywords/tags - create individual t tags
            case 'keywords':
            case 'tags':
            case 't':
            case 'subject':
                // Split by comma and clean up, ensuring proper trimming
                $tags = array_map('trim', explode(',', $value));
                $tags = array_filter($tags, function($tag) { return !empty(trim($tag)); });
                foreach ($tags as $tag) {
                    $metadata['t'][] = trim($tag);
                }
                break;
                
            // Language
            case 'lang':
            case 'language':
                $metadata['l'] = $value;
                break;
                
            // Relays - store separately for later use
            case 'relays':
            case 'relay':
                $this->relays = $value;
                break;
                
            // Update settings
            case 'auto_update':
            case 'autoupdate':
            case 'auto-update':
                $metadata['auto_update'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
                
            // Document type
            case 'type':
            case 'doctype':
            case 'document_type':
                $metadata['type'] = $value;
                break;
                
            // Other common attributes
            case 'title':
                if (!isset($metadata['title'])) {
                    $metadata['title'] = $value;
                }
                break;
            case 'subtitle':
                $metadata['subtitle'] = $value;
                break;
            case 'encoding':
                $metadata['encoding'] = $value;
                break;
            case 'sectanchors':
            case 'section_anchors':
                // Boolean attribute - if present, set to true
                $metadata['sectanchors'] = empty($value) ? true : $value;
                break;
            case 'sectlinks':
            case 'section_links':
                $metadata['sectlinks'] = $value;
                break;
            case 'icons':
                $metadata['icons'] = $value;
                break;
            case 'imagesdir':
            case 'images_dir':
                $metadata['imagesdir'] = $value;
                break;
            case 'source-highlighter':
            case 'source_highlighter':
                $metadata['source-highlighter'] = $value;
                break;
            case 'experimental':
                $metadata['experimental'] = $value;
                break;
            case 'compat-mode':
            case 'compat_mode':
                $metadata['compat-mode'] = $value;
                break;
                
            // Custom attributes (pass through)
            default:
                $metadata[$key] = $value;
                break;
        }
        
        return $metadata;
    }
    
    /**
     * Build author field from name parts
     */
    private function buildAuthorFromParts(array $metadata): array
    {
        $nameParts = [];
        
        if (isset($metadata['firstname'])) {
            $nameParts[] = trim($metadata['firstname']);
        }
        if (isset($metadata['middlename'])) {
            $nameParts[] = trim($metadata['middlename']);
        }
        if (isset($metadata['lastname'])) {
            $nameParts[] = trim($metadata['lastname']);
        }
        
        if (!empty($nameParts)) {
            $metadata['author'][] = implode(' ', array_filter($nameParts));
        }
        
        return $metadata;
    }
    
    /**
     * Parse author line (Name <email> or Name, or multiple authors)
     */
    private function parseAuthorLine(string $authorLine, array $metadata): array
    {
        // Check if there are multiple authors (comma-separated)
        if (strpos($authorLine, ',') !== false) {
            $authors = array_map('trim', explode(',', $authorLine));
            $authors = array_filter($authors, function($author) { return !empty(trim($author)); });
            
            foreach ($authors as $author) {
                $author = trim($author);
                // Handle format: Name <email>
                if (preg_match('/^(.+?)\s*<([^>]+)>$/', $author, $matches)) {
                    $name = trim($matches[1]);
                    $email = trim($matches[2]);
                    
                    $metadata['email'][] = $email;
                    $metadata['author'][] = $name;
                } else {
                    $metadata['author'][] = $author;
                }
            }
        } else {
            // Single author
            // Handle format: Name <email>
            if (preg_match('/^(.+?)\s*<([^>]+)>$/', $authorLine, $matches)) {
                $name = trim($matches[1]);
                $email = trim($matches[2]);
                
                    $metadata['email'] = $email;
                    
                    // Try to extract first/last name
                    $nameParts = explode(' ', $name);
                    if (count($nameParts) >= 2) {
                        $metadata['firstname'] = trim($nameParts[0]);
                        $metadata['lastname'] = trim(end($nameParts));
                        if (count($nameParts) > 2) {
                            $metadata['middlename'] = trim(implode(' ', array_slice($nameParts, 1, -1)));
                        }
                    } else {
                        $metadata['author'][] = $name;
                    }
            } else {
                // Just a name
                $name = trim($authorLine);
                
                // Try to extract first/last name
                $nameParts = explode(' ', $name);
                if (count($nameParts) >= 2) {
                    $metadata['firstname'] = trim($nameParts[0]);
                    $metadata['lastname'] = trim(end($nameParts));
                    if (count($nameParts) > 2) {
                        $metadata['middlename'] = trim(implode(' ', array_slice($nameParts, 1, -1)));
                    }
                } else {
                    $metadata['author'][] = $name;
                }
            }
            
            // Build author from parts if we have name parts
            if (isset($metadata['firstname']) || isset($metadata['lastname'])) {
                $metadata = $this->buildAuthorFromParts($metadata);
            }
        }
        
        return $metadata;
    }
    
    /**
     * Parse revision line (v1.0, 2024-01-01, etc.)
     */
    private function parseRevisionLine(string $revisionLine, array $metadata): array
    {
        $revisionLine = trim($revisionLine);
        
        // Handle format: v1.0, 2024-01-01, Initial version
        if (preg_match('/^(.+?)(?:,\s*(.+?))?(?:,\s*(.+))?$/', $revisionLine, $matches)) {
            $version = trim($matches[1]);
            $date = isset($matches[2]) ? trim($matches[2]) : null;
            $remark = isset($matches[3]) ? trim($matches[3]) : null;
            
            // Clean up version (remove 'v' prefix if present)
            if (preg_match('/^v(.+)$/', $version, $versionMatches)) {
                $version = $versionMatches[1];
            }
            
            $metadata['version'] = $version;
            
            if ($date) {
                $metadata['publication_date'] = $date;
            }
            
            if ($remark) {
                $metadata['revremark'] = $remark;
            }
        } else {
            // Just a version number
            $version = $revisionLine;
            if (preg_match('/^v(.+)$/', $version, $versionMatches)) {
                $version = $versionMatches[1];
            }
            $metadata['version'] = $version;
        }
        
        return $metadata;
    }
    
    /**
     * Get relay information for publishing
     */
    public function getRelays(): string
    {
        return $this->relays;
    }

    /**
     * Detect document format from file extension
     */
    private function detectFormat(string $documentPath): string
    {
        $extension = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));
        return match ($extension) {
            'adoc' => 'asciidoc',
            'md' => 'markdown',
            default => throw new \InvalidArgumentException("Unsupported document format: {$extension}")
        };
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
        $this->metadataLines = [];
    }

    /**
     * Convert AsciiDoc content to GitHub Markdown format
     */
    private function convertAsciiDocToMarkdown(string $content): string
    {
        // Convert AsciiDoc admonitions to Markdown blockquotes (MUST be done before headers)
        $content = preg_replace('/\[NOTE\]\s*\n====\s*\n(.*?)\n====/s', "> **Note:**\n> $1", $content);
        $content = preg_replace('/\[TIP\]\s*\n====\s*\n(.*?)\n====/s', "> **Tip:**\n> $1", $content);
        $content = preg_replace('/\[WARNING\]\s*\n====\s*\n(.*?)\n====/s', "> **Warning:**\n> $1", $content);
        $content = preg_replace('/\[IMPORTANT\]\s*\n====\s*\n(.*?)\n====/s', "> **Important:**\n> $1", $content);
        
        // Convert AsciiDoc code blocks [source,language] to Markdown ```language
        $content = preg_replace('/\[source,([^\]]+)\]\s*\n----\s*\n(.*?)\n----/s', "```$1\n$2\n```", $content);
        $content = preg_replace('/\[source\]\s*\n----\s*\n(.*?)\n----/s', "```\n$1\n```", $content);
        
        // Convert AsciiDoc blockquotes [quote]...____ to Markdown blockquotes
        $content = preg_replace('/\[quote\]\s*\n____\s*\n(.*?)\n____/s', '> $1', $content);
        
        // Convert AsciiDoc headers to Markdown headers (after admonitions)
        $content = preg_replace('/^=+\s+(.+)$/m', '# $1', $content);
        
        // Convert AsciiDoc links https://example.com[text] to Markdown [text](https://example.com)
        $content = preg_replace('/(https?:\/\/[^\s\[]+)\[([^\]]+)\]/', '[$2]($1)', $content);
        
        // Convert AsciiDoc images image:filename[alt text] to Markdown ![alt text](filename)
        $content = preg_replace('/image:([^[]+)\[([^\]]*)\]/', '![$2]($1)', $content);
        
        // Convert AsciiDoc italic __text__ to Markdown *text*
        $content = preg_replace('/__([^_]+)__/', '*$1*', $content);
        
        // Convert AsciiDoc monospace +text+ to Markdown `text`
        $content = preg_replace('/\+([^+\n\r]+)\+/', '`$1`', $content);
        
        // Convert AsciiDoc ordered lists . item to Markdown 1. item
        $content = preg_replace('/^\. (.+)$/m', '1. $1', $content);
        
        return $content;
    }
}