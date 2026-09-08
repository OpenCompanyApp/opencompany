# syntax=docker/dockerfile:1.6

ARG RUNTIME_PLATFORM=linux/amd64

# Install the released, architecture-specific engine archive. The checksum is
# for the complete provenance-bearing release artifact, before extraction.
FROM --platform=$RUNTIME_PLATFORM debian:bookworm-slim AS ruby_engine
ARG TARGETARCH
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl \
    && rm -rf /var/lib/apt/lists/*
RUN case "$TARGETARCH" in \
      amd64) engine_arch=amd64; engine_sha256=3c4b1d629d4813b5ffa977fc1559844c58ea94ffd391a6ffce9e7ac0b016605c ;; \
      arm64) engine_arch=arm64; engine_sha256=5d52067f4f25301925cdf92507fbadc11e987ac2c8ea3cd347ac81b7fd7691ad ;; \
      *) echo "Unsupported Ruby engine architecture: $TARGETARCH" >&2; exit 1 ;; \
    esac \
    && engine_archive="bowerbird-ruby-engine-v0.1.0-rc.1-linux-${engine_arch}.tar.gz" \
    && curl -fsSL "https://github.com/OpenCompanyApp/bowerbird-ruby-engine/releases/download/v0.1.0-rc.1/${engine_archive}" \
      -o "/tmp/${engine_archive}" \
    && echo "${engine_sha256}  /tmp/${engine_archive}" | sha256sum -c - \
    && mkdir -p /engine \
    && tar -xzf "/tmp/${engine_archive}" -C /tmp \
    && install -m 0555 "/tmp/bowerbird-ruby-engine-v0.1.0-rc.1-linux-${engine_arch}/bin/ruby-engine" /engine/ruby-engine

# --- Stage 1: Composer dependencies ---
FROM --platform=$RUNTIME_PLATFORM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
# Composer resolves OpenCompany integration packages from path repositories.
# Provide clean repository archives as named contexts, never a sibling checkout
# with its Git metadata, credentials, dependency cache, or build outputs:
# docker build \
#   --build-context integrations=/path/to/integrations-archive \
#   --build-context astronomy=/path/to/astronomy-archive \
#   --build-context chatogrator=/path/to/chatogrator-archive \
#   -t opencompany .
COPY --from=integrations / /integrations-mruby
COPY --from=astronomy / ./tmp/astronomy-bundle-php
# This path repository is intentionally preferred by Composer when it is
# present. A named source context keeps its locked install independent of a
# mutable VCS fallback and keeps local/CI dependency resolution identical.
COPY --from=chatogrator / ./tmp/chatogrator
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
# Vite's production bundle exceeds Node's cgroup-derived ~2 GiB default on the
# 4 GiB ARM qualification VM. Leave headroom for the concurrent image stages;
# this only affects the disposable asset stage, never the final runtime.
ENV NODE_OPTIONS=--max-old-space-size=2560
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=composer /app/vendor ./vendor
COPY --from=wayfinder /app/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /app/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /app/resources/js/wayfinder ./resources/js/wayfinder
RUN WAYFINDER_SKIP_GENERATE=true npm run build \
    && touch /asset-build-ready

# --- Stage 4: Production image ---
FROM --platform=$RUNTIME_PLATFORM dunglas/frankenphp:1-php8.4

# BuildKit executes independent stages in parallel. Make the memory-intensive
# native PHP extension layer wait for Vite, keeping the 4 GiB ARM qualification
# VM within its cgroup limit without changing the final runtime footprint.
COPY --from=assets /asset-build-ready /tmp/asset-build-ready

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

# Ruby executes in disposable OS-contained subprocesses, not a PHP extension.
COPY --from=ruby_engine /engine/ruby-engine /usr/local/bin/ruby-engine
ENV RUBY_ENGINE_BINARY=/usr/local/bin/ruby-engine
RUN chmod 0555 /usr/local/bin/ruby-engine \
    && sha256sum /usr/local/bin/ruby-engine > /usr/local/share/ruby-engine.sha256

# PlantUML JAR
RUN curl -fsSL https://github.com/plantuml/plantuml/releases/latest/download/plantuml.jar \
    -o /usr/local/share/plantuml.jar

# These release assets are architecture-specific. Keep the final image runnable
# on the ARM64 qualification context as well as the hosted AMD64 default.
ARG TARGETARCH
RUN case "$TARGETARCH" in \
      amd64) typst_arch=x86_64-unknown-linux-musl ;; \
      arm64) typst_arch=aarch64-unknown-linux-musl ;; \
      *) echo "Unsupported Typst architecture: $TARGETARCH" >&2; exit 1 ;; \
    esac \
    && curl -fsSL "https://github.com/typst/typst/releases/latest/download/typst-${typst_arch}.tar.xz" \
      | tar -xJ --strip-components=1 -C /usr/local/bin "typst-${typst_arch}/typst"

# Mermaid renderer is a native Rust binary — no Chromium needed.
ARG TARGETARCH
RUN case "$TARGETARCH" in \
      amd64) mmdr_arch=x86_64-unknown-linux-gnu ;; \
      arm64) mmdr_arch=aarch64-unknown-linux-gnu ;; \
      *) echo "Unsupported Mermaid renderer architecture: $TARGETARCH" >&2; exit 1 ;; \
    esac \
    && curl -fsSL "https://github.com/1jehuang/mermaid-rs-renderer/releases/latest/download/mmdr-${mmdr_arch}.tar.gz" \
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
    && php artisan route:cache \
    && php artisan view:cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
