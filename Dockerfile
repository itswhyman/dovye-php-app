FROM php:8.3-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo_mysql

# Enable Apache mod_rewrite for clean URLs if needed
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Ensure correct file permissions
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
