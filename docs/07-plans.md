# Development plans

The single home of all **active** plans. A completed stage is removed
from here in the same commit that closes its last item (long-lived
decisions go to [08-decisions.md](./08-decisions.md); git history
preserves the rest).

Process: for every stage — a detailed plan (a section right here),
owner sign-off, commit-by-commit implementation.

---

## 1. Accepted product decisions (2026-07-11)

Recorded in [08-decisions.md](./08-decisions.md) ("Product decisions
2026-07-11"): the `is_bot` flag always at the root of the callback
payload; A/B sticky — hash of `(ip, user_agent, link_id)`; raw IP +
scheduled pruning (~90 days); no custom slugs. Approved dependencies:
`jaybizzle/crawler-detect` (stage 1; replaced by
`matomo/device-detector` in stage 3).

## 2. Stage and dependency map

| # | Stage | Depends on | New dependencies |
|---|---|---|---|
| 1 | Bots + click counters | — | jaybizzle/crawler-detect |
| 2 | Link lifecycle + PATCH API | counters (max clicks) | — |
| 3 | New conditions + A/B split | — | matomo/device-detector |
| 4 | Click geolocation | — | geoip2/geoip2 + MaxMind key |
| 5 | Geo conditions and geo analytics | 3 (framework), 4 (data) | — |
| 6 | Domain reputation (not-found, orphan, robots, Safe Browsing) | — | Google Safe Browsing key |

Stages 3, 4, and 6 are independent of each other — their relative order
can change; 5 requires both 3 and 4.

---

## 3. Stage 1 — bot detection + slotted counters

**Status: implemented 2026-07-12** (all 8 items, each passed Codex
review). The deploy steps below remain to be executed; after the deploy
the section is removed per the rules in the readme.

Done as a single bundle so that the counters are born with the
"bot / non-bot" breakdown from the start and do not have to be migrated
twice.

### Key design decisions

**The bot flag lives on the `user_agents` dictionary, not on the
click.** The User-Agent is already normalized into a dictionary
(`user_agents.value` is unique). UA-based detection is deterministic →
the flag is computed **once per unique UA** when the dictionary row is
created. A click gets the attribute via `userAgent->is_bot`. A click
without a UA counts as **not a bot**.

**Detection — `jaybizzle/crawler-detect`** behind our own `BotDetector`
interface (a single point for swapping the library in stage 3).

**Counters are slotted, per-link, with a bot breakdown.** Table
`link_click_counters (link_id FK, is_bot boolean, slot smallint 1..100,
count bigint)`, unique `(link_id, is_bot, slot)`. The increment is an
UPSERT (`ON CONFLICT ... DO UPDATE SET count = count + 1`) **in the
same transaction as the click insert** and only when
`wasRecentlyCreated` (a `RecordClickJob` retry with an existing `uuid`
does not touch the counter). The 100 slots spread out lock contention —
no hot row. Total = sum of slots.

**What the counters do NOT solve.** They provide per-link totals.
Time-windowed dashboard queries ("today", "this week", the per-day
chart) stay on indexed COUNTs over `clicks(created_at)` — deliberately.
The counters cover: `clicks_count` in the links table (currently
`withCount`, degrades as data grows), sorting by clicks, and the
"excluding bots" breakdown.

### Stage 1a steps — detection

1. `composer require jaybizzle/crawler-detect`.
2. Migration: `user_agents.is_bot boolean NOT NULL DEFAULT false`.
3. Service `App\Services\Links\Clicks\BotDetector` — a wrapper around
   CrawlerDetect behind our own interface.
4. `ClickRecorder`: UA resolution gets a dedicated branch: set `is_bot`
   when creating a new `user_agents` row. The insertOrIgnore + SELECT
   pattern is kept (race-safe). Existing rows are left untouched.
5. Callbacks (contract, 03-api.md §10): the `{{click.is_bot}}` variable
   (`"true"`/`"false"`) + `is_bot: bool` always at the root of the
   payload; a client-supplied `is_bot` key is overridden (document
   this).
6. Backfill: the `user-agents:detect-bots` command — keyset traversal,
   recomputes the flag; `Cache::lock` against concurrent runs. Will
   also come in handy when the pattern library is updated.
7. Admin panel: a "bot" `IconColumn` + `TernaryFilter` in Clicks; a
   column in UserAgents. Translations in `lang/{en,ru}`.
8. An explicit `Cache-Control: no-store` on all resolve responses (302
   and interstitials) — the browser cache must not swallow repeat
   visits: every click = a callback (the opposite decision to the
   source's RED-02).

Tests: `BotDetector` unit tests (empty UA / browser / crawler); a click
sets the flag on a new UA row and does not touch an existing one; the
callback payload contains `is_bot`, the client-supplied one is
overridden; the backfill command; the `no-store` header on the resolve
response.

### Stage 1b steps — counters

1. The `link_click_counters` migration (schema above).
2. `ClickCounterIncrementer` (upsert with a random slot). Called from
   `ClickRecorder::record`, wrapped in a `DB::transaction` together
   with the click's `firstOrCreate`; increment only when
   `wasRecentlyCreated`.
3. The `LinkClickCounter` model + relations as traits
   (`app/Models/Relations/`).
4. The `clicks:rebuild-counters` command: TRUNCATE + an aggregate
   `INSERT ... SELECT` in a transaction (the slot is always 1 on
   rebuild — the distribution is only needed by live concurrent
   increments). A lock. This is both the initial backfill and a
   reconciliation tool for the future (will come in handy for mass
   click deletion).
5. Consumers: `LinksTable.clicks_count` → sum from the counters
   ("total / excluding bots", sorting); `StatsOverview` — the "total
   clicks" card from the counters; "today / this week" and
   `ClicksChart` — unchanged.
6. An invariant in 01-architecture.md: all "link click numbers" in
   lists/sorting come from the counters; a counter changes only in a
   transaction with a click or via the rebuild command.

Tests: increment on a new click and NO increment on a retry with the
same uuid; the is_bot breakdown; rebuild converges with COUNT
(including clicks without a UA); the column and sorting in LinksTable.

### Stage 1 deploy

1. Migrations (column + table).
2. `user-agents:detect-bots` (flag backfill).
3. `clicks:rebuild-counters` — strictly after the flag backfill; the
   TRUNCATE inside the rebuild guarantees no double counting with
   clicks recorded between the deploy and the rebuild.
4. Update 03-api.md §10 (notify partners), 02-glossary.md,
   01-architecture.md.

---

## 4. Stage 2 — link lifecycle + PATCH API

**Status: implemented 2026-07-12** (all 10 items, each passed Codex
review). After the deploy the section is removed per the rules in the
readme.

Product decisions were made 2026-07-12 (see
[08-decisions.md](./08-decisions.md)): `max_clicks` counts all clicks
including bots; we add `valid_since`; we introduce `/api/v1`.

**Model.** `links` + `valid_since timestamptz NULL`, `valid_until
timestamptz NULL`, `max_clicks integer NULL`.

**Resolution.** A link is "alive" when all of the following hold at
once: `valid_since` is not in the future, `valid_until` is not in the
past, the counter sum (all clicks, including bots) < `max_clicks`. A
dead link = 404, indistinguishable from a nonexistent one: no click, no
callback. Because click recording is asynchronous the limit is slightly
"soft" — deliberately: synchronous reservation was rejected (it
contradicts the asynchronous core), the error margin is bounded by the
queue lag (seconds) and is documented in 03-api.md.

**API version.** All new endpoints and contract changes go under
`/api/v1` (the same controllers as legacy, plus the new bits). Legacy
`/api/*` is frozen: the old error format, the old feature set,
deprecated in 03-api.md.

**PATCH `/api/v1/links/{code}`** — a new endpoint. Editable:
`valid_since`, `valid_until`, `max_clicks`, `title`, `forward_query`,
`callback_data`, rules (by replacing the whole set). Immutable: code,
domain, service. Sentinel pattern: "not passed" ≠ "null"; null clears
the constraint.

**GET `/api/v1/links/{code}`** — reads the link state: the same shape
as the POST response, plus `valid_since`/`valid_until`/`max_clicks` and
a click summary from the counters (total / excluding bots).

**Access and token scope.** New abilities `links:read` /
`links:update` (issued from the admin panel, like `links:create`).
Strict tenant scope: a token sees and edits **only its own service's
links**; someone else's code → 404 (not 403 — do not reveal that the
code exists). Full-fledged role filters (KEY-02) remain deferred.

**Traffic hygiene (TRK-04).** A config list of ignored sources
(IP / CIDR): on a match the click is not recorded and the callback is
not sent (checked in `RecordClickJob` against the original IP). Plus a
tracking opt-out query parameter (name in config) for test visits; it
is stripped from the `forward_query` pass-through. Right now office
checks and monitoring produce not only junk clicks but real callbacks
to partners.

**Visit query → callbacks (GAP-03).** The click stores the raw query
string of the visit (`visited_query`, byte-based truncation like the
referrer's); `callback_data` gets `{{click.query.<param>}}`
placeholders (empty string when absent). The partner gets their
sub_id/utm back in the postback — currently the query can only be
forwarded into the target URL, but not into the callback. Contract —
03-api.md §10.

**API errors.** Under `/api/v1` — the RFC 7807 Problem Details format
with stable machine codes (`validation-error`, `link-not-found`, …);
legacy routes keep the old format until decommissioned.

**Pruning** (artisan, locks, keyset):
- `links:prune-dead` — soft-deletes links with an expired
  `valid_until` / exhausted `max_clicks`; `--dry-run`; does nothing
  without condition flags (a safeguard);
- `ips:prune` — clicks older than N days (default 90, config) get
  `ip_address_id = NULL`, orphaned `ip_addresses` rows are deleted.
  In-place masking will not do: the unique index on `value` would
  produce mask collisions. Scheduler daily;
- `clicks:prune --link=` (+ an admin action) — deletes a link's clicks
  (GDPR requests, scanner junk, TRK-06); transactional with a rebuild
  of that link's counters.

**Admin panel.** Deletion threshold: `requiresConfirmation` with a
warning when clicks exceed the threshold (config, default 15); "hide
dead" filters; valid_until/max_clicks fields in the forms.

No open questions remain — all decisions are recorded in
[08-decisions.md](./08-decisions.md) (2026-07-12).

## 5. Stage 3 — new conditions + A/B split

**Status: implemented 2026-07-12** (all 8 items, each passed Codex
review). After the deploy the section is removed per the rules in the
readme.

The framework is ready: `ConditionRegistry` + handlers,
`ConditionContext` carries the whole `Request`.

**Step 0 — multi-conditions on a rule (RUL-01).** A rule currently has
exactly one `condition_id`; as the number of types grows, AND
combinations are needed right away ("device=ios AND utm_source=tg").
Model: a `condition_rule` pivot (many-to-many; a rule matches when
**all** of its conditions match; a rule without conditions is
unconditional, as now). `rules.condition_id` migrates into the pivot.
API: `condition` → `conditions: []`; a single `condition` keeps being
accepted (backward compatibility); the per-rule condition limit is
enforced by validation. Done **before** the new types — cheaper than
migrating afterwards.

**Conditions** — per the "Adding a new condition type" checklist from
01-architecture.md, in order of value:
1. `after_date` — a mirror of `time_before`;
2. `query_param` — exact key=value;
3. `ip_address` — exact / CIDR / wildcard;
4. `device` — android/ios/mobile/desktop/…; "UA → multiple types"
   matching (iPhone = ios AND mobile), enum validation;
5. `language` — Accept-Language with a q ≥ 0.9 threshold.

**Device detector**: `matomo/device-detector`; at the same time
**migrate `BotDetector` to it and drop crawler-detect** (a single
dependency). After the migration — `user-agents:detect-bots` +
`clicks:rebuild-counters` (the patterns differ).

**A/B split.** Table `rule_variants (rule_id FK cascade, url_id FK,
weight smallint, label nullable)`. When a rule wins → if variants
exist, the URL is chosen by weights; `rules.url_id` is the default
(without variants the behavior stays as it is now). Sticky — per the
decision in §1; the split is `hash % sum(weights)` (weights do not
have to sum to 100). The click records the actual `url_id` **and**
`rule_variant_id` (nullable FK) — the variant is distinguishable even
when variants lead to the same URL; the callback gets a
`{{click.variant}}` placeholder (label or ordinal number — to be
pinned down during detailing; empty without a split).

**API**: `variants: [{url, weight, label?}]` inside a rule in POST and
PATCH. Validation: weight ≥ 1, ≥ 2 variants.

Tests: a unit test per handler; the AND semantics of multi-conditions
(all / not all match, empty set); sticky determinism (same ip+ua →
always the same variant) + a frequency test over a grid of inputs; the
API with variants and `conditions[]` (including backward compatibility
of a single `condition`).

## 6. Stage 4 — click geolocation

**Status: implemented 2026-07-12** (all 6 items, each passed Codex
review). The deploy steps remain to be executed (see
[06-deploy.md](./06-deploy.md)); after the deploy the section is
removed per the rules in the readme.

**Resolution** — inside `RecordClickJob` (click recording is already
asynchronous, the redirect is not affected). A local MaxMind
GeoLite2-City database, the `geoip2/geoip2` package, the key in env
(kept in sync across `.env` / `.env.example` / `.env.production`).

**Schema**: a `geo_locations` dictionary `(country_code, region, city,
UNIQUE on the tuple)` + `clicks.geo_location_id FK NULL`. We do not
store coordinates / timezone (no consumer for them).

**Traffic-driven auto-update** (no cron): after a click — an
asynchronous check of the database age (> 30 days → download);
`Cache::lock`; attempt history; backoff after a series of errors. The
initial download is `geo:download-db` during deploy.

**Geolocation catch-up**: `geo:locate-clicks` — keyset over clicks
without a location. The IP may already have been purged by `ips:prune`
— those stay without a location.

Geo failures never break click recording: an empty location + a
warning.

## 7. Stage 5 — geo conditions and geo analytics

- The `country` condition (ISO 3166-1 alpha-2). Requires geo
  **synchronously in the resolve**: `GeoLocator` is called in
  `ResolveLink` lazily — only when the link has a country rule; the
  result is passed through to `RecordClickJob` to avoid resolving
  twice.
- Admin panel: country/city in Clicks (filters), a "clicks by country"
  widget with an "excluding bots" breakdown.
- Callbacks: the `{{click.country}}`, `{{click.city}}` variables
  (contract).

## 8. Stage 6 — domain reputation

**Not-found redirects.** Three miss types: the domain root / an
invalid or dead code / any other 404. Settings: `root_redirect_url`,
`invalid_code_redirect_url`, `not_found_redirect_url` on `domains` +
global values in the application settings; priority: domain → global
→ 404. Placeholders `{DOMAIN}` / `{ORIGINAL_PATH}`.

**Orphan visits** — a **separate table** `orphan_visits (type, domain_id,
path, referrer_id, user_agent_id, ip_address_id, redirect_url,
created_at)`, not `clicks` with nullable FKs: a miss has neither a
link nor a service. `redirect_url` is where the miss was actually
redirected (NULL if a 404 was served): it shows how the not-found
rules fired and where traffic leaks away. Recorded asynchronously, the
dictionaries are reused. An admin panel resource + a filter by type;
include retention in the pruning. A separate limit on orphan
recording — decide during detailing.

**robots.txt** — a route with `Disallow: /` (links are not meant for
indexing).

**Safe Browsing.** A target URL check on creation (an asynchronous
job) + periodic recheck of active URLs (Lookup API batches). A flag on
`urls` (`flagged_at`, `flag_reason`). **The reaction to a flag is an
open question**; recommendation: do not auto-block, but a notification
+ a badge in the admin panel (false positives cost more). Fetch
hygiene: timeouts, no redirects; `CallbackUrlGuard` as the model.

---

## 9. Cross-cutting rules for all stages

- Every stage: detailed plan → sign-off → implementation, one commit
  per logical step.
- Invariants: an integration/enrichment failure does not affect the
  redirect or click recording; bulk operations — keyset + a lock;
  model relations — as traits.
- Contracts: a change to the callback payload or the API means editing
  03-api.md in the same commit; terminology — 02-glossary.md;
  subsystems — 01-architecture.md.
- env keys — kept in sync across `.env` / `.env.example` /
  `.env.production`.
- Tests for every step, `sail bin pint --dirty` before finalizing.

## 10. Deferred (decisions recorded, not part of the plan)

Custom slugs and everything behind them (multi-segment slugs, extra
path, trailing slash); a tracking pixel; 307/308; auto-title; QR;
events/brokers (Mercure/RabbitMQ); full token role filters (KEY-02 —
the mandatory PATCH/GET tenant scope is part of stage 2); DNT/GPC;
findIfExists; import from external shorteners; CLI parity; Matomo;
domain fallback (conflicts with the rotation model); tags (TAG-* —
campaign grouping lives in the client's `callback_data`; revisit when
per-campaign admin analytics is needed); a pagination envelope
(API-03 — until list endpoints appear); CSV click export (ANA-03 —
stock Filament, on demand); an in-depth health check (API-06 — `/up`
already exists).

From the 2026-06 audit (the findings registry is in
[08-decisions.md](./08-decisions.md)): a role model beyond the
`users.is_admin` flag — introduce when untrusted operators appear;
`clicks`/`callbacks` retention — partially covered by the stage 2
pruning, the full policy is a separate decision.

The 2026-07-13 cross-review (registry r20–r33 — in
[08-decisions.md](./08-decisions.md)) has been fully worked through:
all items are implemented, the fix plan is closed.

From the 2026-07-12 cross-review (registry r1–r19 — in
[08-decisions.md](./08-decisions.md)), scalability for a large
`clicks` table — revisit as load grows:

- **PATCH `rules` on a link with A/B history (r16).**
  `$link->rules()->delete()` cascades a NULL into
  `clicks.rule_variant_id` across the whole history inside the request
  transaction and erases A/B attribution retroactively. Options:
  denormalize the variant label onto the click (preserving
  attribution) or detach instead of delete; batch the nulling outside
  the lock. Not painful at the current scale.
- **env-configurable `trustProxies` (r17).** The proxy subnet list
  goes to env so production can narrow it without editing
  `bootstrap/app.php`.
- **`clicks:rebuild-counters` without a global lock (r15).** Aggregate
  into staging + a short swap — only if it becomes a bottleneck;
  currently REJECTED (the current `ACCESS EXCLUSIVE` is precisely the
  guarantee that increments between the snapshot and the TRUNCATE are
  not lost).
- **Denormalizing `is_bot` onto `clicks`.** If the admin bot filter
  over `clicks × user_agents` becomes slow — capture the flag on the
  click at write time + a partial index.
- **Partial lifecycle indexes on `links`** (`valid_until`,
  `max_clicks`) for `links:prune-dead` and the "alive" admin filter —
  as the number of links grows.

---

## 11. Engineering baseline alignment (audit 2026-11-24)

Result of a full audit of the repository against the maintainers'
engineering baseline. Independent of the product stages above.

**How to execute.** One item = one commit (`type(scope): description`)
in `main`, in the listed order unless an item says otherwise. Every
behaviour change ships with a test, written red first. Before each
commit: Pint, PHPStan, the affected tests; after B1 — `composer
quality`. Docs (EN + `docs/ru/`) change in the same commit as the
behaviour. An item is removed from this section in the commit that
closes it. Items marked **[approval]** change dependencies, delete
tracked files, or touch GitHub settings or the server — stop and ask
the owner before doing that part. Never push, tag or deploy without an
explicit request. Before using a framework or package API, check the
installed version and its documentation.

**Owner decisions already taken (2026-11-24).** Every panel user is an
administrator, roles are not used yet. `declare(strict_types=1)` is
adopted as planned work (E4). The default time zone of this public
project stays `UTC`.

### A. Security and production hygiene

**A1. Trusted proxies come from config, never from `env()` at
bootstrap.** `bootstrap/app.php` calls `env('TRUSTED_PROXIES')`. With
a cached config the mounted `.env` is not loaded, so the value is
expected to be ignored and `TrustedProxies::DEFAULT_RANGES` (all of
RFC 1918) to apply; `'*'` is also accepted.
- First reproduce: a test that boots with the value present only in
  config and asserts it is honoured.
- Add `'trusted_proxies' => env('TRUSTED_PROXIES')` to
  `config/app.php`; apply it from a service provider's `boot()` through
  the framework's trusted-proxy middleware API; drop the `env()` call
  from `bootstrap/app.php`.
- `App\Support\TrustedProxies`: remove `DEFAULT_RANGES` and the `'*'`
  branch; an empty value means "trust nobody". Update its unit test.
- `docker-compose.production.yml`: pin the subnet of the `proxy`
  network (`ipam`) and document that `TRUSTED_PROXIES` equals it (or
  the host gateway address in the host-proxy layout). Update both env
  templates and `docs/06-deploy.md`.
- Feature tests: `X-Forwarded-For` from an address inside the trusted
  subnet yields the forwarded client IP; from an address outside it is
  ignored.

**A2. A policy for every Filament resource.** There is no
`app/Policies`; `User::canAccessPanel()` returns `true`.
- Generate a policy per model behind the 14 resources
  (`make:policy --model`). All users are administrators, so every
  ability returns `true` for an authenticated user — through one shared
  trait or base class, so that roles can be introduced later in a
  single place. Keep the existing restrictions as they are (read-only
  resources, `RestrictedDeleteAction`, no force-delete on links).
- Settings: register a policy for the settings model of the package.
- Tests: a guest gets no access; a user reaches list/view/create/edit
  of each resource; the existing restrictions still hold.

**A3. Authentication log. [approval: new dependencies]** Record every
login, failed login and logout with IP and user agent.
- Check that `rappasoft/laravel-authentication-log` and
  `tapp/filament-authentication-log` support the installed Laravel and
  Filament majors, their maintenance state and licence; report to the
  owner, then `chore(deps)` as its own commit.
- Wire the trait into `User`, add the Filament plugin to the panel,
  set an explicit retention period and schedule the purge command
  (with the guards from A5).
- Tests: successful login, failed login with an existing and with an
  unknown e-mail, logout — each leaves a record.

**A4. `Model::shouldBeStrict()` outside production.** Add
`Model::shouldBeStrict(! $this->app->isProduction())` to
`AppServiceProvider::boot()`. Run the whole suite; fix every lazy-load
with `with()` / `load()` at the query, every missing attribute at its
source. No `withoutStrict` escapes.

**A5. Scheduler guards** (`routes/console.php`).
- Every task: `withoutOverlapping()` with an expiry longer than the
  task can run.
- Tasks that write data or call out (`callbacks` redispatch,
  `sanctum:prune-expired`, `ips:prune`, later the auth-log purge):
  `onOneServer()`, `sentryMonitor()`, and `runInBackground()` when they
  can exceed 5 seconds.
- The 10-second heartbeat and the Horizon snapshot only get
  `withoutOverlapping()`.
- Test: iterate `Schedule::events()` and assert the flags per command.

**A6. Explicit job limits.** `RecordClickJob`: add `$backoff` and
`$timeout`. `SendCallbackJob`: add `$timeout` above the 10 s HTTP
timeout. `UpdateGeoDatabaseJob`: add `$tries` and `$backoff` (keep
`$timeout = 300`; `retry_after` 360 and the supervisor timeout stay
above it). Test the values through the job instances.

**A7. Sentry configuration.** Publish `config/sentry.php`. Set
explicitly: `traces_sample_rate` and `profiles_sample_rate` from env
with a default of `0.1`, `send_default_pii` false, `environment` from
`SENTRY_ENVIRONMENT`, and an ignore list for 404, validation and
authorization exceptions. Add the keys with comments to both env
templates; pin `SENTRY_LARAVEL_DSN=""` in `phpunit.xml`. Test the
config values.

**A8. Structured logs.** `.env.production.example`:
`LOG_CHANNEL=stderr`,
`LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter`. `.env.example`:
`LOG_STACK=daily` instead of `single`. Update `docs/06-deploy.md`.

**A9. Dry-run by default for bulk data commands.** `PruneClickIps`,
`PruneLinkClicks`, `RebuildClickCounters`, `BackfillGeoCoordinates`,
`LocateClicks`, `DetectBotUserAgents` follow `PruneDeadLinks`: without
`--execute` they only report what would change; add `--limit`. The
scheduled `ips:prune` gets `--execute`. Update the command list in the
docs and the pending post-deploy steps that call these commands.
Tests: no writes without `--execute`; `--limit` is respected.

**A10. Time-zone-aware timestamps in framework tables.** New migration
converting `users`, `password_reset_tokens`, `personal_access_tokens`
and `failed_jobs` timestamp columns to `timestamptz` (state explicitly in
which zone the stored values are interpreted — look at how the earlier
`timestamptz` migrations in `database/migrations` handled it). Check on a disposable database: `migrate` and
`migrate:rollback`.

### B. CI and quality tooling

**B1. `composer quality` and `composer quality:ci`.**
- `quality`: `composer audit --locked`, `pint --test`, PHPStan.
  `quality:ci`: `quality` plus the full test suite.
- `config`: `"audit": {"abandoned": "report"}`, `"platform": {"php":
  "<image runtime>"}`; `require.php` → `^8.4` (the image runs 8.4).
  **[approval: `composer.json`/lock change]**
- Remove the `dev` script (it runs `artisan serve` and `queue:listen`,
  neither is how the app runs) and the unused `pestphp/pest-plugin`
  entry in `allow-plugins`.
- `CONTRIBUTING.md` and `docs/05-development.md` switch to the two
  scripts.

**B2. GitHub Actions hardening.**
- `ci.yml`: `permissions: contents: read`, every action pinned by full
  commit SHA with the version in a comment, `persist-credentials:
  false`, run `composer quality:ci`; make it reusable
  (`workflow_call`) and trigger it for tags too.
- `docker.yml`: the image job `needs` the quality job, so a red check
  blocks the build. Stop publishing the `latest` tag; replace the
  `:latest` default in `.env.production.example` and the compose error
  hint with a release tag / digest placeholder.
- New job: `shellcheck` over the deploy scripts.
- New `.github/dependabot.yml`: composer, npm, github-actions, docker,
  each with a cooldown.

**B3. Protect `main` and tags. [approval: GitHub settings]** The
repository has no rulesets and `main` is unprotected. Create two
rulesets through the API: `main` — no force-push, no deletion; tags
`*.*.*` — no update, no deletion. Record them in `docs/06-deploy.md`.

**B4. PHPStan configuration and model docblocks.** Add
`parseModelCastsMethod: true` and `tmpDir: storage/phpstan` (ignore
the directory). Delete the hand-written `@property` / `@mixin` blocks
from all 16 models — types come from migrations and `casts()`. If
Larastan loses columns created through raw SQL, commit a schema dump
(`schema:dump`) instead of restoring docblocks. No baseline, no
`@phpstan-ignore`.

**B5. PHPStan level ratchet 5 → max.** One commit per level; fix at
the source. The level is never lowered.

**B6. Rector as a metric. [approval: new dev dependencies]**
`rector/rector` + `driftingly/rector-laravel`, `rector.php` covering
`app/` with the PHP sets and the Laravel code-quality and collection
sets. Only `--dry-run` at first; applying suggestions is separate work
per subsystem.

**B7. Local environment parity.** `docker-compose.yml`: run Octane on
FrankenPHP with `--watch` through `SUPERVISOR_PHP_COMMAND`; pin
`redis` and `postgres` to the production versions from the version
passport (D3). `.editorconfig`: the compose section becomes
`[{docker-compose,compose}*.{yml,yaml}]`. One-off per clone, documented
in `docs/05-development.md`: `git config release.tagMessage 'Release
{tag}'`.

### C. Image, Compose and server

**C1. Dockerfile.**
- Non-root: a user with a fixed numeric UID, `USER` before start,
  writable `storage` and `bootstrap/cache` for that UID, `setcap -r`
  on the FrankenPHP binary. The `geoip_data` volume must be `chown`ed
  on the server right before the first non-root rollout — document it
  in `docs/06-deploy.md` as a one-time step.
- Pin the Composer image by version and digest; keep composer, git and
  unzip out of the final stage (separate build stage for `vendor/`).
- `composer install … --classmap-authoritative`; production `php.ini`
  with OPcache on and `display_errors=Off`.
- Verify: build locally, run the container, `/up` answers, `id` shows
  the UID, Horizon and the scheduler start.

**C2. `docker-compose.production.yml`.** `restart: unless-stopped`;
memory and CPU limits per role; `cap_drop: [ALL]` and
`security_opt: ["no-new-privileges:true"]` on application roles;
`json-file` logging with `max-size` / `max-file`; explicit `--workers`
and `--max-requests` for Octane (at most two workers per allotted
CPU); `redis` and `postgres` pinned by version and digest; a
healthcheck on every service; separate Redis instances for queues
(`noeviction`) and cache (`allkeys-lru`) with matching connections in
`config/database.php`. Verify with `docker compose config` for all
three overlays and a local smoke run.

**C3. Caddy as the only web server. [approval: deletes tracked
files; needs a maintenance window]** Replace `nginx-proxy` +
`acme-companion` (Docker socket access, unpinned images) and the host
Nginx example.
- A Caddy service in Compose, official image pinned by version and
  digest, non-root, `cap_drop: [ALL]`, static Caddyfile in the
  repository, two modes selected by Compose profile and env: `public`
  (own TLS, 80/443) and `behind-host` (plain HTTP on
  `127.0.0.1:<port>`).
- TLS 1.2–1.3 with the upper bound from env, `protocols h1 h2`, HSTS,
  `nosniff`, frame and referrer policies, `encode zstd gzip`,
  immutable caching for hashed assets, one upload limit shared by
  Caddy, PHP and validation, timeouts ordered along the chain.
- A chain test script (client → Caddy → echo backend) built from the
  production service definition: client IP reaches the app, forged
  `X-Forwarded-*` are replaced, HTTP redirects to HTTPS, unknown SNI
  gets no certificate.
- After the production switch: delete `deploy.sh`, `deploy-proxy.sh`,
  `nginx.brevity.conf.example`, the disabled `.gitlab-ci.yml`,
  `tests/deployment-proxy.sh` and the tests that only cover them;
  rewrite `docs/06-deploy.md`.

**C4. Server layout. [approval: server work]** Move the installation
from `/opt/brevity` to `/srv/brevity` owned by a dedicated `brevity`
user; `DEPLOY_PATH` in `deploy.yml` becomes a variable. Until the move
the current paths, volume names and Compose project name stay
untouched.

**C5. Backups and monitoring are documented.** `docs/06-deploy.md`
gains: what is backed up, how often, retention, where it is stored,
the date and result of the last restore check, the decision on file
backups (the geo database is re-downloadable; proxy certificates are
not), the uptime monitor and the alert rules. Facts come from the
owner — ask, do not invent.

### D. Documentation and agent files

**D1. `AGENTS.md` project sections** above the generated block: stack
and versions, commands (run, tests, `composer quality`,
`composer quality:ci`, Pint, asset build), release scheme, data notes
(Hashids short codes are the public identifier by design, click UUIDs
are legacy, new external identifiers are ULIDs, raw IPs are kept 90
days).

**D2. `CONTRIBUTING.md`.** Commit format with a mandatory scope; the
two composer scripts.

**D3. `docs/production-software-versions.md`** (+ `docs/ru/`): each
production service with its version, the date and the way it was
checked on the running service, the date of the last update check and
of the last vulnerability check (2026-11-24: 13 Composer advisories
and 6 npm advisories found and fixed the same day). Versions come
from the server — ask the owner.

**D4. Remove finished material.** Sections 3–6 of this file once their
deploy steps are confirmed done; "Outstanding post-deploy steps" and
the historical GitLab runbook in `docs/06-deploy.md`. Long-lived
decisions move to `08-decisions.md` first.

**D5. Small fixes.** `docs/06-deploy.md`: the `DB_HOST=172.18.0.1`
example becomes a host name. Env templates: every key carries a
comment; keys present in a real production file but missing from
`.env.production.example` are added (or their absence is commented).
`docs/ru/02-glossary.md`: add a pointer file to the bilingual
glossary so the mirror has the same file set.

### E. Planned code alignment

**E1. JSON translations.** Replace the structured `lang/{en,ru}/*.php`
keys with English-string keys and `lang/ru.json` (sorted
case-insensitively). One resource or area per commit: change the
`__()` calls, move the Russian strings, delete the emptied PHP files.
Static Filament labels hold the English key; a small localization
trait translates them. Test: a key-coverage test that every `__()`
string has a Russian translation.

**E2. Filament conventions.**
- Edit pages contain only the form: move Delete / Restore (and drop
  View) from the five Edit pages to the View pages; update
  `EditLinkHasNoForceDeleteTest` to assert the same guarantee on the
  page that now holds the actions.
- Redirect to View after Create and Edit — panel-wide if the installed
  Filament offers it, otherwise `getRedirectUrl()` in a shared trait.
- Filters apply immediately: `deferFilters(false)` through one
  `Table::configureUsing()`.
- Replace the inline `<style>` blocks in `link/redirect` and
  `filament/layouts/simple` with classes in the built CSS.
- Tests for the redirects and for the moved actions.

**E3. Naming.** No abbreviations or one-letter names (`$q`, `$tz`,
`$c`, `$v`, `$t`, closure parameters). Fix in files touched by other
items; no mass rename.

**E4. `declare(strict_types=1)`.** Last, when A–B are done and the
suite is strongest. Add the declaration to `app/` area by area (one
commit per top-level namespace), running the full suite and PHPStan
after each; then `database/`, `routes/`, `config/`, `tests/`. Fix type
errors at the source (casts at the boundary: request input, env,
database scalars). Finally enable Pint's `declare_strict_types` rule
so new files cannot miss it.
