# Brevity

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

The repository ships a production `Dockerfile` (FrankenPHP + Octane) and
a fully parameterised `docker-compose.production.yml` (nginx-proxy +
Let's Encrypt, one-shot migrator, web, scheduler, Horizon, Redis).
`.gitlab-ci.yml` is a reference build-and-deploy pipeline that works
with any GitLab instance and container registry — everything
infrastructure-specific is supplied via CI variables. See
[docs/06-deploy.md](./docs/06-deploy.md).

## Intended use

Brevity is built for legitimate link management: branded short links,
campaign routing, click accounting and partner integrations for traffic
you own or operate with permission. Do not use it for cloaking, spam,
phishing, or evading the terms of advertising networks or any other
platform.

## License

[MIT](./LICENSE)
