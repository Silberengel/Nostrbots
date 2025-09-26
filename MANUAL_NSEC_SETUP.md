# Manual NSEC Setup Guide

This guide explains how to use your existing Nostr private key (nsec) with Nostrbots instead of generating a new one.

## 🔑 Supported Key Formats

Nostrbots supports both formats for your private key:

- **Hex Format**: 64-character hexadecimal string (e.g., `a1b2c3d4e5f6...`)
- **NSEC Format**: Bech32 encoded nsec (e.g., `nsec1abc123...`)

## 🚀 Quick Setup

### Option 1: Using the Key Generator Script

```bash
# Generate keys using your existing private key (hex format)
php generate-key.php --key YOUR_HEX_PRIVATE_KEY

# Generate keys using your existing nsec
php generate-key.php --key YOUR_NSEC_KEY

# Use custom password for encryption
php generate-key.php --key YOUR_HEX_PRIVATE_KEY --password "your_custom_password"
```

### Option 2: Manual Environment Setup

```bash
# 1. Create .env file
cat > .env << EOF
NOSTR_BOT_KEY_ENCRYPTED=<encrypted_key>
NOSTR_BOT_NPUB=<your_public_key>
EOF

# 2. Set your private key as environment variable
export NOSTR_BOT_KEY=YOUR_HEX_PRIVATE_KEY

# 3. Generate encrypted key and public key
php generate-key.php --key YOUR_HEX_PRIVATE_KEY --encrypt
```

## 📋 Step-by-Step Setup

### Step 1: Prepare Your Key

If you have an nsec, you can use it directly:
```bash
# Your nsec (example)
nsec1abc123def456...
```

If you have a hex private key:
```bash
# Your hex private key (example)
a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456
```

### Step 2: Generate Encrypted Keys

```bash
# Using nsec directly
php generate-key.php --key "nsec1abc123def456..." --encrypt

# Using hex private key
php generate-key.php --key "a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456" --encrypt
```

### Step 3: Verify Setup

```bash
# Test key decryption
php decrypt-key.php

# Test note writing
./note "Hello from my existing key!" --dry-run
```

## 🔧 Advanced Configuration

### Using Custom Passwords

```bash
# Generate with custom password
php generate-key.php --key YOUR_KEY --password "my_secure_password" --encrypt

# Decrypt with custom password
php generate-key.php --decrypt --key ENCRYPTED_KEY --password "my_secure_password"
```

### Multiple Key Slots

```bash
# Use key slot 2
php generate-key.php --key YOUR_KEY --slot 2

# This creates NOSTR_BOT_KEY2 environment variable
```

### Jenkins Integration

```bash
# Generate keys for Jenkins (with encryption)
php generate-key.php --key YOUR_KEY --jenkins
```

## 🧪 Testing Your Setup

### Test Key Decryption

```bash
# Test if your key can be decrypted
php decrypt-key.php

# Should output your nsec
```

### Test Note Writing

```bash
# Test with dry run
./note "Testing my existing key!" --dry-run

# Test actual publishing
./note "Hello from my existing Nostr key!"
```

### Test Bot Publishing

```bash
# Test Hello World bot
php bots/hello-world/generate-content.php
php nostrbots.php bots/hello-world/output/hello-world.adoc --dry-run
```

## 🔒 Security Considerations

### Password Security

- **Default Password**: Uses a deterministic default (secure but predictable)
- **Custom Password**: Recommended for production use
- **Memory Security**: Passwords are cleared from memory after use

### Key Storage

- **Encrypted Storage**: Private keys are encrypted before storage
- **Environment Variables**: Keys are loaded from environment variables
- **No File Storage**: Decrypted keys are never written to files

### Best Practices

1. **Use Strong Passwords**: Choose a strong, unique password
2. **Secure Environment**: Ensure your system is secure
3. **Regular Backups**: Keep encrypted key backups
4. **Test Thoroughly**: Always test with dry runs first

## 🚨 Troubleshooting

### Common Issues

**"Invalid nsec format" error:**
```bash
# Make sure your nsec is valid
# Valid format: nsec1abc123def456...
# Invalid: nsec1abc123 (too short)
```

**"Private key must be 64-character hex" error:**
```bash
# Make sure your hex key is exactly 64 characters
# Valid: a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456
# Invalid: a1b2c3d4e5f6 (too short)
```

**"Failed to decrypt key" error:**
```bash
# Check if you're using the correct password
# Try with default password first
php generate-key.php --decrypt --key ENCRYPTED_KEY

# Then try with custom password
php generate-key.php --decrypt --key ENCRYPTED_KEY --password "your_password"
```

**"Environment variable not set" error:**
```bash
# Make sure .env file exists and has correct values
cat .env

# Or set environment variable manually
export NOSTR_BOT_KEY=YOUR_HEX_PRIVATE_KEY
```

### Key Validation

```bash
# Validate your key format
php -r "
\$key = 'YOUR_KEY_HERE';
if (str_starts_with(\$key, 'nsec')) {
    echo 'NSEC format detected\n';
} elseif (ctype_xdigit(\$key) && strlen(\$key) === 64) {
    echo 'Hex format detected\n';
} else {
    echo 'Invalid key format\n';
}
"
```

## 📚 Examples

### Example 1: Using Existing NSEC

```bash
# Your existing nsec
nsec1abc123def456ghi789jkl012mno345pqr678stu901vwx234yz567890

# Generate encrypted keys
php generate-key.php --key "nsec1abc123def456ghi789jkl012mno345pqr678stu901vwx234yz567890" --encrypt

# Test the setup
./note "Hello from my existing nsec!" --dry-run
```

### Example 2: Using Hex Private Key

```bash
# Your existing hex private key
a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456

# Generate with custom password
php generate-key.php --key "a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456" --password "my_secure_password" --encrypt

# Test the setup
./note "Hello from my hex key!" --dry-run
```

### Example 3: Production Setup

```bash
# 1. Generate encrypted keys
php generate-key.php --key YOUR_KEY --password "production_password" --encrypt

# 2. Test locally
./note "Testing production key" --dry-run

# 3. Deploy to production
sudo ./setup-production-with-elasticsearch.sh
```

## 🔄 Migration from Generated Keys

If you already have generated keys and want to switch to your existing key:

```bash
# 1. Backup current setup
cp .env .env.backup

# 2. Generate new keys with your existing key
php generate-key.php --key YOUR_EXISTING_KEY --encrypt

# 3. Test the new setup
./note "Testing migrated key" --dry-run

# 4. If everything works, remove backup
rm .env.backup
```

## 📞 Support

If you encounter issues:

1. **Check the logs**: Look for error messages in the output
2. **Test with dry runs**: Always test with `--dry-run` first
3. **Validate your key**: Make sure your key format is correct
4. **Check documentation**: Refer to other guides for related issues

---

**Ready to use your existing key?** Run `php generate-key.php --key YOUR_KEY --encrypt` to get started! 🚀
