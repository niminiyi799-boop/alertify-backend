# ============================================================
# Stage 1: Build — install Composer dependencies
# ============================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Install prod dependencies only, no scripts (scripts need the full app)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ============================================================
# Stage 2: Runtime image
# ============================================================
FROM php:8.4-fpm-alpine AS runtime

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        unzip \
        openssl \
    && docker-php-ext-install \
        intl \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        zip \
        opcache \
    && docker-php-ext-enable opcache

# Recommended OPcache settings for production
RUN { \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.save_comments=1'; \
        echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy vendor from build stage
COPY --from=vendor /app/vendor ./vendor

# Copy application source
COPY . .

# Create writable directories Symfony needs
RUN mkdir -p var/cache var/log public/uploads/media \
    && chmod -R 777 var public/uploads

# Generate JWT keys if they don't exist (useful when secrets are injected via env)
# In production, mount real keys via secrets/volumes instead
RUN mkdir -p config/jwt \
    && if [ ! -f config/jwt/private.pem ]; then \
        openssl genrsa -out config/jwt/private.pem 4096 && \
        openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem; \
    fi

# Run post-install scripts now that the full app is present
RUN APP_ENV=prod php bin/console cache:warmup --no-debug

EXPOSE 9000

CMD ["php-fpm"]
