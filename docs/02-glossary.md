# Glossary

The single source of truth for terminology. Code identifiers, DB
tables, admin panel labels, and user-facing copy must match this
table.

**Convention:**
- **Code term** — used in PHP (class, table, and field names). English.
- **EN label** — the English admin UI (`lang/en/...`).
- **RU label** — the Russian admin UI (`lang/ru/...`). The canonical
  user-facing Russian form.
- **Notes** — disambiguation, related concepts.

## Core domain entities

| Code term | EN label | RU label | Notes |
|---|---|---|---|
| `Service` | Service | Сервис | An external system that owns links and receives callbacks. |
| `Link` | Link | Ссылка | A short link. Has a `code` (hashid), belongs to a `Service`. Optional activity window `valid_since`/`valid_until` and a `max_clicks` limit (see "lifecycle"). |
| `Domain` | Domain | Домен | The short link's host (e.g. `short.example.com`). Shared dictionary. |
| `DomainGroup` | Domain group | Группа доменов | A set of domains (many-to-many); a domain may belong to several groups. `name` is for admins, `code` is the machine name for the API. |
| `Url` | URL | URL | The target URL. Shared dictionary, normalized + with sorted query. |
| `Rule` | Rule | Правило | Maps a `Link` to a `Url` via an optional `Condition`. Ordered by priority. |
| `Condition` | Condition | Условие | A reusable predicate (e.g. `time_before`). Shared dictionary. |
| `Click` | Click | Клик | A recorded visit that resolved a link. Analytics. |
| `Callback` | Callback | Колбек | An outgoing HTTP POST to `Service.callback_url` after a click. |
| `Referrer` | Referrer | Реферер | The HTTP Referer value. Shared dictionary. |
| `UserAgent` | User agent | User agent | The HTTP User-Agent value. Shared dictionary. Carries the `is_bot` flag (crawler detection). The term is kept in English. |
| `LinkClickCounter` | Click counter | Счётчик кликов | A slotted pre-aggregate of a link's clicks, split by bot/non-bot; total = sum of slots. |
| `IpAddress` | IP address | IP-адрес | The visitor's IP. Shared dictionary. |
| `Setting` | Setting | Настройка | A key/value application setting (vaslv/laravel-settings). |
| `User` | User | Пользователь | An admin panel user. |

## Enums / fixed values

### Transition mode (`Rule.transition_mode`)

How the server responds when a rule fires.

| Value | EN label | RU label | Behavior |
|---|---|---|---|
| `direct` | Direct | Прямой | HTTP 302 redirect. The default. |
| `delayed` | Delayed | Отложенный | An HTML page that auto-redirects after a countdown. |
| `manual` | Manual | Ручной | An HTML page with a "continue" button. |

### Callback status (`Callback.status`)

| Value | EN label | RU label |
|---|---|---|
| `pending` | Pending | Ожидание |
| `sent` | Sent | Отправлен |
| `failed` | Failed | Ошибка |

### Condition type (`Condition.type`)

| Value | EN label | RU label | `data` shape |
|---|---|---|---|
| `time_before` | Time before | До указанного времени | `{ before: ISO8601 }` |

New condition types are added via `ConditionHandler` implementations
registered in the `ConditionRegistry`. Every new type must add a
label to `lang/{en,ru}/resources/condition.php` under `types.*`
and `data_fields.*`.

### Domain selection strategy (`domain_strategy`)

How the server picks a link's domain when it is not given explicitly
(`POST /api/links`). Selection runs over a pool: the group identified
by the `domain_group` code, or all domains.

| Value | EN label | RU label | Behavior |
|---|---|---|---|
| `random` | Random | Случайный | A random domain from the pool. |
| `round_robin` | Round robin | По кругу | Least recently used — domains in rotation. |
| `coldest` | Coldest | Самый холодный | The fewest links over the period (`domains.coldest_period_days`). |

New strategies are added via `DomainSelectionStrategyHandler`
implementations registered in the `DomainStrategyRegistry`.

## Navigation groups (admin panel)

| Key | EN label | RU label | Contents |
|---|---|---|---|
| `main` | Main | Основное | Services, Links, Domains, Domain groups |
| `analytics` | Analytics | Аналитика | Clicks, Callbacks |
| `dictionaries` | Dictionaries | Справочники | URLs, Conditions, IP addresses, Referrers, User agents |
| `system` | System | Система | Users, Settings |

## Contested terms — settled

Debated during the i18n pass. Recorded here so we don't re-litigate.

| Term | Chosen | Rejected |
|---|---|---|
| Callback (RU) | **Колбек** | Коллбэк, Вебхук |
| Bot (RU) | **Бот** | Краулер, Робот |
| Referrer (RU) | **Реферер** | Источник перехода |
| User Agent (RU) | **User agent** (kept in English) | Агент пользователя |
| Click (RU) | **Клик** | Переход |

## Anti-patterns — do not use

- «Переход» for `Click` — conflicts with Transition mode.
- «Токен» on its own for `PersonalAccessToken` — use «API-токен».
- «Адрес» for `Url` — reserved for the IP address / physical address.
- Mixing "Callback" and "Webhook" — we only have Callbacks.

## Change process

1. Edit this file first.
2. Update `lang/en/resources/*.php` and `lang/ru/resources/*.php`.
3. If a code term changes (rare), plan a rename migration.
4. Grep the codebase for the old term to catch stragglers.
