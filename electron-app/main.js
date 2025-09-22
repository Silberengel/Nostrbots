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
