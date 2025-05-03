# Use official PHP 8.x Apache image
FROM php:8.2-apache-bullseye

# Install PHP extensions for MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite (for pretty URLs, optional)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files from src/ to Apache web root
COPY src/ /var/www/html/

# Set recommended permissions (optional, for dev)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 (Apache default)
EXPOSE 80
