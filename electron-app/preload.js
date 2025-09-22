const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  // File operations
  selectFile: () => ipcRenderer.invoke('select-file'),
  selectOutputDirectory: () => ipcRenderer.invoke('select-output-directory'),
  readFile: (filePath) => ipcRenderer.invoke('read-file', filePath),
  listFiles: (directory) => ipcRenderer.invoke('list-files', directory),
  
  // Document parsing
  parseDocument: (options) => ipcRenderer.invoke('parse-document', options),
  
  // System operations
  openFile: (filePath) => ipcRenderer.invoke('open-file', filePath),
  openDirectory: (directoryPath) => ipcRenderer.invoke('open-directory', directoryPath),
  
  // Platform info
  platform: process.platform
});
