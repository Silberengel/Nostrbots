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

### Option 1: Docker Full Setup (Recommended)

Complete setup with Jenkins, Orly relay, and testing:

```bash
# Clone the repository
git clone <repository-url>
cd Nostrbots

# Run complete Docker setup
bash scripts/docker-full-setup.sh

# Or with custom ports
bash scripts/docker-full-setup.sh --jenkins-port 8080 --orly-port 3334
```

### Option 2: Individual Setup

Step-by-step setup for more control:

```bash
# 1. Generate encrypted Nostr key
bash scripts/01-generate-key.sh

# 2. Setup Jenkins with encrypted environment
bash scripts/02-setup-jenkins.sh

# 3. Create dedicated nostrbots user
bash scripts/02a-create-nostrbots-user.sh

# 4. Setup distributed builds
bash scripts/02b-setup-distributed-builds.sh

# 5. Verify environment
bash scripts/03-verify-environment.sh

# 6. Create Jenkins pipeline
bash scripts/04-create-pipeline.sh

# 7. Verify complete setup
bash scripts/05-verify-setup.sh

# 8. Test with hello world bot
bash scripts/test-hello-world.sh
```

### Option 3: Orly Relay Setup

For local development and testing:

```bash
# Complete Orly setup
bash scripts/setup-orly-complete.sh

# Or individual steps
bash scripts/01-install-orly.sh
bash scripts/02-configure-orly.sh
bash scripts/03-verify-orly.sh
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

### Hello World Bot Test

Comprehensive test of the entire setup:

```bash
# Full test (dry run + publish + relay verification)
bash scripts/test-hello-world.sh

# Dry run only
bash scripts/test-hello-world.sh --dry-run-only

# Publish only
bash scripts/test-hello-world.sh --publish-only

# Skip Orly relay verification
bash scripts/test-hello-world.sh --no-orly-verify
```

### Quick Test

Fast validation for CI/CD:

```bash
# Quick validation
bash scripts/quick-test.sh
```

### Test Features
- **Dry Run Validation**: Tests bot configuration without publishing
- **Actual Publishing**: Publishes content to Nostr relays
- **Relay Verification**: Queries Orly to confirm event publication
- **Content Verification**: Checks output files and timestamps
- **Public Key Extraction**: Derives pubkey from private key for verification

## 🔧 Configuration

### Using Existing Keys

Use your favorite Nostr key instead of generating a new one:

```bash
# Use existing key with default password
bash scripts/01-generate-key.sh --key <your_hex_private_key>

# Use existing key with custom password
bash scripts/01-generate-key.sh --key <your_hex_private_key> --password "your_password"
```

### Custom Ports

Customize ports for Jenkins and Orly:

```bash
# Jenkins on custom ports
bash scripts/02-setup-jenkins.sh 8081 50001

# Orly on custom port
bash scripts/01-install-orly.sh 3335
```

### Environment Variables

Key environment variables (set automatically by scripts):

```bash
# Encrypted Nostr key and password
NOSTR_BOT_KEY_ENCRYPTED=<encrypted_key>

# Jenkins configuration
JENKINS_PORT=8080
AGENT_PORT=50000
JENKINS_ADMIN_PASSWORD=<admin_password>
NOSTRBOTS_PASSWORD=<bot_user_password>

# Orly configuration
ORLY_PORT=3334
```

## 📁 Project Structure

```
Nostrbots/
├── bots/                          # Bot configurations
│   ├── hello-world/              # Test bot
│   └── daily-office/             # Example bot
├── scripts/                      # Setup and utility scripts
│   ├── 01-generate-key.sh        # Key generation and encryption
│   ├── 02-setup-jenkins.sh       # Jenkins container setup
│   ├── 02a-create-nostrbots-user.sh # Dedicated user creation
│   ├── 02b-setup-distributed-builds.sh # Distributed builds
│   ├── 03-verify-environment.sh  # Environment verification
│   ├── 04-create-pipeline.sh     # Jenkins pipeline creation
│   ├── 05-verify-setup.sh        # Complete setup verification
│   ├── test-hello-world.sh       # Comprehensive test suite
│   ├── quick-test.sh             # Fast validation
│   └── docker-full-setup.sh      # Complete Docker setup
├── src/                          # PHP source code
├── docs/                         # Documentation
├── examples/                     # Example documents
├── Dockerfile                    # Container definition
├── docker-compose.yml            # Basic Docker Compose
├── docker-compose.full.yml       # Complete setup
├── Jenkinsfile                   # Jenkins CI/CD pipeline
└── generate-key.php              # Key management utility
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

**Jenkins not accessible:**
```bash
# Check if Jenkins is running
docker ps | grep jenkins

# Check Jenkins logs
docker logs jenkins-nostrbots

# Restart Jenkins
docker compose -f docker-compose.jenkins.yml restart jenkins
```

**Key decryption fails:**
```bash
# Verify environment variables
echo $NOSTR_BOT_KEY_ENCRYPTED

# Test decryption manually
php generate-key.php --key $NOSTR_BOT_KEY_ENCRYPTED --decrypt
```

**Orly relay not responding:**
```bash
# Check if Orly is running
docker ps | grep orly

# Test Orly connection
curl http://localhost:3334

# Install websocat for proper testing
apt install websocat  # Ubuntu/Debian
brew install websocat # macOS
```

### Logs and Debugging

- **Jenkins Logs**: `docker logs jenkins-nostrbots`
- **Bot Logs**: `logs/` directory
- **Security Logs**: Check system logs for `[SECURITY]` entries
- **Test Output**: `bots/hello-world/output/` for test results

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run the test suite: `bash scripts/test-hello-world.sh`
5. Submit a pull request to [Silberengel](https://github.com/Silberengel/nostrbots)

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Nostr Protocol**: For the decentralized social protocol
- **Jenkins**: For the CI/CD platform
- **Orly**: For the Nostr relay implementation
- **PHP Community**: For the excellent libraries and tools

---

**Ready to start botting?** Run `bash scripts/docker-full-setup.sh` and you'll be publishing to Nostr in minutes! 🚀