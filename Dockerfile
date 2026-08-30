FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        cron \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        libpng-dev \
        libsqlite3-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by Laravel + SQLite
RUN docker-php-source extract \
    && cp /usr/src/php/ext/sqlite3/config0.m4 /usr/src/php/ext/sqlite3/config.m4 \
    && docker-php-ext-install sqlite3 pdo_sqlite dom xml mbstring intl bcmath gd zip \
    && docker-php-source delete

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Disable Composer's security-advisory blocking during install
ENV COMPOSER_POLICY_ADVISORIES_BLOCK=false
ENV COMPOSER_PROCESS_TIMEOUT=600

# Apache rewrite for Laravel routing
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy application source
COPY . .

# Ensure writable cache dir (excluded from build context via .dockerignore)
RUN mkdir -p bootstrap/cache storage/app/public

# Use container-friendly environment (SQLite at /data, no host paths)
RUN cp .env.docker .env \
    && rm -f composer.lock \
    && composer install --no-interaction --no-dev --optimize-autoloader \
    && mkdir -p /data \
    && chown -R www-data:www-data storage bootstrap/cache /data \
    && php artisan storage:link

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
