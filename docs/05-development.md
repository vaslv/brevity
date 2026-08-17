# Development

## Running

All commands go through Sail.

```bash
vendor/bin/sail up -d          # bring up the containers
vendor/bin/sail artisan migrate
vendor/bin/sail npm run dev    # Vite dev server (if you touch the frontend)
```

For the first run, additionally:

```bash
vendor/bin/sail composer install
cp .env.example .env
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan lang:publish   # publishes Laravel core en/ strings
```

## Tests

```bash
vendor/bin/sail test                            # full suite
vendor/bin/sail test --filter=LinkResolveRate   # by name
vendor/bin/sail test tests/Feature/Callbacks/   # by path
```

All tests must pass before any commit that lands on `main`.
Regression tests in `tests/Feature/Regressions/` — one file per
historical bug (the docstring references the finding ID in
[08-decisions.md](./08-decisions.md)); they must not be deleted
without the owner's decision.

## Code style

```bash
vendor/bin/sail bin pint --dirty --format agent
```

Runs automatically in CI; run it locally before committing.

Conventions (from `AGENTS.md` / Laravel Boost):

- PHP 8.4; constructor property promotion everywhere.
- Explicit return and parameter types on every public method.
- Tests instead of tinker scripts.
- Don't invent directory structure; mirror the neighbors.

## i18n process

1. Touching admin copy? Edit the matching
   `lang/en/resources/<x>.php` and `lang/ru/resources/<x>.php` — both
   sides.
2. Changing a term? Update [docs/02-glossary.md](./02-glossary.md)
   first.
3. Filament chrome / Laravel core strings come from vendor files —
   don't reinvent them.

For the full i18n layout, see [docs/04-admin.md](./04-admin.md).

## Releases

Versioning: semver **without** the `v` prefix (`1.4.1`, not `v1.4.1`).
The git tag is the **single** source of truth — nothing in the working
tree records the version, and `composer.json` deliberately carries no
`version` field.

The interactive release command is provided by the
[`vaslv/composer-release`](https://github.com/vaslv/composer-release)
dev package (a composer plugin installed from Packagist; there is no
local script in the repo):

```bash
composer release
```

Run it wherever git + SSH keys for pushing are available; from inside
Sail you can create the commit and tag, but answer `n` to the push
prompt and push from the host
(`git push origin main && git push origin <tag>`).

The command:
1. Checks that the tree is clean and on the expected branch.
2. Shows the current tag and offers patch / minor / major / custom.
3. Creates an annotated tag — **no release commit**, nothing in the
   tree changes.
4. Asks before pushing.

### How the version reaches the application

```
git tag 1.4.1
   └─ CI: --build-arg APP_VERSION=$CI_COMMIT_TAG   (.gitlab-ci.yml)
        └─ Dockerfile: ENV APP_VERSION + OCI image labels
             └─ config/app.php: 'version' => env('APP_VERSION')
                  └─ config/filament-app-version.php → the topbar chip
```

The same value also becomes `SENTRY_RELEASE`, so every Sentry issue is
attributed to the release it came from.

Outside a built image `APP_VERSION` is unset and the resolver chain
falls through to `GitVersionResolver`, which reads the short commit SHA
straight out of `.git`. So locally the chip shows something like
`ad2f26b` — the commit you are actually running, which is more useful
than a tag you have not cut yet. A production image has no `.git`
(see `.dockerignore`), so the fallback there is the literal `dev`,
which only happens if the image was built by hand without the build
argument.

Consequences worth knowing:

- **A version bump now requires a rebuild.** The value lives in the
  image, not in a file the running app rereads.
- **Do not put `APP_VERSION` in `.env`.** Laravel's dotenv is immutable
  and leaves an already-set process variable alone, so the value from
  the image always wins and the `.env` entry would be silently ignored.
- The image tag, `APP_VERSION` and the
  `org.opencontainers.image.version` label are the same string by
  construction, so they cannot disagree.

## Git conventions

- Conventional commits: `feat(...)`, `fix(...)`, `refactor(...)`,
  `chore(...)`.
- **Commit messages are in English** (both subject and body), even
  when the task discussion happens in Russian.
- One logical change per commit.
- Subject in the imperative mood, ≤ 72 characters.
- The body explains *why*; the code explains *what*.
- No force-pushing to `main`.

## Agent workflow tips

(For Claude Code / other AI agents working in this repo.)

- Trust Filament's stock translations — don't duplicate keys in
  `ru.json` that already live in `vendor/filament/*/lang/ru/`.
- Always use `Read` before `Write` (enforced).
- Run `sail test` before committing; don't bundle large UI changes.
- Prefer per-resource/per-subsystem commits over one giant commit —
  a revert is cheaper.
- When adding a new string, add the translation to both `en/` and
  `ru/` *in the same commit* so the locales don't drift.
