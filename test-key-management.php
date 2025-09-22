<?php

/**
 * Test Key Management Functionality
 * 
 * Tests the simplified KeyManager with single key support and profile fetching.
 */

require __DIR__ . '/src/bootstrap.php';

use Nostrbots\Utils\KeyManager;
use Symfony\Component\Yaml\Yaml;

function testKeyManager(): void
{
    echo "🔑 Testing Single Key Manager" . PHP_EOL;
    echo "=============================" . PHP_EOL . PHP_EOL;
    
    $keyManager = new KeyManager();
    
    // Test 1: Generate a new key
    echo "Test 1: Generate New Key" . PHP_EOL;
    echo "------------------------" . PHP_EOL;
    try {
        $result = $keyManager->generateNewBotKey('NOSTR_BOT_KEY');
        echo "✅ Key generation successful!" . PHP_EOL;
        echo "   Environment Variable: {$result['env_variable']}" . PHP_EOL;
        echo "   NPub: {$result['key_set']['bechPublicKey']}" . PHP_EOL;
        echo "   Hex Private Key: " . substr($result['key_set']['hexPrivateKey'], 0, 16) . "..." . PHP_EOL . PHP_EOL;
    } catch (\Exception $e) {
        echo "❌ Key generation failed: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    }
    
    // Test 2: Get current key
    echo "Test 2: Get Current Key" . PHP_EOL;
    echo "----------------------" . PHP_EOL;
    try {
        $key = $keyManager->getBotKey('NOSTR_BOT_KEY');
        if ($key) {
            echo "✅ Found configured key" . PHP_EOL;
            echo "   🔐 NOSTR_BOT_KEY" . PHP_EOL;
            echo "      NPub: {$key['npub']}" . PHP_EOL;
            echo "      Display Name: {$key['display_name']}" . PHP_EOL;
            if ($key['profile_pic']) {
                echo "      Profile Pic: {$key['profile_pic']}" . PHP_EOL;
            }
        } else {
            echo "❌ No NOSTR_BOT_KEY found" . PHP_EOL;
        }
        echo PHP_EOL;
    } catch (\Exception $e) {
        echo "❌ Failed to get key: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    }
    
    // Test 3: Validate current key
    echo "Test 3: Validate Current Key" . PHP_EOL;
    echo "----------------------------" . PHP_EOL;
    try {
        $isValid = $keyManager->validateBotKey('NOSTR_BOT_KEY');
        if ($isValid) {
            echo "✅ NOSTR_BOT_KEY is valid" . PHP_EOL;
            
            $key = $keyManager->getBotKey('NOSTR_BOT_KEY');
            if ($key) {
                echo "   NPub: {$key['npub']}" . PHP_EOL;
                echo "   Display Name: {$key['display_name']}" . PHP_EOL;
            }
        } else {
            echo "❌ NOSTR_BOT_KEY is not valid or not found" . PHP_EOL;
        }
        echo PHP_EOL;
    } catch (\Exception $e) {
        echo "❌ Key validation failed: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    }
    
    // Test 5: Test profile fetching
    echo "Test 5: Profile Fetching" . PHP_EOL;
    echo "-----------------------" . PHP_EOL;
    try {
        $testNpub = 'npub1test1234567890abcdefghijklmnopqrstuvwxyz';
        $profile = $keyManager->fetchProfile($testNpub);
        echo "✅ Profile fetching successful (mock implementation)" . PHP_EOL;
        echo "   Profile fields available: " . implode(', ', array_keys($profile)) . PHP_EOL . PHP_EOL;
    } catch (\Exception $e) {
        echo "❌ Profile fetching failed: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    }
    
    // Test 6: Test key set generation
    echo "Test 6: Key Set Generation" . PHP_EOL;
    echo "-------------------------" . PHP_EOL;
    try {
        $keySet = $keyManager->generateNewKeySet();
        echo "✅ Key set generation successful!" . PHP_EOL;
        echo "   Hex Private Key: " . substr($keySet['hexPrivateKey'], 0, 16) . "..." . PHP_EOL;
        echo "   Hex Public Key: " . substr($keySet['hexPublicKey'], 0, 16) . "..." . PHP_EOL;
        echo "   Bech32 Private Key: " . substr($keySet['bechPrivateKey'], 0, 20) . "..." . PHP_EOL;
        echo "   Bech32 Public Key: " . substr($keySet['bechPublicKey'], 0, 20) . "..." . PHP_EOL . PHP_EOL;
    } catch (\Exception $e) {
        echo "❌ Key set generation failed: " . $e->getMessage() . PHP_EOL . PHP_EOL;
    }
    
    echo "🎉 Key Management Tests Completed!" . PHP_EOL;
}

function testCLIKeyManagement(): void
{
    echo "🖥️  Testing CLI Key Management" . PHP_EOL;
    echo "=============================" . PHP_EOL . PHP_EOL;
    
    // Test the manage-keys.php script
    echo "Testing manage-keys.php list command..." . PHP_EOL;
    $output = shell_exec('php manage-keys.php list 2>&1');
    echo "Output:" . PHP_EOL;
    echo $output . PHP_EOL;
    
    echo "Testing manage-keys.php generate command..." . PHP_EOL;
    $output = shell_exec('php manage-keys.php generate 2>&1');
    echo "Output:" . PHP_EOL;
    echo $output . PHP_EOL;
}

function testPublishingFunctionality(): void
{
    echo "🚀 Testing Publishing Functionality" . PHP_EOL;
    echo "===================================" . PHP_EOL . PHP_EOL;
    
    // Create a test config file
    $testConfig = [
        'title' => 'Test Article',
        'kind' => 30023,
        'npub' => [
            'environment_variable' => 'NOSTR_BOT_KEY',
            'public_key' => 'npub1test1234567890abcdefghijklmnopqrstuvwxyz'
        ],
        'content' => 'This is a test article content for publishing tests.'
    ];
    
    $testFile = 'test-config.yml';
    $yamlContent = Yaml::dump($testConfig);
    file_put_contents($testFile, $yamlContent);
    
    echo "Created test config file: {$testFile}" . PHP_EOL . PHP_EOL;
    
    // Test dry-run mode
    echo "Testing dry-run mode..." . PHP_EOL;
    $output = shell_exec("php test-publish.php --dry-run {$testFile} 2>&1");
    echo "Output:" . PHP_EOL;
    echo $output . PHP_EOL;
    
    // Test test mode
    echo "Testing test mode..." . PHP_EOL;
    $output = shell_exec("php test-publish.php --test {$testFile} 2>&1");
    echo "Output:" . PHP_EOL;
    echo $output . PHP_EOL;
    
    // Clean up
    unlink($testFile);
    echo "Cleaned up test file." . PHP_EOL . PHP_EOL;
}

// Run all tests
echo "🧪 Nostrbots Key Management & Publishing Tests" . PHP_EOL;
echo "==============================================" . PHP_EOL . PHP_EOL;

testKeyManager();
testCLIKeyManagement();
testPublishingFunctionality();

echo "✅ All tests completed!" . PHP_EOL;
