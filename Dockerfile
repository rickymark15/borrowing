FROM php:8.2-apache

# Install PHP database extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set website directory
WORKDIR /var/www/html

# Copy website files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Render web service port
EXPOSE 80
