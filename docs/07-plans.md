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
