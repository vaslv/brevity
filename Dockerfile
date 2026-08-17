# Pin the base image to the PHP 8.4 tag + manifest-list digest for reproducible,
# multi-arch builds (was an untagged `latest`, which could silently change the
# bundled PHP version). Bump the tag and digest together when updating.
# Build the Vite entries (the dashboard's map bundle, app css/js): the
# runtime image must carry public/build — .dockerignore keeps any local build
# out of the context, and the panel dashboard loads a @vite entry at runtime.
# Pinned tag + manifest-list digest, same convention as the base image below.
FROM node:22-alpine@sha256:16e22a550f3863206a3f701448c45f7912c6896a62de43add43bb9c86130c3e2 AS assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

RUN npm run build

FROM dunglas/frankenphp:php8.4@sha256:b153e1d6d869d26986e3091738a006f9fde2ee9fb66f7e8b8cfc5a75ec640984

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt update \
    && apt upgrade -y \
    && apt install -y \
    git \
    unzip \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN install-php-extensions \
    bcmath \
    intl \
    pcntl \
    pdo_pgsql \
    redis \
    zip

# Dependencies before the source tree, so that editing application code does not
# re-download all 130-odd packages: this layer's cache key is the two manifests
# alone, and a release that leaves composer.lock untouched reuses it wholesale.
# Anonymous dist downloads are rate-limited per IP by GitHub, and a shared CI
# runner that refetches everything on every tag eventually collects an HTTP 429
# mid-install — so the cheapest fix for a flaky build is to not download at all.
COPY composer.json composer.lock ./

# --no-scripts / --no-autoloader because both need the application code, which
# arrives only in the next layer: post-autoload-dump runs `package:discover` and
# `filament:upgrade`, and the PSR-4 roots point at app/ and database/. The
# autoloader is generated further down instead, once the tree is in place.
#
# The BuildKit cache mount keeps Composer's archive cache on the runner between
# builds (DOCKER_BUILDKIT=1 is set in .gitlab-ci.yml), so even a lock bump only
# fetches what actually changed. COMPOSER_CACHE_DIR has to be pointed at the
# mount explicitly — the default lives under HOME and would be baked into the
# layer rather than cached outside it.
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist

COPY . /app

COPY --from=assets /app/public/build /app/public/build

# Deferred from the install above. `dump-autoload` also fires
# post-autoload-dump, so `package:discover` and `filament:upgrade` still run —
# and it needs no network, because vendor/ is already complete.
RUN composer dump-autoload --no-dev --optimize --no-interaction

# The application version, baked into the image. The git tag is the single
# source of truth: CI passes it here as a build argument (.gitlab-ci.yml), and
# .dockerignore keeps .git out of the image, so an environment variable is the
# only channel a running container has. The mounted /app/.env cannot override
# it — Laravel's dotenv is immutable and leaves an already-set process
# variable alone — so the version always describes the image, never the run.
# The `dev` default keeps a hand-built image honest instead of blank.
#
# VCS_REF and BUILD_DATE default to EMPTY rather than to a placeholder: only CI
# knows the commit and the build time, and an empty label reads as "not
# supplied", while a made-up one would read as a fact. So a hand-built image
# carries blank `revision` and `created` labels — that is the honest answer.
#
# Deliberately the last layer: a version bump then invalidates nothing above it.
ARG APP_VERSION=dev
ARG VCS_REF=
ARG BUILD_DATE=

ENV APP_VERSION=${APP_VERSION} \
    SENTRY_RELEASE=${APP_VERSION}

# The static labels mirror composer.json — `description`, `license` and
# `support.source` — so the image and the package cannot end up describing two
# different projects. Update them together.
LABEL org.opencontainers.image.title="Brevity" \
      org.opencontainers.image.description="Self-hosted link shortener with rule-based routing, click analytics, outgoing callbacks and multi-domain support." \
      org.opencontainers.image.source="https://github.com/vaslv/brevity" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.created="${BUILD_DATE}"
