// Simple test for rendering functionality
// This can be run in the browser console to test rendering

function testMarkdownRendering() {
    const testContent = `# Test Markdown

This is a **test** document.

## Features

- Item 1
- Item 2

\`\`\`javascript
console.log("Hello World");
\`\`\`
`;

    if (typeof marked !== 'undefined') {
        const rendered = marked.parse(testContent);
        console.log('✅ Markdown rendering works');
        console.log('Rendered HTML:', rendered);
        return rendered;
    } else {
        console.error('❌ Marked library not loaded');
        return null;
    }
}

function testAsciidocRendering() {
    const testContent = `= Test Asciidoc

This is a *test* document.

== Features

* Item 1
* Item 2

[source,javascript]
----
console.log("Hello World");
----
`;

    if (typeof Asciidoctor !== 'undefined') {
        const asciidoctor = Asciidoctor();
        const rendered = asciidoctor.convert(testContent, {
            safe: 'safe',
            backend: 'html5',
            doctype: 'article'
        });
        console.log('✅ Asciidoc rendering works');
        console.log('Rendered HTML:', rendered);
        return rendered;
    } else {
        console.error('❌ Asciidoctor library not loaded');
        return null;
    }
}

function runAllTests() {
    console.log('🧪 Running rendering tests...');
    
    const markdownResult = testMarkdownRendering();
    const asciidocResult = testAsciidocRendering();
    
    if (markdownResult && asciidocResult) {
        console.log('✅ All rendering tests passed!');
    } else {
        console.log('❌ Some rendering tests failed');
    }
}

// Auto-run tests when loaded
if (typeof window !== 'undefined') {
    window.testRendering = {
        testMarkdown: testMarkdownRendering,
        testAsciidoc: testAsciidocRendering,
        runAll: runAllTests
    };
    
    console.log('🔧 Rendering tests loaded. Run testRendering.runAll() to test');
}
