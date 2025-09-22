#!/usr/bin/env node

// Simple test script to verify the GUI components work
const { exec } = require('child_process');
const path = require('path');

console.log('🧪 Testing Nostrbots Desktop GUI...\n');

// Test 1: Check if Electron can start
console.log('1. Testing Electron startup...');
exec('npm start', { cwd: __dirname, timeout: 10000 }, (error, stdout, stderr) => {
    if (error) {
        console.log('❌ Electron startup failed:', error.message);
    } else {
        console.log('✅ Electron startup successful');
    }
});

// Test 2: Check if dependencies are installed
console.log('2. Checking dependencies...');
const fs = require('fs');
const requiredFiles = [
    'node_modules/marked/marked.min.js',
    'node_modules/asciidoctor/dist/browser/asciidoctor.min.js'
];

let allDepsPresent = true;
requiredFiles.forEach(file => {
    const filePath = path.join(__dirname, file);
    if (fs.existsSync(filePath)) {
        console.log(`✅ ${file} exists`);
    } else {
        console.log(`❌ ${file} missing`);
        allDepsPresent = false;
    }
});

if (allDepsPresent) {
    console.log('✅ All dependencies present');
} else {
    console.log('❌ Some dependencies missing - run npm install');
}

// Test 3: Check if test files exist
console.log('3. Checking test files...');
const testFiles = [
    'test-article.adoc',
    'test-markdown.md',
    'test-discrete-headers.adoc',
    'test-rendering.js'
];

testFiles.forEach(file => {
    const filePath = path.join(__dirname, file);
    if (fs.existsSync(filePath)) {
        console.log(`✅ ${file} exists`);
    } else {
        console.log(`❌ ${file} missing`);
    }
});

console.log('\n🎯 To test the GUI:');
console.log('1. Run: npm run dev');
console.log('2. Open DevTools (F12)');
console.log('3. In console, run: testRendering.runAll()');
console.log('4. Try loading test-article.adoc, test-markdown.md, or test-discrete-headers.adoc');
console.log('5. Switch between Raw and Rendered tabs');

console.log('\n📝 Test checklist:');
console.log('- [ ] App starts without errors');
console.log('- [ ] File selection works');
console.log('- [ ] Raw tab shows source content');
console.log('- [ ] Rendered tab shows formatted content');
console.log('- [ ] Markdown files render correctly');
console.log('- [ ] Asciidoc files render correctly');
console.log('- [ ] Error messages display properly');
console.log('- [ ] Parse button works');
console.log('- [ ] Generated files can be opened');
