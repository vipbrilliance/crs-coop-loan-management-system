FROM php:8.2-apache

# Install system dependencies (zip + git required for Composer package downloads)
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    git \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PDO MySQL extension and zip extension
RUN docker-php-ext-install pdo pdo_mysql zip

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Allow .htaccess overrides
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Increase upload limits for landing page photo uploads
RUN echo "upload_max_filesize = 20M\npost_max_size = 25M\nmax_execution_time = 60" \
    > /usr/local/etc/php/conf.d/uploads.ini

# Add Composer (required for PHPUnit test suite)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

EXPOSE 80
