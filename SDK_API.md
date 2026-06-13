# Brevity API Contract (for SDK Development)

This document describes the current public API contract required to build a client SDK.

## 1. General Information

- Base URL: `https://<your-host>`
- Data format: `application/json`
- API versioning: none (current path: `/api/...`)
- Authentication: `Bearer` token (Laravel Sanctum personal access token). The token must carry the `links:create` ability, otherwise the request is rejected with `403 Forbidden`. The same ability gates the read-only catalog endpoints.
- Rate limiting: each endpoint is throttled per service in its own bucket (`429 Too Many Requests` on exceed).

Recommended SDK headers:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>
```

## 2. Endpoints

### `POST /api/links`

Creates a short link and transition rules.

#### Request body

```json
{
  "domain": "short.example.com",
  "title": "Campaign link",
  "forward_query": true,
  "callback_data": {
    "campaign_id": "cmp-42"
  },
  "rules": [
    {
      "url": "https://example.com/landing?b=2&a=1",
      "condition": {
        "type": "time_before",
        "data": {
          "before": "2026-03-05T10:00:00+00:00"
        }
      },
      "transition_mode": "delayed"
    }
  ]
}
```

#### Request fields

- `domain` (`string|null`, max 255): short-link domain; must exist in the system (`exists:domains,value`).
- `title` (`string|null`, max 64): link title.
- `forward_query` (`boolean|null`): whether to forward query parameters on direct HTTP redirect.
- `callback_data` (`object|null`, max 50 entries): callback payload template (see [Callback System](#5-callback-system)).
- `rules` (`array`, required, min 1, max 50): transition rules in priority order.
- `rules[].url` (`string`, required): destination URL, must be an `http`/`https` URL, max 2000 bytes.
- `rules[].condition` (`object|null`): condition for applying the rule.
- `rules[].condition.type` (`string`, required if `condition` is present, max 32): condition type.
- `rules[].condition.data` (`object|null`): condition payload.
- `rules[].transition_mode` (`string|null`, max 16): transition mode (`direct`, `delayed`, `manual`).

#### Supported condition types

Currently registered:

1. `time_before`
   - `data.before` (`string`, required)
   - format: `Y-m-d\TH:i:sP` (example: `2026-03-05T10:00:00+00:00`)

Important: validation for `rules[].condition.data` is dynamic and resolved from `ConditionHandler::rules()` based on `condition.type`.

#### Supported transition modes

1. `direct` (default)
   - Server returns a standard HTTP redirect response.
2. `delayed`
   - Server returns an HTML page with destination info and automatic redirect countdown.
   - Countdown duration is currently fixed to 5 seconds.
3. `manual`
   - Server returns an HTML page with destination info and a manual continue button.

### `GET /api/domains`

Lists the domain catalog, optionally scoped to a single group.

#### Query parameters

- `group_id` (`integer|null`, optional): when present, only domains attached to that group are returned; it must exist (`exists:domain_groups,id`) or the request fails with `422`. Omit it to list every domain.

#### Response — `200 OK`

```json
{
  "data": [
    { "domain": "short.example.com", "url": "https://short.example.com", "is_default": true },
    { "domain": "go.example.com", "url": "https://go.example.com", "is_default": false }
  ]
}
```

- `domain` (`string`): the domain host.
- `url` (`string`): the domain as an `https://` URL.
- `is_default` (`boolean`): whether the domain is used when a link is created without an explicit `domain`.

Domains are ordered by `domain` ascending.

### `GET /api/domain-groups`

Lists every domain group with the number of domains it holds. Use a group's `id` as the `group_id` filter on `GET /api/domains`.

#### Response — `200 OK`

```json
{
  "data": [
    { "id": 1, "name": "Primary", "domains_count": 3 },
    { "id": 2, "name": "Campaigns", "domains_count": 5 }
  ]
}
```

- `id` (`integer`): group identifier (use as `group_id`).
- `name` (`string`): group name.
- `domains_count` (`integer`): number of domains attached to the group.

Groups are ordered by `name` ascending.

## 3. Server Behavior

- Rule priority is defined by array order in `rules` (first item = highest priority).
- `rules[].url` is normalized by the server (URL normalization + query sorting).
- If a condition with the same `type` and identical `data` already exists, the server reuses it.
- On success, the endpoint returns `201 Created`.

## 4. Responses

### 201 Created

```json
{
  "data": {
    "url": "https://short.example.com/AbC12345",
    "domain": "short.example.com",
    "code": "AbC12345",
    "title": "Campaign link",
    "forward_query": true,
    "callback_data": {
      "campaign_id": "cmp-42"
    },
    "rules": [
      {
        "url": "https://example.com/landing?a=1&b=2",
        "condition": {
          "type": "time_before",
          "data": {
            "before": "2026-03-05T10:00:00+00:00"
          }
        },
        "transition_mode": "delayed"
      }
    ]
  }
}
```

For a rule created without a condition, `rules[].condition` is `null` in the
response (and `transition_mode` is `null` when not supplied, meaning `direct`).

### 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden

Returned when the token lacks the `links:create` ability.

```json
{
  "message": "Invalid ability provided."
}
```

### 422 Unprocessable Entity (validation error)

`message` is the first validation error (not a fixed string); `errors` maps each
field to its messages.

```json
{
  "message": "The rules field is required.",
  "errors": {
    "rules.0.condition.data.before": [
      "The rules.0.condition.data.before field is required."
    ]
  }
}
```

### 429 Too Many Requests

Returned when the per-service link-creation rate limit is exceeded.

## 5. Callback System

When a service has a `callback_url` configured **and** a link has a non-null `callback_data`, the server sends an HTTP `POST` request to `callback_url` after every click on that link.

### Trigger conditions

Both conditions must be true:

- `Service.callback_url` is set (non-null)
- `Link.callback_data` is non-null

### Request format

```http
POST {service.callback_url}
Content-Type: application/json

{rendered callback_data}
```

The request body is the rendered `callback_data` object. There are no additional authentication headers.

### Template variables

String values inside `callback_data` may contain template variables in the form `{{variable_name}}`. The server replaces them with real click data before sending.

Available variables:

| Variable | Type | Description |
|---|---|---|
| `{{click.id}}` | string | Unique click ID |
| `{{click.created_at}}` | string | Click timestamp (ISO 8601, UTC) |
| `{{click.ip}}` | string | Visitor IP address (empty string if unavailable) |
| `{{click.url}}` | string | Destination URL the visitor was redirected to |
| `{{click.referrer}}` | string | HTTP Referer header value (empty string if absent) |
| `{{click.user_agent}}` | string | Visitor User-Agent (empty string if absent) |
| `{{link.id}}` | string | Short link ID |
| `{{link.code}}` | string | Short link code (e.g. `AbC12345`) |
| `{{link.title}}` | string | Short link title (empty string if not set) |

Template variables are only substituted inside **string values**. Keys and non-string values are not processed.

### Example

Link created with:

```json
{
  "callback_data": {
    "campaign_id": "cmp-42",
    "click_id": "{{click.id}}",
    "timestamp": "{{click.created_at}}",
    "source_ip": "{{click.ip}}",
    "meta": {
      "referrer": "{{click.referrer}}"
    }
  }
}
```

Callback payload sent after a click:

```json
{
  "campaign_id": "cmp-42",
  "click_id": "1337",
  "timestamp": "2026-04-21T14:05:00+00:00",
  "source_ip": "203.0.113.42",
  "meta": {
    "referrer": "https://t.me/channel"
  }
}
```

### Delivery guarantees

- Up to **5 attempts** with backoff between them: 1 min → 5 min → 15 min → 1 h.
- A response with HTTP 2xx is considered successful.
- Redirects are **not** followed; a 3xx or 4xx response is a permanent failure (no retry).
- A 5xx response or a connection/timeout error triggers a retry.
- After all attempts are exhausted, the callback is marked `failed`.
- The server records `response_code` and `response_body` (truncated to 10 000 characters) for each final attempt.

## 7. SDK Recommendations

Minimum public client contract:

- `createLink(CreateLinkRequest $request): CreateLinkResponse`

Recommended DTOs:

- `CreateLinkRequest`
- `CreateLinkRule`
- `CreateLinkCondition`
- `CreateLinkResponse`
- `CreateLinkResponseRule`

Recommended SDK exceptions:

- `AuthenticationException` (HTTP 401)
- `ValidationException` (HTTP 422, with `errors` as `field -> messages[]`)
- `ApiException` (other 4xx/5xx)
- `TransportException` (timeout/network)

Recommended SDK technical practices:

- Default request timeout: 5-10 seconds.
- Retry only network/5xx failures (no retry for 4xx).
- Always send `Accept: application/json`.
- Do not transform `condition.data` except JSON serialization.

## 8. Ready-to-Use SDK Test Payloads

### Valid `time_before`

```json
{
  "rules": [
    {
      "url": "https://example.com/redirect",
      "condition": {
        "type": "time_before",
        "data": {
          "before": "2026-03-05T10:00:00+00:00"
        }
      }
    }
  ]
}
```

### Valid delayed transition

```json
{
  "rules": [
    {
      "url": "https://example.com/redirect",
      "transition_mode": "delayed"
    }
  ]
}
```

### Invalid `time_before` (invalid date format)

```json
{
  "rules": [
    {
      "url": "https://example.com/redirect",
      "condition": {
        "type": "time_before",
        "data": {
          "before": "2026-03-05 10:00:00"
        }
      }
    }
  ]
}
```

### Invalid `time_before` (missing required field)

```json
{
  "rules": [
    {
      "url": "https://example.com/redirect",
      "condition": {
        "type": "time_before",
        "data": {}
      }
    }
  ]
}
```
