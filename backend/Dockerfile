FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Allow .htaccess overrides
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Increase upload limits for landing page photo uploads
RUN echo "upload_max_filesize = 20M\npost_max_size = 25M\nmax_execution_time = 60" \
    > /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80
