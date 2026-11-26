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
assets) and publishes it to `ghcr.io/vaslv/brevity` for `linux/amd64`.
The server needs Docker with Compose v2. Brevity's own Caddy is the web
server in front of the application and switches traffic between releases,
so updates have no downtime. It either holds ports 80/443 and obtains
certificates itself (`CADDY_MODE=public`), or sits behind a web server on
the host (`CADDY_MODE=behind-host`).

```bash
# In the install directory, with the compose files, Caddyfile and deploy-host.sh:
cp .env.production.example .env && chmod 600 .env
# Fill APP_KEY, HASHIDS_SALT, APP_URL, APP_HOST, ACME_EMAIL, DB_PASSWORD
# and SMTP settings. BREVITY_DATABASE=bundled runs PostgreSQL in Docker.
sudo install -d -m 700 -o 65532 -g 65532 caddy/data caddy/config
mkdir -p .deploy && touch .deploy/database-ready
sh deploy-host.sh deploy ghcr.io/vaslv/brevity:<release>
sh deploy-host.sh artisan make:filament-user
```

Every release runs as a new generation: migrations, then the new web,
then Caddy switches to it and the previous generation is drained and
removed. Migrations must stay compatible with the running release. The
admin panel is at `https://<your-host>/login`.

See the [deployment guide](./docs/06-deploy.md) for key generation, GHCR
access, the host web server setup and updates.

## Intended use

Brevity is built for legitimate link management: branded short links,
campaign routing, click accounting and partner integrations for traffic
you own or operate with permission. Do not use it for cloaking, spam,
phishing, or evading the terms of advertising networks or any other
platform.

## License

[MIT](./LICENSE)
