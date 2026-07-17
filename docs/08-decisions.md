# Accepted Decisions and Known Risks

A log of durable decisions (ADR-lite). This is where things go that
cannot be derived from the code: the "why", the accepted trade-offs and
the residual risks. The full texts of the 2026-06-05 audit and the
2026-06-01 review have been removed from the repository (they exist in
git history: `docs/AUDIT_2026-06.md`, `docs/CODE_REVIEW.md`);
everything essential from them is here.

## Architectural and product decisions

- **Every `User` is a fully trusted administrator.** There is
  deliberately no role model; creating an account = granting full
  access (including Horizon and token issuance). The escape hatch, if
  ever needed, is the `users.is_admin` flag.
  Details: [04-admin.md](./04-admin.md).
- **A soft-deleted link = disabled**: public resolution returns 404,
  the admin sees it trashed. Clicks and callbacks are historical facts
  and **outlive the link** (`withTrashed()` in jobs, FK
  `restrictOnDelete`).
- **Model relations live only in traits** in `app/Models/Relations/`,
  even one-liners (deliberately contrary to the audit's recommendation
  to "inline" them).
- **A click is counted when the interstitial renders** (delayed/manual)
  and for bots/prefetch — a product decision; bots are flagged
  (stage 1 of the [plans](./07-plans.md)) rather than discarded.
- **The login footer is rendered as HTML without escaping** — accepted
  by design: `content.footer` is set only by a trusted admin. Revisit
  if untrusted operators ever appear.
- **Code generation in `Link::booted()`** — accepted as idiomatic (the
  audit proposed moving it into `LinkCreator`; rejected).
- **IP pinning in callbacks (TOCTOU / DNS rebinding) is deliberately
  not implemented**: `callback_url` is set only by the admin,
  follow-redirect is disabled, and a clean implementation
  (CURLOPT_RESOLVE) is untestable via `Http::fake`. Enable it when
  moving to user-supplied `callback_url`.
- **trustProxies = private subnets** (`10/8`, `172.16/12`,
  `192.168/16`), not `*`. In production it should be narrowed to the
  actual proxy. Residual risk: `172.16/12` covers all docker bridge
  networks.
- **`HASHIDS_SALT` is independent of `APP_KEY`** — rotating the app key
  does not change link codes. `links.code` is `varchar(16)`: the
  Hashids ceiling is lifted for the entire bigint range (a 9-character
  code starts at id ~52.5 billion).
- **The root route `GET /{code}`** is restricted by a constraint on the
  code alphabet/length; it has been verified that Hashids does not
  encode ids into Filament's reserved slugs (`login`, `services`, …).
  The residual shadowing risk exists only if the alphabet changes.
- **`round_robin` for domains is deliberately cursor-free**: the order
  is derived from link history (`MAX(links.created_at)`), so it
  self-corrects when domains are added/removed. Under load two
  concurrent requests may get the same domain — an accepted best-effort
  trade-off; a strict guarantee would require a cursor table with
  `lockForUpdate`. Rotation statistics are global (across all
  services): a domain is a shared resource.
- **Verified at runtime** (do not change "just in case"):
  `firstOrCreate` in Laravel 13 is race-safe via `createOrFirst` +
  savepoint; Filament v5 `unique()` ignores the current record on edit
  by default.

## Product decisions 2026-07-11

- **Callbacks for bot clicks are always sent, with an `is_bot` flag at
  the payload root** (a client-supplied `is_bot` key is overridden).
  The partner decides for themselves; a false-positive detection does
  not lose the conversion.
- **A/B stickiness is a deterministic hash of `(ip, user_agent,
  link_id)`** against the weights; no cookies and no state. Trade-off:
  changing IP/browser may yield a different variant.
- **The IP is stored raw + a scheduled cleanup** (target: 90 days):
  the raw IP is needed for anti-fraud and partner callbacks; indefinite
  storage is not.
- **No custom slugs**: links are created programmatically, and the
  invariant "the code comes from Hashids, no collisions" is preserved.
  Revisit upon an explicit client request.

## Product decisions 2026-07-12 (plan stage 2)

- **`max_clicks` counts all clicks, including bots** — the limit is
  deterministic and explainable to the client; the bot share is visible
  in the counter breakdown, and disputed cases are resolved via the
  `is_bot` flag in callbacks.
- **`valid_since` is added** — symmetric to `valid_until`; before the
  start the link is indistinguishable from a nonexistent one (404, no
  click, no callback). The workaround via `after_date` conditions
  remains valid, but the field is cheaper and more explicit.
- **API versioning: introducing `/api/v1`.** RFC 7807 Problem Details
  and all new functionality (PATCH/GET of links, new fields) live only
  under `/api/v1`. Legacy `/api/*` routes are frozen: the previous
  error format, the previous feature set, marked deprecated in
  03-api.md; removal is a separate decision after clients migrate.
  *Clarification 2026-07-12 (cross-review, r13):* the freeze = paths,
  error format and no breaking changes. Controllers/Requests/Resources
  are shared with v1, so legacy responses receive new **additive**
  fields (`conditions`, `variants`, lifecycle) and shared input
  validation — this is accepted deliberately; forking the classes for
  a byte-for-byte freeze was rejected.

## Registry of audit and review findings (for regression tests)

Regression tests reference these IDs (`Regression for
docs/08-decisions.md (audit 2026-06) — H2` and the like). All findings
are closed or accepted; the full texts are in git history.

### Review 2026-06-01 (`docs/CODE_REVIEW.md` in history)

| ID | Summary | Outcome |
|---|---|---|
| C1 | `trustProxies('*')` → IP spoofing, rate-limiter bypass | Fix: trust only private subnets |
| M1 | "`firstOrCreate` is not race-safe" | Withdrawn: race-safe via `createOrFirst` |
| M2 | `RecordClickJob` not idempotent → duplicate clicks/callbacks | Fix: `clicks.uuid` + `firstOrCreate` |
| M3 | Callback SSRF: follow-redirect, TOCTOU | Fix: redirects disabled; pinning is an accepted risk |
| M4 | "`SettingForm.key` breaks edit" | Withdrawn: Filament ignores the current record |
| M5 | Fragile precedence of `GET /{code}` vs Filament/`/up` | Fix: constraint on the code + guard test |
| M6 | No roles — any user = admin | By design (see above) |
| M7 | No `clicks(created_at)` index; non-sargable badges | Fix: index + range filters |
| m1 | Dead relation `Callback::link` | Removed |
| m2 | `rules.*.url` without a scheme restriction | Fix: `url:http,https` |
| m3 | No upper validation bounds (`rules`, `callback_data`) | Fix: limits + stripping extra keys |
| m4 | Tokens never expire, all abilities | Fix: `links:create` ability, expiry, prune |
| m5 | Duplicate `priority` in the rules form → raw DB error | Fix: uniqueness validation |
| m6 | Raw DB errors in the admin panel (duplicate condition and more) | Fix: dedup/friendly errors |
| m7 | ForceDelete of a link with clicks → FK error | Removed |
| m8 | `email_verified_at` not in `$fillable` | Fixed |
| m9 | Timezone in click aggregation | Consistent (UTC), comment |
| m10 | Click filter loads all links | Fix: searchable relationship |
| m11 | Off-by-one in callback backoff (5 delays for 5 attempts) | Fix: 5 attempts / 4 intervals |
| m12 | A link created from the admin panel without rules → 404 | Fix: notification after create |
| m13 | `callback_data` in the admin panel is a flat map | Fix: JSON editor, round-trip |
| m14 | Superfluous transaction in `ClickRecorder` | Removed |

### Audit 2026-06-05 (`docs/AUDIT_2026-06.md` in history)

| ID | Summary | Outcome |
|---|---|---|
| C1 | Click loss: btree overflow from multibyte Referer/UA | Fix: byte-wise truncation (`mb_strcut`) |
| C2 | Secrets baked into the Docker image (no `.dockerignore`) | Fix + credential rotation |
| H1 | Contract: `condition:null` was returned as `{type:null,…}` | Fix: null guard in `RuleResource` |
| H2 | No unique on `callbacks.click_id` → duplicate webhooks | Fix: unique index |
| H3 | Stored XSS via the footer on the login page | Accepted by design (see above) |
| H4 | `HASHIDS_SALT` defaulted to `APP_KEY` | Fix: independent salt |
| H5 | No FK indexes on `links` | Fixed |
| H6 | No `migrate` on deploy; mail set to `log` | Fixed |
| H7 | `varchar(8)` ceiling for the code | Fix: `varchar(16)` |
| H8 | `POST /api/links` without a throttle | Fix: 120/min per service |
| M1 | `title` `max:255` vs `varchar(64)` | Fix: `max:64` |
| M2/M3 | Soft-deleting a link lost the click / broke the callback | Fix: `withTrashed()` in jobs |
| M4 | A stuck `Pending` callback with no sweeper | Fix: `callbacks:dispatch-pending` |
| M5 | Horizon gate = any authenticated user | Fix: gate via `canAccessPanel` |
| M6 | Tokens without TTL/prune | Fixed |
| M7 | Raw Postgres 500s in Filament | Fix: `->before()` guards |
| M8 | ForceDelete on EditLink | Removed |
| M9 | Footgun: `database` queue vs Horizon `redis` | Fix: fail-fast assert |
| M10 | The URL scheme was not re-checked on read | Fixed |
| M11 | `LOG_LEVEL=debug` in production, ephemeral logs | Fix: warning + stderr |
| M12 | Code generation in a boot hook | Accepted (idiomatic) |
| M13 | A single `default` queue — callbacks starve clicks | Fix: separate `callbacks` queue |
| M14 | Condition re-validated on every resolve | Fix: parse on read |
| M15 | Bulk-delete of users without a guard | Fix: cannot delete yourself/the last user |
| Low | Dead code, styles, dictionary resolver, A/B CI blocks, UX | Closed; "a trait per relation" — rejected (see above) |

### Pre-push cross-review 2026-07-12 (stages 1–3)

The `origin/main..HEAD` range (30 commits) went through 8 independent
Claude passes (adversarial + specialists: security, migrations, tests,
API contract, performance, maintainability) and a Codex review. The
stage migrations had not been deployed at that point, so they were
fixed in place (no repair migrations).

| ID | Summary | Outcome |
|---|---|---|
| r1 | `->constrained()->…->index()` does NOT create an index — it clobbers the FK constraint NAME into `"1"`, also breaking rollback (`dropConstrainedForeignId` looks for the conventional name); the `clicks.rule_variant_id` and `rule_variants.rule_id` indexes never existed (confirmed via psql) | Fix: `$table->index(...)` as a separate call; the dev DB repaired manually |
| r2 | No `clicks.ip_address_id` index: the RI check for every DELETE from `ip_addresses` in `ips:prune` = a seq scan of clicks per row | Fix: index |
| r3 | The detach phase of `ips:prune` re-read the entire history up to the cutoff daily | Fix: partial index `clicks(created_at) WHERE ip_address_id IS NOT NULL` |
| r4 | PATCH race: attributes were read before `lockForUpdate`, two concurrent PATCHes could commit a crossed since>until window | Fix: `refresh()` under the lock + test |
| r5 | `parse_str` mangles `.`/space/`[` in query parameter names → `{{click.query.sub.id}}` would forever return `''` | Fix: manual pair parsing, names taken literally |
| r6 | `Condition` carried `HasManyRules` over the dropped `rules.condition_id` column — SQL error on first access | Fix: trait removed (there were no call sites) |
| r7 | Bot detection ran the full OS/client/device parse for NON-bots; the comment promised the opposite | Fix: `Parser\Bot` directly + `discardDetails()` |
| r8 | The `device` condition parsed the raw UA (uncapped) once per device rule per redirect | Fix: a 2000-byte cap + a single-element memo |
| r9 | The 404 title from `getMessage()` would leak the model class under a future `findOrFail` in v1 | Fix: a fixed title for 404 |
| r10 | `conditions()` without ORDER BY: nondeterministic order of the array and of the "first" condition for legacy `condition` | Fix: `orderBy('conditions.id')` |
| r11 | A repeated `detect-bots` silently left the counters stale | Fix: warn "run clicks:rebuild-counters" when changes occur |
| r12 | `condition_rule.condition_id` without an index (restrict check on condition deletion) | Fix: index |
| r13 | Legacy `/api/*` is not "byte-for-byte": shared classes gave legacy new response fields and shared validation | Accepted by decision (see "/api/v1" above) |
| r14 | `max_clicks` is exceeded under a concurrent burst: the check happens before the asynchronous click write (Codex) | Accepted: a soft limit (documented in 03-api); a synchronous reservation contradicts the async hot-path design |
| r15 | `rebuild-counters` holds ACCESS EXCLUSIVE on the counters for the whole aggregation — redirects of links with `max_clicks` are blocked too | Accepted: the command is rare, run it in a window (06-deploy). "Staging swap" REJECTED: it loses increments between the aggregation snapshot and the TRUNCATE — the current locking is precisely the correctness guarantee |
| r16 | PATCH `rules` on a link with a large per-variant click history: a SET NULL sweep of the entire history inside the request transaction + retroactive erasure of A/B attribution | Deferred to the backlog (07-plans §10); not painful at the current scale |
| r17 | `trustProxies` trusts all of RFC1918: behind a Docker gateway X-Forwarded-For is spoofable (ip_address conditions, ignored sources, the rate limiter) | Deferred: narrow the CIDR in production to the actual proxy (06-deploy); env configurability — backlog |
| r18 | The `TRACKING_DISABLE_PARAM` name is effectively a secret: knowing the name = bypassing tracking and `max_clicks` | Accepted: the name = a rotatable secret (03-api/06-deploy) |
| r19 | Test gaps: the schema guard via a variant, SET NULL on PATCH with clicks, lock-abort of the two prune commands, the v1 401 matrix + `domain-groups`, the mirrored window check, the `visited_query` cap, dots in parameter names | Fix: +10 tests (371 in the suite) |

### Cross-review 2026-07-13 (stage 4 + inter-stage seams)

The `origin/main..HEAD` range (47 commits); the focus was the geo stage
`48e115a..HEAD` as a whole (each item of the stage had already been
reviewed by Codex individually) plus a separate end-to-end pass over
the seams of stages 1–4. 8 passes: 5 specialists + 2 adversarial
Claude + Codex. **All of r20–r33 were implemented 2026-07-13** (commits
`3090e59`..`fc35cd5`, every substantial one via Codex review; +21
tests, 427 green); plan §11 was removed from 07-plans after completion.
The main finding (r20) is a bug of stages 2–3 missed by the r1–r19
review. 11 seams were confirmed clean: retry idempotency
(uuid + `wasRecentlyCreated` for counters and callbacks),
bot×`max_clicks`×API (a single source — the slot counters), the
placement of the disable-param/ignored-sources guards, no geo in the
synchronous redirect, PATCH×in-flight click, the A/B `min:1` guard,
job serialization with DI via `handle()`.

| ID | Summary | Outcome |
|---|---|---|
| r20 | **P1.** Old `RecordClickJob` payloads (serialized by origin/main code predating `visitedQuery`/`ruleVariantId`) crash on the new worker: typed properties are not initialized on unserialize, constructor defaults are not applied — `Error: Typed property … must not be accessed before initialization` on all 3 tries. The "deployable-safe" comment in the constructor was wrong. The deploy plan itself prescribes letting the Horizon backlog accumulate during migrations → the entire backlog is poisoned: no clicks, no counters, no callbacks. Reproduced in tinker | Implemented |
| r21 | **P1.** The `.mmdb` lives in the container's ephemeral FS (`x-laravel` mounts only `./.env`), and per the docs `geo:download-db` runs in `laravel-web`, while all lookups happen in `laravel-horizon`. Geo silently does not work; every deploy destroys the database (MaxMind download limits + a geo-less window while the throttle key lives in redis) | Implemented |
| r22 | **P1.** `UpdateGeoDatabaseJob` without `$timeout` → supervisor-1: worker timeout 60s < HTTP timeout 120s, `tries=1`, `retry_after=90`. A slow download → SIGKILL bypasses `finally` → the `geo:download` lock hangs for up to 30 min, `recordOutcome` is never called → backoff never kicks in → an hourly kill cycle of the click-recording worker | Implemented |
| r23 | **P2.** `GEOIP_LICENSE_KEY` leaks on a transport error: Guzzle puts the full URL (with the query) into the `ConnectException` message → `report()` (logs/tracker) and the output of `geo:download-db` | Implemented |
| r24 | **P2.** The downloaded `.mmdb` is installed without validation: a corrupt file makes `filemtime` fresh (auto-healing disabled by `max_age_days`), and a `new Reader` failure is not cached → `report()` on every click of every worker — a tracker storm on top of dead geolocation | Implemented |
| r25 | **P2.** The tarball (~50 MB) is buffered in worker memory twice (`$response->body()` + `File::put`) — an OOM risk with the same consequences as r22 (SIGKILL bypassing `finally`) | Implemented |
| r26 | **P2.** The geo migration is NOT "instant", contrary to 06-deploy: the partial index scans the entire heap (the predicate is checked per tuple), FK validation is a second full scan, both inside the migration transaction under the ACCESS EXCLUSIVE from `ADD COLUMN` — a full lock on `clicks` for the duration of two scans | Implemented |
| r27 | **P2.** The `ip → geo_location_id` memo in `geo:locate-clicks` is unbounded: millions of unique IPs → CLI process OOM in the middle of `chunkById`, the 6-hour lock is left hanging | Implemented |
| r28 | **P2.** `phpunit.xml` does not pin `GEOIP_LICENSE_KEY`/`GEOIP_DATABASE_PATH` with `QUEUE_CONNECTION=sync`: on a machine/CI with a real key in `.env` the suite actually reaches out to MaxMind and installs the database from a test | Implemented |
| r29 | **P2.** `Cache::lock()`/`get()`/`release()` in `GeoDatabaseDownloader::download()` are outside the fail-safe boundary: a cache-backend failure crashes `geo:download-db`/the job without a `GeoDownloadResult` (Codex) | Implemented |
| r30 | **P3.** `country_code` is not normalized for `char(2)` (unlike the region/city caps): a nonstandard code from a future source → a silent dictionary failure or a tuple split by letter case | Implemented |
| r31 | **P3.** `geo:locate-clicks` without a database on disk performs a useless full pass (everything to null, including the memo) — a preflight is needed; plus document "re-run after installing the database" (Codex) | Implemented |
| r32 | **P3.** Stage 4 test gaps: `geo:download-db` exit codes; a corrupt database (`locate` → null); the true branch of mtime staleness + the `max_age_days` boundary (a `<`→`>` mutation would have passed the suite); backoff lifting on expiry; the 128-character cap; country-only dedup (`''`); IPv6 lookup; the re-check in `UpdateGeoDatabaseJob::handle`; an end-to-end e2e download→install→click→located (Codex) | Implemented |
| r33 | **P3.** Maintainability: the magic 7-day TTL ×2 in `recordOutcome`; a bare `Http::timeout(120)` (hidden coupling with LOCK_SECONDS); `download_backoff_minutes` being the only geo key without an env; the duplicated `makeTarball`/setUp across two test files; fixed tmp names with a per-process phar cache; a doc note about `octane:reload` after a manual re-download (Codex) | Implemented |

Rejected as a result of the pass: burning the throttle key before the
dispatch is confirmed (a conscious trade-off, documented in the code;
self-heals within an hour on a 30-day cycle); an exit code for
`geo:locate-clicks` when the lock is busy (a consistency-only change);
tying the TOOLONG test to pgsql strictness (the project pins pgsql in
tests).

The only items remaining open from the audit: a full
`clicks`/`callbacks` retention policy (partially closed by the stage 2
cleanups of the [plans](./07-plans.md)) and a role model behind
`users.is_admin` — when untrusted operators appear.

### Cross-review 2026-07-14 (full audit of `app/` for serious bugs)

Five parallel review agents across all of `app/` (~9.5k lines): the
HTTP hot path, the Links services, jobs/geo/console, 16 models + 37
migrations, Filament/providers. Every finding was verified against the
sources and migrations (r35 — empirically in the container, the r36
SSRF confirmed by three independent agents). **All of r34–r47 were
implemented 2026-07-14** (commits `65cc732`..`8a1f95b`, one commit per
finding, each with a test; +18 tests, 453 green). Plan §11 was removed
from 07-plans after completion. Confirmed clean: tenant scope (the
panel is single-tenant by design), mass assignment (an allowlist
everywhere), click/callback idempotency (unique + `wasRecentlyCreated`),
cascades/orphans, Octane singletons (stateless/correctly memoized), geo
archive extraction (no path traversal, atomic `File::move`), SSRF on
literal IPs.

| ID | Summary | Outcome |
|---|---|---|
| r34 | **P1.** `EditUser` retained a `DeleteAction`, although `UsersTable` documents the invariant "users are undeletable from the panel" (registration/password reset are disabled → deleting the last user = irreversible lockout). The table guard was dead while the edit screen exposed the action | Implemented |
| r35 | **P1.** The byte cap on URLs (2000) in `StoreLinkRequest` was measured on the raw input, while `LinkRuleSetWriter` percent-encodes the URL only after validation → an internationalized path (Cyrillic/CJK) >~680 characters passes the validator and overflows `urls.value varchar(2048)` (SQLSTATE 22001), an uncaught 500 instead of a 422. Verified empirically | Implemented |
| r36 | **P2.** SSRF via DNS rebinding in callbacks: `CallbackUrlGuard` resolves and validates the IP, but Guzzle resolves the hostname again → a domain with a short TTL slips in an internal address. Admin-only vector. Cheap compromise: IPv4 alignment of the guard + `force_ip_resolve=v4` (full pinning deferred) | Implemented |
| r37 | **P2.** `bootstrap/app.php` trusted all of RFC1918 as proxies → a VPC/cluster neighbor forges X-Forwarded-For (a fake click IP, rate-limit bypass). Moved to the `TRUSTED_PROXIES` env (also closing the deferred r17); production narrows it to the actual LB subnet | Implemented |
| r38 | **P2.** `EncryptCookies` (the `web` group) nulls out the plaintext `tz` cookie → `FilamentTimezone` is always null, the panel renders and parses condition times in UTC instead of the admin's locale. Exception `encryptCookies(except: ['tz'])` | Implemented |
| r39 | **P2.** A bare `DeleteAction` on `EditService` with `restrictOnDelete` (`links.service_id`/`clicks.service_id`) → a 500 (23503) instead of a friendly notification. Added a single-record `RestrictedDeleteAction` (the counterpart of the bulk one) | Implemented |
| r40 | **P2.** `SettingForm` (the json branch) saved the value without validation, while the package reads with `JSON_THROW_ON_ERROR` → a typo = a latent 500 for the first consumer. Added `->rules(['nullable', 'json'])` | Implemented |
| r41 | **P2.** The panel sat under the bare `web` group without `AuthenticateSession` → rotating the password of a compromised account does not invalidate its other sessions. Added `AuthenticateSession` to the panel stack | Implemented |
| r42 | **P2.** The click workers (`RecordClickJob` resolves geo) were never recycled (`maxTime=0`), `MaxMindGeoLocator` caches the `Reader` for the worker's lifetime → a fresh `.mmdb` is only picked up on deploy. `maxTime=3600` on supervisor-1 | Implemented |
| r43 | **P2.** `clicks.created_at` was stamped with the job execution time, not the visit time → with a queue backlog a lag of minutes to hours skews time-based analytics and the `ips:prune` cutoff. The visit moment is captured in `ResolveLink` and passed through to the job | Implemented |
| r44/r45 | **P2.** PHP mangles `.`/space/`[` in a parameter name into `_`: a `query_param` condition on `sub.id` never fired (it looked for `sub_id`), and `forward_query` corrupted dotted parameters of the target URL. A shared `QueryString` helper (a manual split of the raw query string) in both places | Implemented |
| r46 | **P2.** The clicks table made six relation columns searchable → one search = OR'ed `whereHas` with leading-wildcard `ILIKE` over the unbounded `clicks` (self-DoS under Octane). Search narrowed to `link.code` + `ipAddress.value`; service/bot moved to filters | Implemented |
| r47 | **P3.** `crc32($key) % $totalWeight` in `RuleVariantSelector` → a `DivisionByZeroError` on the hot path when the variant weight sum is 0 (unreachable via the API: `weight >= 1` + a DB CHECK; reachable via the admin panel/raw SQL). Fail closed to the rule's own url | Implemented |
| r48 | The `app/Console/Commands/V.php` command (empty `handle`, "Command description") is NOT dead repository code: it is deliberately in `.gitignore` (line 32) and was never committed. The owner's local scratch command. Leave it alone | Not a bug |

No substantive rejections. Open deploy item: production must put the
real LB subnet into `TRUSTED_PROXIES` (`.env.production`) — an empty
value falls back to the broad RFC1918 fallback (the current behavior
until narrowed). The deferred r1–r19 scale backlog (§ of the
[plans](./07-plans.md)) is untouched.

### Cross-review 2026-07-15 (key processes, serious bugs only)

Four+ parallel adversarial agents over the hot paths (redirect, click
recording, callbacks, geo+counters), then manual verification of every
item against the sources (r49 — by reproducing the shift via a real
timestamptz round-trip, r53 confirmed by two agents independently). The
diff of three commits (`e65bc0a` geo-memory, `f014634` env, `d46d90c`
topbar) is clean. **r49–r52, r55 — a clear fix, awaiting
implementation; r53–r54 need a choice of approach (open questions); r56
deferred.** Remaining accepted/re-confirmed: the soft `max_clicks` (a
conscious "slightly soft" design; with the queue down the over-issue is
unbounded — confirmed by 3 agents, kept as an accepted risk) and the
callback SSRF DNS rebinding (already [r36](#), partial mitigation, full
IP pinning deferred). Clean by tracing: click idempotency by `uuid`
(savepoint-safe), the atomic counter upsert (`count + 1`, only on
`wasRecentlyCreated`), geo/dictionaries exception-safe, `withTrashed`
in callbacks.

| ID | Summary | Outcome |
|---|---|---|
| r49 | **P1** (follow-up to r43). `clicks.created_at` is written shifted by the `APP_TIMEZONE` offset (+3h for `Europe/Moscow`): `ClickRecorder` puts the offset-ISO `$visitedAt` from r43 into `created_at` without UTC normalization, Eloquent formats the Carbon in its own zone (`Y-m-d H:i:s`, the offset dropped), and the Postgres session is in UTC → Moscow wall-clock time is read as UTC. `LinkCreator::parseInstant` is protected by `->utc()`, the click path is not. Hits analytics, daily buckets around midnight, and `click.created_at` in the partner callback. Tests do not catch it (`phpunit.xml` pins UTC). Fix: normalize the visit to UTC (mirroring `LinkCreator`) + a regression test in a non-UTC zone | Planned |
| r50 | **P1.** `RecordClickJob` has `tries=3` but no `backoff()` (`SendCallbackJob` has one) → the 3 attempts fly by within milliseconds; a brief Postgres restart/failover burns the entire retry budget for in-flight clicks → the click and its callback are lost (`failed_jobs` is in the same unavailable DB). Fix: `public function backoff(): array { return [10, 60]; }` (within `REDIS_QUEUE_RETRY_AFTER=360`) | Planned |
| r51 | **P1.** The redirect 500s when Redis is unavailable: `RecordClickJob::dispatch` in `ResolveLink::__invoke` is not isolated — the resolve only needs Postgres, but the push to the redis queue throws → a 500 on every redirect for the entire Redis outage window. Losing analytics is better than taking the product down. Fix: try/catch around the dispatch (`report($e)` + still serve the redirect). Adjacent: the `throttle:link-resolve` limiter is also on Redis — optionally fail-open | Planned |
| r52 | **P1.** The redirect route sits under the full `web` group → `StartSession` (redis driver) writes a session for every cookie-less visit: pointless durable state on the hottest path; a distributed crawl bloats Redis to exhaustion (taking down Horizon jobs + the limiter). Fix: a stateless route (`withoutMiddleware` StartSession/CSRF/cookies — the resolve never reads the session) | Planned |
| r53 | **P1** (revisits r15). `clicks:rebuild-counters` (`TRUNCATE` in one transaction with the full-scan `INSERT..SELECT`) holds `ACCESS EXCLUSIVE` on `link_click_counters` → it blocks not only the upserts (accounted for in r15) but also the `isAlive()` SUM on the **redirect hot path** (r15 missed the read side). On a large `clicks`, redirects of `max_clicks` links hang until the rebuild finishes; the write workers run into supervisor-1's 60s timeout. Exactly the command from the post-deploy runbook. **Decision:** `TRUNCATE` → `DELETE` (takes ROW EXCLUSIVE instead of ACCESS EXCLUSIVE → `isAlive` readers see the old rows via MVCC and are not blocked; the downside — dead tuples/VACUUM, counters stay "old" until the rebuild commits) | Planned |
| r54 | **P1** (adjacent to r16). `LinkRuleResolver` loads rules and conditions with separate SELECTs (READ COMMITTED): if `LinkRuleSetWriter::replace` (`rules()->delete()` + recreation) commits between them, the old rules come back with an **empty** condition list (the pivot is cascade-deleted) → `ruleMatches` treats empty as "unconditional" → within the PATCH window a gated offer is served to all visitors (for a cloaker — a target-audience leak). The window is milliseconds on every rule edit of a live link. **Decision:** wrap the resolve read (rules+conditions+variants) in a REPEATABLE READ transaction — a single snapshot, conditions cannot vanish between the SELECTs | Planned |
| r55 | **P2.** `callbacks:redispatch-stale` assumes a Pending >2h has lost its job, but with a queue backlog >2h the original `SendCallbackJob` is still queued, and the job is not `ShouldBeUnique` → the partner receives a duplicate postback (a double-counted conversion). Fix: `ShouldBeUnique` keyed by `callback_id` for the delivery window, or an atomic claim (`where status=Pending → update`) before the redispatch | Planned |
| r56 | **P3.** `forward_query` loses repeated scalar keys (`b=1&b=2`→`b=2`) and renames `[]` keys (`a[]`→`a[0]`): `Request::getQueryString()` normalizes (parseQuery+ksort+http_build_query) before `QueryString::parse`, and the parse is last-wins. The same effect on `visited_query` and `query_param` condition matching. Rare parameter shapes in affiliate links | Deferred |

### Static analysis 2026-07-15 (larastan adoption + level 5 cleanup)

Larastan v3 (phpstan 2.2, level 5, paths: `app/`) was adopted as the
typecheck dimension of the /health dashboard; the command stack is
pinned in `CLAUDE.md` § Health Stack (`a013d7b`). First run: 16 errors
in 8 files, all triaged and closed in separate commits
(`768fcbd`…`88306d0`); the result — phpstan clean, Pint clean, 455
tests green. Not a single finding turned out to be a runtime bug; r59
incidentally tightens auth. No suppressions (`@phpstan-ignore`,
baseline) were introduced.

| ID | Summary | Outcome |
|---|---|---|
| r57 | 11× a redundant `?->` to the left of `??` (nullsafe.neverNull): `??` already walks the chain with isset() semantics, `?->` adds nothing. In `SendCallbackJob` the runtime protection against "vanished" click/link/service (types are non-null per the NOT NULL FKs) is kept as the `?? null` chain, the comment extended with the mechanics. Behavior is identical on all paths | Fixed (`f885c68`, `1cc9fea`) |
| r58 | method.notFound: the relation traits (`app/Models/Relations/*`) carried no generics → the result of `rules()->create()` was typed as the base `Model` without `conditions()`/`variants()`. `@return BelongsTo<X, $this>` annotations added to all 19 traits; in `CreateLink::afterCreate` — an `instanceof Link` narrowing (Filament's `$record` is the base `Model`) | Fixed (`768fcbd`, `88306d0`) |
| r59 | instanceof.alwaysFalse in `StoreLinkRequest::authorize()`: larastan derives the type of `Request::user()` from the guard providers in `config/auth.php`, which listed only `User`, although Sanctum returns tokenable=`Service`. The `sanctum` guard is now declared explicitly with the `services` provider — with a **tightening** as a bonus: bearer tokens now resolve only to `Service` (the default `provider=null` accepted any tokenable; `User` has no `HasApiTokens`, existing flows unaffected) | Fixed (`d983930`) |
| r60 | arrayValues.list in `StoreLinkRequest::rules()`: `array_values(array_filter([...]))` re-listed a list from which only the tail element could ever drop out. Replaced with a conditional spread | Fixed (`b18d0ae`) |
