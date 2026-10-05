# Imagen de la app HotelLink: PHP 8.3 + Apache.
# Trae todo lo necesario para correr Laravel 12 sin instalar nada en la máquina.
FROM php:8.3-apache

# Paquetes del sistema necesarios para las extensiones de PHP y utilidades.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
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

# Extensiones de PHP que usa el proyecto (BD, imágenes, zip, intl, colas...).
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

# Apache: habilitar rewrite y apuntar el DocumentRoot a /public (estándar Laravel).
RUN a2enmod rewrite
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Config de PHP recomendada para la app (tamaño de subida de imágenes, etc.).
COPY docker/php/php.ini /usr/local/etc/php/conf.d/hotellink.ini

WORKDIR /var/www/html

# El entrypoint prepara el proyecto en cada arranque (composer, migrate, seed...).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
