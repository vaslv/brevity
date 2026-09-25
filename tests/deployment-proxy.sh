#!/bin/sh
# Integration test: real Compose scaling, nginx-proxy readiness and draining.
# Uses an isolated project/network and a randomly allocated localhost port.
set -eu
root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
directory=$(mktemp -d)
project="brevity-deploy-test-$$"
proxy="$project-proxy"
cleanup() {
    if test -n "${watcher:-}"; then
        touch "$directory/stop"
        wait "$watcher" || true
    fi
    docker compose -p "$project" -f "$directory/compose.yml" down >/dev/null 2>&1 || true
    docker rm -f "$proxy" >/dev/null 2>&1 || true
    docker network rm "$project" >/dev/null 2>&1 || true
    rm -rf "$directory"
}
trap cleanup EXIT
trap 'exit 130' HUP INT TERM
docker network create "$project" >/dev/null
docker run -d --name "$proxy" --network "$project" -p 127.0.0.1::80 \
    -v /var/run/docker.sock:/tmp/docker.sock:ro nginxproxy/nginx-proxy:alpine >/dev/null
port=$(docker inspect -f '{{(index (index .NetworkSettings.Ports "80/tcp") 0).HostPort}}' "$proxy")
cat > "$directory/compose.yml" <<YAML
services:
  web:
    image: nginxproxy/nginx-proxy:alpine
    container_name: $project-legacy
    entrypoint: [sh, -c]
    command: ['printf "events {} http { server { listen 8000; location / { return 200 old; } } }" > /tmp/nginx.conf; exec nginx -c /tmp/nginx.conf -g "daemon off;"']
    expose: [8000]
    environment:
      VIRTUAL_HOST: deploy.test
      VIRTUAL_PORT: 8000
      HTTPS_METHOD: nohttps
    networks: [test]
networks:
  test:
    external: true
    name: $project
YAML
compose() { docker compose -p "$project" -f "$directory/compose.yml" "$@"; }
configure_proxy() { docker exec -i "$proxy" sh -s -- "${1:-}" < "$root/deploy-proxy.sh"; }
request() { curl -fsS --max-time 3 -H 'Host: deploy.test' "http://127.0.0.1:$port/"; }
assert_response() {
    expected=$1
    for attempt in 1 2 3 4 5 6 7 8 9 10; do
        if test "$(request || true)" = "$expected"; then break; fi
        sleep 1
    done
    for attempt in 1 2 3 4 5; do test "$(request)" = "$expected"; done
}
compose up -d
old=$(docker inspect -f '{{.Id}}' "$project-legacy")
configure_proxy
assert_response old
(
    while ! test -f "$directory/stop"; do
        request >> "$directory/responses" 2>/dev/null || echo failure >> "$directory/errors"
        sleep 0.1
    done
) &
watcher=$!

# Simulate migration from the existing named web to scalable web containers.
sed '/container_name:/d; s/return 200 old/return 200 new/' "$directory/compose.yml" > "$directory/next.yml"
mv "$directory/next.yml" "$directory/compose.yml"
# A candidate answers HTTP but must not receive traffic until Docker marks it ready.
cat > "$directory/health.yml" <<'YAML'
services:
  web:
    healthcheck:
      test: [CMD-SHELL, 'test -f /tmp/ready']
      interval: 1s
      retries: 1
YAML
docker compose -p "$project" -f "$directory/compose.yml" -f "$directory/health.yml" up -d --no-deps --no-recreate --scale web=2
new=
for id in $(compose ps -q web); do
    id=$(docker inspect -f '{{.Id}}' "$id")
    if test "$id" != "$old"; then new=$id; fi
done
test -n "$new"
test "$(docker inspect -f '{{.State.Running}}' "$old")" = true
configure_proxy
assert_response old
docker exec "$new" touch /tmp/ready
for attempt in 1 2 3 4 5; do
    if test "$(docker inspect -f '{{.State.Health.Status}}' "$new")" = healthy; then break; fi
    sleep 1
done
test "$(docker inspect -f '{{.State.Health.Status}}' "$new")" = healthy
configure_proxy "$old"
assert_response new
docker stop "$old" >/dev/null
assert_response new
docker start "$old" >/dev/null
# A failed post-switch check can return traffic to the still-running old web.
configure_proxy "$new"
assert_response old
sleep 10
docker stop --time 60 "$new" >/dev/null
docker rm "$new" >/dev/null
assert_response old
touch "$directory/stop"
wait "$watcher"
watcher=
test ! -s "$directory/errors"
echo 'PASS: legacy web preserved, unready candidate excluded, switch and rollback verified.'
