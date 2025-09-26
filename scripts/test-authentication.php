<?php
/**
 * Test script to verify Nostr authentication functionality
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Nostrbots\Utils\KeyManager;

class AuthenticationTester
{
    private KeyManager $keyManager;

    public function __construct()
    {
        $this->keyManager = new KeyManager();
    }

    private function log(string $message): void
    {
        $timestamp = date('c');
        echo "[{$timestamp}] {$message}" . PHP_EOL;
    }

    public function testKeyRetrieval(): bool
    {
        $this->log("Testing key retrieval...");
        
        try {
            $privateKey = $this->keyManager->ensureKeyExists();
            
            if ($privateKey) {
                $this->log("✅ Private key retrieved successfully");
                $this->log("Key length: " . strlen($privateKey) . " characters");
                $this->log("Key format: " . (ctype_xdigit($privateKey) ? "Hex" : "Other"));
                return true;
            } else {
                $this->log("❌ No private key found");
                return false;
            }
        } catch (Exception $e) {
            $this->log("❌ Key retrieval failed: " . $e->getMessage());
            return false;
        }
    }

    public function testKeyValidation(): bool
    {
        $this->log("Testing key validation...");
        
        try {
            $privateKey = $this->keyManager->ensureKeyExists();
            
            if (!$privateKey) {
                $this->log("❌ No private key to validate");
                return false;
            }
            
            // Check if it's an nsec key and convert to hex
            if (str_starts_with($privateKey, 'nsec')) {
                $this->log("Key is in nsec format, converting to hex...");
                $key = new \swentel\nostr\Key\Key();
                $hexKey = $key->convertToHex($privateKey);
                
                if (strlen($hexKey) === 64 && ctype_xdigit($hexKey)) {
                    $this->log("✅ nsec key converted to hex successfully");
                    $this->log("Hex key length: " . strlen($hexKey));
                    return true;
                } else {
                    $this->log("❌ Failed to convert nsec to valid hex");
                    return false;
                }
            }
            
            // Basic validation for hex keys
            if (strlen($privateKey) !== 64) {
                $this->log("❌ Invalid key length: " . strlen($privateKey));
                return false;
            }
            
            if (!ctype_xdigit($privateKey)) {
                $this->log("❌ Key is not valid hex");
                return false;
            }
            
            $this->log("✅ Key validation passed");
            return true;
        } catch (Exception $e) {
            $this->log("❌ Key validation failed: " . $e->getMessage());
            return false;
        }
    }

    public function testPublicKeyDerivation(): bool
    {
        $this->log("Testing public key derivation...");
        
        try {
            $privateKey = $this->keyManager->ensureKeyExists();
            
            if (!$privateKey) {
                $this->log("❌ No private key to derive public key from");
                return false;
            }
            
            // Use the nostr-php library to derive public key
            $key = new \swentel\nostr\Key\Key();
            $publicKey = $key->getPublicKey($privateKey);
            $npub = $key->convertPublicKeyToBech32($publicKey);
            
            if (strlen($publicKey) === 64 && ctype_xdigit($publicKey)) {
                $this->log("✅ Public key derived successfully");
                $this->log("Public key: " . substr($publicKey, 0, 16) . "...");
                $this->log("NPUB: " . substr($npub, 0, 20) . "...");
                return true;
            } else {
                $this->log("❌ Invalid public key derived");
                return false;
            }
        } catch (Exception $e) {
            $this->log("❌ Public key derivation failed: " . $e->getMessage());
            return false;
        }
    }

    public function testAuthEventCreation(): bool
    {
        $this->log("Testing AuthEvent creation...");
        
        try {
            $privateKey = $this->keyManager->ensureKeyExists();
            
            if (!$privateKey) {
                $this->log("❌ No private key for AuthEvent creation");
                return false;
            }
            
            // Create a test AuthEvent
            $relayUrl = "wss://test-relay.example.com";
            $challenge = "test-challenge-123";
            
            $authEvent = new \swentel\nostr\Nip42\AuthEvent($relayUrl, $challenge);
            $signer = new \swentel\nostr\Sign\Sign();
            $signer->signEvent($authEvent, $privateKey);
            
            // Verify the event was signed
            if ($authEvent->getSignature() && strlen($authEvent->getSignature()) === 128) {
                $this->log("✅ AuthEvent created and signed successfully");
                $this->log("Event ID: " . substr($authEvent->getId(), 0, 16) . "...");
                $this->log("Signature: " . substr($authEvent->getSignature(), 0, 16) . "...");
                return true;
            } else {
                $this->log("❌ AuthEvent signature invalid");
                return false;
            }
        } catch (Exception $e) {
            $this->log("❌ AuthEvent creation failed: " . $e->getMessage());
            return false;
        }
    }

    public function runAllTests(): bool
    {
        $this->log("Starting Nostr authentication tests...");
        
        $tests = [
            'Key Retrieval' => [$this, 'testKeyRetrieval'],
            'Key Validation' => [$this, 'testKeyValidation'],
            'Public Key Derivation' => [$this, 'testPublicKeyDerivation'],
            'AuthEvent Creation' => [$this, 'testAuthEventCreation']
        ];
        
        $allPassed = true;
        
        foreach ($tests as $testName => $testMethod) {
            $this->log("Running test: {$testName}");
            if (!$testMethod()) {
                $allPassed = false;
                $this->log("❌ Test failed: {$testName}");
                break;
            }
        }
        
        if ($allPassed) {
            $this->log("✅ All authentication tests passed!");
            $this->log("The event indexer should be able to authenticate with protected relays.");
        } else {
            $this->log("❌ Some authentication tests failed");
            $this->log("Check your key configuration and try again.");
        }
        
        return $allPassed;
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new AuthenticationTester();
    $success = $tester->runAllTests();
    exit($success ? 0 : 1);
}
