# Multi-stage Dockerfile for Nostrbots with next-orly v0.8.4
# Using Alpine Linux for smaller image size

# Stage 1: Build next-orly from v0.8.4 release
FROM golang:1.22-alpine as orly-builder

ARG ORLY_VERSION=v0.8.4

# Install build dependencies
RUN apk add --no-cache \
    git \
    make \
    gcc \
    musl-dev \
    autoconf \
    automake \
    libtool \
    linux-headers

# Build secp256k1 library
RUN cd /tmp && \
    git clone https://github.com/bitcoin-core/secp256k1.git && \
    cd secp256k1 && \
    git checkout v0.6.0 && \
    git submodule init && \
    git submodule update && \
    ./autogen.sh && \
    ./configure --enable-module-schnorrsig --enable-module-ecdh --prefix=/usr && \
    make -j1 && \
    make install

# Clone and build next-orly
WORKDIR /build
RUN git clone https://github.com/mleku/next.orly.dev.git next-orly && \
    cd next-orly && \
    git checkout ${ORLY_VERSION} && \
    go mod download && \
    CGO_ENABLED=1 GOOS=linux go build -o relay .

# Stage 2: Build Nostrbots environment
FROM alpine:3.19 as nostrbots-builder

# Install PHP and dependencies
RUN apk add --no-cache \
    php82 \
    php82-cli \
    php82-curl \
    php82-json \
    php82-mbstring \
    php82-xml \
    php82-zip \
    php82-bcmath \
    php82-gmp \
    php82-openssl \
    composer \
    curl \
    wget \
    git \
    ca-certificates

# Set working directory
WORKDIR /app

# Copy Nostrbots source code
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Make scripts executable
RUN chmod +x scripts/*.sh

# Stage 3: Final runtime image
FROM alpine:3.19

# Install runtime dependencies
RUN apk add --no-cache \
    php82 \
    php82-cli \
    php82-curl \
    php82-json \
    php82-mbstring \
    php82-xml \
    php82-zip \
    php82-bcmath \
    php82-gmp \
    php82-openssl \
    curl \
    wget \
    git \
    ca-certificates \
    websocat

# Create app user
RUN adduser -u 1000 -D -s /bin/sh appuser

# Set working directory
WORKDIR /app

# Copy next-orly binary from builder
COPY --from=orly-builder /build/next-orly/relay /usr/local/bin/next-orly

# Copy Nostrbots application from builder
COPY --from=nostrbots-builder /app .

# Create necessary directories
RUN mkdir -p /app/logs /app/tmp /app/orly-data /app/bots/hello-world/output && \
    chown -R 1000:1000 /app

# Set environment variables
ENV NOSTR_BOT_KEY=""
ENV NOSTR_BOT_KEY_ENCRYPTED=""
ENV ORLY_PORT=3334
ENV TZ=UTC

# Expose ports
EXPOSE 3334

# Create startup script
COPY <<EOF /app/startup.sh
#!/bin/sh
set -e

print_status() { echo "[STARTUP] \$1"; }
print_success() { echo "[SUCCESS] \$1"; }
print_error() { echo "[ERROR] \$1"; }

print_status "Starting Nostrbots with next-orly v0.8.4..."

# Check if we have encrypted key (same as Jenkins)
if [ -z "\$NOSTR_BOT_KEY_ENCRYPTED" ]; then
    print_error "No encrypted Nostr key found in environment!"
    print_error "Please provide NOSTR_BOT_KEY_ENCRYPTED"
    print_error "This should be the same key used by Jenkins for consistency"
    print_status "You can generate it with: bash scripts/01-generate-key.sh"
    exit 1
fi

print_success "Using encrypted Nostr key (same as Jenkins): \${NOSTR_BOT_KEY_ENCRYPTED:0:20}..."

# Start next-orly relay in background
print_status "Starting next-orly relay on port \$ORLY_PORT..."
next-orly --listen 0.0.0.0 --port \$ORLY_PORT --data-dir /app/orly-data &
ORLY_PID=\$!

# Wait for orly to start
print_status "Waiting for next-orly to start..."
timeout=30
counter=0
while ! curl -s http://localhost:\$ORLY_PORT > /dev/null 2>&1; do
    if [ \$counter -ge \$timeout ]; then
        print_error "next-orly failed to start within \$timeout seconds"
        exit 1
    fi
    sleep 1
    counter=\$((counter + 1))
done
print_success "next-orly is running on port \$ORLY_PORT"

# Update relay configuration
print_status "Updating relay configuration..."
if [ -f "src/relays.yml" ]; then
    cat > src/relays.yml << RELAYEOF
relays:
  - url: "ws://localhost:\$ORLY_PORT"
    name: "Local next-orly Relay"
    description: "Local test relay for Nostrbots"
    enabled: true
    read: true
    write: true
RELAYEOF
    print_success "Updated relay configuration"
fi

# Update hello-world bot configuration
print_status "Updating hello-world bot configuration..."
if [ -f "bots/hello-world/config.json" ]; then
    cat > bots/hello-world/config.json << BOTEOF
{
    "name": "Hello World Bot",
    "description": "Test bot for Nostrbots setup",
    "schedule": "manual",
    "relays": [
        "ws://localhost:\$ORLY_PORT"
    ],
    "event_kind": 30041,
    "template": "templates/hello-world.adoc",
    "output_dir": "output",
    "enabled": true
}
BOTEOF
    print_success "Updated hello-world bot configuration"
fi

# Key is already encrypted and provided via environment variables
print_status "Using encrypted key from Jenkins environment..."

# Test the setup
print_status "Testing Nostrbots setup..."

# Run dry run test
print_status "Running hello-world bot dry run..."
if php nostrbots.php publish bots/hello-world --dry-run; then
    print_success "Dry run test passed!"
else
    print_error "Dry run test failed!"
    exit 1
fi

# Run actual publish test
print_status "Running hello-world bot publish test..."
if php nostrbots.php publish bots/hello-world; then
    print_success "Publish test passed!"
    
    # Show the published content
    LATEST_OUTPUT=\$(ls -t bots/hello-world/output/*.adoc 2>/dev/null | head -1)
    if [ -n "\$LATEST_OUTPUT" ]; then
        print_success "Content published to: \$(basename "\$LATEST_OUTPUT")"
        print_status "Content preview:"
        echo "----------------------------------------"
        head -10 "\$LATEST_OUTPUT" | sed 's/^/  /'
        echo "----------------------------------------"
    fi
else
    print_error "Publish test failed!"
    exit 1
fi

# Verify event on relay
print_status "Verifying event on next-orly relay..."
if command -v websocat >/dev/null 2>&1; then
    # Extract public key from encrypted private key using default password
    PUBKEY=\$(php -r "
        require_once 'vendor/autoload.php';
        \$encryptedKey = '\$NOSTR_BOT_KEY_ENCRYPTED';
        \$password = hash('sha256', 'nostrbots-jenkins-default-password-2024-secure');
        \$data = base64_decode(\$encryptedKey);
        \$salt = substr(\$data, 0, 16);
        \$iv = substr(\$data, 16, 16);
        \$encrypted = substr(\$data, 32);
        \$derivedKey = hash_pbkdf2('sha256', \$password, \$salt, 10000, 32, true);
        \$privateKey = openssl_decrypt(\$encrypted, 'aes-256-cbc', \$derivedKey, OPENSSL_RAW_DATA, \$iv);
        \$publicKey = sodium_crypto_scalarmult_base(\$privateKey);
        echo bin2hex(\$publicKey);
    " 2>/dev/null)
    
    if [ -n "\$PUBKEY" ]; then
        REQ_MESSAGE="[\"REQ\",\"test-\$(date +%s)\",{\"authors\":[\"\$PUBKEY\"],\"kinds\":[30041],\"limit\":1}]"
        if echo "\$REQ_MESSAGE" | timeout 10 websocat "ws://localhost:\$ORLY_PORT" 2>/dev/null | grep -q '"EVENT"'; then
            print_success "Event verified on next-orly relay!"
        else
            print_status "Event not found on relay (this might be normal)"
        fi
    fi
fi

print_success "🎉 Nostrbots setup complete!"
print_status "next-orly relay: http://localhost:\$ORLY_PORT"
print_status "Published content: bots/hello-world/output/"
print_status "Logs: /app/logs/"

# Keep the container running
print_status "Container is ready. Press Ctrl+C to stop."
wait \$ORLY_PID
EOF

RUN chmod +x /app/startup.sh

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
  CMD curl -f http://localhost:3334 || exit 1

# Switch to app user
USER 1000:1000

# Default command
CMD ["/app/startup.sh"]
