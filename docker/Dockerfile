# Imagen de la aplicacion HotelLink: PHP 8.3 + Apache (proyecto integrador InfraWeb HA).
# Contiene el codigo, las dependencias de Composer y la configuracion del servidor,
# de modo que la misma imagen corre en la PC del equipo y en el servidor.
FROM php:8.3-apache

LABEL org.opencontainers.image.title="HotelLink" \
      org.opencontainers.image.description="Plataforma de reservas HotelLink (Laravel 12 + Apache)" \
      org.opencontainers.image.vendor="Equipo HotelLink - COIDS3904"

# Paquetes del sistema necesarios para las extensiones de PHP y utilidades.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libwebp-dev \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP que usa el proyecto (BD, imagenes, zip, intl, colas...).
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
        exif \
        pcntl \
        opcache

# Composer (copiado desde su imagen oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: habilitar rewrite y apuntar el DocumentRoot a /public (estandar Laravel).
RUN a2enmod rewrite
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Config de PHP recomendada para la app (tamano de subida de imagenes, etc.).
COPY docker/php/php.ini /usr/local/etc/php/conf.d/hotellink.ini

WORKDIR /var/www/html

# Codigo de la aplicacion (lo que excluye .dockerignore no entra al contexto).
COPY --chown=www-data:www-data . /var/www/html

# Dependencias de PHP dentro de la imagen: la imagen es autosuficiente.
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts --no-progress \
    && php artisan package:discover --ansi || true \
    && chown -R www-data:www-data storage bootstrap/cache

# El entrypoint prepara el proyecto en cada arranque (composer, migrate, seed...).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
