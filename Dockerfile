# Dockerfile

FROM php:8.2-cli

# Install cron, unzip, netcat (for nc command)
RUN apt-get update && apt-get install -y cron unzip netcat-openbsd

# Set working directory
WORKDIR /app

# Copy files
COPY . .

# Install Composer inside container
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# Install PHP dependencies if needed
RUN composer install || true

# Setup cron job
RUN echo "SHELL=/bin/bash\nPATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin" > /etc/cron.d/cronjob \
 && echo '*/20 * * * * bash -c "/usr/local/bin/php /app/checkConnection.php >> /var/log/cron.log 2>&1"' >> /etc/cron.d/cronjob \
 && echo '* * * * * bash -c "curl -k https://core_nginx/cron/run >> /var/log/cron.log 2>&1"' >> /etc/cron.d/cronjob \
 && chmod 0644 /etc/cron.d/cronjob \
 && crontab /etc/cron.d/cronjob



# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/cronjob

# Apply cron job
RUN crontab /etc/cron.d/cronjob

# Create log file
RUN touch /var/log/cron.log

# Run the command on container startup
CMD ["cron", "-f"]
