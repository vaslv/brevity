# Contributing to Brevity

Thanks for your interest! This document covers the essentials; the
canonical development reference is
[docs/05-development.md](./docs/05-development.md).

## Development environment

Local development runs on [Laravel Sail](https://laravel.com/docs/sail)
— see the [README quick start](./README.md#quick-start). Prefix PHP,
Artisan, Composer and Node commands with `vendor/bin/sail`.

## Before you open a PR

Run the full health stack — CI enforces all three:

```bash
vendor/bin/sail bin pint --test        # code style
vendor/bin/sail bin phpstan analyse --memory-limit=1G   # static analysis
vendor/bin/sail artisan test --compact # tests
```

- **Every behaviour change ships with a test** (new or updated).
- **Documentation lives with the change**: if a commit changes
  behaviour, API payloads or terminology, update the affected file in
  `docs/` (and its Russian mirror in `docs/ru/`) in the same commit.
- **Terminology** comes from [docs/02-glossary.md](./docs/02-glossary.md)
  — code identifiers, DB tables and UI labels must match it.

## Code conventions

- Follow the existing code style; Pint settles disputes.
- Every Eloquent relation lives in its own trait — never inline in the
  model class (see `app/Models/Relations/` for the pattern).
- Mind Laravel Octane: no request state in singletons or static
  properties; the app boots once and serves many requests.

## Commits

Conventional-commit style, imperative mood, English:

```
feat(admin): add click counter widget
fix(api): reject empty domain group codes
docs, chore, build, style, refactor, test — as appropriate
```

## Releases

Maintainers cut releases with `composer release` (the
[`vaslv/composer-release`](https://github.com/vaslv/composer-release)
plugin): it bumps `version` in `composer.json`, creates an annotated
semver tag (no `v` prefix) and offers to push.

## Security issues

Please do not open public issues for vulnerabilities — see
[SECURITY.md](./SECURITY.md).
