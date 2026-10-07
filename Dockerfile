# ============================================================
# Stage 1: Build — install Composer dependencies
# ============================================================
FROM composer:2 AS vendor

# Ensure zip + unzip are available
RUN apk add --no-cache unzip zip libzip-dev \
    && docker-php-ext-install zip

WORKDIR /app

COPY composer.json composer.lock ./

# Install prod dependencies only (no scripts — full app not present yet)
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ============================================================
# Stage 2: Download Mercure hub binary
# ============================================================
FROM alpine:3.19 AS mercure

RUN apk add --no-cache curl tar

# Mercure v0.16 — latest stable as of 2026
RUN curl -fsSL \
    "https://github.com/dunglas/mercure/releases/download/v0.16.3/mercure_Linux_x86_64.tar.gz" \
    -o /tmp/mercure.tar.gz \
    && tar -xzf /tmp/mercure.tar.gz -C /usr/local/bin mercure \
    && chmod +x /usr/local/bin/mercure

# ============================================================
# Stage 3: Runtime image
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

# Copy Mercure binary from dedicated stage
COPY --from=mercure /usr/local/bin/mercure /usr/local/bin/mercure

WORKDIR /app

# 1. Copy application source first
COPY . .

# 2. Copy vendor on top (must come AFTER source so it is not overwritten)
COPY --from=vendor /app/vendor ./vendor

# Create writable directories Symfony and Mercure need
RUN mkdir -p var/cache var/log var/mercure public/uploads/media \
    && chmod -R 777 var public/uploads

# Generate JWT keys if not already present
RUN mkdir -p config/jwt \
    && if [ ! -f config/jwt/private.pem ]; then \
        openssl genrsa -out config/jwt/private.pem 4096 \
        && openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem; \
    fi

# Warm up the Symfony cache at build time
RUN APP_ENV=prod php bin/console cache:warmup --no-debug

# Copy and set entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# PHP server port (Railway injects $PORT)
EXPOSE 8000
# Mercure hub port
EXPOSE 3000

ENTRYPOINT ["docker-entrypoint.sh"]
