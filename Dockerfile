# syntax=docker/dockerfile:1

# ---- Stage 1: Composer binary ----
FROM composer:2 AS composer

# ---- Stage 2: Build dependencies ----
FROM php:8.3-cli AS builder

WORKDIR /app

# Make the Composer binary available in this PHP image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Install dependencies first to leverage Docker layer caching
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --ignore-platform-reqs

# Copy the rest of the application source
COPY . .

# Regenerate the optimized autoloader now that the full source is present
RUN composer dump-autoload --optimize --no-dev --no-interaction

# ---- Stage 3: Runtime image ----
FROM php:8.3-cli

WORKDIR /app

# Bring in the application code and installed vendor directory from the build stage
COPY --from=builder /app /app

ENV PORT=8080
EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t public"]
