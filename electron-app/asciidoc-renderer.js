// Simple Asciidoc renderer for basic syntax
// This is a fallback when the full Asciidoctor library isn't available

function simpleAsciidocRenderer(content) {
    let html = content;
    
    // Convert discrete headers (with [discrete] attribute)
    html = html.replace(/^\[discrete\]\s*\n= (.+)$/gm, '<h1 class="discrete">$1</h1>');
    html = html.replace(/^\[discrete\]\s*\n== (.+)$/gm, '<h2 class="discrete">$1</h2>');
    html = html.replace(/^\[discrete\]\s*\n=== (.+)$/gm, '<h3 class="discrete">$1</h3>');
    html = html.replace(/^\[discrete\]\s*\n==== (.+)$/gm, '<h4 class="discrete">$1</h4>');
    html = html.replace(/^\[discrete\]\s*\n===== (.+)$/gm, '<h5 class="discrete">$1</h5>');
    html = html.replace(/^\[discrete\]\s*\n====== (.+)$/gm, '<h6 class="discrete">$1</h6>');
    
    // Convert regular headers
    html = html.replace(/^= (.+)$/gm, '<h1>$1</h1>');
    html = html.replace(/^== (.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^=== (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^==== (.+)$/gm, '<h4>$1</h4>');
    html = html.replace(/^===== (.+)$/gm, '<h5>$1</h5>');
    html = html.replace(/^====== (.+)$/gm, '<h6>$1</h6>');
    
    // Convert bold and italic
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    html = html.replace(/_([^_]+)_/g, '<em>$1</em>');
    
    // Convert code blocks
    html = html.replace(/^----\s*\n([\s\S]*?)\n----$/gm, '<pre><code>$1</code></pre>');
    html = html.replace(/^```(\w+)?\s*\n([\s\S]*?)\n```$/gm, '<pre><code class="language-$1">$2</code></pre>');
    
    // Convert inline code
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    
    // Convert lists
    html = html.replace(/^\* (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
    
    html = html.replace(/^- (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
    
    html = html.replace(/^\. (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ol>$1</ol>');
    
    // Convert paragraphs (double newline)
    html = html.replace(/\n\n/g, '</p><p>');
    html = '<p>' + html + '</p>';
    
    // Clean up empty paragraphs
    html = html.replace(/<p><\/p>/g, '');
    html = html.replace(/<p>\s*<\/p>/g, '');
    
    // Convert line breaks
    html = html.replace(/\n/g, '<br>');
    
    return html;
}

// Make it available globally
if (typeof window !== 'undefined') {
    window.simpleAsciidocRenderer = simpleAsciidocRenderer;
}
