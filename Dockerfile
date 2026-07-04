FROM php:8.1-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install PHP extensions required (e.g. pdo_mysql)
RUN docker-php-ext-install pdo pdo_mysql

# Copy the application files to the container
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/

# Expose port 80
EXPOSE 80
