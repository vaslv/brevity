#!/bin/sh
# Release orchestration of deploy-host.sh with isolated command doubles: no Docker needed.
# The curl double answers like the application Caddy: with the generation named in the
# upstream file, so a probe proves which route is live.
set -eu
root=$(CDPATH='' cd -- "$(dirname "$0")/.." && pwd)
directory=$(mktemp -d)
cleanup() {
    result=$?
    if test "$result" -ne 0; then
        cat "$directory/output" "$TEST_LOG" >&2 || true
    fi
    rm -rf "$directory"
}
trap cleanup EXIT
app="$directory/app"
mkdir "$directory/bin" "$directory/state" "$app"
cp "$root/deploy-host.sh" "$root/Caddyfile" "$app/"
export TEST_LOG="$directory/commands" TEST_STATE="$directory/state" TEST_APP="$app"
export TEST_DIGEST="ghcr.io/vaslv/brevity@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
export PATH="$directory/bin:$PATH"
: > "$TEST_LOG"
: > "$directory/output"

cat > "$directory/bin/docker" <<'SH'
#!/bin/sh
printf '%s\n' "docker $*" >> "$TEST_LOG"
args="$*"
fails() { test "${1:-0}" = 0 || exit 1; }
case "$*" in
    pull*) fails "${TEST_PULL_FAIL:-0}" ;;
    'image inspect'*) printf '%s\n' "$TEST_DIGEST" ;;
    *'config --hash'*) printf '%s\n' 'caddy-behind-host hash-1' ;;
    *'up -d --wait --wait-timeout 300 caddy-behind-host') touch "$TEST_STATE/caddy" ;;
    *' run --rm --no-deps -T --name brevity-migrate-'*) fails "${TEST_MIGRATE_FAIL:-0}" ;;
    *'up -d --no-deps --wait --wait-timeout 300 web') fails "${TEST_WEB_FAIL:-0}" ;;
    *'up -d --no-deps --wait --wait-timeout 300 scheduler horizon') fails "${TEST_WORKERS_FAIL:-0}" ;;
    'ps -q'*service=caddy-behind-host*) if test -f "$TEST_STATE/caddy"; then echo caddy-1; fi ;;
    'ps -q'*project=brevity\ *service=scheduler*|'ps -q'*project=brevity\ *service=horizon*)
        if test "${TEST_LEGACY:-0}" = 1; then echo "legacy-${args##*service=}"; fi ;;
    'ps -q'*project=brevity\ *service=web*) if test "${TEST_LEGACY:-0}" = 1; then echo legacy-web; fi ;;
    'ps -q'*project=brevity-gen-*service=scheduler*|'ps -q'*project=brevity-gen-*service=horizon*)
        project=${args#*project=}; project=${project%% *}; echo "$project-${args##*service=}" ;;
    'inspect --format'*config-hash*) echo hash-1 ;;
    *'.Subnet}}'*) echo '172.31.250.0/24' ;;
    *'.Gateway}}'*) echo '172.31.250.1' ;;
    'exec caddy-1 curl'*'/reverse_proxy/upstreams') echo '[{"address":"brevity-web-1:8000","num_requests":0,"fails":0}]' ;;
    'exec caddy-1 curl'*) fails "${TEST_HEALTH_FAIL:-0}" ;;
    'exec caddy-1 caddy reload'*) fails "${TEST_RELOAD_FAIL:-0}" ;;
esac
exit 0
SH
cat > "$directory/bin/curl" <<'SH'
#!/bin/sh
printf '%s\n' "curl $*" >> "$TEST_LOG"
headers=
while test $# -gt 0; do
    if test "$1" = -D; then headers=$2; fi
    shift
done
set -- $(cat "$TEST_APP/caddy/conf/upstream.caddy")
code=200
if test "${TEST_PROBE_FAIL:-0}" = 1; then code=502; fi
printf 'HTTP/1.1 %s\r\nX-Brevity-Generation: %s\r\n\r\n' "$code" "${4:-}" > "$headers"
printf '%s' "$code"
SH
cat > "$directory/bin/flock" <<'SH'
#!/bin/sh
test "${TEST_LOCK_FAIL:-0}" = 0
SH
cat > "$directory/bin/sleep" <<'SH'
#!/bin/sh
exit 0
SH
# caddy/data and caddy/config belong to Caddy's UID on a server; a test user cannot chown.
cat > "$directory/bin/ls" <<'SH'
#!/bin/sh
echo "drwx------ 2 65532 65532 64 Jan 1 00:00 $3"
SH
chmod +x "$directory/bin/"*

deploy() { sh "$app/deploy-host.sh" deploy "${1:-ghcr.io/vaslv/brevity:1.12.0}" > "$directory/output" 2>&1; }
expect_failure() {
    if deploy "$@"; then
        echo 'Expected deployment to fail.' >&2
        exit 1
    fi
}
expect_log() {
    grep -Fq -- "$1" "$TEST_LOG" || { echo "Expected command: $1" >&2; exit 1; }
}
refuse_log() {
    if grep -Fq -- "$1" "$TEST_LOG"; then echo "Unexpected command: $1" >&2; exit 1; fi
}
route() { cat "$app/caddy/conf/upstream.caddy"; }
active() { sed -n 's/^GENERATION=//p' "$app/.deploy/active.env"; }

# Refused before any Docker call.
expect_failure 'not-an-image'
expect_failure
test ! -s "$TEST_LOG"
cat > "$app/.env" <<'ENV'
APP_HOST=brevity.example.com,lnk1.example.com
CADDY_MODE=behind-host
CADDY_PORT=8082
CADDY_TRUSTED_PROXY=172.31.250.1
TRUSTED_PROXIES=10.0.0.0/8
ENV
expect_failure
grep -q 'TRUSTED_PROXIES=172.31.250.0/24' "$directory/output"
sed -i.bak 's#^TRUSTED_PROXIES=.*#TRUSTED_PROXIES=172.31.250.0/24#' "$app/.env"
expect_failure
grep -q 'Database is not ready' "$directory/output"
mkdir -p "$app/.deploy" "$app/caddy/data" "$app/caddy/config"
touch "$app/.deploy/database-ready"
(export TEST_LOCK_FAIL=1; expect_failure)
test ! -s "$TEST_LOG"
(export TEST_PULL_FAIL=1; expect_failure)
refuse_log 'docker stop'
refuse_log 'compose'

# First release on the layout before generations: its web keeps serving the host web server
# until the operator moves it to Caddy; its background roles stop before the migration.
: > "$TEST_LOG"
(export TEST_LEGACY=1; deploy)
test "$(route)" = 'import brevity_generation brevity-web-1:8000 1'
test "$(active)" = 1
grep -q "^IMAGE=$TEST_DIGEST$" "$app/.deploy/active.env"
expect_log 'docker update --restart=no legacy-scheduler'
expect_log 'docker stop --time 360 legacy-horizon'
expect_log 'up -d --no-recreate --wait --wait-timeout 300 redis'
expect_log 'http://127.0.0.1:8082/login'
refuse_log 'down'
refuse_log 'legacy-web'
grep -q 'retire-legacy' "$directory/output"
test "$(cat "$app/caddy/conf/Caddyfile")" = "$(cat "$app/Caddyfile")"

# A failed migration keeps the route and restarts the stopped roles of the live generation.
: > "$TEST_LOG"
(export TEST_MIGRATE_FAIL=1; expect_failure)
test "$(route)" = 'import brevity_generation brevity-web-1:8000 1'
test "$(active)" = 1
expect_log 'docker stop --time 360 brevity-gen-1-scheduler'
expect_log 'docker start brevity-gen-1-scheduler'
expect_log 'docker start brevity-gen-1-horizon'
expect_log 'compose -p brevity-gen-2 down'
refuse_log 'up -d --no-deps --wait --wait-timeout 300 web'

# A failed probe after the switch puts the previous route back.
: > "$TEST_LOG"
(export TEST_PROBE_FAIL=1; expect_failure)
test "$(route)" = 'import brevity_generation brevity-web-1:8000 1'
test "$(active)" = 1
expect_log 'compose -p brevity-gen-3 down'
expect_log 'docker start brevity-gen-1-horizon'
refuse_log 'scheduler horizon'

# A rejected reload restores the route file Caddy still serves.
: > "$TEST_LOG"
(export TEST_RELOAD_FAIL=1; expect_failure)
test "$(route)" = 'import brevity_generation brevity-web-1:8000 1'
expect_log 'compose -p brevity-gen-4 down'

# Caddy cannot reach the new web: no switch at all.
: > "$TEST_LOG"
(export TEST_HEALTH_FAIL=1; expect_failure)
test "$(route)" = 'import brevity_generation brevity-web-1:8000 1'
refuse_log 'caddy reload'

# A corrupt route file is refused before anything stops.
cp "$app/caddy/conf/upstream.caddy" "$directory/route"
printf 'reverse_proxy example.com\n' > "$app/caddy/conf/upstream.caddy"
: > "$TEST_LOG"
expect_failure
refuse_log 'docker stop'
refuse_log 'caddy reload'
cp "$directory/route" "$app/caddy/conf/upstream.caddy"

# A successful release switches, starts the new background roles, drains and removes the old.
: > "$TEST_LOG"
deploy
test "$(route)" = 'import brevity_generation brevity-web-6:8000 6'
test "$(active)" = 6
grep -q '^GENERATION=1$' "$app/.deploy/previous.env"
expect_log 'up -d --no-deps --wait --wait-timeout 300 scheduler horizon'
expect_log '/reverse_proxy/upstreams'
expect_log 'compose -p brevity-gen-1 down'
refuse_log 'compose -p brevity-gen-6 down'
if grep -Eq 'nginx-proxy|acme-companion|pgsql|force-recreate' "$TEST_LOG"; then
    echo 'A release touched the entrance or infrastructure.' >&2
    exit 1
fi
echo 'Host deployment: refusals, legacy layout, migration, probe, reload and health failures, and a full switch passed.'
