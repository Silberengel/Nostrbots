# Nostrbots Docker Image
# Multi-bot publishing system for Nostr

FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    jq \
    libzip-dev \
    libgmp-dev \
    libxml2-dev \
    su-exec \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    gmp \
    xml \
    zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Create directories for bot configurations and outputs
RUN mkdir -p /app/bots /app/logs /app/tmp

# Set permissions
RUN chmod +x nostrbots.php generate-key.php run-tests.php

# Security: Create jenkins user (will be activated by entrypoint)
RUN useradd -m -u 1000 -s /bin/bash jenkins || true

# Create entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD php nostrbots.php --help > /dev/null || exit 1

# Default command
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["help"]
