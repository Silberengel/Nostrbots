# Testing Guide for Nostrbots

## Test Relay System

Nostrbots includes a comprehensive test relay system to ensure safe testing without affecting production relays.

### Test Relay Configuration

The test relays are configured in `src/relays.yml`:

```yaml
test-relays:
  - wss://freelay.sovbit.host
  - ws://localhost:7777
```

### Using Test Relays

#### Method 1: Test Mode Configuration
Add `test_mode: true` to your bot configuration:

```yaml
bot_name: "My Test Bot"
test_mode: true
# ... other configuration
```

#### Method 2: CLI Test Flag
Use the `--test` flag to override any configuration:

```bash
php nostrbots.php myBot --test
php nostrbots.php myBot --test --verbose
php nostrbots.php myBot --test --dry-run
```

#### Method 3: Pre-configured Test Bot
Use the included test bot:

```bash
php nostrbots.php testBot
php nostrbots.php testBot --verbose
```

### Test Features

- **Safe Testing**: Uses dedicated test relays
- **Fallback Support**: Falls back to `wss://freelay.sovbit.host` if other relays fail
- **Validation**: Full validation system works with test relays
- **Performance Monitoring**: All performance features available in test mode
- **Error Handling**: Comprehensive error handling and retry logic

### Test Scripts

#### Core Functionality Test
```bash
php test-core-functionality.php
```
Tests all core systems including relay connectivity.

#### Publishing Test
```bash
php test-publish.php
```
Tests actual publishing to test relays with validation.

#### Edge Case Tests
```bash
php run-tests.php
```
Comprehensive test suite for edge cases and error scenarios.

### Test Relay Behavior

1. **Primary Test Relays**: Uses relays from `test-relays` section
2. **Fallback Logic**: If test relays fail, tries default relay
3. **Final Fallback**: If all fail, tries `wss://freelay.sovbit.host`
4. **Error Handling**: Graceful failure with detailed error messages

### Safety Features

- ✅ **No Production Impact**: Test relays are separate from production
- ✅ **Clear Indication**: Test mode is clearly indicated in output
- ✅ **Validation**: Full validation system ensures events are published correctly
- ✅ **Retry Logic**: Robust retry system handles network issues
- ✅ **Performance Monitoring**: Track performance and memory usage

### Example Test Workflow

```bash
# 1. Test configuration validation
php nostrbots.php myBot --test --dry-run --verbose

# 2. Test actual publishing
php nostrbots.php myBot --test --verbose

# 3. Test with performance monitoring
php nostrbots.php myBot --test --profile

# 4. Run comprehensive tests
php run-tests.php
```

### Troubleshooting

If test relays are not accessible:
- Check network connectivity
- Verify relay URLs in `src/relays.yml`
- Use `--dry-run` for configuration testing
- Check error messages for specific relay issues

The system will automatically fall back to available relays and provide clear error messages if no relays are accessible.
