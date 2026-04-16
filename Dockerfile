FROM php:8.2-cli

# Install system dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install zip gd

# Install gRPC (required by google/cloud-firestore)
RUN pecl install grpc \
    && docker-php-ext-enable grpc

# Clean up to reduce image size
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Prevent memory issues in composer
ENV COMPOSER_MEMORY_LIMIT=-1

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Expose port (optional but fine)
EXPOSE 10000

# Start server (Render-safe)
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t public"]
