# syntax=docker/dockerfile:1.6

ARG RUNTIME_PLATFORM=linux/amd64

# --- Stage 1: Composer dependencies ---
FROM --platform=$RUNTIME_PLATFORM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
# Composer resolves OpenCompany integration packages from path repositories.
# The Docker build supplies the sibling integrations repo as a named context:
# docker build --build-context integrations=../integrations -t opencompany .
COPY --from=integrations / /integrations
COPY tmp/astronomy-bundle-php ./tmp/astronomy-bundle-php
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --ignore-platform-reqs

# --- Stage 2: Generate Wayfinder sources with the production PHP runtime ---
FROM --platform=$RUNTIME_PLATFORM dunglas/frankenphp:1-php8.4 AS wayfinder
WORKDIR /app
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini
COPY . .
COPY --from=composer /app/vendor ./vendor
RUN rm -f bootstrap/cache/*.php \
    && php artisan route:clear \
    && php artisan wayfinder:generate

# --- Stage 3: Node asset build ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=composer /app/vendor ./vendor
COPY --from=wayfinder /app/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /app/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /app/resources/js/wayfinder ./resources/js/wayfinder
RUN WAYFINDER_SKIP_GENERATE=true npm run build

# --- Stage 4: Production image ---
FROM --platform=$RUNTIME_PLATFORM dunglas/frankenphp:1-php8.4

# System deps + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libstdc++6 \
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

# QuickJS sandbox PHP extension. Pin both release and digest so production
# cannot silently pick up a replaced native artifact.
ARG QUICKJS_SANDBOX_VERSION=v1.0.0
ARG QUICKJS_SANDBOX_SHA256=925c7f290f15292b4e1e113ccb81b823f467b85cfacd6557431c282f19876cb4
RUN curl -fsSL "https://github.com/OpenCompanyApp/quickjs-sandbox/releases/download/${QUICKJS_SANDBOX_VERSION}/quickjs_sandbox-php84-linux-x86_64.so" \
    -o /tmp/quickjs_sandbox.so \
    && echo "${QUICKJS_SANDBOX_SHA256}  /tmp/quickjs_sandbox.so" | sha256sum -c - \
    && install -m 0644 /tmp/quickjs_sandbox.so "$(php-config --extension-dir)/quickjs_sandbox.so" \
    && echo "extension=quickjs_sandbox.so" > /usr/local/etc/php/conf.d/quickjs-sandbox.ini \
    && rm /tmp/quickjs_sandbox.so \
    && php --ri quickjs_sandbox

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
    && php -r "class_exists('QuickJS\\Sandbox') || exit(1);" \
    && php artisan package:discover --ansi \
    && php artisan route:cache \
    && php artisan view:cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
