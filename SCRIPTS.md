# Nostrbots Scripts

This document describes the simplified script structure for Nostrbots.

## Main Scripts

### `setup-env.sh` - Environment Setup
Sets up the .env file with proper keys:
- Creates .env from template
- Generates new Nostr keys
- Validates key generation

```bash
# Setup environment and generate keys
./setup-env.sh
```

### `setup.sh` - Complete Setup
The main setup script that handles everything:
- Checks Docker installation
- Generates keys if needed (creates .env file)
- Sets up Jenkins with Docker support
- **Creates Jenkins pipeline job automatically**
- **Verifies pipeline job exists and works**
- Tests the setup

```bash
# Complete setup
./setup.sh

# Setup only Jenkins
./setup.sh jenkins

# Test the setup
./setup.sh test

# Generate keys only
./setup.sh keys

# Create Jenkins pipeline job
./setup.sh create-pipeline

# Check if Jenkins pipeline exists
./setup.sh check-pipeline
```

### `run-pipeline.sh` - Run Pipeline Locally
Runs the nostrbots pipeline locally for testing:

```bash
./run-pipeline.sh
```

### `cleanup.sh` - Cleanup Everything
Removes all containers, volumes, and temporary files:

```bash
# Basic cleanup (stops containers, removes volumes, cleans temp files)
./cleanup.sh

# Comprehensive cleanup (includes generated content and full Docker cleanup)
./cleanup.sh --all
```

**What the cleanup script does:**
- **Basic cleanup**: Stops containers, removes volumes, cleans temporary files
- **With `--all` flag**: Also removes generated content and performs comprehensive Docker cleanup (removes all unused images, containers, networks, and build cache)

### `test-env-loading.sh` - Test Environment Loading
Tests that all containers can read from the .env file:

```bash
# Test environment loading
./test-env-loading.sh
```

## Core Files

### `nostrbots-script.sh`
The main pipeline script that:
- Generates content using the nostrbots Docker image
- Publishes content to Nostr relays
- Handles environment variables properly

### `Jenkinsfile`
The Jenkins pipeline definition that:
- Uses Docker containers for content generation
- Publishes to Nostr relays
- Includes proper error handling

## Removed Scripts

The following redundant scripts have been removed and consolidated:

**Root directory:**
- `install-docker-in-jenkins.sh` → Integrated into `setup.sh`
- `restart-jenkins-fixed.sh` → Integrated into `setup.sh`
- `test-nostrbots-docker.sh` → Integrated into `setup.sh`
- `test-docker.sh` → Integrated into `setup.sh`
- `create-jenkins-job.sh` → No longer needed
- `setup-jenkins-job.groovy` → No longer needed
- `setup-keys.sh` → Integrated into `setup.sh`
- `load-keys.sh` → Integrated into `setup.sh`

**Scripts directory:**
- `01-generate-key.sh` → Integrated into `setup.sh`
- `02-setup-jenkins.sh` → Integrated into `setup.sh`
- `02a-create-nostrbots-user.sh` → No longer needed
- `02b-setup-distributed-builds.sh` → No longer needed
- `03-verify-environment.sh` → Integrated into `setup.sh`
- `04-create-pipeline.sh` → No longer needed
- `05-verify-setup.sh` → Integrated into `setup.sh`
- `setup-jenkins-complete.sh` → Integrated into `setup.sh`
- `docker-full-setup.sh` → Integrated into `setup.sh`
- `quick-test.sh` → Integrated into `setup.sh`
- `analyze-script-redundancy.sh` → No longer needed

**Jenkins config files:**
- `basic-nostrbots-job-config.xml` → Use `Jenkinsfile` instead
- `freestyle-job-config.xml` → Use `Jenkinsfile` instead
- `minimal-job-config.xml` → Use `Jenkinsfile` instead
- `script-based-job-config.xml` → Use `Jenkinsfile` instead
- `simple-job-config.xml` → Use `Jenkinsfile` instead
- `simple-nostrbots-job-config.xml` → Use `Jenkinsfile` instead
- `nostrbots-pipeline.xml` → Use `Jenkinsfile` instead

## Quick Start

1. **Setup environment and generate keys:**
   ```bash
   ./setup-env.sh
   ```

2. **Complete setup:**
   ```bash
   ./setup.sh
   ```

3. **Test the pipeline:**
   ```bash
   ./run-pipeline.sh
   ```

4. **Access Jenkins:**
   - Go to http://localhost:8080
   - Login with admin/admin (or your custom credentials from .env)
   - Run the 'nostrbots-pipeline' job

5. **Cleanup when done:**
   ```bash
   ./cleanup.sh
   ```

## Environment Configuration

The project now uses a `.env` file for configuration instead of hardcoded values:

- **Keys are generated automatically** when you run `./setup-env.sh`
- **Environment variables are loaded** from `.env` file by all scripts
- **Docker Compose uses environment variables** instead of hardcoded values
- **Jenkins credentials** can be customized in the `.env` file
- **All containers can read from the .env file** (mounted as read-only volume)
- **Environment variables are passed to Docker containers** automatically

### Environment Variables

Key environment variables in `.env`:
- `NOSTR_BOT_KEY_ENCRYPTED` - Your encrypted Nostr private key
- `NOSTR_BOT_NPUB` - Your Nostr public key
- `JENKINS_ADMIN_ID` - Jenkins admin username (default: admin)
- `JENKINS_ADMIN_PASSWORD` - Jenkins admin password (default: admin)
- `NOSTRBOTS_PASSWORD` - Nostrbots password (default: nostrbots123)

## Remaining Scripts

The following scripts are kept for specific purposes:

**Root directory:**
- `generate-key.php` - PHP script for key generation
- `generate-unified-keys.sh` - Key generation script
- `nostrbots.php` - Main nostrbots application
- `run-tests.php` - Test runner

**Scripts directory:**
- `01-install-orly.sh` - Orly relay installation
- `02-configure-orly.sh` - Orly relay configuration
- `03-verify-orly.sh` - Orly relay verification
- `build-next-orly-docker.sh` - Orly Docker build
- `setup-orly-complete.sh` - Complete Orly setup
- `test-hello-world.sh` - Hello world bot test
- `test-next-orly-docker.sh` - Orly Docker test

These remaining scripts are for Orly relay setup and testing, which are separate from the main nostrbots pipeline.
