# Use official PHP CLI image
FROM php:8.2-cli

# Install system dependencies + cron
RUN apt-get update && \
    apt-get install -y \
        curl screen libzip-dev libpng-dev unzip \
        libonig-dev libxml2-dev libpq-dev \
        libssl-dev libmcrypt-dev gnupg \
        cron \
    && docker-php-ext-install pdo pdo_mysql pcntl mbstring zip xml opcache bcmath \
    && apt-get clean

# Set working directory
WORKDIR /usr/src/app

# Copy PHP scripts
COPY scripts/ ./scripts/

# Make scripts executable
RUN chmod +x ./scripts/*.php

RUN ln -sf /usr/share/zoneinfo/Africa/Dar_es_Salaam /etc/localtime

# Create cron job (runs daily at 13:00)
# RUN echo "0 13 * * * /usr/local/bin/php /usr/src/app/scripts/send_bulk_sms.php >> /var/log/cron.log 2>&1" > /etc/cron.d/sms-cron

# # Give correct permissions
# RUN chmod 0644 /etc/cron.d/sms-cron

# # Apply cron job
# RUN crontab /etc/cron.d/sms-cron

# # Create log file
# RUN touch /var/log/cron.log

# Run cron in foreground
CMD ["cron", "-f"]
