const { app, BrowserWindow, ipcMain, dialog, shell } = require('electron');
const path = require('path');
const { exec } = require('child_process');
const fs = require('fs');

let mainWindow;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload.js')
    },
    icon: path.join(__dirname, 'assets', 'icon.png'),
    title: 'Nostrbots Desktop - Document Parser'
  });

  // Load the HTML file
  mainWindow.loadFile('index.html');

  // Open DevTools in development
  if (process.argv.includes('--dev')) {
    mainWindow.webContents.openDevTools();
  }

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  }
});

// IPC handlers
ipcMain.handle('select-file', async () => {
  const result = await dialog.showOpenDialog(mainWindow, {
    properties: ['openFile'],
    filters: [
      { name: 'Documents', extensions: ['adoc', 'asciidoc', 'md', 'markdown'] },
      { name: 'Asciidoc', extensions: ['adoc', 'asciidoc'] },
      { name: 'Markdown', extensions: ['md', 'markdown'] },
      { name: 'All Files', extensions: ['*'] }
    ]
  });

  if (!result.canceled && result.filePaths.length > 0) {
    return result.filePaths[0];
  }
  return null;
});

ipcMain.handle('select-output-directory', async () => {
  const result = await dialog.showOpenDialog(mainWindow, {
    properties: ['openDirectory']
  });

  if (!result.canceled && result.filePaths.length > 0) {
    return result.filePaths[0];
  }
  return null;
});

ipcMain.handle('parse-document', async (event, options) => {
  return new Promise((resolve, reject) => {
    const { filePath, contentKind, outputDir, hierarchical, contentLevel } = options;
    
    // Build command
    let command = `php parse-document.php "${filePath}" ${contentKind}`;
    
    if (hierarchical) {
      command += ' --hierarchical';
      if (contentLevel) {
        command += ` --content-level ${contentLevel}`;
      }
    }
    
    if (outputDir) {
      command += ` "${outputDir}"`;
    }

    console.log('Executing command:', command);

    exec(command, { 
      cwd: path.join(__dirname, '..'),
      env: { ...process.env }
    }, (error, stdout, stderr) => {
      if (error) {
        console.error('Error:', error);
        reject({
          success: false,
          error: error.message,
          stderr: stderr
        });
        return;
      }

      if (stderr) {
        console.warn('Warning:', stderr);
      }

      resolve({
        success: true,
        output: stdout,
        stderr: stderr
      });
    });
  });
});

ipcMain.handle('read-file', async (event, filePath) => {
  try {
    const content = fs.readFileSync(filePath, 'utf8');
    return { success: true, content };
  } catch (error) {
    return { success: false, error: error.message };
  }
});

ipcMain.handle('list-files', async (event, directory) => {
  try {
    const files = fs.readdirSync(directory);
    return { success: true, files };
  } catch (error) {
    return { success: false, error: error.message };
  }
});

ipcMain.handle('open-file', async (event, filePath) => {
  try {
    await shell.openPath(filePath);
    return { success: true };
  } catch (error) {
    return { success: false, error: error.message };
  }
});

ipcMain.handle('open-directory', async (event, directoryPath) => {
  try {
    await shell.openPath(directoryPath);
    return { success: true };
  } catch (error) {
    return { success: false, error: error.message };
  }
});

// Key management handlers
ipcMain.handle('get-all-bot-keys', async () => {
  return new Promise((resolve, reject) => {
    // Use the load-keys script to get keys with proper environment
    const command = './load-keys.sh';
    
    exec(command, { 
      cwd: path.join(__dirname, '..'),
      env: { ...process.env } // Inherit all environment variables
    }, (error, stdout, stderr) => {
      
      if (error) {
        console.error('Error getting keys:', error);
        reject({ success: false, error: error.message });
        return;
      }
      
      try {
        // Parse the actual output from the PHP script
        const keys = [];
        const lines = stdout.split('\n');
        
        let currentKey = null;
        for (const line of lines) {
          const trimmedLine = line.trim();
          
          // Look for key environment variable lines
          if (trimmedLine.startsWith('🔐 NOSTR_BOT_KEY')) {
            const envVarMatch = trimmedLine.match(/🔐 (NOSTR_BOT_KEY\d+)/);
            if (envVarMatch) {
              currentKey = {
                env_variable: envVarMatch[1],
                npub: '',
                display_name: 'Unknown',
                profile_pic: null
              };
            }
          }
          // Look for NPub lines
          else if (trimmedLine.startsWith('NPub:') && currentKey) {
            const npubMatch = trimmedLine.match(/NPub: (.+)/);
            if (npubMatch) {
              currentKey.npub = npubMatch[1];
            }
          }
          // Look for display name lines
          else if (trimmedLine.startsWith('Display Name:') && currentKey) {
            const nameMatch = trimmedLine.match(/Display Name: (.+)/);
            if (nameMatch) {
              currentKey.display_name = nameMatch[1];
              // Add the key to our list and reset
              keys.push(currentKey);
              currentKey = null;
            }
          }
        }
        
        resolve(keys);
      } catch (parseError) {
        console.error('Parse error:', parseError);
        reject({ success: false, error: parseError.message });
      }
    });
  });
});

ipcMain.handle('show-add-key-modal', async () => {
  return new Promise((resolve, reject) => {
    // Generate a new key using the PHP script
    exec('php manage-keys.php generate', { cwd: path.join(__dirname, '..') }, (error, stdout, stderr) => {
      if (error) {
        console.error('Error generating key:', error);
        reject({ success: false, error: error.message });
        return;
      }
      
      try {
        // Parse the output to get the new key information
        const lines = stdout.split('\n');
        let newKey = null;
        
        for (const line of lines) {
          const trimmedLine = line.trim();
          
          if (trimmedLine.startsWith('🔐 New key generated:')) {
            const envVarMatch = trimmedLine.match(/🔐 New key generated: (NOSTR_BOT_KEY\d+)/);
            if (envVarMatch) {
              newKey = {
                env_variable: envVarMatch[1],
                npub: '',
                display_name: 'New Bot Key',
                profile_pic: null
              };
            }
          } else if (trimmedLine.startsWith('   NPub:') && newKey) {
            const npubMatch = trimmedLine.match(/   NPub: (.+)/);
            if (npubMatch) {
              newKey.npub = npubMatch[1];
            }
          }
        }
        
        if (newKey) {
          console.log('Generated new key:', newKey);
          resolve({ success: true, key: newKey });
        } else {
          resolve({ success: false, error: 'Could not parse generated key information' });
        }
      } catch (parseError) {
        console.error('Parse error:', parseError);
        reject({ success: false, error: parseError.message });
      }
    });
  });
});

ipcMain.handle('delete-key', async (event, envVar) => {
  return new Promise((resolve, reject) => {
    // Delete the key using the PHP script
    exec(`php manage-keys.php delete ${envVar}`, { cwd: path.join(__dirname, '..') }, (error, stdout, stderr) => {
      if (error) {
        console.error('Error deleting key:', error);
        reject({ success: false, error: error.message });
        return;
      }
      
      console.log(`Deleted key: ${envVar}`);
      resolve({ success: true });
    });
  });
});

// Relay management handlers
ipcMain.handle('get-relays', async () => {
  try {
    const relaysPath = path.join(__dirname, '..', 'src', 'relays.yml');
    const content = fs.readFileSync(relaysPath, 'utf8');
    
    // Simple YAML parsing for the relays file
    const relays = [];
    const lines = content.split('\n');
    let currentSection = null;
    
    for (const line of lines) {
      const trimmed = line.trim();
      if (trimmed.startsWith('default-relays:') || trimmed.startsWith('test-relays:')) {
        currentSection = trimmed.replace(':', '').replace('-', '');
      } else if (trimmed.startsWith('- ') && currentSection) {
        const url = trimmed.substring(2);
        relays.push({
          url: url,
          type: currentSection === 'defaultrelays' ? 'Default' : 'Test'
        });
      }
    }
    
    return relays;
  } catch (error) {
    console.error('Error loading relays:', error);
    return [];
  }
});

ipcMain.handle('show-edit-relays-modal', async (event, currentRelays) => {
  // For now, return the current relays unchanged
  // In a full implementation, this would show a modal to edit relays
  const result = await dialog.showMessageBox(mainWindow, {
    type: 'info',
    title: 'Edit Relays',
    message: 'Relay editing feature coming soon!',
    detail: 'This will open a relay configuration dialog.',
    buttons: ['OK']
  });
  
  return result.response === 0 ? currentRelays : null;
});

// Bot configuration management handlers
ipcMain.handle('get-bot-configs', async () => {
  try {
    const botDataPath = path.join(__dirname, '..', 'botData');
    const configs = [];
    
    if (fs.existsSync(botDataPath)) {
      const entries = fs.readdirSync(botDataPath, { withFileTypes: true });
      
      for (const entry of entries) {
        if (entry.isDirectory()) {
          const configPath = path.join(botDataPath, entry.name, 'config.yml');
          if (fs.existsSync(configPath)) {
            try {
              const content = fs.readFileSync(configPath, 'utf8');
              configs.push({
                name: entry.name,
                path: configPath,
                content: content
              });
            } catch (error) {
              console.error(`Error reading config for ${entry.name}:`, error);
            }
          }
        }
      }
    }
    
    return { success: true, configs };
  } catch (error) {
    console.error('Error getting bot configs:', error);
    return { success: false, error: error.message };
  }
});

ipcMain.handle('save-bot-config', async (event, configData) => {
  try {
    const { name, content } = configData;
    const botDataPath = path.join(__dirname, '..', 'botData');
    const botPath = path.join(botDataPath, name);
    const configPath = path.join(botPath, 'config.yml');
    
    // Create directory if it doesn't exist
    if (!fs.existsSync(botPath)) {
      fs.mkdirSync(botPath, { recursive: true });
    }
    
    // Write the config file
    fs.writeFileSync(configPath, content, 'utf8');
    
    return { success: true };
  } catch (error) {
    console.error('Error saving bot config:', error);
    return { success: false, error: error.message };
  }
});

ipcMain.handle('delete-bot-config', async (event, configName) => {
  try {
    const botDataPath = path.join(__dirname, '..', 'botData');
    const botPath = path.join(botDataPath, configName);
    
    if (fs.existsSync(botPath)) {
      fs.rmSync(botPath, { recursive: true, force: true });
      return { success: true };
    } else {
      return { success: false, error: 'Bot configuration not found' };
    }
  } catch (error) {
    console.error('Error deleting bot config:', error);
    return { success: false, error: error.message };
  }
});

ipcMain.handle('get-bot-config-template', async (event, contentType) => {
  try {
    let template = '';
    
    switch (contentType) {
      case 'longform':
        template = `# Bot metadata
bot_name: "My Article Bot"
bot_description: "Publishes articles about Nostr"

# Event kind
event_kind: 30023

# Identity
npub:
  environment_variable: "NOSTR_BOT_KEY"
  public_key: "npub1..."  # Will be filled automatically when you run the bot

# Content
title: "My Article Title"
summary: "A brief description of the article"
topics: ["nostr", "article"]

# Load content from file
content_files:
  markdown: "botData/myBot/article.md"

# Relay selection
relays: "favorite-relays"  # or "all" or specific URL

# Optional: Create notification
create_notification: true`;
        break;
        
      case 'publication':
        template = `# Bot metadata
bot_name: "My Publication Bot"
bot_description: "Publishes publication content"

# Event kind
event_kind: 30041

# Identity
npub:
  environment_variable: "NOSTR_BOT_KEY"
  public_key: "npub1..."  # Will be filled automatically when you run the bot

# Content
title: "My Publication Chapter"
summary: "A chapter in a larger publication"

# Load content from file
content_files:
  content: "botData/myBot/chapter.md"

# Relay selection
relays: "favorite-relays"`;
        break;
        
      case 'wiki':
        template = `# Bot metadata
bot_name: "My Wiki Bot"
bot_description: "Publishes wiki articles"

# Event kind
event_kind: 30818

# Identity
npub:
  environment_variable: "NOSTR_BOT_KEY"
  public_key: "npub1..."  # Will be filled automatically when you run the bot

# Content
title: "My Wiki Article"
summary: "A collaborative wiki article"

# Load content from file
content_files:
  asciidoc: "botData/myBot/wiki-article.adoc"

# Wiki-specific options
static_d_tag: true      # No timestamp in d-tag
normalize_d_tag: true   # Apply NIP-54 normalization

# Relay selection
relays: "favorite-relays"`;
        break;
        
      default:
        template = `# Bot metadata
bot_name: "My Bot"
bot_description: "A Nostr bot"

# Event kind
event_kind: 30023

# Identity
npub:
  environment_variable: "NOSTR_BOT_KEY"
  public_key: "npub1..."  # Will be filled automatically when you run the bot

# Content
title: "My Content"
summary: "A brief description"

# Load content from file
content_files:
  markdown: "botData/myBot/content.md"

# Relay selection
relays: "favorite-relays"`;
    }
    
    return { success: true, template };
  } catch (error) {
    console.error('Error getting bot config template:', error);
    return { success: false, error: error.message };
  }
});

// Publishing handlers
ipcMain.handle('publish-content', async (event, options) => {
  const { mode, key, files, relays } = options;
  
  return new Promise((resolve, reject) => {
    let command;
    
    if (mode === 'dry-run') {
      command = `php test-publish.php --dry-run --key ${key.env_variable}`;
    } else if (mode === 'test') {
      command = `php test-publish.php --test --key ${key.env_variable}`;
    } else {
      command = `php test-publish.php --key ${key.env_variable}`;
    }
    
    // Add file arguments
    files.forEach(file => {
      command += ` "${file}"`;
    });
    
    console.log('Executing publish command:', command);
    
    exec(command, { 
      cwd: path.join(__dirname, '..'),
      env: { ...process.env }
    }, (error, stdout, stderr) => {
      if (error) {
        console.error('Publish error:', error);
        resolve({
          success: false,
          error: error.message,
          stderr: stderr
        });
        return;
      }
      
      resolve({
        success: true,
        output: stdout,
        stderr: stderr
      });
    });
  });
});
