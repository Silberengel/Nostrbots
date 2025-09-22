# Relay Selection Logic

This document explains how Nostrbots selects relays in different scenarios.

## Relay Configuration Structure

The `src/relays.yml` file contains two main sections:

```yaml
default-relays:
  - wss://thecitadel.nostr1.com
  - wss://freelay.sovbit.host

test-relays:
  - ws://localhost:7777
```

## Relay Selection Rules

### 1. **Normal Production Mode**
- **Primary**: Uses relays from `default-relays` section in `relays.yml`
- **Fallback**: If no relays work, falls back to `wss://thecitadel.nostr1.com`
- **Override**: Any relay configuration in bot config overrides defaults

### 2. **Test Mode** (`--test` flag or `test_mode: true`)
- **Primary**: Uses relays from `test-relays` section in `relays.yml`
- **Fallback**: If no test relays work, falls back to `wss://freelay.sovbit.host`
- **Override**: Test mode always overrides other relay configurations

### 3. **Dry Run Mode** (`--dry-run` flag)
- **Behavior**: No relays are contacted at all
- **Purpose**: Validates configuration without publishing
- **Safety**: Completely safe - no network activity

### 4. **Custom Relay Configuration**
- **Direct URLs**: `relays: "wss://custom-relay.com"`
- **Category Names**: `relays: "favorite-relays"` (legacy support)
- **Arrays**: `relays: ["wss://relay1.com", "wss://relay2.com"]`

## Selection Priority

1. **Test Mode Override**: `--test` flag or `test_mode: true` in config
2. **Bot Configuration**: `relays` field in bot config file
3. **Default Section**: `default-relays` from `relays.yml`
4. **Fallback Relay**: Hardcoded fallback based on mode

## Examples

### Production Publishing
```bash
php nostrbots.php myBot
# Uses: default-relays → thecitadel fallback
```

### Test Publishing
```bash
php nostrbots.php myBot --test
# Uses: test-relays → freelay fallback
```

### Safe Configuration Check
```bash
php nostrbots.php myBot --dry-run
# Uses: No relays (dry run)
```

### Custom Relays
```yaml
# In bot config
relays: "wss://my-custom-relay.com"
# Uses: my-custom-relay.com → appropriate fallback
```

## Fallback Logic

### Production Fallback
- **Primary**: `default-relays` from `relays.yml`
- **Fallback**: `wss://thecitadel.nostr1.com`
- **Error**: If both fail, throws exception

### Test Fallback
- **Primary**: `test-relays` from `relays.yml`
- **Fallback**: `wss://freelay.sovbit.host`
- **Error**: If both fail, throws exception

## Safety Features

- ✅ **Dry runs never contact relays**
- ✅ **Test mode uses separate relay set**
- ✅ **Fallback relays ensure connectivity**
- ✅ **Configuration validation before publishing**
- ✅ **Clear error messages for relay failures**

## Configuration Override Hierarchy

1. **CLI Flags**: `--test` flag overrides everything
2. **Bot Config**: `test_mode: true` or `relays:` field
3. **Default Sections**: `default-relays` or `test-relays`
4. **Hardcoded Fallbacks**: `thecitadel` or `freelay`

This ensures that `relays.yml` always takes precedence over hardcoded defaults, but test mode and CLI flags can override everything for safety.
