# Admin panel (Filament 5)

Served at `/` via `App\Providers\Filament\MainPanelProvider`.
The authentication guard is `web`; the login page is
`App\Filament\Pages\Auth\Login` (custom layout, otherwise stock
Filament).

## Access and authorization

**Deployment invariant: every `User` is a fully trusted administrator.**
The absence of a role model is intentional — this is an internal tool
for trusted operators:

- `User::canAccessPanel()` returns `true` for every authenticated
  user: whoever can log in can see and edit all resources.
- `HorizonServiceProvider::gate()` (`viewHorizon`) delegates to
  `User::canAccessPanel()` — the same gate as the panel, so Horizon
  (it exposes job payloads and failed-job traces) stays in lockstep
  with panel access.
- Users are created right in the panel (the "System → Users" section),
  so **creating an account = granting full access**. Only create
  accounts for trusted operators.

If permission separation is ever needed (e.g. a read-only role),
introduce a minimal `users.is_admin` flag and gate `canAccessPanel()`
and `viewHorizon` with it (see `docs/08-decisions.md`, M6).

## Navigation

Top level (no group): Services, Links, Domains.

Groups (see the glossary for the Russian names):

- **Analytics** — Clicks, Callbacks
- **Dictionaries** — URLs, Conditions, IP addresses, Referrers, User agents
- **System** — Users, Settings

The order within groups is set by `$navigationSort` on each Resource.

## Resource layout convention

Each resource lives in `app/Filament/Resources/{Plural}/`:

```
{Resource}Resource.php       Metadata + page wiring
Pages/                       ListX, CreateX, EditX, ViewX
Schemas/
  {Resource}Form.php         Create/edit form schema
  {Resource}Infolist.php     View page schema
Tables/
  {Resource}sTable.php       Index table
RelationManagers/            Optional (e.g. RulesRelationManager)
```

The Resource class overrides:

```php
public static function getNavigationGroup(): ?string  // __('navigation.groups.<key>')
public static function getNavigationLabel(): string   // __('resources/<name>.navigation_label')
public static function getModelLabel(): string        // __('resources/<name>.label')
public static function getPluralModelLabel(): string  // __('resources/<name>.plural_label')
```

## i18n

**All user-facing strings go through `__()`**, sourced from
structured lang files:

```
lang/
  en/
    navigation.php           groups.{analytics,dictionaries,system}
    resources/
      service.php
      link.php           ← includes rules.*, transition_modes.*
      domain.php
      click.php
      callback.php       ← includes statuses.*
      url.php
      condition.php      ← includes types.*, data_fields.*
      ip_address.php
      referrer.php
      user_agent.php
      user.php
      setting.php
  ru/                          same tree, Russian values
  ru.json                      legacy JSON keys (kept, still in use)
```

Filament chrome (Create/Edit/Delete/View labels, pagination,
bulk-action confirmations, the login form) resolves through Filament's
stock translations in `vendor/filament/*/resources/lang/ru/`.
`APP_LOCALE=ru` is set in `.env`.

**Laravel core strings** (validation, auth, passwords) live in
`lang/en/` — published via `php artisan lang:publish` so they can be
edited when needed.

## Adding a resource — checklist

1. `artisan make:filament-resource {Name} --generate --view` (or equivalent).
2. Set `$navigationIcon`, `$navigationSort` and override the four
   label methods (see above).
3. Create `lang/{en,ru}/resources/<snake>.php` with `label`,
   `plural_label`, `navigation_label`, `fields.*`.
4. Wire every `->label()` in the form/table/infolist through
   `__('resources/<snake>.fields.<key>')`.
5. Add the term to [02-glossary.md](./02-glossary.md).
6. Run `sail test` — no new failures.

## Notes on specific resources

- **Service** — `ViewService` has a custom "Create API token" header
  action; the token dialog strings live under `resources/service.tokens.*`.
- **Link** — uses soft deletes.
  `LinkResource::getRecordRouteBindingEloquentQuery()` includes trashed
  links so deleted records remain viewable.
- **Rule** — not a resource; managed only through `RulesRelationManager`
  on the Link page. Translations are in `resources/link.rules.*`.
- **Condition** — the form is reactive on type: selecting a type swaps
  in the matching `data.*` field. Keys are in
  `resources/condition.data_fields.<type>.*`. The dictionary is
  read-only (there is a Create, but no Edit).
- **Callback / Click** — historical records only. Tables have only a
  `ViewAction`, no Edit. Deletion is allowed but is a rare operation.
- **Dictionaries (URL, Conditions, IpAddress, Referrer, UserAgent)** —
  read-only: tables have only a `ViewAction` (+ deletion), no
  `EditAction`/`EditPage`.

## Version chip

`resources/views/filament/version-chip.blade.php` renders a badge with
`config('app.version')` (sourced from `composer.json`). It is injected
via render hooks in `MainPanelProvider`:

- `PanelsRenderHook::SIDEBAR_LOGO_AFTER`
- `PanelsRenderHook::TOPBAR_LOGO_AFTER`
