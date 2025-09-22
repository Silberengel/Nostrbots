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

  // Load with dev flag if in development mode
  const isDev = process.argv.includes('--dev');
  const url = isDev ? 'index.html?dev=true' : 'index.html';
  mainWindow.loadFile(url);

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

    exec(command, { cwd: path.join(__dirname, '..') }, (error, stdout, stderr) => {
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
    exec('php manage-keys.php list', { cwd: path.join(__dirname, '..') }, (error, stdout, stderr) => {
      if (error) {
        console.error('Error getting keys:', error);
        reject({ success: false, error: error.message });
        return;
      }
      
      try {
        // Parse the output to extract key information
        // For now, return a mock structure - in a real implementation,
        // we'd parse the actual output from the PHP script
        const keys = [];
        
        // Check for environment variables
        for (let i = 1; i <= 10; i++) {
          const envVar = `NOSTR_BOT_KEY${i}`;
          const value = process.env[envVar];
          if (value) {
            keys.push({
              env_variable: envVar,
              npub: `npub1${'0'.repeat(58)}`, // Mock npub
              display_name: `Bot Key ${i}`,
              profile_pic: null
            });
          }
        }
        
        resolve(keys);
      } catch (parseError) {
        reject({ success: false, error: parseError.message });
      }
    });
  });
});

ipcMain.handle('show-add-key-modal', async () => {
  // For now, return a simple confirmation
  // In a full implementation, this would show a modal dialog
  const result = await dialog.showMessageBox(mainWindow, {
    type: 'info',
    title: 'Add New Key',
    message: 'Key generation feature coming soon!',
    detail: 'This will open a key generation dialog where you can create a new bot key.',
    buttons: ['OK']
  });
  
  return result.response === 0;
});

ipcMain.handle('delete-key', async (event, envVar) => {
  // For now, just return success
  // In a full implementation, this would actually remove the environment variable
  console.log(`Would delete key: ${envVar}`);
  return { success: true };
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
    
    exec(command, { cwd: path.join(__dirname, '..') }, (error, stdout, stderr) => {
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
