FROM php:8.3-apache

# Extensiones de PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Habilitar mod_rewrite y headers
RUN a2enmod rewrite headers

# Configuración del VirtualHost con DocumentRoot en /public
COPY ./apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
