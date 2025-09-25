# Jenkins Setup for Nostrbots

This document describes how to set up Jenkins for Nostrbots with encrypted key management.

## Overview

The Jenkins setup uses a modular script architecture where each script has a single responsibility and can be run independently or as part of a complete setup process.

## Script Architecture

### Individual Scripts

Each script can be run independently and handles one specific task:

1. **`scripts/01-generate-key.sh`** - Generates and encrypts a Nostr bot key
2. **`scripts/02-setup-jenkins.sh`** - Sets up Jenkins container with encrypted environment variables
3. **`scripts/02a-create-nostrbots-user.sh`** - Creates dedicated nostrbots user with proper permissions
4. **`scripts/02b-setup-distributed-builds.sh`** - Sets up distributed builds with Jenkins agents
5. **`scripts/03-create-credentials.sh`** - Configures Jenkins credentials (using environment variables)
6. **`scripts/04-create-pipeline.sh`** - Creates the Jenkins pipeline job
7. **`scripts/05-verify-setup.sh`** - Verifies that everything is working correctly

### Master Script

**`scripts/setup-jenkins-complete.sh`** - Runs all individual scripts in sequence for a complete setup.

## Quick Start

### Complete Setup (Recommended)

Run the master script to set up everything automatically:

```bash
# Default ports (8080 for Jenkins, 50000 for agent)
bash scripts/setup-jenkins-complete.sh

# Custom ports
bash scripts/setup-jenkins-complete.sh 9080 51000

# Use your existing Nostr key
bash scripts/setup-jenkins-complete.sh 8080 50000 --key YOUR_EXISTING_KEY
```

This will:
1. Generate and encrypt a Nostr bot key
2. Set up Jenkins container with encrypted environment variables
3. Complete Jenkins setup wizard
4. Create the pipeline job
5. Verify everything is working

### Individual Scripts

You can also run individual scripts if you need to:

```bash
# Generate encrypted key
bash scripts/01-generate-key.sh

# Use your existing key
bash scripts/01-generate-key.sh --key YOUR_EXISTING_KEY

# Setup Jenkins container (with custom ports)
bash scripts/02-setup-jenkins.sh 9080 51000

# Configure credentials
bash scripts/03-create-credentials.sh

# Create pipeline job
bash scripts/04-create-pipeline.sh

# Verify setup
bash scripts/05-verify-setup.sh
```

## Security Features

### Encrypted Key Management

- **Nostr keys are encrypted** using AES-256-CBC encryption
- **Keys are only decrypted at runtime** in Jenkins
- **No plain text secrets** are stored in environment variables or logs
- **All keys stored only in environment variables** - no files are created
- **No sensitive data written to disk** for maximum security

### Environment Variables

Jenkins uses these environment variables:
- `NOSTR_BOT_KEY_ENCRYPTED` - The encrypted Nostr bot key

The Jenkinsfile automatically decrypts the key at runtime using the default password:
```groovy
NOSTR_BOT_KEY = sh(
    script: 'php generate-key.php --key "${NOSTR_BOT_KEY_ENCRYPTED}" --decrypt --quiet',
    returnStdout: true
).trim()
```

## File Structure

After setup, these files are created:

```
docker-compose.jenkins.yml     # Jenkins Docker Compose configuration
jenkins-data/                  # Jenkins data directory
```

**Important**: No sensitive files (keys, passwords, secrets) are created. All sensitive data is stored only in environment variables for maximum security.

## Access Information

- **Jenkins URL**: http://localhost:8080 (or custom port if specified)
- **Username**: admin
- **Password**: admin (or check environment for initial password)
- **Pipeline Job**: nostrbots-pipeline

### Port Customization

You can customize the Jenkins ports when running the setup:

```bash
# Use custom ports
bash scripts/setup-jenkins-complete.sh 9080 51000

# This will make Jenkins available at:
# http://localhost:9080
```

## Testing the Setup

1. Visit http://localhost:8080
2. Login with admin/admin
3. Go to the `nostrbots-pipeline` job
4. Click "Build Now" to test the pipeline

## Management Commands

### View Jenkins Logs
```bash
docker logs jenkins-nostrbots
```

### Stop Jenkins
```bash
docker compose -f docker-compose.jenkins.yml down
```

### Restart Jenkins
```bash
docker compose -f docker-compose.jenkins.yml restart
```

### Regenerate Key
```bash
bash scripts/01-generate-key.sh --force
```

## Troubleshooting

### Jenkins Not Starting
- Check if Docker is running: `docker info`
- Check Jenkins logs: `docker logs jenkins-nostrbots`
- Ensure port 8080 is not in use: `netstat -tlnp | grep 8080`

### Pipeline Job Not Visible
- Wait a few minutes for Jenkins to process the job
- Check Jenkins logs for errors
- Verify the job was created: `docker exec jenkins-nostrbots ls -la /var/jenkins_home/jobs/`

### Key Decryption Issues
- Verify environment variables are set: `docker exec jenkins-nostrbots env | grep NOSTR_BOT_KEY`
- Check environment variables: `echo $NOSTR_BOT_KEY_ENCRYPTED` 
- Test decryption manually using the `generate-key.php` script

### Authentication Issues
- Check admin password in environment: `echo $JENKINS_ADMIN_PASSWORD`
- Try both admin:admin and admin with initial password
- Complete Jenkins setup wizard if prompted

## Advanced Usage

### Using Existing Keys

If you already have a Nostr key, you can use it instead of generating a new one:

```bash
# Use your existing key with the setup script
bash scripts/01-generate-key.sh --key YOUR_EXISTING_KEY

# Or use it with the complete setup
bash scripts/setup-jenkins-complete.sh 8080 50000 --key YOUR_EXISTING_KEY

# Or use the PHP script directly
php generate-key.php --key YOUR_EXISTING_KEY --encrypt --jenkins
```

### Custom Encryption Key

Use a specific encryption key:

```bash
php generate-key.php --encryption-key YOUR_ENCRYPTION_KEY --encrypt
```

### Decrypting Keys

To decrypt an existing encrypted key:

```bash
php generate-key.php --key ENCRYPTED_KEY --decrypt
```

## Security Best Practices

1. **Never commit sensitive data** to git (no key files are created)
2. **Keep environment variables secure** and backed up separately
3. **Use strong encryption keys** (the script generates 256-bit keys)
4. **Regularly rotate keys** if needed
5. **Monitor Jenkins logs** for any security issues
6. **Environment variables are session-based** - they don't persist across reboots

## Integration with CI/CD

The Jenkins pipeline automatically:
- Decrypts the Nostr key at runtime
- Builds the Nostrbots Docker image
- Runs tests and validation
- Executes scheduled bot publishing
- Handles errors gracefully

The pipeline runs every hour to check for scheduled bots and can be triggered manually for testing.
