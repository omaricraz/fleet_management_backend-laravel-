# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Stage 1: Install Composer dependencies (no application code required yet)
# -----------------------------------------------------------------------------
FROM php:8.2-cli-alpine AS composer_deps

RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install -j"$(nproc)" zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# -----------------------------------------------------------------------------
# Stage 2: Production runtime (Alpine PHP CLI + extensions, php -S on :80)
# -----------------------------------------------------------------------------
FROM php:8.2-cli-alpine AS production

RUN apk add --no-cache \
    freetype-dev \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
    bcmath \
    gd \
    opcache \
    pdo_mysql \
    zip \
    && apk del --no-network $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/* /tmp/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY --from=composer_deps /var/www/html/vendor ./vendor

COPY . .

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi --no-interaction || true \
    && rm -f /usr/bin/composer

RUN if ! id www-data >/dev/null 2>&1; then \
    addgroup -S www-data -g 82 \
    && adduser -S www-data -u 82 -G www-data -h /var/www -s /sbin/nologin; \
    fi

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENV APP_ENV=production \
    LOG_CHANNEL=stderr \
    PORT=80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
