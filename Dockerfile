# Use official PHP CLI image
FROM php:8.2-cli

# Install dependencies for PDO MySQL and CSV parsing
RUN apt-get update && \
    apt-get install -y curl screen libzip-dev libpng-dev unzip libonig-dev libxml2-dev libpq-dev libssl-dev libmcrypt-dev gnupg \
    && docker-php-ext-install pdo pdo_mysql

# Set working directory
WORKDIR /usr/src/app

# Copy PHP scripts
COPY scripts/ ./scripts/

# Make scripts executable (optional)
RUN chmod +x ./scripts/*.php

# Default to bash shell
CMD ["bash"]
