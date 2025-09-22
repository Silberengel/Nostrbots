// DOM elements
const filePathInput = document.getElementById('filePath');
const selectFileBtn = document.getElementById('selectFileBtn');
const filePreview = document.getElementById('filePreview');
const fileContent = document.getElementById('fileContent');
const contentKindSelect = document.getElementById('contentKind');
const outputDirInput = document.getElementById('outputDir');
const selectOutputBtn = document.getElementById('selectOutputBtn');
const modeRadios = document.querySelectorAll('input[name="mode"]');
const hierarchicalOptions = document.getElementById('hierarchicalOptions');
const contentLevelSelect = document.getElementById('contentLevel');
const parseBtn = document.getElementById('parseBtn');
const openOutputBtn = document.getElementById('openOutputBtn');
const resultsSection = document.getElementById('resultsSection');
const resultsContent = document.getElementById('resultsContent');
const filesSection = document.getElementById('filesSection');
const filesList = document.getElementById('filesList');

// State
let selectedFilePath = null;
let selectedOutputDir = null;
let generatedFiles = [];

// Event listeners
selectFileBtn.addEventListener('click', selectFile);
selectOutputBtn.addEventListener('click', selectOutputDirectory);
parseBtn.addEventListener('click', parseDocument);
openOutputBtn.addEventListener('click', openOutputDirectory);

// Mode change handler
modeRadios.forEach(radio => {
    radio.addEventListener('change', handleModeChange);
});

// File selection
async function selectFile() {
    try {
        const filePath = await window.electronAPI.selectFile();
        if (filePath) {
            selectedFilePath = filePath;
            filePathInput.value = filePath;
            
            // Read and preview file content
            const result = await window.electronAPI.readFile(filePath);
            if (result.success) {
                fileContent.textContent = result.content;
                filePreview.classList.remove('hidden');
            } else {
                showError('Failed to read file: ' + result.error);
            }
            
            updateParseButton();
        }
    } catch (error) {
        showError('Error selecting file: ' + error.message);
    }
}

// Output directory selection
async function selectOutputDirectory() {
    try {
        const dirPath = await window.electronAPI.selectOutputDirectory();
        if (dirPath) {
            selectedOutputDir = dirPath;
            outputDirInput.value = dirPath;
        }
    } catch (error) {
        showError('Error selecting output directory: ' + error.message);
    }
}

// Mode change handler
function handleModeChange() {
    const isHierarchical = document.querySelector('input[name="mode"]:checked').value === 'hierarchical';
    
    if (isHierarchical) {
        hierarchicalOptions.classList.remove('hidden');
    } else {
        hierarchicalOptions.classList.add('hidden');
    }
}

// Parse document
async function parseDocument() {
    if (!selectedFilePath) {
        showError('Please select a document file first');
        return;
    }

    const contentKind = contentKindSelect.value;
    const isHierarchical = document.querySelector('input[name="mode"]:checked').value === 'hierarchical';
    const contentLevel = isHierarchical ? parseInt(contentLevelSelect.value) : 1;

    // Update UI
    parseBtn.disabled = true;
    parseBtn.querySelector('.btn-text').textContent = 'Parsing...';
    parseBtn.querySelector('.btn-spinner').classList.remove('hidden');
    
    resultsSection.classList.add('hidden');
    filesSection.classList.add('hidden');

    try {
        const options = {
            filePath: selectedFilePath,
            contentKind: contentKind,
            outputDir: selectedOutputDir,
            hierarchical: isHierarchical,
            contentLevel: contentLevel
        };

        const result = await window.electronAPI.parseDocument(options);
        
        if (result.success) {
            showSuccess('Document parsed successfully!');
            displayResults(result.output);
            await loadGeneratedFiles();
        } else {
            showError('Parsing failed: ' + result.error);
            if (result.stderr) {
                console.error('Stderr:', result.stderr);
            }
        }
    } catch (error) {
        showError('Error parsing document: ' + error.message);
    } finally {
        // Reset UI
        parseBtn.disabled = false;
        parseBtn.querySelector('.btn-text').textContent = 'Parse Document';
        parseBtn.querySelector('.btn-spinner').classList.add('hidden');
    }
}

// Display results
function displayResults(output) {
    resultsContent.textContent = output;
    resultsSection.classList.remove('hidden');
}

// Load generated files
async function loadGeneratedFiles() {
    if (!selectedOutputDir) {
        // Use current directory
        selectedOutputDir = '.';
    }

    try {
        const result = await window.electronAPI.listFiles(selectedOutputDir);
        if (result.success) {
            generatedFiles = result.files.filter(file => 
                file.endsWith('.yml') || file.endsWith('.adoc') || file.endsWith('.md')
            );
            displayGeneratedFiles();
        }
    } catch (error) {
        console.error('Error loading files:', error);
    }
}

// Display generated files
function displayGeneratedFiles() {
    filesList.innerHTML = '';
    
    if (generatedFiles.length === 0) {
        filesList.innerHTML = '<p>No generated files found</p>';
        return;
    }

    generatedFiles.forEach(file => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        
        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        
        const fileName = document.createElement('div');
        fileName.className = 'file-name';
        fileName.textContent = file;
        
        const filePath = document.createElement('div');
        filePath.className = 'file-path';
        filePath.textContent = selectedOutputDir + '/' + file;
        
        fileInfo.appendChild(fileName);
        fileInfo.appendChild(filePath);
        
        const openBtn = document.createElement('button');
        openBtn.className = 'btn btn-outline';
        openBtn.textContent = 'Open';
        openBtn.addEventListener('click', () => openFile(selectedOutputDir + '/' + file));
        
        fileItem.appendChild(fileInfo);
        fileItem.appendChild(openBtn);
        filesList.appendChild(fileItem);
    });
    
    filesSection.classList.remove('hidden');
    openOutputBtn.classList.remove('hidden');
}

// Open file
async function openFile(filePath) {
    try {
        await window.electronAPI.openFile(filePath);
    } catch (error) {
        showError('Error opening file: ' + error.message);
    }
}

// Open output directory
async function openOutputDirectory() {
    try {
        await window.electronAPI.openDirectory(selectedOutputDir || '.');
    } catch (error) {
        showError('Error opening directory: ' + error.message);
    }
}

// Update parse button state
function updateParseButton() {
    parseBtn.disabled = !selectedFilePath;
}

// Show success message
function showSuccess(message) {
    // Simple success notification
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #48bb78;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 1000;
        font-weight: 600;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        document.body.removeChild(notification);
    }, 3000);
}

// Show error message
function showError(message) {
    // Simple error notification
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #f56565;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 1000;
        font-weight: 600;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        document.body.removeChild(notification);
    }, 5000);
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    updateParseButton();
    
    // Set default output directory to current directory
    outputDirInput.value = 'Current directory';
});
