# ============================================================
# Stage 1: Build — install Composer dependencies
# ============================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Install prod dependencies only (no scripts — full app not present yet)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ============================================================
# Stage 2: Runtime image
# ============================================================
FROM php:8.4-cli-alpine AS runtime

# Install system dependencies and required PHP extensions
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

# OPcache settings
RUN { \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.save_comments=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

# 1. Copy application source first
COPY . .

# 2. Copy vendor on top (must come AFTER source so it is not overwritten)
COPY --from=vendor /app/vendor ./vendor

# Create writable directories Symfony needs
RUN mkdir -p var/cache var/log public/uploads/media \
    && chmod -R 777 var public/uploads

# Generate JWT keys if not already present
RUN mkdir -p config/jwt \
    && if [ ! -f config/jwt/private.pem ]; then \
        openssl genrsa -out config/jwt/private.pem 4096 \
        && openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem; \
    fi

# Warm up the Symfony cache at build time
RUN APP_ENV=prod php bin/console cache:warmup --no-debug

EXPOSE 8000

# Use PORT env var (Railway injects it); fall back to 8000
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t public"]
