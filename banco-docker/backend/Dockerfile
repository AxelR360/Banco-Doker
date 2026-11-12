FROM php:8.3-apache

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Instalar dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilitar módulos Apache
RUN a2enmod rewrite headers

# Configuración Apache
COPY ./apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copiar el composer.json primero para aprovechar el caché de capas Docker
COPY composer.json composer.lock* /var/www/html/
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copiar el resto del código (vendor/ ya está instalado en la imagen)
COPY . /var/www/html

# Dar permisos correctos a Apache
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

RUN sed -i 's/\r$//' /var/www/html/banco-entrypoint.sh \
    && install -m 755 /var/www/html/banco-entrypoint.sh /usr/local/bin/banco-entrypoint
ENTRYPOINT ["banco-entrypoint"]
CMD ["apache2-foreground"]
