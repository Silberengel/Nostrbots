# Nostrbots

A secure, enterprise-grade PHP tool for publishing content to Nostr from AsciiDoc and Markdown documents, with automated bots, Jenkins CI/CD, and comprehensive security features.

## 🚀 Features

### Core Publishing
- **Direct Publishing**: Publish from AsciiDoc/Markdown files with embedded metadata
- **Multiple Event Kinds**: 30023 (Long-form), 30040/30041 (Publications), 30818 (Wiki)
- **Bot System**: Automated content generation and scheduled publishing
- **Template Engine**: Dynamic content generation with PHP templates

### Security & Encryption
- **🔒 Password-Based Encryption**: AES-256-CBC with PBKDF2 key derivation
- **🔒 Secure Memory Management**: Automatic cleanup of sensitive data
- **🔒 Non-Root Containers**: Jenkins and bots run as non-privileged users
- **🔒 Network Isolation**: Localhost-only binding and resource limits
- **🔒 Environment-Only Secrets**: No sensitive data written to disk

### CI/CD & Automation
- **Jenkins Integration**: Complete CI/CD pipeline with encrypted environment
- **Distributed Builds**: Jenkins agents for scalable bot execution
- **Automated Testing**: Comprehensive test suite with relay verification
- **Docker Support**: Containerized deployment with security hardening

### Development & Testing
- **Orly Relay**: Local Nostr relay for testing and development
- **Test Suite**: Hello world bot with dry-run and publish verification
- **Relay Verification**: Query Orly to confirm event publication
- **Modular Scripts**: Individual setup steps for flexible deployment

## 📋 Supported Event Kinds

| Kind  | Name | Description |
|-------|------|-------------|
| 30023 | Long-form Content | Articles and blog posts (Markdown) |
| 30040 | Publication Index | Table of contents for publications |
| 30041 | Publication Content | Sections/chapters (AsciiDoc) |
| 30818 | Wiki Article | Collaborative wiki articles |

## 🛠️ Quick Start

### Option 1: Complete Automated Setup (Recommended)

**One-command setup** with Jenkins, Orly relay, and pipeline:

```bash
# Clone the repository
git clone <repository-url>
cd Nostrbots

# Run complete automated setup
./setup.sh

# That's it! Everything is set up automatically:
# ✅ Keys generated and encrypted
# ✅ Orly relay started
# ✅ Jenkins started with Docker support
# ✅ Pipeline job created
# ✅ Environment tested
```

### Option 2: Individual Components

For more control over the setup process:

```bash
# Generate keys only
./setup.sh keys

# Setup Jenkins only (includes Orly relay)
./setup.sh jenkins

# Test the setup
./setup.sh test

# Create Jenkins pipeline job
./setup.sh create-pipeline

# Check if pipeline exists
./setup.sh check-pipeline
```

### Option 3: Manual Pipeline Testing

Test the pipeline locally without Jenkins:

```bash
# Run the pipeline locally
./run-pipeline.sh

# Clean up everything
./cleanup.sh

# Clean up everything including generated content
./cleanup.sh --all
```

## 🔐 Security Features

### Password-Based Encryption
- **Default Password**: Secure, deterministic default (no manual setup required)
- **Custom Passwords**: Use your own password with `--password` option
- **PBKDF2 Key Derivation**: Industry-standard key derivation with random salt/IV
- **Memory Protection**: Sensitive data cleared from memory after use

### Container Security
- **Non-Root Users**: Jenkins and bots run as `jenkins` user (UID 1000)
- **No Privilege Escalation**: `no-new-privileges` security option
- **Resource Limits**: Memory and CPU limits to prevent resource exhaustion
- **Network Isolation**: Services bound to localhost only
- **Secure Filesystems**: Temporary filesystems for sensitive operations

### Environment Security
- **No File Storage**: All secrets stored in environment variables only
- **Automatic Cleanup**: Environment variables cleared on script exit
- **Signal Handling**: Secure cleanup on interruption (SIGINT/SIGTERM)
- **Audit Logging**: Security events logged without sensitive data

## 🧪 Testing

### Automated Testing

The setup includes comprehensive automated testing:

```bash
# Test environment loading
./test-env-loading.sh

# Test the complete setup
./setup.sh test

# Test pipeline locally
./run-pipeline.sh
```

### Jenkins Pipeline Testing

Test the complete CI/CD pipeline:

```bash
# 1. Go to Jenkins: http://localhost:8080
# 2. Login: admin/admin
# 3. Go to 'nostrbots-pipeline' job
# 4. Click 'Build Now'

# The pipeline will:
# ✅ Generate Hello World content
# ✅ Decrypt keys securely
# ✅ Publish to Nostr (dry-run first, then real)
# ✅ Verify publication success
```

### Test Features
- **Environment Testing**: Verifies .env file loading and Docker access
- **Key Decryption**: Tests encrypted key handling
- **Content Generation**: Creates test content with timestamps
- **Dry Run Validation**: Tests configuration without publishing
- **Actual Publishing**: Publishes content to Nostr relays
- **Relay Verification**: Confirms events are published to Orly relay

## 🔧 Configuration

### Environment Variables

The system uses a `.env` file for configuration (created automatically):

```bash
# Nostr Bot Configuration (auto-generated)
NOSTR_BOT_KEY_ENCRYPTED=<encrypted_private_key>
NOSTR_BOT_NPUB=<public_key>

# Jenkins Configuration
JENKINS_ADMIN_ID=admin
JENKINS_ADMIN_PASSWORD=admin
NOSTRBOTS_PASSWORD=nostrbots123

# Optional: Custom relay configuration
# NOSTR_RELAYS=wss://relay1.example.com,wss://relay2.example.com
```

### Using Existing Keys

Use your own Nostr key instead of generating a new one:

```bash
# Generate keys with your existing private key
php generate-key.php --key <your_hex_private_key>

# Or use the setup script
./setup.sh keys
```

### Relay Configuration

Configure which relays to use in `src/relays.yml`:

```yaml
test-relays:
  - ws://localhost:3334    # Local Orly relay
  - wss://freelay.sovbit.host  # Public relay

favorite-relays:
  - wss://thecitadel.nostr1.com
  - wss://theforest.nostr1.com
```

## 📁 Project Structure

```
Nostrbots/
├── bots/                          # Bot configurations
│   ├── hello-world/              # Test bot with templates
│   └── daily-office/             # Example bot
├── src/                          # PHP source code
│   ├── Bot/                      # Bot framework
│   ├── EventKinds/               # Event kind handlers
│   ├── Utils/                    # Utilities (KeyManager, RelayManager, etc.)
│   └── relays.yml                # Relay configuration
├── docs/                         # Documentation
├── examples/                     # Example documents
├── .env                          # Environment configuration (auto-generated)
├── env.example                   # Environment template
├── setup.sh                      # Main setup script
├── run-pipeline.sh               # Local pipeline testing
├── cleanup.sh                    # Cleanup script
├── test-env-loading.sh           # Environment testing
├── decrypt-key.php               # Key decryption utility
├── generate-key.php              # Key generation utility
├── nostrbots.php                 # Main publishing script
├── Jenkinsfile                   # Jenkins CI/CD pipeline
├── docker-compose.yml            # Main Docker Compose
├── docker-compose.jenkins.yml    # Jenkins-specific setup
└── Dockerfile                    # Container definition
```

## 🌐 Access Information

After setup, you can access:

- **Jenkins Web Interface**: http://localhost:8080
- **Jenkins Agent**: localhost:50000
- **Orly Relay**: ws://localhost:3334
- **Bot Output**: `bots/*/output/` directory

## 📚 Documentation

- **[Jenkins Setup Guide](docs/JENKINS_SETUP.md)**: Detailed Jenkins configuration
- **[Using Existing Keys](docs/USING_EXISTING_KEYS.md)**: How to use your own Nostr key
- **[Direct Publishing](docs/DIRECT_PUBLISHING.md)**: Publishing without bots
- **[Relay Selection](docs/RELAY_SELECTION.md)**: Choosing and configuring relays

## 🔍 Troubleshooting

### Common Issues

**Setup fails:**
```bash
# Clean up and restart
./cleanup.sh --all
./setup.sh

# Check Docker is running
docker --version
docker compose --version
```

**Jenkins not accessible:**
```bash
# Check if Jenkins is running
docker ps | grep jenkins

# Check Jenkins logs
docker logs jenkins-nostrbots

# Restart Jenkins
docker compose -f docker-compose.jenkins.yml restart jenkins
```

**Pipeline fails with key errors:**
```bash
# Test key decryption
./test-env-loading.sh

# Check .env file
cat .env

# Regenerate keys
./setup.sh keys
```

**Orly relay not responding:**
```bash
# Check if Orly is running
docker ps | grep orly

# Test Orly connection
curl http://localhost:3334

# Restart Orly relay
docker compose -f docker-compose.yml restart orly-relay
```

**Pipeline can't find files:**
```bash
# Check if files exist
ls -la bots/hello-world/output/

# Test content generation
./run-pipeline.sh
```

### Logs and Debugging

- **Jenkins Logs**: `docker logs jenkins-nostrbots`
- **Orly Logs**: `docker logs orly-relay`
- **Pipeline Logs**: Check Jenkins console output
- **Test Output**: `bots/hello-world/output/` for generated content
- **Environment Test**: `./test-env-loading.sh` for environment issues

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run the test suite: `./setup.sh test`
5. Test the pipeline: `./run-pipeline.sh`
6. Submit a pull request to [Silberengel](https://github.com/Silberengel/nostrbots)

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Nostr Protocol**: For the decentralized social protocol
- **Jenkins**: For the CI/CD platform
- **Orly**: For the Nostr relay implementation
- **PHP Community**: For the excellent libraries and tools

---

**Ready to start botting?** Run `./setup.sh` and you'll be publishing to Nostr in minutes! 🚀

## 🎯 What's New

### Recent Improvements
- ✅ **One-command setup**: `./setup.sh` does everything automatically
- ✅ **Automatic key management**: Keys generated and encrypted automatically
- ✅ **Orly relay integration**: Local relay starts with Jenkins
- ✅ **Pipeline automation**: Jenkins pipeline job created automatically
- ✅ **Environment testing**: Comprehensive environment validation
- ✅ **Cleanup tools**: Easy cleanup with `./cleanup.sh --all`
- ✅ **Local testing**: Test pipeline locally with `./run-pipeline.sh`
- ✅ **Fixed key decryption**: Standalone decryption script for Jenkins
- ✅ **Updated relay config**: Correct Orly relay port (3334)
- ✅ **Docker Compose v2**: Updated to modern Docker Compose syntax