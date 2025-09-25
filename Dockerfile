FROM php:8.2-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    gmp-dev \
    curl \
    git \
    unzip

# Install PHP extensions
RUN docker-php-ext-install gmp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy the entire Nostrbots application
COPY . .

# Create necessary directories
RUN mkdir -p logs tmp orly-data

# Set permissions
RUN chmod +x generate-key.php

# Default command
CMD ["php", "nostrbots.php", "--help"]
