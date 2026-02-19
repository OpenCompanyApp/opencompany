# --- Stage 1: Composer dependencies ---
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --ignore-platform-reqs

# --- Stage 2: Node asset build ---
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=composer /app/vendor ./vendor
RUN npm run build

# --- Stage 3: Production image ---
FROM dunglas/frankenphp:1-php8.4

# System deps + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    supervisor \
    curl \
    # Rendering tools
    default-jre-headless \
    librsvg2-bin \
    # Node.js for Vega-Lite render script
    nodejs \
    npm \
    && docker-php-ext-install pdo_pgsql pgsql pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PlantUML JAR
RUN curl -fsSL https://github.com/plantuml/plantuml/releases/latest/download/plantuml.jar \
    -o /usr/local/share/plantuml.jar

# Typst binary
RUN curl -fsSL https://github.com/typst/typst/releases/latest/download/typst-x86_64-unknown-linux-musl.tar.xz \
    | tar -xJ --strip-components=1 -C /usr/local/bin typst-x86_64-unknown-linux-musl/typst

# Mermaid renderer (native Rust binary — no Chromium needed)
RUN curl -fsSL https://github.com/1jehuang/mermaid-rs-renderer/releases/latest/download/mmdr-x86_64-unknown-linux-gnu.tar.gz \
    | tar -xz -C /usr/local/bin

# PHP config for production
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini \
    && echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app
COPY . .
COPY --from=composer /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Docker configs
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Laravel setup
RUN rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
