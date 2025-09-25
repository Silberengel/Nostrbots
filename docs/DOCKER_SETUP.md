# Docker Setup for Nostrbots with next-orly v0.8.4

This guide explains how to build and run the Nostrbots Docker image that includes next-orly v0.8.4 relay server and the complete Nostrbots environment.

## 🐳 What's Included

The Docker image includes:

- **next-orly v0.8.4**: Built from the official GitHub release
- **Complete Nostrbots environment**: PHP, dependencies, and all scripts
- **Hello World Bot**: Pre-configured and ready to run
- **Automatic setup**: All scripts run automatically on container startup
- **Relay integration**: Local next-orly relay for testing and development

## 🚀 Quick Start

### Option 1: Build and Run Locally

```bash
# Build the Docker image
bash scripts/build-next-orly-docker.sh

# Run the container
docker run --rm -p 3334:3334 silberengel/next-orly:latest
```

### Option 2: Use Docker Compose

```bash
# Start with docker-compose
docker-compose -f docker-compose.next-orly.yml up

# Or run in background
docker-compose -f docker-compose.next-orly.yml up -d
```

### Option 3: Pull from Docker Hub

```bash
# Pull the pre-built image
docker pull silberengel/next-orly:latest

# Run the container
docker run --rm -p 3334:3334 silberengel/next-orly:latest
```

## 🔧 Building the Image

### Basic Build

```bash
# Build with default tag (latest)
bash scripts/build-next-orly-docker.sh
```

### Custom Tag

```bash
# Build with custom tag
bash scripts/build-next-orly-docker.sh --tag v0.8.4
```

### Build and Push to Docker Hub

```bash
# Build and push to Docker Hub
bash scripts/build-next-orly-docker.sh --tag v0.8.4 --push
```

### Build Options

- `--tag TAG`: Set custom Docker image tag (default: latest)
- `--push`: Push image to Docker Hub after building
- `--no-cache`: Build without using Docker cache

## 🧪 Testing the Image

### Quick Test

```bash
# Run quick functionality test
bash scripts/test-next-orly-docker.sh --quick
```

### Full Test

```bash
# Run complete test including container startup
bash scripts/test-next-orly-docker.sh
```

### Test Options

- `--tag TAG`: Test specific image tag
- `--quick`: Run quick test only (no full container test)

## 🏃‍♂️ Running the Container

### Basic Run

```bash
# Run with default settings
docker run --rm -p 3334:3334 silberengel/next-orly:latest
```

### With Encrypted Key (Same as Jenkins)

```bash
# First, generate encrypted key (same system as Jenkins)
bash scripts/01-generate-key.sh

# Run with encrypted key
docker run --rm -p 3334:3334 \
  -e NOSTR_BOT_KEY_ENCRYPTED=$NOSTR_BOT_KEY_ENCRYPTED \
  silberengel/next-orly:latest
```

### With Persistent Data

```bash
# Run with persistent data volumes
docker run --rm -p 3334:3334 \
  -v $(pwd)/orly-data:/app/orly-data \
  -v $(pwd)/logs:/app/logs \
  -v $(pwd)/bots:/app/bots \
  silberengel/next-orly:latest
```

### Background Mode

```bash
# Run in background
docker run -d --name nostrbots \
  -p 3334:3334 \
  -v $(pwd)/orly-data:/app/orly-data \
  silberengel/next-orly:latest

# Check logs
docker logs nostrbots

# Stop container
docker stop nostrbots
```

## 🔧 Configuration

### Key Consistency with Jenkins

**Important**: The Docker setup uses the same encrypted key system as Jenkins for consistency. This means:

- Both Docker and Jenkins use the same `NOSTR_BOT_KEY_ENCRYPTED` with the same default password
- Content published from Docker will appear to come from the same Nostr identity as Jenkins
- You can seamlessly switch between Docker and Jenkins deployments
- Keys are generated once with `bash scripts/01-generate-key.sh` and used everywhere
- No password needs to be stored in environment variables (uses deterministic default)

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `NOSTR_BOT_KEY_ENCRYPTED` | Encrypted Nostr key (same as Jenkins) | **Required** |
| `ORLY_PORT` | next-orly relay port | 3334 |
| `TZ` | Timezone | UTC |

### Ports

- **3334**: next-orly relay WebSocket and HTTP interface
- **8080**: Reserved for future web interface

### Volumes

- `/app/orly-data`: next-orly relay data directory
- `/app/logs`: Nostrbots application logs
- `/app/bots`: Bot configurations and output
- `/app/tmp`: Temporary files

## 📋 What Happens on Startup

When the container starts, it automatically:

1. **Generates or uses provided Nostr key**
2. **Starts next-orly relay** on port 3334
3. **Updates relay configuration** to use local relay
4. **Configures hello-world bot** for local testing
5. **Runs dry-run test** to validate setup
6. **Publishes hello-world content** to the relay
7. **Verifies publication** by querying the relay
8. **Keeps container running** for continued use

## 🔍 Monitoring and Logs

### View Container Logs

```bash
# View real-time logs
docker logs -f container_name

# View last 100 lines
docker logs --tail 100 container_name
```

### Access Relay Interface

- **Web Interface**: http://localhost:3334
- **WebSocket**: ws://localhost:3334

### Check Published Content

```bash
# View published content
docker exec container_name ls -la /app/bots/hello-world/output/

# View latest content
docker exec container_name cat /app/bots/hello-world/output/latest.adoc
```

## 🛠️ Development

### Building from Source

The Dockerfile builds next-orly from the official v0.8.4 release:

```dockerfile
# Clone and build next-orly from v0.8.4 release
RUN git clone https://github.com/mleku/next.orly.dev.git next-orly && \
    cd next-orly && \
    git checkout v0.8.4 && \
    go mod download && \
    CGO_ENABLED=1 GOOS=linux go build -gcflags "all=-N -l" -o relay .
```

### Customizing the Build

To modify the build process:

1. Edit the `Dockerfile`
2. Update the `ORLY_VERSION` build argument
3. Rebuild the image

### Local Development

For local development with live code changes:

```bash
# Run with mounted source code
docker run --rm -p 3334:3334 \
  -v $(pwd):/app \
  silberengel/next-orly:latest
```

## 🐛 Troubleshooting

### Common Issues

**Container fails to start:**
```bash
# Check container logs
docker logs container_name

# Check if ports are available
netstat -tulpn | grep 3334
```

**Relay not accessible:**
```bash
# Test relay connectivity
curl http://localhost:3334

# Check if container is running
docker ps | grep next-orly
```

**Bot publishing fails:**
```bash
# Check bot logs
docker exec container_name cat /app/logs/bot.log

# Verify Nostr key
docker exec container_name echo $NOSTR_BOT_KEY
```

### Debug Mode

Run container with debug output:

```bash
docker run --rm -p 3334:3334 \
  -e DEBUG=true \
  silberengel/next-orly:latest
```

### Reset Everything

```bash
# Stop and remove all containers
docker stop $(docker ps -q) 2>/dev/null || true
docker rm $(docker ps -aq) 2>/dev/null || true

# Remove volumes
docker volume prune -f

# Rebuild image
bash scripts/build-next-orly-docker.sh --no-cache
```

## 📚 Additional Resources

- [next-orly GitHub Repository](https://github.com/mleku/next.orly.dev)
- [next-orly v0.8.4 Release](https://github.com/mleku/next.orly.dev/releases/tag/v0.8.4)
- [Docker Hub Repository](https://hub.docker.com/r/silberengel/next-orly)
- [Nostrbots Documentation](README.md)

## 🤝 Contributing

To contribute to the Docker setup:

1. Fork the repository
2. Make your changes to the Dockerfile or scripts
3. Test with `bash scripts/test-next-orly-docker.sh`
4. Submit a pull request

## 📄 License

This Docker setup is part of the Nostrbots project and is licensed under the MIT License.
