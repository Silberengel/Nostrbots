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

// Bot configuration management elements
const selectedBotSelect = document.getElementById('selectedBot');
const refreshBotsBtn = document.getElementById('refreshBotsBtn');
const createBotBtn = document.getElementById('createBotBtn');
const editBotBtn = document.getElementById('editBotBtn');
const deleteBotBtn = document.getElementById('deleteBotBtn');
const botInfo = document.getElementById('botInfo');
const botName = document.getElementById('botName');
const botDescription = document.getElementById('botDescription');
const botEventKind = document.getElementById('botEventKind');

// Bot configuration modals
const createBotModal = document.getElementById('createBotModal');
const editBotModal = document.getElementById('editBotModal');
const newBotNameInput = document.getElementById('newBotName');
const newBotTypeSelect = document.getElementById('newBotType');
const newBotConfigTextarea = document.getElementById('newBotConfig');
const editBotNameInput = document.getElementById('editBotName');
const editBotConfigTextarea = document.getElementById('editBotConfig');

// Key management elements (simplified - no longer used for management)
// const selectedKeySelect = document.getElementById('selectedKey');
// const refreshKeysBtn = document.getElementById('refreshKeysBtn');
// const addKeyBtn = document.getElementById('addKeyBtn');
// const deleteKeyBtn = document.getElementById('deleteKeyBtn');
// const keyInfo = document.getElementById('keyInfo');
// const keyAvatar = document.getElementById('keyAvatar');
// const keyInitials = document.getElementById('keyInitials');
// const keyDisplayName = document.getElementById('keyDisplayName');
// const keyNpub = document.getElementById('keyNpub');

// Relay management elements
const editRelaysBtn = document.getElementById('editRelaysBtn');
const relayList = document.getElementById('relayList');

// Publishing elements
const publishSection = document.getElementById('publishSection');
const dryRunBtn = document.getElementById('dryRunBtn');
const testPublishBtn = document.getElementById('testPublishBtn');
const publishBtn = document.getElementById('publishBtn');

// Preview elements
const rawTab = document.getElementById('rawTab');
const renderedTab = document.getElementById('renderedTab');
const rawContent = document.getElementById('rawContent');
const renderedContent = document.getElementById('renderedContent');
const renderedHtml = document.getElementById('renderedHtml');

// State
let selectedFilePath = null;
let selectedOutputDir = null;
let generatedFiles = [];
let relays = [];
let selectedBot = null;
let availableBots = [];

// Event listeners
selectFileBtn.addEventListener('click', selectFile);
selectOutputBtn.addEventListener('click', selectOutputDirectory);
parseBtn.addEventListener('click', parseDocument);
openOutputBtn.addEventListener('click', openOutputDirectory);

// Bot configuration event listeners
refreshBotsBtn.addEventListener('click', loadBotConfigurations);
createBotBtn.addEventListener('click', openCreateBotModal);
editBotBtn.addEventListener('click', editBot);
deleteBotBtn.addEventListener('click', deleteBot);
selectedBotSelect.addEventListener('change', onBotSelectionChange);

// Bot configuration modal event listeners
document.getElementById('closeCreateBotModal').addEventListener('click', closeCreateBotModal);
document.getElementById('cancelCreateBot').addEventListener('click', closeCreateBotModal);
document.getElementById('saveCreateBot').addEventListener('click', createBot);
document.getElementById('closeEditBotModal').addEventListener('click', closeEditBotModal);
document.getElementById('cancelEditBot').addEventListener('click', closeEditBotModal);
document.getElementById('saveEditBot').addEventListener('click', saveBotEdit);
newBotTypeSelect.addEventListener('change', (e) => loadBotTemplate(e.target.value));

// Key management event listeners (removed - using command line instead)
// refreshKeysBtn.addEventListener('click', loadKeys);
// addKeyBtn.addEventListener('click', showAddKeyModal);
// deleteKeyBtn.addEventListener('click', deleteSelectedKey);
// selectedKeySelect.addEventListener('change', handleKeySelection);

// Relay management event listeners
editRelaysBtn.addEventListener('click', showEditRelaysModal);

// Publishing event listeners
dryRunBtn.addEventListener('click', () => publishContent('dry-run'));
testPublishBtn.addEventListener('click', () => publishContent('test'));
publishBtn.addEventListener('click', () => publishContent('publish'));

// Preview tab listeners
rawTab.addEventListener('click', () => switchPreviewTab('raw'));
renderedTab.addEventListener('click', () => switchPreviewTab('rendered'));

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
                
                // Render the content based on file type
                const renderedContent = await renderContent(result.content, filePath);
                renderedHtml.innerHTML = renderedContent;
                
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
        publishSection.classList.add('hidden');
        return;
    }
    
    // Show publish section when files are generated
    publishSection.classList.remove('hidden');

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

// Preview tab switching
function switchPreviewTab(tab) {
    // Update tab buttons
    rawTab.classList.toggle('active', tab === 'raw');
    renderedTab.classList.toggle('active', tab === 'rendered');
    
    // Update content tabs
    rawContent.classList.toggle('active', tab === 'raw');
    renderedContent.classList.toggle('active', tab === 'rendered');
}

// Content rendering
async function renderContent(content, filePath) {
    const extension = filePath.toLowerCase().split('.').pop();
    
    try {
        if (extension === 'md' || extension === 'markdown') {
            // Render Markdown using marked (loaded via script tag)
            if (typeof marked !== 'undefined') {
                return marked.parse(content);
            } else {
                return `<div class="error">Markdown renderer not loaded</div>`;
            }
        } else if (extension === 'adoc' || extension === 'asciidoc') {
            // Render Asciidoc using simple renderer
            if (typeof simpleAsciidocRenderer !== 'undefined') {
                return simpleAsciidocRenderer(content);
            } else {
                return `<div class="error">Asciidoc renderer not loaded</div>`;
            }
        } else {
            // Fallback to plain text with basic formatting
            return `<pre>${escapeHtml(content)}</pre>`;
        }
    } catch (error) {
        console.error('Rendering error:', error);
        return `<div class="error">Error rendering content: ${escapeHtml(error.message)}</div>`;
    }
}

// HTML escaping utility
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Key Management Functions
async function loadKeys() {
    try {
        const keys = await window.electronAPI.getAllBotKeys();
        availableKeys = keys;
        updateKeySelector();
        
        if (keys.length > 0 && !selectedKey) {
            selectedKeySelect.value = keys[0].env_variable;
            handleKeySelection();
        }
    } catch (error) {
        console.error('Error loading keys:', error);
        showError('Failed to load bot keys');
    }
}

function updateKeySelector() {
    selectedKeySelect.innerHTML = '';
    
    if (availableKeys.length === 0) {
        selectedKeySelect.innerHTML = '<option value="">No keys found</option>';
        return;
    }
    
    availableKeys.forEach(key => {
        const option = document.createElement('option');
        option.value = key.env_variable;
        option.textContent = `${key.display_name} (${key.npub.substring(0, 16)}...)`;
        selectedKeySelect.appendChild(option);
    });
}

function handleKeySelection() {
    const envVar = selectedKeySelect.value;
    if (!envVar) {
        selectedKey = null;
        keyInfo.classList.add('hidden');
        deleteKeyBtn.disabled = true;
        return;
    }
    
    selectedKey = availableKeys.find(key => key.env_variable === envVar);
    if (selectedKey) {
        updateKeyInfo(selectedKey);
        keyInfo.classList.remove('hidden');
        deleteKeyBtn.disabled = false;
    }
}

function updateKeyInfo(key) {
    keyDisplayName.textContent = key.display_name;
    keyNpub.textContent = key.npub;
    
    // Set avatar or initials
    if (key.profile_pic) {
        keyAvatar.src = key.profile_pic;
        keyAvatar.classList.remove('hidden');
        keyInitials.classList.add('hidden');
    } else {
        keyAvatar.classList.add('hidden');
        keyInitials.textContent = key.display_name.charAt(0).toUpperCase();
        keyInitials.classList.remove('hidden');
    }
}

async function showAddKeyModal() {
    try {
        const result = await window.electronAPI.showAddKeyModal();
        if (result) {
            await loadKeys();
            showSuccess('Key added successfully!');
        }
    } catch (error) {
        console.error('Error adding key:', error);
        showError('Failed to add key');
    }
}

async function deleteSelectedKey() {
    if (!selectedKey) return;
    
    const confirmed = confirm(`Are you sure you want to delete the key "${selectedKey.display_name}"?\n\nThis will remove the key from your environment variables.`);
    if (!confirmed) return;
    
    try {
        await window.electronAPI.deleteKey(selectedKey.env_variable);
        await loadKeys();
        showSuccess('Key deleted successfully!');
    } catch (error) {
        console.error('Error deleting key:', error);
        showError('Failed to delete key');
    }
}

// Relay Management Functions
async function loadRelays() {
    try {
        const relayData = await window.electronAPI.getRelays();
        relays = relayData;
        updateRelayList();
    } catch (error) {
        console.error('Error loading relays:', error);
        showError('Failed to load relay configuration');
    }
}

function updateRelayList() {
    relayList.innerHTML = '';
    
    relays.forEach(relay => {
        const relayItem = document.createElement('div');
        relayItem.className = 'relay-item';
        relayItem.innerHTML = `
            <span class="relay-url">${relay.url}</span>
            <span class="relay-status">${relay.type || 'Default'}</span>
        `;
        relayList.appendChild(relayItem);
    });
}

async function showEditRelaysModal() {
    try {
        const result = await window.electronAPI.showEditRelaysModal(relays);
        if (result) {
            relays = result;
            updateRelayList();
            showSuccess('Relay configuration updated!');
        }
    } catch (error) {
        console.error('Error editing relays:', error);
        showError('Failed to update relay configuration');
    }
}

// Publishing Functions
async function publishContent(mode) {
    if (!selectedKey) {
        showError('Please select a bot key first');
        return;
    }
    
    if (generatedFiles.length === 0) {
        showError('Please parse a document first');
        return;
    }
    
    try {
        const result = await window.electronAPI.publishContent({
            mode: mode,
            key: selectedKey,
            files: generatedFiles,
            relays: relays
        });
        
        if (result.success) {
            showSuccess(`Content ${mode} completed successfully!`);
        } else {
            showError(`Publish failed: ${result.error}`);
        }
    } catch (error) {
        console.error('Error publishing content:', error);
        showError('Failed to publish content');
    }
}

// Show success message
function showSuccess(message) {
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

// Bot Configuration Management Functions
async function loadBotConfigurations() {
    try {
        const result = await window.electronAPI.getBotConfigs();
        if (result.success) {
            availableBots = result.configs;
            updateBotSelector();
        } else {
            console.error('Failed to load bot configurations:', result.error);
            showNotification('Failed to load bot configurations', 'error');
        }
    } catch (error) {
        console.error('Error loading bot configurations:', error);
        showNotification('Error loading bot configurations', 'error');
    }
}

function updateBotSelector() {
    selectedBotSelect.innerHTML = '<option value="">Select a bot configuration...</option>';
    
    if (availableBots.length === 0) {
        selectedBotSelect.innerHTML = '<option value="">No bot configurations found</option>';
        return;
    }
    
    availableBots.forEach(bot => {
        const option = document.createElement('option');
        option.value = bot.name;
        option.textContent = bot.name;
        selectedBotSelect.appendChild(option);
    });
}

function updateBotInfo() {
    if (!selectedBot) {
        botInfo.classList.add('hidden');
        editBotBtn.disabled = true;
        deleteBotBtn.disabled = true;
        return;
    }
    
    botInfo.classList.remove('hidden');
    editBotBtn.disabled = false;
    deleteBotBtn.disabled = false;
    
    // Parse the YAML content to extract bot information
    try {
        const lines = selectedBot.content.split('\n');
        let botNameValue = selectedBot.name;
        let botDescriptionValue = 'No description';
        let botEventKindValue = 'Unknown';
        
        for (const line of lines) {
            const trimmed = line.trim();
            if (trimmed.startsWith('bot_name:')) {
                botNameValue = trimmed.replace('bot_name:', '').trim().replace(/['"]/g, '');
            } else if (trimmed.startsWith('bot_description:')) {
                botDescriptionValue = trimmed.replace('bot_description:', '').trim().replace(/['"]/g, '');
            } else if (trimmed.startsWith('event_kind:')) {
                const kind = trimmed.replace('event_kind:', '').trim();
                switch (kind) {
                    case '30023':
                        botEventKindValue = 'Long-form Article (30023)';
                        break;
                    case '30041':
                        botEventKindValue = 'Publication Content (30041)';
                        break;
                    case '30818':
                        botEventKindValue = 'Wiki Article (30818)';
                        break;
                    default:
                        botEventKindValue = `Event Kind ${kind}`;
                }
            }
        }
        
        botName.textContent = botNameValue;
        botDescription.textContent = botDescriptionValue;
        botEventKind.textContent = botEventKindValue;
    } catch (error) {
        console.error('Error parsing bot configuration:', error);
        botName.textContent = selectedBot.name;
        botDescription.textContent = 'Error parsing configuration';
        botEventKind.textContent = 'Unknown';
    }
}

async function createBot() {
    const name = newBotNameInput.value.trim();
    const type = newBotTypeSelect.value;
    
    if (!name) {
        showNotification('Please enter a bot name', 'error');
        return;
    }
    
    // Validate bot name (alphanumeric and hyphens only)
    if (!/^[a-z0-9-]+$/.test(name)) {
        showNotification('Bot name can only contain lowercase letters, numbers, and hyphens', 'error');
        return;
    }
    
    // Check if bot already exists
    if (availableBots.some(bot => bot.name === name)) {
        showNotification('A bot with this name already exists', 'error');
        return;
    }
    
    try {
        // Get template for the selected type
        const templateResult = await window.electronAPI.getBotConfigTemplate(type);
        if (!templateResult.success) {
            showNotification('Failed to get bot template', 'error');
            return;
        }
        
        // Replace placeholder values in template
        let config = templateResult.template;
        config = config.replace(/my-bot/g, name);
        config = config.replace(/My Bot/g, name.charAt(0).toUpperCase() + name.slice(1).replace(/-/g, ' '));
        
        // Save the bot configuration
        const saveResult = await window.electronAPI.saveBotConfig({
            name: name,
            content: config
        });
        
        if (saveResult.success) {
            showNotification('Bot configuration created successfully!', 'success');
            closeCreateBotModal();
            await loadBotConfigurations();
        } else {
            showNotification('Failed to create bot configuration: ' + saveResult.error, 'error');
        }
    } catch (error) {
        console.error('Error creating bot:', error);
        showNotification('Error creating bot configuration', 'error');
    }
}

async function editBot() {
    if (!selectedBot) return;
    
    editBotNameInput.value = selectedBot.name;
    editBotConfigTextarea.value = selectedBot.content;
    editBotModal.classList.remove('hidden');
}

async function saveBotEdit() {
    if (!selectedBot) return;
    
    try {
        const saveResult = await window.electronAPI.saveBotConfig({
            name: selectedBot.name,
            content: editBotConfigTextarea.value
        });
        
        if (saveResult.success) {
            showNotification('Bot configuration updated successfully!', 'success');
            closeEditBotModal();
            await loadBotConfigurations();
            // Re-select the current bot
            selectedBotSelect.value = selectedBot.name;
            onBotSelectionChange();
        } else {
            showNotification('Failed to update bot configuration: ' + saveResult.error, 'error');
        }
    } catch (error) {
        console.error('Error saving bot edit:', error);
        showNotification('Error saving bot configuration', 'error');
    }
}

async function deleteBot() {
    if (!selectedBot) return;
    
    const confirmed = confirm(`Are you sure you want to delete the bot configuration "${selectedBot.name}"? This action cannot be undone.`);
    if (!confirmed) return;
    
    try {
        const deleteResult = await window.electronAPI.deleteBotConfig(selectedBot.name);
        
        if (deleteResult.success) {
            showNotification('Bot configuration deleted successfully!', 'success');
            selectedBot = null;
            await loadBotConfigurations();
            updateBotInfo();
        } else {
            showNotification('Failed to delete bot configuration: ' + deleteResult.error, 'error');
        }
    } catch (error) {
        console.error('Error deleting bot:', error);
        showNotification('Error deleting bot configuration', 'error');
    }
}

function onBotSelectionChange() {
    const selectedName = selectedBotSelect.value;
    selectedBot = availableBots.find(bot => bot.name === selectedName) || null;
    updateBotInfo();
}

function openCreateBotModal() {
    newBotNameInput.value = '';
    newBotTypeSelect.value = 'longform';
    newBotConfigTextarea.value = 'Loading template...';
    createBotModal.classList.remove('hidden');
    
    // Load template for default type
    loadBotTemplate('longform');
}

function closeCreateBotModal() {
    createBotModal.classList.add('hidden');
}

function closeEditBotModal() {
    editBotModal.classList.add('hidden');
}

async function loadBotTemplate(type) {
    try {
        const result = await window.electronAPI.getBotConfigTemplate(type);
        if (result.success) {
            newBotConfigTextarea.value = result.template;
        } else {
            newBotConfigTextarea.value = 'Error loading template';
        }
    } catch (error) {
        console.error('Error loading template:', error);
        newBotConfigTextarea.value = 'Error loading template';
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    updateParseButton();
    
    // Set default output directory to current directory
    outputDirInput.value = 'Current directory';
    
    // Load initial data
    await loadBotConfigurations();
    await loadRelays();
});
