<img src="./public/images/logo.svg" alt="Brevity" width="280">

Self-hosted link shortener with rule-based routing, click analytics,
outgoing callbacks and multi-domain support.

**Русская версия: [README.ru.md](./README.ru.md)**

## Features

- **Rule-based routing** — each link carries an ordered list of rules;
  the first matching condition wins. Transition modes per rule: instant
  302 redirect, delayed countdown page, or manual "continue" page.
- **A/B split** — deterministic sticky variant selection with no
  cookies or server-side state.
- **Click analytics** — asynchronous click recording (the visitor is
  never slowed down), bot detection, per-link counters, click
  geolocation (MaxMind GeoIP2) with a geo map in the admin panel.
- **Outgoing callbacks** — POST notifications to partner systems with
  templated payloads (`{{click.*}}` / `{{link.*}}` variables), retries
  with backoff, and SSRF protection.
- **Multi-domain** — a pool of short-link domains with rotation
  strategies and domain groups; domain reputation monitoring.
- **Link lifecycle** — activation window (`valid_since` /
  `valid_until`) and click limits (`max_clicks`).
- **Admin panel** — Filament 5, bilingual (EN/RU).
- **HTTP API** — token-authenticated (Laravel Sanctum), built for
  programmatic link creation; see [docs/03-api.md](./docs/03-api.md).
  Official PHP client:
  [vaslv/brevity-php-sdk](https://github.com/vaslv/brevity-php-sdk).

## Stack

PHP 8.4, Laravel 13, PostgreSQL, Filament 5, Horizon, Octane
(FrankenPHP), Redis. Local development runs on Laravel Sail.

## Quick start

```bash
git clone https://github.com/vaslv/brevity.git && cd brevity

# Install dependencies without a local PHP (one-off bootstrap):
docker run --rm -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install

cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate
vendor/bin/sail test
```

The admin panel is served on the technical host (the host of `APP_URL`);
every other configured hostname acts as a short-link domain only.

## Documentation

The canonical reference lives in [docs/](./docs/readme.md):
architecture, glossary, HTTP API, admin panel, development workflow,
deployment, plans and recorded decisions.

## Deployment

GitHub Actions builds the production image (FrankenPHP + Octane, bundled
assets) and publishes it to `ghcr.io/vaslv/brevity`. You need Docker with
Compose v2, a server with ports 80/443 available, and DNS pointing to it.
The published image targets `linux/amd64`.

```bash
cp .env.production.example .env
# Fill APP_KEY, HASHIDS_SALT, APP_URL, APP_HOST,
# LETSENCRYPT_EMAIL, DB_PASSWORD and SMTP settings.
# LARAVEL_IMAGE defaults to ghcr.io/vaslv/brevity:latest.

# Option 1: PostgreSQL in Docker, DB_HOST=pgsql
docker compose --env-file .env \
    -f docker-compose.production.yml -f docker-compose.db.yml up -d --pull always --wait --wait-timeout 300

# Option 2: existing PostgreSQL — fill DB_HOST/PORT/DATABASE/USERNAME/PASSWORD,
# then use only the base file instead of the command above:
# docker compose --env-file .env -f docker-compose.production.yml up -d --pull always --wait --wait-timeout 300

docker compose --env-file .env -f docker-compose.production.yml exec web php artisan make:filament-user
```

The scheduler applies migrations automatically; web and Horizon wait for
it to become healthy. Bundled PostgreSQL persists data in a Docker volume.
An external database must be reachable from containers; `localhost` means
the container itself. Use `host.docker.internal` for a database on the
Docker host. The admin panel is at `https://<your-host>/login`.

See the [deployment guide](./docs/06-deploy.md) for key generation, GHCR
access and updates. The old GitLab pipeline is disabled.

## Intended use

Brevity is built for legitimate link management: branded short links,
campaign routing, click accounting and partner integrations for traffic
you own or operate with permission. Do not use it for cloaking, spam,
phishing, or evading the terms of advertising networks or any other
platform.

## License

[MIT](./LICENSE)
