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

COPY . /app

COPY --from=assets /app/public/build /app/public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

# The application version, baked into the image. The git tag is the single
# source of truth: CI passes it here as a build argument (.gitlab-ci.yml), and
# .dockerignore keeps .git out of the image, so an environment variable is the
# only channel a running container has. The mounted /app/.env cannot override
# it — Laravel's dotenv is immutable and leaves an already-set process
# variable alone — so the version always describes the image, never the run.
# The `dev` default keeps a hand-built image honest instead of blank.
#
# Deliberately the last layer: a version bump then invalidates nothing above it.
ARG APP_VERSION=dev
ARG VCS_REF=
ARG BUILD_DATE=

ENV APP_VERSION=${APP_VERSION} \
    SENTRY_RELEASE=${APP_VERSION}

LABEL org.opencontainers.image.title="Brevity" \
      org.opencontainers.image.description="Self-hosted link shortener with rule-based routing, click analytics, outgoing callbacks and multi-domain support." \
      org.opencontainers.image.source="https://github.com/vaslv/brevity" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.created="${BUILD_DATE}"
