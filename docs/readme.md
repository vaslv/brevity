# Brevity — documentation

A URL shortener with rule-based routing, click accounting, and
outgoing callbacks. Laravel 13 + Filament 5 + PostgreSQL, runs under
Laravel Sail.

This folder is the canonical reference for humans and AI agents. Both
must be able to answer "how does X work?" without grepping the
codebase, and understand where to make changes.

## Map (in reading order)

1. [01-architecture.md](./01-architecture.md) — stack, request flows,
   subsystems, data model, directory layout.
2. [02-glossary.md](./02-glossary.md) — bilingual (EN ↔ RU)
   terminology. **Start here if you are unsure what to call
   something.**
3. [03-api.md](./03-api.md) — the full HTTP API reference: tokens,
   link creation, domains/groups, callbacks, payloads for SDK authors.
4. [04-admin.md](./04-admin.md) — the Filament admin panel: resources,
   navigation, i18n conventions.
5. [05-development.md](./05-development.md) — running the app, tests,
   code style, releases, git conventions.
6. [06-deploy.md](./06-deploy.md) — production topology, CI/CD, manual
   operations on the server.
7. [07-plans.md](./07-plans.md) — all active development plans (the
   stage roadmap + detailed plans).
8. [08-decisions.md](./08-decisions.md) — accepted decisions, known
   risks, the registry of findings from past audits (regression tests
   reference it).

Russian versions of these documents live in
[docs/ru/](./ru/readme.md); the English set is canonical.

## Keeping it up to date

These files are part of the project. Update them in the same commit
that changes behavior or terminology; documentation that has drifted
from reality is a bug. New decisions and trade-offs go into
08-decisions.md. A completed or obsolete document is deleted (git
history preserves it), after first handing its long-lived decisions
over to 08-decisions.md.
