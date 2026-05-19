FROM php:8.2-apache

# Install system dependencies and enable required PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    supervisor \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for nice URLs if needed
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory to the Apache document root
WORKDIR /var/www/html

# Copy project files to the container
COPY . /var/www/html

# Install PHP dependencies via Composer (ignoring dev tools and optimizing autoloader)
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies for the backend daemon
WORKDIR /var/www/html/backend
RUN npm install

# Return to web root
WORKDIR /var/www/html

# Adjust permissions so Apache can serve and read files properly
RUN chown -R www-data:www-data /var/www/html

# Expose port (Render uses the PORT environment variable, defaults to 80 or uses 10000)
# For Render, exposing 80 is fine, it maps it automatically.
EXPOSE 80

# Start Supervisor which runs both Apache and the Node Daemon
CMD ["/usr/bin/supervisord", "-c", "/var/www/html/supervisord.conf"]
