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
# Provide the Chatogrator source as a clean archive context, never a sibling
# checkout with its Git metadata, credentials, dependency cache, or build output:
# docker build --build-context chatogrator=/path/to/chatogrator-archive -t opencompany .
# (The integration package system and its path repositories were deleted.)
# This path repository is intentionally preferred by Composer when it is
# present. A named source context keeps its locked install independent of a
# mutable VCS fallback and keeps local/CI dependency resolution identical.
COPY --from=chatogrator / ./tmp/chatogrator
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --ignore-platform-reqs

# --- Stage 2: Generate Wayfinder sources with the production PHP runtime ---
FROM --platform=$RUNTIME_PLATFORM dunglas/frankenphp:1.13.2-php8.4@sha256:0de5a32944f72c13b45d183e8e6c53546b8172cec21cc8599e24b12e0661506d AS wayfinder
WORKDIR /app
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini
COPY . .
COPY --from=composer /app/vendor ./vendor
RUN rm -f bootstrap/cache/*.php \
    && php artisan route:clear \
    && php artisan wayfinder:generate

# --- Stage 3: Node asset build ---
FROM node:22.23.4-alpine AS assets
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
# PHP 8.4.27 (CVE-2026-109618 / CVE-2026-109619)
FROM --platform=$RUNTIME_PLATFORM dunglas/frankenphp:1.13.2-php8.4@sha256:0de5a32944f72c13b45d183e8e6c53546b8172cec21cc8599e24b12e0661506d

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
    # SVG to PNG rendering for the built-in render_svg tool
    librsvg2-bin \
    && docker-php-ext-install pdo_pgsql pgsql pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Distrust the compromised eMudhra (emSign) roots for outbound TLS
RUN sed -i '/emSign/d' /etc/ca-certificates.conf \
    && rm -f /usr/share/ca-certificates/mozilla/emSign* /etc/ssl/certs/*emSign* \
    && update-ca-certificates

# Ruby executes in disposable OS-contained subprocesses, not a PHP extension.
COPY --from=ruby_engine /engine/ruby-engine /usr/local/bin/ruby-engine
ENV RUBY_ENGINE_BINARY=/usr/local/bin/ruby-engine
RUN chmod 0555 /usr/local/bin/ruby-engine \
    && sha256sum /usr/local/bin/ruby-engine > /usr/local/share/ruby-engine.sha256

# PHP config for production
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini \
    && printf 'expose_php=Off\nsession.upload_progress.enabled=0\n' > /usr/local/etc/php/conf.d/security.ini \
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
