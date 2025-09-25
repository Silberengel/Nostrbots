# Deterministic Password Update

## 🔄 Changes Made

All scripts have been updated to use **only the deterministic default password** for key encryption/decryption. No more password parameters or environment variables.

### ✅ What Changed

1. **Removed password parameters** from all scripts
2. **Removed NOSTR_BOT_KEY_PASSWORD** environment variable
3. **Simplified key management** - only `NOSTR_BOT_KEY_ENCRYPTED` needed
4. **Consistent security model** - same deterministic password everywhere

### 🔧 Updated Files

#### Scripts
- `scripts/01-generate-key.sh` - Removed `--password` parameter
- `scripts/docker-full-setup.sh` - Removed `--password` parameter
- `scripts/02-setup-jenkins.sh` - Removed `NOSTR_BOT_KEY_PASSWORD` env var

#### Docker
- `Dockerfile` - Uses deterministic password for decryption
- `docker-compose.next-orly.yml` - Removed password env var
- `scripts/build-next-orly-docker.sh` - Updated usage examples
- `scripts/test-next-orly-docker.sh` - Removed password references

#### Documentation
- `docs/JENKINS_SETUP.md` - Updated Jenkins decryption code
- `docs/USING_EXISTING_KEYS.md` - Removed password references
- `docs/DOCKER_SETUP.md` - Updated environment variables
- `docs/KEY_CONSISTENCY_UPDATE.md` - Updated to reflect changes

### 🚀 New Usage Pattern

```bash
# 1. Generate encrypted key (uses deterministic password)
bash scripts/01-generate-key.sh

# 2. Run Docker with just the encrypted key
docker run --rm -p 3334:3334 \
  -e NOSTR_BOT_KEY_ENCRYPTED=$NOSTR_BOT_KEY_ENCRYPTED \
  silberengel/next-orly:latest

# 3. Or use docker-compose
docker-compose -f docker-compose.next-orly.yml up
```

### 🔑 Key Benefits

1. **Simplified Security**: No password management needed
2. **Consistent Behavior**: Same password everywhere
3. **Reduced Complexity**: Fewer environment variables
4. **Better Security**: No passwords in environment variables
5. **Easier Deployment**: One less thing to configure

### ⚠️ Breaking Changes

- **Removed `--password` parameter** from all scripts
- **Removed `NOSTR_BOT_KEY_PASSWORD`** environment variable
- **All scripts now use deterministic default password**
- **Jenkins decryption updated** to use `generate-key.php`

### 🧪 Testing

The test script automatically generates keys with the deterministic password:

```bash
bash scripts/test-next-orly-docker.sh
```

### 🔒 Security Model

The system now uses a **deterministic default password** derived from:
```php
hash('sha256', 'nostrbots-jenkins-default-password-2024-secure')
```

This ensures:
- **Consistency** across all deployments
- **No password storage** in environment variables
- **Same security level** as before
- **Simplified key management**

### 📋 Migration Guide

If you have existing deployments:

1. **Remove** `NOSTR_BOT_KEY_PASSWORD` from environment variables
2. **Keep** `NOSTR_BOT_KEY_ENCRYPTED` (it's compatible)
3. **Update** any custom scripts to remove password parameters
4. **Test** with the new simplified setup

The encrypted keys remain compatible - only the password handling has changed.
