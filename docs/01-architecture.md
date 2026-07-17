# Architecture

## Stack

- PHP 8.4, Laravel 13
- PostgreSQL (jsonb for `callback_data`, `Condition.data`, `Callback.response_body`)
- Filament 5 (admin panel)
- Laravel Sanctum (API authentication via personal access tokens)
- Laravel Horizon (queue monitoring)
- Laravel Octane (long-lived worker — **mind the Octane gotchas**:
  no state in singletons, no static accumulation)
- Laravel Sail (Docker dev environment)
- `vaslv/laravel-settings` (key/value application settings)
- `hashids/hashids` (short codes for `Link.code`)
- `sentry/sentry-laravel` (error reporting; wired up in `bootstrap/app.php`)

## Request flows

### Link resolution — `GET /{code}`

Entry point: `App\Http\Controllers\ResolveLink` (single-action, invokable).
Rate-limited via `throttle:link-resolve` (per-link + per-IP).

1. Find the `Link` by `code` (404 otherwise).
2. Walk the `rules` in priority order, asking each `Condition`
   whether it matches, via the `ConditionRegistry`.
3. First match wins → resolve the `Url`.
4. Record the `Click` asynchronously (`RecordClickJob`) — the job
   carries a `uuid` generated during this resolve, so re-execution is
   idempotent. The slotted counter is incremented in the same
   transaction as the click insert
   (see "Bots and click counters").
5. **Inside `RecordClickJob`** (after the click is recorded)
   `CallbackDispatcher`: if `Service.callback_url` is set **and**
   `Link.callback_data` is not null → it creates a `Callback` and
   queues `SendCallbackJob` (which needs `click.id`).
6. Render the response according to `Rule.transition_mode`:
   - `direct` → 302
   - `delayed` → Blade page with a countdown
   - `manual` → Blade page with a "continue" button

### Link creation — `POST /api/links`

Entry point: `App\Http\Controllers\Api\LinkController::store` (thin,
delegates). Auth: Sanctum bearer.

1. Request validation (rules depend on the condition type).
2. `App\Services\Links\LinkCreator::create($validated)` wraps
   everything in a transaction:
   - `resolveDomainId` — an explicit domain (lookup), or automatic
     selection by `domain_strategy` via `DomainSelector` (optionally
     within a `domain_group` — the group code), or the default domain.
     An empty pool with a strategy set → `ValidationException` (422).
   - `resolveUrlId` — insertOrIgnore + SELECT (race-safe).
   - `resolveConditionId` — same pattern, keyed by `(type, data)`.
   - Insert the `Link`, then its `Rule`s in order.
3. Returns 201 with the created payload (for the exact shape, see
   `docs/03-api.md`).

## Key subsystems

### Conditions

`App\Services\Links\Conditions\`

- `ConditionRegistry` — a list of handlers keyed by `type`.
- `ConditionHandler` interface: instance method `matches(Condition $c, ConditionContext $ctx): bool`
  plus **static** `rules(): array`, `type(): string`, `validate(array $data): array`
  (called as `$handler::rules()` / `::validate()`).
- `AbstractConditionHandler` — shared plumbing.
- `TimeBeforeConditionHandler` — the only current type.

**Adding a new condition type:**
1. Create a handler class extending `AbstractConditionHandler`.
2. Register it in the service container (`ConditionRegistry` picks it
   up via constructor injection — see how `TimeBeforeConditionHandler`
   is wired up).
3. Add a render branch to the `ConditionForm` schema.
4. Translate `types.<new_type>` and `data_fields.<new_type>.*` in
   `lang/{en,ru}/resources/condition.php`.
5. Update `docs/03-api.md` and `docs/02-glossary.md`.

### Callbacks

`App\Services\Links\Callbacks\` + `app/Jobs/SendCallbackJob.php`.

- `CallbackDataRenderer` — substitutes `{{click.*}}` / `{{link.*}}`
  variables into the string values of the callback payload.
- `CallbackUrlGuard` — blocks private/internal IPs and invalid
  schemes at send time.
- `CallbackDispatcher` — creates the `Callback` (idempotent by
  `click_id`) and queues `SendCallbackJob`.
- `SendCallbackJob` — HTTP POST **without following redirects**
  (`allow_redirects = false`; 3xx/4xx is a permanent failure). Retry
  schedule: 1m → 5m → 15m → 1h (5 attempts, 4 intervals).
- The response body is stored truncated (10_000 characters) and
  sanitized: `mb_convert_encoding($body, 'UTF-8', 'UTF-8')` + NUL-byte
  stripping (Postgres text columns do not accept NUL).

### Click recording

`app/Jobs/RecordClickJob.php` + `app/Services/Links/Clicks/`.

Runs outside the main flow so that the visitor's redirect stays fast.
The dictionary resolvers (`DictionaryValueResolver`) use
insertOrIgnore + SELECT for each dictionary (`Referrer`, `UserAgent`,
`IpAddress`, `Url`); the user agent is resolved by a dedicated branch
(see below).

**Do not cascade soft deletes onto analytics.** Clicks and callbacks
are historical facts and must outlive the `Link` they reference.

Resolve responses (the 302 and the interstitials) carry
`Cache-Control: no-store`: a transition cached by the browser never
reaches the tracker, and every click is a callback to the partner.

### Bots and click counters

`app/Services/Links/Clicks/` + `app/Console/Commands/`.

- **The bot flag lives on the `user_agents` dictionary** (`is_bot`),
  not on the click: detection is deterministic by UA and is computed
  once when the row is created (`BotDetector` — an interface,
  implemented by `DeviceDetectorBotDetector` on top of
  matomo/device-detector; a singleton in `AppServiceProvider`).
  Existing rows are not re-detected on the hot path — re-detection
  after a pattern library update:
  `artisan user-agents:detect-bots` (keyset + lock). A click without
  a UA is not a bot.
- **Slotted counters** (`link_click_counters`): `(link_id, is_bot,
  slot 1..100, count)`, unique on the triple. `ClickCounterIncrementer`
  performs an UPSERT with a random slot **in the same transaction as
  the click insert** and only when `wasRecentlyCreated` — a job retry
  does not double the count; 100 slots eliminate the hot row under
  concurrency. A link's total = the sum of its slots.
- **Invariant:** all "link click numbers" in lists/sorts/summaries
  are read from the counters, not COUNT over `clicks`; a counter
  changes only in the transaction with the click or via the
  `clicks:rebuild-counters` command (TRUNCATE + aggregate
  INSERT..SELECT — initial backfill and reconciliation).
  Time-windowed dashboard queries ("today", "this week", the chart)
  stay on indexed COUNTs over `clicks(created_at)` — the counters
  have no time dimension, deliberately.
- **Callbacks**: every payload carries `is_bot` at the root (the key
  is reserved, any client-supplied value is overridden) + the
  `{{click.is_bot}}` placeholder — the contract is in
  [03-api.md](./03-api.md) §10.

### Code generation

`App\Services\Links\CodeStrategy\HashidCodeGenerator` wraps
`Hashids` with a fixed 52-character alphabet and a minimum length of
5, stored as `varchar(8)`. Bijective — collisions are impossible by
construction.

### Domain selection

`App\Services\Links\Domains\`

- `DomainSelectionStrategy` — enum: `random`, `round_robin`, `coldest`.
- `DomainSelectionStrategyHandler` interface: `select(Builder $pool): ?Domain`
  plus a static `strategy(): DomainSelectionStrategy`.
- `RandomDomainStrategy` / `RoundRobinDomainStrategy` / `ColdestDomainStrategy`.
  round_robin — least recently used (from link history, no cursor);
  coldest — the smallest counter over the
  `config('domains.coldest_period_days')` window.
- `DomainStrategyRegistry` — a `strategy → handler` map built from
  tagged services.
- `DomainSelector` — builds the pool (the group by its `domain_group`
  code, or all domains) and delegates to the handler; `null` on an
  empty pool.
- Wiring: `DomainStrategyServiceProvider` (the `domain.strategy` tag),
  invoked from `LinkCreator::resolveDomainId`.

The round_robin/coldest statistics are global (across all services),
excluding soft-deleted links.

**Adding a new strategy:**
1. Implement `DomainSelectionStrategyHandler`.
2. Add the value to the `DomainSelectionStrategy` enum.
3. Tag the class in `DomainStrategyServiceProvider` (`domain.strategy`).
4. Update `docs/03-api.md`, `docs/02-glossary.md`.

### Rate limiting

`bootstrap/app.php` (or a ServiceProvider) defines `link-resolve`:
per-link + per-IP. Protects against scraping and code enumeration.

## Data model

The authoritative schema lives in `database/migrations/`. Notable
constraints:

- `conditions` — unique `(type, data)` for dedup.
- `clicks(service_id, created_at)`, `clicks(link_id, created_at)` —
  composite indexes for the admin panel analytics.
- `callbacks(service_id, created_at)` — same.
- `links` — soft deletes. **A trashed link counts as disabled:**
  `Link::findByCode` applies the SoftDeletes global scope, so a public
  resolve of a deleted link yields a 404. (The admin panel still sees
  trashed records — `LinkResource::getRecordRouteBindingEloquentQuery`
  removes the scope.)

## Directory layout

```
app/
  Filament/                 Admin panel (see docs/04-admin.md)
  Http/Controllers/
    ResolveLink.php         GET /{code}
    Api/LinkController.php  POST /api/links
  Jobs/
    RecordClickJob.php
    SendCallbackJob.php
  Models/                   Eloquent models (thin)
  Services/Links/           Business logic
    Callbacks/              Outgoing callbacks
    Clicks/                 Click recording, dictionary resolvers
    CodeStrategy/           Short code generation
    Conditions/             Rule matching predicates
    LinkCreator.php         Link creation via the API
    LinkRuleResolver.php    Picking the winning rule on a visit
    TransitionMode.php      Enum
config/
database/migrations/
docs/                       This folder
lang/
  en/, ru/                  Structured lang files (see docs/04-admin.md)
  ru.json                   Legacy JSON keys, still in use
routes/
  web.php                   GET /{code}
  api.php                   POST /api/links
scripts/
  release.sh                Interactive tagger (see docs/05-development.md)
tests/                      Feature + unit tests
```

**Model relations (convention).** Every Eloquent relation lives in its
own single-method trait in `app/Models/Relations/` (`BelongsToService`,
`HasManyClicks`, …); models pull them in via `use`. Relations are
**never** declared inline in a model — even one-liners, and even if a
trait is used by exactly one model. This keeps the models thin and lets
you read a model's full set of relations straight from its `use` block.
(Duplicated in `CLAUDE.md`; deliberately diverges from the audit
recommendation to "inline the trivial ones".)

## External contracts

- **Public API** → `docs/03-api.md`.
- **Callback payloads** → also `docs/03-api.md` (§10).
- **Filament admin panel** → internal only.
