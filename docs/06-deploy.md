# Production and deployment

Operational facts that cannot be derived from the code, plus the
runbook for moving the deployment (performed 2026-06-10; useful when
changing the server or the deploy user).

## Deploying your own instance

Everything ships in the repo except PostgreSQL: the `Dockerfile` builds
the production image (FrankenPHP + Octane, assets prebuilt), and
`docker-compose.production.yml` runs the full stack — nginx-proxy +
acme-companion (automatic Let's Encrypt), a one-shot migrator, web,
scheduler, Horizon and Redis.

1. **Build and publish the image** (any registry, or build straight on
   the server):

   ```bash
   docker build -t registry.example.com/you/brevity:1.0.0 .
   docker push registry.example.com/you/brevity:1.0.0
   ```

2. **Prepare the server**: Docker with Compose v2, ports 80/443 free,
   DNS records for every hostname you plan to serve pointing at it.
   **PostgreSQL is not part of the compose file** — bring a managed
   database or run your own, reachable from the containers.

3. **Copy `docker-compose.production.yml` and a production `.env`**
   (start from `.env.example`) to the server. The load-bearing keys:

   - `APP_KEY` — generate with `php artisan key:generate --show`;
   - `APP_URL=https://<technical-host>` — the host that serves the
     admin panel, API and Horizon;
   - `APP_HOST` — comma-separated list of **all** hostnames (the
     technical host and every short-link domain); it feeds
     `VIRTUAL_HOST`/`LETSENCRYPT_HOST` for the proxy, and
     `domains:sync` seeds the Domain dictionary from it on deploy;
   - `LETSENCRYPT_EMAIL` — certificate expiry notices;
   - `DB_*` — your PostgreSQL; `REDIS_HOST=redis` (the compose
     service);
   - `HASHIDS_SALT` — independent of `APP_KEY`, immutable once links
     exist (changing it breaks every issued short code);
   - optional: SMTP credentials, `GEOIP_LICENSE_KEY` (MaxMind) for
     click geolocation.

4. **Start the stack**:

   ```bash
   export LARAVEL_IMAGE=registry.example.com/you/brevity:1.0.0
   docker compose --env-file .env -f docker-compose.production.yml up -d
   ```

   The `migrate` one-shot applies migrations and syncs domains; web,
   scheduler and Horizon start only after it succeeds; the proxy
   obtains certificates automatically.

5. **Create the first admin user**:

   ```bash
   docker exec -it laravel-web php artisan make:filament-user
   ```

6. **CI/CD (optional)**: `.gitlab-ci.yml` is a reference pipeline for
   any GitLab instance — build, push, deploy over SSH on tag push,
   with a manual rollback job. Every infrastructure-specific value
   comes from CI/CD variables; see the header comment in that file for
   the full list.

## Topology

- Production: Docker Compose, project **`brevity`** (the name is pinned
  in `docker-compose.production.yml` and does not depend on the
  directory it is launched from).
- Named volumes: `brevity_nginx_html`, `brevity_nginx_certs`,
  `brevity_nginx_acme` (Let's Encrypt), `brevity_redis_data`. Losing
  the certs/acme volumes = reissuing the certificates.
- Application: Octane (FrankenPHP); queues — Horizon (`redis`), with a
  separate `callbacks` queue (isolated from click recording).

## CI/CD (GitLab, deploy on tag)

- The pipeline deploys over SSH as `DEPLOY_USER` (a CI variable;
  production — `brevity`, not root). `.env` and the compose file are
  copied into that user's home directory.
- **`LARAVEL_IMAGE` is not stored in the server `.env`** — CI exports
  it in the deploy command. Any manual `docker compose` command on the
  server requires it explicitly (for `down`/`ps` a stub is fine:
  `LARAVEL_IMAGE=dummy`). The real image:
  `docker inspect laravel-web --format '{{.Config.Image}}'`.
- Migrations run during deploy (gated `migrate --force`).
- Post-deploy smoke test — `HEALTHCHECK_URL`
  (`https://brevity.example.com/up`); manual `rollback` job with
  `ROLLBACK_TAG`.
- A pipeline for an already existing tag does not rerun after edits to
  `.gitlab-ci.yml` — recreate the tag or run the pipeline manually.

## Releases

Semver **without** the `v` prefix; the source of truth is `version` in
`composer.json` + the git tag. The interactive command is
`composer release` (the `vaslv/composer-release` dev package); pushing
the tag triggers the deploy. Details —
[05-development.md](./05-development.md).

## Manual operations on the server

Before stopping containers, let Horizon finish processing the queue:

```bash
docker exec laravel-horizon php artisan horizon:terminate
```

Health check:

```bash
docker compose -p brevity ps
curl -I https://brevity.example.com/up
docker exec laravel-horizon php artisan horizon:status
```

## Outstanding post-deploy steps (development stages 1–4)

Run **once** on the first deploy of the branches with these stages;
cross them off here afterwards. Migrations apply automatically (gated
`migrate`).

**Deploy stages 1–3 in a quiet window.** Reasons (review 2026-07-12):

- The migrations build four indexes on `clicks` with a regular
  (blocking) `CREATE INDEX`. On a table with millions of rows this
  holds up click writes for the duration of the build — redirects do
  not suffer (accounting is asynchronous), but Horizon accumulates a
  backlog. At production scale it makes sense to create them by hand
  in advance with `CREATE INDEX CONCURRENTLY` before `migrate` (the
  migration then completes instantly) — or simply deploy in a
  low-traffic window.
- The stage 3 migration **drops `rules.condition_id`** (moved to the
  `condition_rule` pivot). The old code still expects that column, so
  in the "new schema + not-yet-updated code" window it misroutes
  conditional rules and throws 500s on writes. Keep the
  incompatibility window short: migrations and the code rollout as a
  single deploy, with no long gap in between.
- `clicks:rebuild-counters` takes `ACCESS EXCLUSIVE` on the counters
  for the whole aggregation over `clicks` — during that time redirects
  of links with `max_clicks` stall as well (their `isAlive` reads the
  sum of the counters). Run it in the same window, not under load.

**Stages 1 and 3 (bots + counters)** — strictly in this order, after
the migrations:

```bash
docker exec laravel-web php artisan user-agents:detect-bots
docker exec laravel-web php artisan clicks:rebuild-counters   # only AFTER detect-bots
```

The order is mandatory: `rebuild-counters` takes the bot flag from
`user_agents`, so the flags must be set beforehand. `rebuild-counters`
is idempotent (TRUNCATE + recount) and can be repeated. **Stage 3
changed the bot detection library** (crawler-detect →
device-detector), so `detect-bots` is also mandatory when deploying
stage 3: the verdict for some UAs changes, and the counters must be
rebuilt.

**Stage 2 (lifecycle + API)** — new server `.env` keys (the defaults
are safe, so they need not be set right away):

- `TRACKING_IGNORED_SOURCES=` — office/monitoring IPs and CIDRs,
  comma-separated (empty = track everything);
- `TRACKING_DISABLE_PARAM=` — name of the query parameter that
  disables tracking (empty = off). **This is effectively a secret:**
  knowing the name = bypassing click accounting, the callback, and the
  `max_clicks` limit (a visit with the parameter does not spend the
  link's budget). Pick an unguessable name and rotate it if it leaks;
  do not publish it in partner docs. The parameter is stripped from
  the forwarded `forward_query`, but is visible in the
  browser/history.
- `TRACKING_IP_RETENTION_DAYS=90` — retention period for raw IPs;
- `LINK_DELETE_CONFIRM_THRESHOLD=15` — warning threshold on deletion.

`ips:prune` is included in the scheduler (daily) — on its first run on
an old database it will anonymize all clicks older than the retention
period in a single pass; no special action is required.

**Stage 4 (geolocation) — in the same quiet window as stages 1 and
3.** Contrary to the initial estimate, the migration is NOT instant
(review 2026-07-13, r26): `clicks.geo_location_id` is added nullable
(that part is cheap — metadata only, no rewrite), but the partial
index `WHERE geo_location_id IS NOT NULL` still scans the entire
`clicks` heap (the predicate only filters which tuples end up in the
index — Postgres does not skip pages), and the `restrictOnDelete` FK
is a second full scan (RI_Initial_Check), and both run inside the
migration's transaction under the ACCESS EXCLUSIVE taken by
`ADD COLUMN`. On a table with millions of rows this holds up click
writes for the duration of the two scans. As with stages 1/3: either
deploy in a low-traffic window, or at production scale do it by hand
in advance, before `migrate` — `ADD COLUMN` separately, the FK as
`ADD CONSTRAINT … NOT VALID` + a separate `VALIDATE CONSTRAINT`
(which takes only SHARE UPDATE EXCLUSIVE), and
`CREATE INDEX CONCURRENTLY` (the migration then completes instantly).
After the migration:

- New server `.env` keys (already in `.env.example`; without the key
  geo is simply off and clicks are left without a location — click
  recording does not suffer):
  - `GEOIP_LICENSE_KEY=` — MaxMind license key; empty = database
    download and auto-update are disabled;
  - `GEOIP_DATABASE_PATH=` — path to the `.mmdb` (empty =
    `storage/app/geoip/GeoLite2-City.mmdb`);
  - `GEOIP_MAX_AGE_DAYS=30` — threshold for the traffic-driven
    auto-update.
- Initial database download (after setting the key):

  ```bash
  docker exec laravel-web php artisan geo:download-db
  ```

  The database lives on the shared `geoip_data` named volume (mounted
  into all laravel containers), so a download from `laravel-web` is
  immediately visible to the reader in `laravel-horizon`, where the
  lookups happen, and survives container re-creation on deploy. From
  then on the database keeps itself fresh: a click asynchronously
  pings the age check and, when the database is stale, schedules a
  background re-download (no cron needed). A worker that has not yet
  opened the reader picks up the initial install immediately; on a
  **manual re-download over an already open** database the octane
  workers keep reading the old inode until a restart — if an
  immediate swap is needed, run
  `docker exec laravel-web php artisan octane:reload` (geo data
  changes slowly, usually not required).
- Backfilling geolocation for historical clicks — **after** the
  database is installed (without it the command exits immediately
  without scanning anything). Idempotent and resumable, can be run in
  the background in separate passes:

  ```bash
  docker exec laravel-web php artisan geo:locate-clicks
  ```

**`trustProxies` — narrow it down to the real proxy.**
`bootstrap/app.php` currently trusts all private subnets (`10/8`,
`172.16/12`, `192.168/16`). Behind the Docker gateway the
`REMOTE_ADDR` of every request is the gateway's private address, so
`X-Forwarded-For` can be spoofed. This affects IP conditions
(`ip_address`), the `TRACKING_IGNORED_SOURCES` list, and the per-IP
resolve rate limiter. Before actively using IP conditions as a gate,
narrow the list down to the CIDR of the real front proxy. Until then,
IP conditions are a heuristic, not an access control.

Contract changes for integrators: the `is_bot` flag in every callback
(stage 1); paths moved to `/api/v1` with RFC 7807 errors, legacy
`/api/*` is deprecated (stage 2). Notify the partners — see
[03-api.md](./03-api.md).

## Secrets and configs outside git

Server `.env`: `DB_PASSWORD`, `APP_KEY`, `HASHIDS_SALT` (independent
of `APP_KEY` — see [08-decisions.md](./08-decisions.md)), SMTP
credentials. Convention: the keys (not the values) of `.env` /
`.env.example` / `.env.production` are kept in sync.

## Runbook: moving the deployment (new server / deploy user)

First performed 2026-06-10 (root → `brevity`). The downtime window
between stopping and bringing back up is 2–5 minutes.

1. **User** (on the server, as root): `useradd -m -s /bin/bash
   <user>`, `usermod -aG docker <user>`, copy `authorized_keys` into
   `~/.ssh` (permissions 700/600). Check from the local machine:
   `ssh <user>@<host> docker ps` — without sudo.
2. **Stop the old project without touching the volumes** (first
   `horizon:terminate`, wait for the container to stop):

   ```bash
   cd <old-directory>
   LARAVEL_IMAGE=dummy docker compose -p <old-project> --env-file .env \
     -f docker-compose.production.yml down     # WITHOUT -v!
   ```

3. **Move the volumes to the new prefix** (Docker has no volume
   rename — copy the contents):

   ```bash
   for v in nginx_html nginx_certs nginx_acme redis_data; do
     docker volume create brevity_$v
     docker run --rm -v <old>_$v:/from:ro -v brevity_$v:/to alpine \
       sh -c "cp -a /from/. /to/"
   done
   ```

4. **GitLab CI variables** (Settings → CI/CD → Variables):
   `DEPLOY_USER=<user>`, `HEALTHCHECK_URL=https://<host>/up`. The
   Protected flag — only if the deploy tags are protected.
5. **Deploy**: create and push a new tag.
6. **Verify**: `docker compose -p brevity ps` (up/healthy), `curl -I
   .../up` (200), `horizon:status` (running), `docker logs
   acme-companion --since 10m` (it does **not** issue new
   certificates — a sign that the certs volumes moved over correctly),
   `docker exec redis redis-cli dbsize` (not empty).
7. **Cleanup** — only a day or two after a successful check: delete
   the old volumes and the `.env`/compose file from the old directory.

**Rollback**: as long as the old volumes are intact — bring the old
project up from the old directory (`LARAVEL_IMAGE=<real-image> docker
compose -p <old-project> ... up -d`).

**First-run pitfalls**: `LARAVEL_IMAGE` is required even for `down`
(compose fails validation without it); a line containing `: ` inside a
plain scalar in `script:` of `.gitlab-ci.yml` is parsed as a mapping —
wrap the whole line in single quotes; a pipeline for an existing tag
will not rerun on its own.
