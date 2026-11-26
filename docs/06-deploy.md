# Production and deployment

Operational facts that cannot be derived from the code.

## How a release works

A production installation has two kinds of Compose projects:

- **Stable project `brevity`** (`docker-compose.production.yml`): the
  application Caddy, Redis and, with `BREVITY_DATABASE=bundled`, PostgreSQL
  (`docker-compose.db.yml`). They outlive releases.
- **Generation `brevity-gen-<N>`** (`docker-compose.generation.yml`): web
  (Octane on FrankenPHP), Horizon and the scheduler of one image digest.
  Every release starts a new generation next to the live one.

The application Caddy is the web server in front of FrankenPHP and the
switch between generations. Its configuration is the `Caddyfile` of the
repository; during a release only `caddy/conf/upstream.caddy` changes, a
single line naming the live generation.

`sh deploy-host.sh deploy <image>` runs these steps under a host lock:

1. Pull the image and resolve its digest. Nothing has changed yet.
2. Prepare the entrance: check the directories and settings, keep Redis
   (and the bundled PostgreSQL) running without recreating them, start
   Caddy if it is not running, apply a changed `Caddyfile` with
   `caddy validate` and `caddy reload`.
3. Stop the scheduler and Horizon of the live generation (Horizon finishes
   its running jobs, up to 360 s).
4. Run `migrate --force` and `domains:sync` from the new image.
5. Start the new web role and wait for its health check.
6. Check the new web from inside Caddy, write the route, `caddy validate`,
   `caddy reload`.
7. Probe `/up` and `/login` through Caddy: both must answer 200 with
   `X-Brevity-Generation: <N>`.
8. Start the new scheduler and Horizon and wait for their health checks.
9. Record the live generation in `.deploy/active.env` (the previous record
   moves to `.deploy/previous.env`).
10. Wait until Caddy has no request in flight to the previous web (up to
    300 s), then remove the previous generation.

A failure before step 9 puts the previous route back, removes the
candidate and restarts the stopped background roles: the previous
generation keeps serving. **Migrations are not rolled back**: they must
stay compatible with the code of the running generation (add columns and
tables first, drop them in a later release).

Consequences:

- HTTP has no downtime. Queue processing and scheduled tasks pause from
  step 3 to step 8 (mostly the migrations); jobs wait in Redis.
- Two web roles run side by side during a switch: the host must fit both.
- Caddy 2.11 may reset a connection that waits in the queue of the old
  listener during a reload; requests in flight finish.
- Rollback is a deploy of an earlier digest (`IMAGE` in
  `.deploy/previous.env`). The schema of the newer release stays.

## Server layout

The install directory (`/opt/brevity` in production) holds:

| Path | Content |
|---|---|
| `docker-compose.*.yml`, `Caddyfile`, `deploy-host.sh` | Uploaded by the deploy workflow on every release |
| `.env` | Settings and secrets of the installation, mode `600` |
| `.deploy/` | Lock, generation counter, `active.env`, `previous.env`, the `database-ready` marker |
| `caddy/conf/` | `Caddyfile` and `upstream.caddy`, written by `deploy-host.sh` (`0755` / `0644`) |
| `caddy/data/`, `caddy/config/` | Caddy data: ACME account and certificates. Owner UID `65532`, mode `0700`, created once by root. Back up `caddy/data` in the public mode |

Docker objects: volumes `brevity_redis_data`, `brevity_geoip_data` (the
MaxMind database shared by every generation) and `brevity_pgsql_data`
(bundled database); networks `brevity_proxy` (Redis, bundled PostgreSQL)
and `brevity-apps` (Caddy and the web role, fixed subnet).

## Settings

`deploy-host.sh` reads these keys from `.env` (template:
`.env.production.example`) and refuses inconsistent values before it
changes anything:

| Key | Meaning |
|---|---|
| `CADDY_MODE` | `public`: Brevity is the only site on the host, Caddy holds 80/443 and obtains certificates for `APP_HOST`. `behind-host`: a web server on the host terminates TLS and proxies to `127.0.0.1:CADDY_PORT` |
| `ACME_EMAIL`, `TLS_MAX` | Public mode: ACME contact; highest TLS version (`tls1.3` by default) |
| `CADDY_PORT` | Behind-host mode: loopback port of the application Caddy (default `8082`) |
| `CADDY_TRUSTED_PROXY` | Behind-host mode: gateway of `brevity-apps`, the address the host web server connects from. The only source whose `X-Forwarded-*` Caddy keeps |
| `BREVITY_APPS_SUBNET` | Fixed subnet of `brevity-apps` (default `172.31.250.0/24`) |
| `TRUSTED_PROXIES` | Must equal `BREVITY_APPS_SUBNET`: the application trusts only its own Caddy |
| `BREVITY_DATABASE` | `external`, `bundled` or `host`; `host` also needs `DATABASE_NETWORK`, the Docker network of the host PostgreSQL |
| `APP_HOST` | Every served name, technical host first. Caddy serves exactly these names; an unknown name gets no certificate |

The version shown in the panel and `SENTRY_RELEASE` are baked into the
image. Do not set `APP_VERSION` in `.env`.

## Host web server (behind-host)

The host web server terminates TLS for every `APP_HOST` name, sets HSTS
and the HTTP-to-HTTPS redirect, and proxies to the application Caddy.
Example site for a Caddy on the host (`/etc/caddy/sites/brevity.caddy`):

```caddyfile
brevity.example.com, lnk1.example.com {
	tls {
		protocols tls1.2 tls1.3
	}
	header Strict-Transport-Security "max-age=31536000"
	request_body {
		max_size 20MB
	}
	reverse_proxy 127.0.0.1:8082 {
		header_up X-Forwarded-Port 443
		header_up -X-Forwarded-Prefix
	}
}
```

The global options of the host Caddy keep `protocols h1 h2` and
`ocsp_stapling off`, and its `write_idle` stays above the 60 s read timeout
of the application route. Apply with `caddy validate` and
`systemctl reload caddy`. The application Caddy publishes its port on
`127.0.0.1` only, and trusts `X-Forwarded-*` only from the gateway of
`brevity-apps`.

## Moving from the layout before generations

Before generations, production ran web, Horizon and the scheduler in the
project `brevity` (`brevity-web-1` on `127.0.0.1:8081`,
`brevity-horizon-1`, `brevity-scheduler-1`), and the host web server
proxied to port 8081. The move
is one deploy plus a change on the host; HTTP keeps working throughout.

1. Back up the database.
2. In `.env` set `CADDY_MODE=behind-host`, `CADDY_PORT=<a free loopback port>`,
   `BREVITY_APPS_SUBNET=<free /24>`, `TRUSTED_PROXIES=<the same subnet>`,
   `CADDY_TRUSTED_PROXY=<first address of the subnet>`,
   `BREVITY_DATABASE=host` and `DATABASE_NETWORK=<network of the host
   PostgreSQL>`; keep `DB_HOST`. The old web read its configuration at
   start and is not affected.
3. As root, once: `install -d -m 700 -o 65532 -g 65532
   /opt/brevity/caddy/data /opt/brevity/caddy/config`.
4. Run **Actions → Deploy production** with the release tag. The script
   stops the old Horizon and scheduler (and disables their restart
   policy), migrates, starts generation 1 and Caddy on
   `127.0.0.1:<CADDY_PORT>`.
   The old web keeps serving through port 8081.
5. On the host, point every Brevity site at `127.0.0.1:<CADDY_PORT>`,
   then `caddy validate` and `systemctl reload caddy` (with Nginx on the
   host: change only `proxy_pass`, keep its forwarding headers, then
   `nginx -t` and `systemctl reload nginx`). Check that
   `curl -sI https://<technical-host>/up` returns `X-Brevity-Generation`.
6. `sh /opt/brevity/deploy-host.sh retire-legacy` removes the old web,
   Horizon and scheduler (and `nginx-proxy` / `acme-companion`, if any).
7. Remove leftover volumes of the old layout (`brevity_nginx_*`) after a
   check.

Until step 6 the way back is: point the host site at 8081 again, then
`docker update --restart=always brevity-horizon-1 brevity-scheduler-1` and
`docker start brevity-horizon-1 brevity-scheduler-1`.

## Deploying your own instance

GitHub Actions publishes `ghcr.io/vaslv/brevity` for `linux/amd64`. The
server needs Docker with Compose v2. In the public mode it also needs free
ports 80/443 and DNS A records of every served name pointing at it; do not
publish AAAA records while Docker networks are IPv4 only, or every IPv6
visitor reaches Caddy from one bridge address.

1. Copy `docker-compose.production.yml`, `docker-compose.generation.yml`,
   `docker-compose.db.yml`, `docker-compose.host.yml`, `Caddyfile`,
   `deploy-host.sh` and `.env.production.example` of a release tag into a
   dedicated directory. Run `cp .env.production.example .env` and
   `chmod 600 .env`. Preserve the existing `.env` and keys when upgrading.
2. The image becomes available after the first successful **Docker
   image** workflow run. To allow anonymous pulls, the owner must make the
   package Public under GitHub → Packages → package → Package settings →
   Change visibility. For private packages, use
   `docker login ghcr.io -u <github-user>` and enter a classic PAT with
   `read:packages` at the password prompt.
3. Generate two independent secrets for a new installation:

   ```bash
   docker run --rm --entrypoint php ghcr.io/vaslv/brevity:<release> -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
   docker run --rm --entrypoint php ghcr.io/vaslv/brevity:<release> -r 'echo bin2hex(random_bytes(16)).PHP_EOL;'
   ```

   Store the first as `APP_KEY`, the second as `HASHIDS_SALT`. Keep the
   salt unchanged once links exist. Fill `APP_URL=https://<technical-host>`,
   `APP_HOST` (all hostnames, comma-separated), the mode settings above,
   SMTP and `DB_*`. Keep `REDIS_HOST=redis`. GeoIP is optional
   (`GEOIP_LICENSE_KEY`).
4. Choose **one** PostgreSQL setup:
   - **Bundled:** `BREVITY_DATABASE=bundled`, `DB_HOST=pgsql`,
     `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME` and a nonempty
     `DB_PASSWORD`. Data persists in `brevity_pgsql_data`; the port is not
     exposed. Editing `.env` does not change the password of an
     initialized database.
   - **External:** `BREVITY_DATABASE=external` and a reachable `DB_HOST`,
     `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` and, if
     required, `DB_SSLMODE`. The database must exist and the user must
     have migration privileges. `localhost` is the container itself; use
     `host.docker.internal` for PostgreSQL on the Docker host.
5. Prepare the directories once and allow the first migration:

   ```bash
   sudo install -d -m 700 -o 65532 -g 65532 caddy/data caddy/config
   mkdir -p .deploy && chmod 700 .deploy && touch .deploy/database-ready
   ```

   Create `.deploy/database-ready` **only when the database is ready**;
   after a restore, verify the backup first. Without the marker the script
   refuses to run migrations.
6. Deploy and create the first administrator:

   ```bash
   sh deploy-host.sh deploy ghcr.io/vaslv/brevity:<release>
   sh deploy-host.sh artisan make:filament-user
   ```

   Sign in at `https://<technical-host>/login`. Other configured hosts
   serve short links only, not the admin panel, API or Horizon.

## GitHub builds and updates

Workflow `.github/workflows/docker.yml`:

- `pull_request`: validation of the Compose configurations, the
  deployment script test and a build without publishing;
- push to `main`: publishes `latest` and `sha-<full SHA>` to GHCR;
- push of a version tag like `1.2.3`: publishes only the release tag,
  without changing `latest` and `sha-<full SHA>`;
- `workflow_dispatch`: manual run; publishing only for `main` and tags.

It uses the built-in `GITHUB_TOKEN` with `packages: write`; no extra
registry, SSH or database secrets are needed, and it never connects to
the server. The separate `CI` workflow runs the tests and static analysis.

Release and SHA tags can be overwritten by a rebuild; `deploy-host.sh`
resolves the tag to a digest at pull time and runs that digest.

Production is updated by **Actions → Deploy production → Run workflow**
on `main` with a published tag (`1.12.0`) or `sha256:<digest>`. A new
image alone does not update the server. The workflow runs in the GitHub
Environment `production`:

- Variables: `DEPLOY_HOST` (server address), `DEPLOY_USER` (SSH user).
- Secrets: `DEPLOY_SSH_KEY` (dedicated private deploy key),
  `DEPLOY_KNOWN_HOSTS` (the verified SSH host key entry).
- The public key is in `authorized_keys` of that user, who has access to
  `/opt/brevity` and Docker.

It uploads the deployment files of the selected commit and runs
`deploy-host.sh deploy`. A self-hosted instance runs the same command by
hand. Never run `docker compose down -v` on the stable project: it
deletes the persistent volumes.

## Manual operations on the server

```bash
cd /opt/brevity
sh deploy-host.sh status                  # live generation, route, containers
sh deploy-host.sh artisan horizon:status  # php artisan in the live web role
docker compose -p brevity-gen-<N> logs --tail 100 web horizon scheduler
curl -sI https://brevity.example.com/up   # X-Brevity-Generation: <N>
```

`sh deploy-host.sh entrance` applies a changed Caddy service definition
(a new Caddy image digest, ports, limits): it validates the configuration
and recreates Caddy. This interrupts the entrance for a few seconds; run
it in a window. A changed `Caddyfile` alone needs no window: every deploy
applies it with a reload. After any change of the `Caddyfile`, the Caddy
service or proxy trust, run `sh tests/caddy-chain.sh` locally.

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
sh deploy-host.sh artisan user-agents:detect-bots
sh deploy-host.sh artisan clicks:rebuild-counters   # only AFTER detect-bots
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
  sh deploy-host.sh artisan geo:download-db
  ```

  The database lives on the shared `geoip_data` named volume (mounted
  into every generation), so a download from the web role is
  immediately visible to the reader in Horizon, where the lookups
  happen, and survives every release. From
  then on the database keeps itself fresh: a click asynchronously
  pings the age check and, when the database is stale, schedules a
  background re-download (no cron needed). A worker that has not yet
  opened the reader picks up the initial install immediately; on a
  **manual re-download over an already open** database the octane
  workers keep reading the old inode until a restart — if an
  immediate swap is needed, run
  `sh deploy-host.sh artisan octane:reload` (geo data
  changes slowly, usually not required).
- Backfilling geolocation for historical clicks — **after** the
  database is installed (without it the command exits immediately
  without scanning anything). Idempotent and resumable, can be run in
  the background in separate passes:

  ```bash
  sh deploy-host.sh artisan geo:locate-clicks
  ```

**`trustProxies`.** The application trusts `X-Forwarded-*` from
`TRUSTED_PROXIES`, and `deploy-host.sh` requires it to equal the
`brevity-apps` subnet, where only its own Caddy sends requests from. The
code still falls back to all private ranges (`10/8`, `172.16/12`,
`192.168/16`) when the variable is empty; that fallback goes away with
item A1 of [07-plans.md](./07-plans.md).

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
