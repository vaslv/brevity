#!/bin/sh
# Deployment orchestration regression tests with isolated command doubles.
set -eu
root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
directory=$(mktemp -d)
cleanup() {
    result=$?
    if test "$result" -ne 0; then
        cat "$directory/output" "$directory/commands" >&2
    fi
    rm -rf "$directory"
}
trap cleanup EXIT
mkdir "$directory/bin" "$directory/app"
cp "$root/deploy-host.sh" "$directory/app/"
export TEST_LOG="$directory/commands"
export TEST_DIGEST="ghcr.io/vaslv/brevity@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
export PATH="$directory/bin:$PATH"
touch "$TEST_LOG"

cat > "$directory/bin/docker" <<'SH'
#!/bin/sh
printf '%s\n' "docker $*" >> "$TEST_LOG"
case "$*" in
    pull*) test "${TEST_PULL_FAIL:-0}" = 0 ;;
    'image inspect'*) printf '%s\n' "$TEST_DIGEST" ;;
    *'up -d --no-deps --force-recreate --wait --wait-timeout 300 scheduler')
        test "${TEST_SCHEDULER_FAIL:-0}" = 0 ;;
    *'port web 8000') printf '%s\n' '127.0.0.1:8081' ;;
esac
SH
cat > "$directory/bin/curl" <<'SH'
#!/bin/sh
printf '%s\n' "curl $*" >> "$TEST_LOG"
test "${TEST_HTTP_FAIL:-0}" = 0
SH
cat > "$directory/bin/flock" <<'SH'
#!/bin/sh
test "${TEST_LOCK_FAIL:-0}" = 0
SH
chmod +x "$directory/bin/"*

deploy() { sh "$directory/app/deploy-host.sh" "${1:-ghcr.io/vaslv/brevity:1.10.21}"; }
expect_failure() {
    if deploy "$@" > "$directory/output" 2>&1; then
        echo 'Expected deployment to fail.' >&2
        exit 1
    fi
}

expect_failure 'not-an-image'
expect_failure
test ! -s "$TEST_LOG"
touch "$directory/app/.env"
expect_failure
test ! -s "$TEST_LOG"
mkdir "$directory/app/.deploy"
touch "$directory/app/.deploy/database-ready"

(export TEST_LOCK_FAIL=1; expect_failure)
test ! -s "$TEST_LOG"
(export TEST_PULL_FAIL=1; expect_failure)
if grep -q ' stop ' "$TEST_LOG"; then
    echo 'Pull failure stopped the existing application.' >&2
    exit 1
fi

printf 'LARAVEL_IMAGE=previous\n' > "$directory/app/.deploy/image.env"
: > "$TEST_LOG"
(export TEST_SCHEDULER_FAIL=1; expect_failure)
grep -q 'stop web horizon scheduler' "$TEST_LOG"
test "$(cat "$directory/app/.deploy/image.env")" = 'LARAVEL_IMAGE=previous'
if grep -q 'up .*web horizon' "$TEST_LOG"; then
    echo 'Started web before scheduler was healthy.' >&2
    exit 1
fi

: > "$TEST_LOG"
(export TEST_HTTP_FAIL=1; expect_failure)
grep -q 'stop web horizon scheduler' "$TEST_LOG"
test "$(cat "$directory/app/.deploy/image.env")" = 'LARAVEL_IMAGE=previous'

deploy > "$directory/output" 2>&1
test "$(cat "$directory/app/.deploy/image.env")" = "LARAVEL_IMAGE=$TEST_DIGEST"
test "$(cat "$directory/app/.deploy/previous-image.env")" = 'LARAVEL_IMAGE=previous'
grep -q 'http://127.0.0.1:8081/up' "$TEST_LOG"
if grep -Eq 'nginx-proxy|acme-companion|pgsql|down|prune' "$TEST_LOG"; then
    echo 'Deployment touched infrastructure outside the application.' >&2
    exit 1
fi
echo 'Host deployment: readiness, lock, pull failure, migration failure, HTTP failure and successful digest recording passed.'
