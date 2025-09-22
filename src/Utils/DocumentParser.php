<?php

namespace Nostrbots\Utils;

/**
 * Document Parser for converting structured documents into Nostr publication hierarchies
 * 
 * Parses Asciidoc (.adoc) and Markdown (.md) files and generates configuration
 * files for hierarchical publication structures with configurable section levels.
 */
class DocumentParser
{
    private string $documentPath;
    private string $documentContent;
    private string $format; // 'asciidoc' or 'markdown'
    private int $contentLevel;
    private string $contentKind;
    private array $sections = [];
    private ?string $preamble = null;
    private string $documentTitle = '';
    private string $baseSlug = '';

    /**
     * Parse a document and generate publication structure
     * 
     * @param string $documentPath Path to the document file
     * @param int $contentLevel Header level that becomes content sections (1-6)
     * @param string $contentKind Kind of content to generate ('30023', '30041', '30818', 'longform', 'publication', 'wiki')
     * @param string $outputDir Directory to save generated configuration files
     * @return array Parse results with generated files
     */
    
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
        // Validate content level
        if ($contentLevel < 1 || $contentLevel > 6) {
            throw new \InvalidArgumentException("Content level must be between 1 and 6, got: {$contentLevel}");
        }

        $this->documentPath = $documentPath;
        $this->contentLevel = $contentLevel;
        $this->contentKind = $this->normalizeContentKind($contentKind);
        
        // Reset state for new document
        $this->reset();
        $this->documentPath = $documentPath;
        $this->contentLevel = $contentLevel;
        $this->contentKind = $this->normalizeContentKind($contentKind);
        
        if (!file_exists($documentPath)) {
            throw new \InvalidArgumentException("Document file not found: {$documentPath}");
        }

        $this->documentContent = file_get_contents($documentPath);
        $this->format = $this->detectFormat($documentPath);
        
        // Parse the document structure
        $this->parseStructure();
        
        // Validate document structure after parsing
        if (empty($this->documentTitle)) {
            throw new \InvalidArgumentException("Invalid document structure: Document must have exactly one document title (level 1 header). Found 0 document headers.");
        }
        
        // Build hierarchical structure for direct publishing
        return $this->buildHierarchicalStructure();
    }

    /**
     * Build hierarchical structure optimized for direct publishing
     * Returns array with content sections and indices in dependency order
     */
    private function buildHierarchicalStructure(): array
    {
        // Extract metadata from document header
        $metadata = $this->extractDocumentMetadata();
        
        $structure = [
            'document_title' => $this->documentTitle,
            'base_slug' => $this->baseSlug,
            'content_level' => $this->contentLevel,
            'content_kind' => $this->contentKind,
            'preamble' => $this->preamble,
            'metadata' => $metadata, // Document metadata from header
            'publish_order' => [], // Order for publishing (content first, then indices)
            'content_sections' => [], // Level >= contentLevel (actual content)
            'index_sections' => [], // Level < contentLevel (indices/containers)
            'main_index' => null // Root index configuration
        ];

        // Organize sections by type
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

        // Build main index (root)
        $structure['main_index'] = [
            'title' => $this->documentTitle,
            'slug' => $this->baseSlug,
            'event_kind' => 30040,
            'd_tag' => $this->baseSlug,
            'content_references' => $this->buildContentReferences($structure)
        ];

        // Build publish order: content sections first, then indices, then main index
        $structure['publish_order'] = array_merge(
            $structure['content_sections'],
            $structure['index_sections'],
            [$structure['main_index']]
        );

        return $structure;
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
            if (!$foundTitle && preg_match('/^=\s+(.+)$/', $line, $matches)) {
                $foundTitle = true;
                continue;
            }
            
            // If we haven't found the title yet, skip
            if (!$foundTitle) {
                continue;
            }
            
            // Stop at first header after title (== or deeper)
            if (preg_match('/^={2,}\s+(.+)$/', $line)) {
                break;
            }
            
            // Parse AsciiDoc attributes (format: :key: value) or key-value pairs (format: key: value)
            if (preg_match('/^:?([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.+)$/', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);
                
                // Set default values for common metadata (author and version first)
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
            }
        }
        
        // Set defaults for required metadata
        $metadata['relays'] = $metadata['relays'] ?? 'favorite-relays';
        $metadata['auto_update'] = $metadata['auto_update'] ?? true;
        $metadata['summary'] = $metadata['summary'] ?? 'Generated from document: ' . $this->documentTitle;
        $metadata['type'] = $metadata['type'] ?? 'documentation';
        $metadata['hierarchy_level'] = $metadata['hierarchy_level'] ?? 0;
        
        return $metadata;
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

    public function parseDocument(string $documentPath, int $contentLevel, string $contentKind, string $outputDir): array
    {
        // Validate content level
        if ($contentLevel < 1 || $contentLevel > 6) {
            throw new \InvalidArgumentException("Content level must be between 1 and 6, got: {$contentLevel}");
        }

        $this->documentPath = $documentPath;
        $this->contentLevel = $contentLevel;
        $this->contentKind = $this->normalizeContentKind($contentKind);
        
        // Reset state for new document (after setting the new parameters)
        $this->reset();
        $this->documentPath = $documentPath;
        $this->contentLevel = $contentLevel;
        $this->contentKind = $this->normalizeContentKind($contentKind);
        
        if (!file_exists($documentPath)) {
            throw new \InvalidArgumentException("Document file not found: {$documentPath}");
        }

        $this->documentContent = file_get_contents($documentPath);
        $this->format = $this->detectFormat($documentPath);
        
        // Parse the document structure
        $this->parseStructure();
        
        // Validate document structure after parsing
        if (empty($this->documentTitle)) {
            throw new \InvalidArgumentException("Invalid document structure: Document must have exactly one document title (level 1 header). Found 0 document headers.");
        }
        
        // Generate configuration files
        return $this->generateConfigurations($outputDir);
    }

    /**
     * Normalize content kind to numeric value
     */
    private function normalizeContentKind(string $kind): int
    {
        $kindMap = [
            '30023' => 30023,
            'longform' => 30023,
            '30041' => 30041, 
            'publication' => 30041,
            '30818' => 30818,
            'wiki' => 30818
        ];

        $normalized = strtolower($kind);
        if (isset($kindMap[$normalized])) {
            return $kindMap[$normalized];
        }

        throw new \InvalidArgumentException("Unsupported content kind: {$kind}. Supported: " . implode(', ', array_keys($kindMap)));
    }

    /**
     * Detect document format based on file extension
     */
    private function detectFormat(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'adoc':
            case 'asciidoc':
                return 'asciidoc';
            case 'md':
            case 'markdown':
                return 'markdown';
            default:
                throw new \InvalidArgumentException("Unsupported file format: {$extension}. Supported: .adoc, .md");
        }
    }

    /**
     * Parse the document structure into hierarchical sections
     */
    private function parseStructure(): void
    {
        $lines = explode("\n", $this->documentContent);
        $currentSection = null;
        $preambleContent = [];
        $foundFirstHeader = false;
        $foundNonTitleHeader = false;
        $documentHeaderCount = 0;

        foreach ($lines as $lineNum => $line) {
            $previousLine = ($lineNum > 0) ? $lines[$lineNum - 1] : null;
            $headerLevel = $this->getHeaderLevel($line, $previousLine);
            
            if ($headerLevel > 0) {
                // Handle preamble if we haven't found a non-title header yet
                // For contentLevel=1, don't clear preamble content when we encounter headers
                if (!$foundNonTitleHeader && !empty($preambleContent) && $this->contentLevel !== 1) {
                    $this->preamble = trim(implode("\n", $preambleContent));
                    $preambleContent = [];
                }
                
                $foundFirstHeader = true;
                $headerText = $this->extractHeaderText($line);

                // Determine section type based on level
                if ($headerLevel === 1) {
                    // Document title - validate only one document header
                    $documentHeaderCount++;
                    if ($documentHeaderCount > 1) {
                        throw new \InvalidArgumentException("Invalid document structure: Found multiple document headers (level 1 headers). AsciiDoc documents can only have one document title. Found at least 2: '{$this->documentTitle}' and '{$headerText}'");
                    }
                    
                    if (empty($this->documentTitle)) {
                        $this->documentTitle = $headerText;
                        $this->baseSlug = $this->generateSlug($headerText);
                        continue;
                    }
                }

                // Mark that we found a non-title header
                if ($headerLevel > 1) {
                    $foundNonTitleHeader = true;
                }

                // For contentLevel = 1, treat everything after the title as a single content section
                if ($this->contentLevel === 1) {
                    // Don't create sections for any headers - treat them all as content
                    // Add to preamble content since we'll convert it to a content section later
                    $preambleContent[] = $line;
                    continue;
                }

                // Only create new sections for headers at or above content level
                // Headers deeper than content level stay within their parent content section
                if ($headerLevel <= $this->contentLevel) {
                    // Create section entry
                    $section = [
                        'level' => $headerLevel,
                        'title' => $headerText,
                        'slug' => '', // Will be set after processing hierarchy
                        'content' => [],
                        'line_start' => $lineNum,
                        'line_end' => null,
                        'children' => [],
                        'parent_slug' => null
                    ];

                    // Close previous section and extract its content
                    if ($currentSection !== null) {
                        $currentSection['line_end'] = $lineNum - 1;
                        $currentSection['content'] = trim(implode("\n", $currentSection['content']));
                        $this->addSection($currentSection);
                    }

                    $currentSection = $section;
                } else {
                    // This is a sub-header within a content section - add to current section content
                    if ($currentSection !== null) {
                        $currentSection['content'][] = $line;
                    }
                }
            } else {
                // Add content to current section or preamble
                if ($currentSection !== null) {
                    $currentSection['content'][] = $line;
                } elseif ($this->contentLevel === 1 || !$foundNonTitleHeader) {
                    // For contentLevel = 1, add all content after title to preamble
                    $preambleContent[] = $line;
                }
            }
        }

        // Close final section
        if ($currentSection !== null) {
            $currentSection['line_end'] = count($lines) - 1;
            $currentSection['content'] = trim(implode("\n", $currentSection['content']));
            $this->addSection($currentSection);
        }

        // Handle preamble if we have content but no non-title headers were found
        if (!$foundNonTitleHeader && !empty($preambleContent)) {
            $this->preamble = trim(implode("\n", $preambleContent));
        }

        // For contentLevel = 1, create a single content section from all content after the title
        if ($this->contentLevel === 1 && !empty($preambleContent)) {
            $contentSection = [
                'level' => 1,
                'title' => $this->documentTitle,
                'slug' => $this->baseSlug,
                'content' => trim(implode("\n", $preambleContent)),
                'line_start' => 0,
                'line_end' => count($lines) - 1,
                'is_content' => true
            ];
            $this->addSection($contentSection);
            // Clear preamble since we're treating this as a content section
            $this->preamble = null;
        }

        // Validate that we have a root header (level 1)
        if (empty($this->documentTitle)) {
            throw new \InvalidArgumentException("Document must have a root header (level 1) to define the document title");
        }

        // Process hierarchical d-tags after all sections are parsed
        $this->generateHierarchicalSlugs();
    }

    /**
     * Get header level from a line (1-6, or 0 if not a header)
     * Also checks if the header is discrete (should not create sections)
     */
    private function getHeaderLevel(string $line, ?string $previousLine = null): int
    {
        $line = trim($line);
        
        if ($this->format === 'asciidoc') {
            // Asciidoc: = (level 1), == (level 2), === (level 3), etc.
            if (preg_match('/^(=+)\s+(.+)$/', $line, $matches)) {
                // Check if this is a discrete header
                if ($previousLine && trim($previousLine) === '[discrete]') {
                    return 0; // Discrete headers don't create sections
                }
                return strlen($matches[1]);
            }
        } else {
            // Markdown: # (level 1), ## (level 2), ### (level 3), etc.
            if (preg_match('/^(#+)\s+(.+)$/', $line, $matches)) {
                return strlen($matches[1]);
            }
        }
        
        return 0;
    }

    /**
     * Extract header text from a header line
     */
    private function extractHeaderText(string $line): string
    {
        $line = trim($line);
        
        if ($this->format === 'asciidoc') {
            if (preg_match('/^=+\s+(.+)$/', $line, $matches)) {
                return trim($matches[1]);
            }
        } else {
            if (preg_match('/^#+\s+(.+)$/', $line, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return '';
    }

    /**
     * Generate URL-friendly slug from text
     */
    private function generateSlug(string $text): string
    {
        // Convert to lowercase and replace non-alphanumeric with hyphens
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
        
        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Limit length to prevent filesystem issues (50 chars should be enough for most cases)
        if (strlen($slug) > 50) {
            $slug = substr($slug, 0, 50);
            // Remove trailing hyphen if it exists
            $slug = rtrim($slug, '-');
        }
        
        return $slug;
    }

    /**
     * Generate hierarchical d-tags for all sections with global uniqueness
     */
    private function generateHierarchicalSlugs(): void
    {
        $parentStack = []; // Stack to track parent slugs at each level
        $parentStack[0] = $this->baseSlug; // Root level is the document slug

        foreach ($this->sections as &$section) {
            $level = $section['level'];
            $basicSlug = $this->generateSlug($section['title']);
            
            // Build hierarchical slug starting with document base for global uniqueness
            $hierarchicalParts = [$this->baseSlug]; // Always start with document slug
            
            // Add parent slugs up to current level (trimmed for length)
            for ($i = 1; $i < $level; $i++) {
                if (isset($parentStack[$i])) {
                    $hierarchicalParts[] = $parentStack[$i];
                }
            }
            
            // Add current section slug
            $hierarchicalParts[] = $basicSlug;
            
            // Create full hierarchical slug with length management
            $section['slug'] = $this->createManagedSlug($hierarchicalParts, false);
            
            // For content sections (at content level), add '-content' suffix
            if ($level === $this->contentLevel) {
                $section['content_slug'] = $this->createManagedSlug($hierarchicalParts, true);
            }
            
            // Update parent stack for this level and clear deeper levels
            $parentStack[$level] = $basicSlug;
            for ($i = $level + 1; $i <= 6; $i++) {
                unset($parentStack[$i]);
            }
            
            // Set parent reference (always includes document base for uniqueness)
            if ($level > 1) {
                // Build parent slug from all parts except the current one
                $parentParts = array_slice($hierarchicalParts, 0, -1);
                $section['parent_slug'] = $this->createManagedSlug($parentParts, false);
            } else {
                $section['parent_slug'] = $this->baseSlug;
            }
        }
    }

    /**
     * Create a managed slug with length limits and optional content suffix
     */
    private function createManagedSlug(array $parts, bool $isContent): string
    {
        $maxLength = 70; // Maximum d-tag length
        $contentSuffix = $isContent ? '-content' : '';
        $suffixLength = strlen($contentSuffix);
        $availableLength = $maxLength - $suffixLength;
        
        // Try full slug first
        $fullSlug = implode('-', $parts);
        if (strlen($fullSlug) <= $availableLength) {
            return $fullSlug . $contentSuffix;
        }
        
        // Need to trim parts to fit
        $trimmedParts = $this->trimPartsToFit($parts, $availableLength);
        return implode('-', $trimmedParts) . $contentSuffix;
    }

    /**
     * Trim slug parts to fit within length limit while preserving meaning
     */
    private function trimPartsToFit(array $parts, int $maxLength): array
    {
        $partCount = count($parts);
        if ($partCount === 0) return $parts;
        
        // Calculate maximum length per part (with hyphens)
        $maxPerPart = max(3, floor(($maxLength - ($partCount - 1)) / $partCount));
        
        $trimmedParts = [];
        foreach ($parts as $index => $part) {
            if ($index === 0 || $index === count($parts) - 1) {
                // Keep document name and final section more intact
                $trimmedParts[] = $this->trimPart($part, min(strlen($part), $maxPerPart + 5));
            } else {
                // Trim middle parts more aggressively
                $trimmedParts[] = $this->trimPart($part, min(strlen($part), $maxPerPart));
            }
        }
        
        // Final check and adjustment if still too long
        $result = implode('-', $trimmedParts);
        if (strlen($result) > $maxLength) {
            // More aggressive trimming needed
            $trimmedParts = array_map(function($part, $index) use ($partCount) {
                if ($index === 0) return substr($part, 0, 12); // Document name
                if ($index === $partCount - 1) return substr($part, 0, 10); // Final section
                return substr($part, 0, 6); // Middle parts
            }, $trimmedParts, array_keys($trimmedParts));
            
            // Final safety check
            $result = implode('-', $trimmedParts);
            if (strlen($result) > $maxLength) {
                // Emergency truncation
                $result = substr($result, 0, $maxLength);
                // Try to end at a word boundary
                $lastDash = strrpos($result, '-');
                if ($lastDash !== false && $lastDash > $maxLength * 0.8) {
                    $result = substr($result, 0, $lastDash);
                }
            }
            return explode('-', $result);
        }
        
        return $trimmedParts;
    }

    /**
     * Trim a single part while preserving readability
     */
    private function trimPart(string $part, int $maxLength): string
    {
        if (strlen($part) <= $maxLength) {
            return $part;
        }
        
        // Try to break at word boundaries or meaningful points
        if ($maxLength >= 6) {
            // Look for natural break points
            $breakPoints = ['-', '_'];
            foreach ($breakPoints as $breakPoint) {
                $pos = strrpos(substr($part, 0, $maxLength), $breakPoint);
                if ($pos !== false && $pos >= 3) {
                    return substr($part, 0, $pos);
                }
            }
        }
        
        // Simple truncation as fallback
        return substr($part, 0, $maxLength);
    }

    /**
     * Add section to the sections array with proper hierarchy
     */
    private function addSection(array $section): void
    {
        // Content should already be processed before this point
        $this->sections[] = $section;
    }

    /**
     * Generate configuration files for the parsed structure
     */
    private function generateConfigurations(string $outputDir): array
    {
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new \RuntimeException("Failed to create output directory: {$outputDir}");
            }
        }

        $results = [
            'document_title' => $this->documentTitle,
            'base_slug' => $this->baseSlug,
            'content_level' => $this->contentLevel,
            'content_kind' => $this->contentKind,
            'generated_files' => [],
            'structure' => []
        ];

        // Generate preamble if exists
        if ($this->preamble) {
            $preambleFile = $this->generatePreambleConfig($outputDir);
            $results['generated_files'][] = $preambleFile;
        }

        // Generate main index
        $mainIndexFile = $this->generateMainIndex($outputDir);
        $results['generated_files'][] = $mainIndexFile;

        // Generate section configurations
        foreach ($this->sections as $section) {
            if ($section['level'] === $this->contentLevel) {
                // Content section
                $contentFile = $this->generateContentSection($section, $outputDir);
                $results['generated_files'][] = $contentFile;
            } elseif ($section['level'] < $this->contentLevel) {
                // Index section
                $indexFile = $this->generateIndexSection($section, $outputDir);
                $results['generated_files'][] = $indexFile;
            }
        }

        // Build structure map
        $results['structure'] = $this->buildStructureMap();

        return $results;
    }

    /**
     * Generate preamble configuration
     */
    private function generatePreambleConfig(string $outputDir): string
    {
        $slug = $this->baseSlug . '-preamble-content'; // Always add -content for actual content
        $configPath = $outputDir . '/' . $slug . '-config.yml';
        $contentPath = $outputDir . '/' . $slug . '-content.' . ($this->format === 'asciidoc' ? 'adoc' : 'md');

        // Write content file
        file_put_contents($contentPath, $this->preamble);

        // Generate configuration
        $config = [
            'bot_name' => 'Document Parser - Preamble',
            'bot_description' => 'Generated preamble section',
            'event_kind' => $this->contentKind,
            'npub' => [
                'environment_variable' => 'NOSTR_BOT_KEY1',
                'public_key' => 'npub1r0r9c7upagp9s5vmxqkcjymj4mqwqw2g8m029j7pgthr2u2yl5dsn9a3r6'
            ],
            'relays' => 'favorite-relays',
            'title' => $this->documentTitle . ' - Preamble',
            'summary' => 'Preamble section for ' . $this->documentTitle,
            'static_d_tag' => true,
            'd-tag' => $slug,
            'content_files' => [
                ($this->format === 'asciidoc' ? 'asciidoc' : 'markdown') => $contentPath
            ],
            'topics' => ['document', 'preamble'],
            'custom_tags' => [
                ['client', 'nostrbots'],
                ['generated', 'document-parser'],
                ['section_type', 'preamble']
            ]
        ];

        $this->writeYamlConfig($configPath, $config);
        return $configPath;
    }

    /**
     * Generate main index configuration
     */
    private function generateMainIndex(string $outputDir): string
    {
        $configPath = $outputDir . '/' . $this->baseSlug . '-index-config.yml';

        $contentReferences = [];
        $order = 0;

        // Add preamble reference if exists
        if ($this->preamble) {
            $contentReferences[] = [
                'kind' => $this->contentKind,
                'pubkey' => 'npub1r0r9c7upagp9s5vmxqkcjymj4mqwqw2g8m029j7pgthr2u2yl5dsn9a3r6',
                'd_tag' => $this->baseSlug . '-preamble-content',
                'relay' => 'wss://thecitadel.nostr1.com',
                'order' => $order++
            ];
        }

        // Add top-level section references only (level 2 if document has level 1 title)
        $topLevel = 2; // Sections directly under the main title
        foreach ($this->sections as $section) {
            if ($section['level'] === $topLevel) {
                $kind = ($section['level'] === $this->contentLevel) ? $this->contentKind : 30040;
                // Use content_slug for content sections, regular slug for indices
                $dTag = ($section['level'] === $this->contentLevel && isset($section['content_slug'])) 
                    ? $section['content_slug'] 
                    : $section['slug'];
                $contentReferences[] = [
                    'kind' => $kind,
                    'pubkey' => 'npub1r0r9c7upagp9s5vmxqkcjymj4mqwqw2g8m029j7pgthr2u2yl5dsn9a3r6',
                    'd_tag' => $dTag,
                    'relay' => 'wss://thecitadel.nostr1.com',
                    'order' => $order++
                ];
            }
        }

        $config = [
            'bot_name' => 'Document Parser - Main Index',
            'bot_description' => 'Generated main publication index',
            'event_kind' => 30040,
            'npub' => [
                'environment_variable' => 'NOSTR_BOT_KEY1',
                'public_key' => 'npub1r0r9c7upagp9s5vmxqkcjymj4mqwqw2g8m029j7pgthr2u2yl5dsn9a3r6'
            ],
            'relays' => 'favorite-relays',
            'title' => $this->documentTitle,
            'auto_update' => true,
            'summary' => 'Main index for ' . $this->documentTitle,
            'type' => 'documentation',
            'hierarchy_level' => 0,
            'static_d_tag' => true,
            'd-tag' => $this->baseSlug,
            'content_references' => $contentReferences,
            'topics' => ['document', 'publication', 'index'],
            'custom_tags' => [
                ['client', 'nostrbots'],
                ['generated', 'document-parser'],
                ['content_level', (string)$this->contentLevel]
            ]
        ];

        $this->writeYamlConfig($configPath, $config);
        return $configPath;
    }

    /**
     * Generate content section configuration
     */
    private function generateContentSection(array $section, string $outputDir): string
    {
        // Use content_slug for actual content sections (includes -content suffix)
        $slug = $section['content_slug'] ?? $section['slug'] . '-content';
        $configPath = $outputDir . '/' . $slug . '-config.yml';
        $contentPath = $outputDir . '/' . $slug . '-content.' . ($this->format === 'asciidoc' ? 'adoc' : 'md');

        // Write content file
        file_put_contents($contentPath, $section['content']);

        $config = [
            'bot_name' => 'Document Parser - Content Section',
            'bot_description' => 'Generated content section',
            'event_kind' => $this->contentKind,
            'npub' => [
                'environment_variable' => 'NOSTR_BOT_KEY1',
                'public_key' => 'npub1r0r9c7upagp9s5vmxqkcjymj4mqwqw2g8m029j7pgthr2u2yl5dsn9a3r6'
            ],
            'relays' => 'favorite-relays',
            'title' => $section['title'],
            'summary' => 'Content section: ' . $section['title'],
            'static_d_tag' => true,
            'd-tag' => $slug,
            'content_files' => [
                ($this->format === 'asciidoc' ? 'asciidoc' : 'markdown') => $contentPath
            ],
            'topics' => ['document', 'section'],
            'custom_tags' => [
                ['client', 'nostrbots'],
                ['generated', 'document-parser'],
                ['section_type', 'content'],
                ['section_level', (string)$section['level']]
            ]
        ];

        $this->writeYamlConfig($configPath, $config);
        return $configPath;
    }

    /**
     * Generate index section configuration
     */
    private function generateIndexSection(array $section, string $outputDir): string
    {
        $configPath = $outputDir . '/' . $section['slug'] . '-index-config.yml';

        // Find direct child sections only
        $contentReferences = [];
        $order = 0;
        $targetLevel = $section['level'] + 1;

        foreach ($this->sections as $childSection) {
            if ($childSection['level'] === $targetLevel) {
                // Check if this child is actually under this parent by checking position
                $isChild = $this->isDirectChild($section, $childSection);
                if ($isChild) {
                    $kind = ($childSection['level'] === $this->contentLevel) ? $this->contentKind : 30040;
                    // Use content_slug for content sections, regular slug for indices
                    $dTag = ($childSection['level'] === $this->contentLevel && isset($childSection['content_slug'])) 
                        ? $childSection['content_slug'] 
                        : $childSection['slug'];
                    $contentReferences[] = [
                        'kind' => $kind,
                        'pubkey' => 'npub1r0r9c7upagp9s5vmxqkcjymj4mqwqw2g8m029j7pgthr2u2yl5dsn9a3r6',
                        'd_tag' => $dTag,
                        'relay' => 'wss://thecitadel.nostr1.com',
                        'order' => $order++
                    ];
                }
            }
        }

        $config = [
            'bot_name' => 'Document Parser - Index Section',
            'bot_description' => 'Generated index section',
            'event_kind' => 30040,
            'npub' => [
                'environment_variable' => 'NOSTR_BOT_KEY1',
                'public_key' => 'npub1r0r9c7upagp9s5vmxqkcjymj4mqwqw2g8m029j7pgthr2u2yl5dsn9a3r6'
            ],
            'relays' => 'favorite-relays',
            'title' => $section['title'],
            'auto_update' => true,
            'summary' => 'Index section: ' . $section['title'],
            'type' => 'documentation',
            'hierarchy_level' => $section['level'] - 1,
            'parent_index' => $section['parent_slug'],
            'static_d_tag' => true,
            'd-tag' => $section['slug'],
            'content_references' => $contentReferences,
            'topics' => ['document', 'index'],
            'custom_tags' => [
                ['client', 'nostrbots'],
                ['generated', 'document-parser'],
                ['section_type', 'index'],
                ['section_level', (string)$section['level']]
            ]
        ];

        $this->writeYamlConfig($configPath, $config);
        return $configPath;
    }

    /**
     * Build structure map for analysis
     */
    private function buildStructureMap(): array
    {
        $structure = [
            'document_title' => $this->documentTitle,
            'has_preamble' => !empty($this->preamble),
            'content_level' => $this->contentLevel,
            'sections' => []
        ];

        foreach ($this->sections as $section) {
            $structureSection = [
                'level' => $section['level'],
                'title' => $section['title'],
                'slug' => $section['slug'],
                'type' => ($section['level'] === $this->contentLevel) ? 'content' : 'index',
                'content_length' => strlen($section['content'])
            ];
            
            // Include is_content flag if it exists
            if (isset($section['is_content'])) {
                $structureSection['is_content'] = $section['is_content'];
            }
            
            // Include content if it exists
            if (isset($section['content'])) {
                $structureSection['content'] = $section['content'];
            }
            
            $structure['sections'][] = $structureSection;
        }

        return $structure;
    }

    /**
     * Check if a section is a direct child of another section
     */
    private function isDirectChild(array $parent, array $child): bool
    {
        // Child must come after parent in the document
        if ($child['line_start'] <= $parent['line_start']) {
            return false;
        }

        // Child must be exactly one level deeper
        if ($child['level'] !== $parent['level'] + 1) {
            return false;
        }

        // Check if there's any section of equal or higher level between parent and child
        foreach ($this->sections as $section) {
            if ($section['line_start'] > $parent['line_start'] && 
                $section['line_start'] < $child['line_start'] && 
                $section['level'] <= $parent['level']) {
                return false; // There's another parent-level section in between
            }
        }

        return true;
    }

    /**
     * Write YAML configuration to file
     */
    private function writeYamlConfig(string $path, array $config): void
    {
        $yaml = "# Generated by Nostrbots Document Parser\n";
        $yaml .= "# " . date('Y-m-d H:i:s') . "\n\n";
        $yaml .= $this->arrayToYaml($config);
        
        file_put_contents($path, $yaml);
    }

    /**
     * Convert array to YAML format (simple implementation)
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // Indexed array
                    $yaml .= $indentStr . $key . ":\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= $indentStr . "  -\n";
                            $yaml .= $this->arrayToYaml($item, $indent + 2);
                        } else {
                            $yaml .= $indentStr . "  - " . $this->yamlValue($item) . "\n";
                        }
                    }
                } else {
                    // Associative array
                    $yaml .= $indentStr . $key . ":\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= $indentStr . $key . ": " . $this->yamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Format value for YAML
     */
    private function yamlValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_string($value) && (strpos($value, ':') !== false || strpos($value, '"') !== false)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }
        return (string)$value;
    }
}
