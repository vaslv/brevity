# Production and deployment

Operational facts that cannot be derived from the code, plus the
runbook for moving the deployment (performed 2026-06-10; useful when
changing the server or the deploy user).

## Deploying your own instance

GitHub Actions publishes `ghcr.io/vaslv/brevity` for `linux/amd64`.
The server needs Docker with Compose v2, available ports 80/443 and DNS
records pointing every served hostname at it. PHP, Composer, Node and
GitLab are not required on the server.

1. Copy `docker-compose.production.yml`, `docker-compose.db.yml` and
   `.env.production.example` into a dedicated directory. Run
   `cp .env.production.example .env` and `chmod 600 .env`.
   Preserve the existing `.env` and keys when upgrading an installation.
2. Set `LARAVEL_IMAGE=ghcr.io/vaslv/brevity:latest` or a published release
   tag / `sha-<full commit SHA>`. For a fork, change the owner/repository.
   The image becomes available after the first successful **Docker image**
   workflow run. To allow anonymous pulls, the owner must make the package
   Public under GitHub → Packages → package → Package settings → Change visibility.
   For private packages, use `docker login ghcr.io -u <github-user>` and
   enter a classic PAT with `read:packages` at the password prompt.
3. Generate two independent secrets for a new installation:

   ```bash
   docker run --rm --entrypoint php ghcr.io/vaslv/brevity:latest -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
   docker run --rm --entrypoint php ghcr.io/vaslv/brevity:latest -r 'echo bin2hex(random_bytes(16)).PHP_EOL;'
   ```

   Store the first as `APP_KEY`, the second as `HASHIDS_SALT`. Keep the salt
   unchanged once links exist. Fill `APP_URL=https://<technical-host>`,
   `APP_HOST` (all hostnames, comma-separated), `LETSENCRYPT_EMAIL`, SMTP
   settings and `DB_*`. Keep `REDIS_HOST=redis`. GeoIP is optional
   (`GEOIP_LICENSE_KEY`).
4. Choose **one** PostgreSQL setup:

   **Bundled database:** set `DB_HOST=pgsql`, `DB_PORT=5432`,
   `DB_DATABASE`, `DB_USERNAME` and a nonempty `DB_PASSWORD`.

   ```bash
   docker compose --env-file .env -f docker-compose.production.yml -f docker-compose.db.yml up -d --pull always --wait --wait-timeout 300
   ```

   Data persists in `brevity_pgsql_data`; the database port is not exposed.
   Editing `.env` does not change the password in an already initialized database.

   **Existing external database:** configure reachable `DB_HOST`, `DB_PORT`,
   `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` and, if required, `DB_SSLMODE`.
   The database must exist and the user must have migration privileges.
   Use `host.docker.internal` for PostgreSQL on the Docker host, not `localhost`.
   No PostgreSQL container is created:

   ```bash
   docker compose --env-file .env -f docker-compose.production.yml up -d --pull always --wait --wait-timeout 300
   ```

   The scheduler applies migrations and syncs domains before starting the
   schedule. Web and Horizon wait until it is healthy. The proxy obtains TLS certificates.
5. Create the first administrator:

   ```bash
   docker compose --env-file .env -f docker-compose.production.yml exec web php artisan make:filament-user
   ```

   Sign in at `https://<technical-host>/login`. Other configured hosts serve
   short links only, not the admin panel/API/Horizon.

## GitHub builds and updates

Workflow `.github/workflows/docker.yml`:

- Pull requests validate both Compose configurations and build without publishing.
- Pushes to `main` publish `latest` and `sha-<full SHA>` to GHCR.
- Version tag pushes such as `1.2.3` publish only the release tag, leaving
  `latest` and `sha-<full SHA>` unchanged.
- `workflow_dispatch` allows manual builds; only `main` and tags publish images.

The built-in `GITHUB_TOKEN` uses `packages: write`; no registry, SSH or
database secrets are required. Organization policy must allow Actions to
create packages. The workflow does not connect to the deployment server.
The separate `CI` workflow continues running tests and static analysis.

The panel version and `SENTRY_RELEASE` are baked into the image: the release
Git tag or `sha-<full SHA>`. Do not set `APP_VERSION` in the server `.env`.
`latest` points to a build from `main`; its name is not the application version.
Release and SHA tags can be overwritten when a build is rerun. To pin an
exact image, set `LARAVEL_IMAGE=ghcr.io/vaslv/brevity@sha256:<published-digest>`
using the digest of the published image.

To update, back up the database, change `LARAVEL_IMAGE` in the server `.env`
and repeat the chosen `up` command above. This is a regular Compose update
and may interrupt service. Do not use `down -v`, which deletes persistent
volumes. Reverting the image does not undo database migrations.

The old `.gitlab-ci.yml` is disabled with `workflow.rules: when: never` and
retained as a historical example. The old deployment move runbook below
describes the previous infrastructure, not the current setup process.

## Topology

- Production: Docker Compose, project **`brevity`** (the name is pinned
  in `docker-compose.production.yml` and does not depend on the
  directory it is launched from).
- Named volumes: `brevity_nginx_html`, `brevity_nginx_certs`,
  `brevity_nginx_acme` (Let's Encrypt), `brevity_redis_data`. Losing
  the certs/acme volumes = reissuing the certificates.
- Application: Octane (FrankenPHP); queues — Horizon (`redis`), with a
  separate `callbacks` queue (isolated from click recording).

## Manual operations on the server

Before stopping containers, let Horizon finish processing the queue:

```bash
docker exec laravel-horizon php artisan horizon:terminate
```

Health check:

```bash
docker compose --env-file .env -f docker-compose.production.yml ps
curl -I https://brevity.example.com/up
docker exec laravel-horizon php artisan horizon:status
```

## Outstanding post-deploy steps (development stages 1–4)

Run **once** on the first deploy of the branches with these stages;
cross them off here afterwards. Migrations apply automatically (scheduler).

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
docker compose --env-file .env -f docker-compose.production.yml exec web php artisan user-agents:detect-bots
docker compose --env-file .env -f docker-compose.production.yml exec web php artisan clicks:rebuild-counters   # only AFTER detect-bots
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
  docker compose --env-file .env -f docker-compose.production.yml exec web php artisan geo:download-db
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
  `docker compose --env-file .env -f docker-compose.production.yml exec web php artisan octane:reload` (geo data
  changes slowly, usually not required).
- Backfilling geolocation for historical clicks — **after** the
  database is installed (without it the command exits immediately
  without scanning anything). Idempotent and resumable, can be run in
  the background in separate passes:

  ```bash
  docker compose --env-file .env -f docker-compose.production.yml exec web php artisan geo:locate-clicks
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
`.env.example` / `.env.production` are kept in sync;
`.env.production.example` (the public template) follows whenever a new
key is production-relevant.

## Historical GitLab runbook: moving the deployment (new server / deploy user)

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
6. **Verify**: `docker compose --env-file .env -f docker-compose.production.yml ps` (up/healthy), `curl -I
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
