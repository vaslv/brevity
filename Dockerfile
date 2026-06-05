# Pin the base image to the PHP 8.4 tag + manifest-list digest for reproducible,
# multi-arch builds (was an untagged `latest`, which could silently change the
# bundled PHP version). Bump the tag and digest together when updating.
FROM dunglas/frankenphp:php8.4@sha256:b153e1d6d869d26986e3091738a006f9fde2ee9fb66f7e8b8cfc5a75ec640984

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt update \
    && apt upgrade -y \
    && apt install -y \
    git \
    unzip \
    openssh-client \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir -p /root/.ssh && ssh-keyscan git.example.com >> /root/.ssh/known_hosts

RUN install-php-extensions \
    bcmath \
    intl \
    pcntl \
    pdo_pgsql \
    redis \
    zip

COPY . /app

RUN --mount=type=ssh composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist
