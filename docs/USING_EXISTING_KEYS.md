# Using Your Existing Nostr Key

This guide shows how to use your existing Nostr key instead of generating a new one for Jenkins setup.

## Why Use Your Existing Key?

- **Consistency**: Use the same key across all your Nostr applications
- **Identity**: Maintain your established Nostr identity
- **Convenience**: No need to manage multiple keys

## How to Use Your Existing Key

### Method 1: Complete Setup with Existing Key

```bash
# Use your existing key with complete Jenkins setup
bash scripts/setup-jenkins-complete.sh 8080 50000 --key YOUR_EXISTING_KEY
```

### Method 2: Individual Script with Existing Key

```bash
# First, encrypt your existing key
bash scripts/01-generate-key.sh --key YOUR_EXISTING_KEY

# Then run the rest of the setup
bash scripts/02-setup-jenkins.sh
bash scripts/02a-create-nostrbots-user.sh
bash scripts/02b-setup-distributed-builds.sh
bash scripts/03-create-credentials.sh
bash scripts/04-create-pipeline.sh
bash scripts/05-verify-setup.sh
```

### Method 3: Direct PHP Script Usage

```bash
# Encrypt your existing key and get Jenkins environment variables
php generate-key.php --key YOUR_EXISTING_KEY --encrypt --jenkins

# This will output something like:
# NOSTR_BOT_KEY_ENCRYPTED=base64_encoded_encrypted_key
# NOSTR_BOT_KEY_PASSWORD=hex_encryption_key
```

## Key Format Support

The scripts support various key formats:

- **Hex format**: `0123456789abcdef...` (64 characters)
- **Bech32 format**: `nsec1...` (starts with nsec1)
- **Already encrypted**: Base64 encoded encrypted keys

## Security Notes

- ✅ **Your key is encrypted** before being used
- ✅ **No plain text keys** are stored anywhere
- ✅ **Keys only exist in environment variables** during setup
- ✅ **No files are created** with your key
- ✅ **Your original key is never modified**

## Examples

### Using a Hex Key
```bash
bash scripts/setup-jenkins-complete.sh --key 0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef
```

### Using a Bech32 Key
```bash
bash scripts/setup-jenkins-complete.sh --key nsec1xyz...
```

### Force Regeneration
If you want to generate a new key even if one exists in the environment:
```bash
bash scripts/01-generate-key.sh --force
```

## Verification

After setup, you can verify your key is being used correctly:

1. **Check Jenkins logs** for successful key decryption
2. **Run a test build** to ensure the key works
3. **Check the relay** for events published with your key

## Troubleshooting

### Key Format Issues
If you get an error about key format:
```bash
# Check your key format
echo "Your key: ${YOUR_KEY:0:10}..."

# Hex keys should be 64 characters
# Bech32 keys should start with 'nsec1'
```

### Key Already Exists
If you see "key already exists in environment":
```bash
# Use --force to regenerate
bash scripts/01-generate-key.sh --force

# Or use a different key
bash scripts/01-generate-key.sh --key YOUR_OTHER_KEY
```

### Encryption Issues
If encryption fails:
```bash
# Test with the PHP script directly
php generate-key.php --key YOUR_KEY --encrypt --jenkins

# Check for any error messages
```

## Best Practices

1. **Backup your original key** before using it
2. **Test with a throwaway key first** if unsure
3. **Use environment variables** to avoid key exposure in command history
4. **Verify the setup** before using in production

## Environment Variables

When using your existing key, these environment variables are set:

```bash
NOSTR_BOT_KEY_ENCRYPTED=base64_encoded_encrypted_key
NOSTR_BOT_KEY_PASSWORD=hex_encryption_key
```

These are used by Jenkins to decrypt your key at runtime.
