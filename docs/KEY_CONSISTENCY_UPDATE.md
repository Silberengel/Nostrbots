# Key Consistency Update

## 🔄 Changes Made

The Docker setup has been updated to use the **same encrypted key system as Jenkins** for consistency.

### ✅ What Changed

1. **Docker startup script** now requires `NOSTR_BOT_KEY_ENCRYPTED` (no password needed)
2. **No more auto-generated keys** - Docker uses the same keys as Jenkins
3. **Consistent identity** - Content published from Docker appears from the same Nostr identity as Jenkins
4. **Seamless switching** - You can switch between Docker and Jenkins deployments without changing keys
5. **Secure password handling** - Uses deterministic default password (no env vars needed)

### 🔧 Updated Files

- `Dockerfile` - Updated startup script to use encrypted keys
- `docker-compose.next-orly.yml` - Updated environment variables
- `scripts/build-next-orly-docker.sh` - Updated usage examples
- `scripts/test-next-orly-docker.sh` - Updated to generate test keys
- `docs/DOCKER_SETUP.md` - Updated documentation

### 🚀 New Usage Pattern

```bash
# 1. Generate encrypted keys (same as Jenkins)
bash scripts/01-generate-key.sh

# 2. Run Docker with the same key
docker run --rm -p 3334:3334 \
  -e NOSTR_BOT_KEY_ENCRYPTED=$NOSTR_BOT_KEY_ENCRYPTED \
  silberengel/next-orly:latest

# 3. Or use docker-compose
docker-compose -f docker-compose.next-orly.yml up
```

### 🔑 Key Benefits

1. **Identity Consistency**: Same Nostr identity across all deployments
2. **Simplified Management**: One set of keys for everything
3. **Production Ready**: Same security model as Jenkins
4. **Easy Migration**: Switch between Docker and Jenkins seamlessly

### ⚠️ Breaking Changes

- Docker containers now **require** encrypted key (no auto-generation)
- Must run `bash scripts/01-generate-key.sh` first
- Environment variable `NOSTR_BOT_KEY_ENCRYPTED` is mandatory
- No password environment variable needed (uses deterministic default)

### 🧪 Testing

The test script automatically generates keys if they don't exist:

```bash
bash scripts/test-next-orly-docker.sh
```

This ensures the Docker setup works with the same key system as Jenkins while maintaining backward compatibility for testing.
