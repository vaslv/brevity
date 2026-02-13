FROM dunglas/frankenphp

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
    intl \
    pcntl \
    pdo_pgsql \
    redis \
    zip

COPY . /app

RUN --mount=type=ssh composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist
