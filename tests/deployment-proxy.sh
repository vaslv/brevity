#!/bin/sh
# Integration test: real Compose scaling, nginx-proxy readiness and draining.
# Uses an isolated project/network and a randomly allocated localhost port.
set -eu
root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
directory=$(mktemp -d)
project="brevity-deploy-test-$$"
proxy="$project-proxy"
php_image=${UPLOAD_TEST_IMAGE:-$(awk '/^FROM dunglas\/frankenphp:/ { print $2 }' "$root/Dockerfile")}
mkdir "$directory/upload"
cat > "$directory/upload/index.php" <<'PHP'
<?php
if (PHP_VERSION_ID < 80427 || PHP_VERSION_ID >= 80500 || ! ini_get('session.upload_progress.enabled')) {
    http_response_code(500);
    exit('Unexpected PHP version or upload progress configuration');
}
$file = $_FILES['file'] ?? null;
if (! $file || $file['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file($file['tmp_name'])) {
    http_response_code(422);
    exit('Missing uploaded file');
}
echo file_get_contents($file['tmp_name']);
PHP
printf '%s' 'multipart-upload-test' > "$directory/payload.txt"
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
  upload:
    image: $php_image
    entrypoint: [frankenphp]
    command: [php-server, --listen, ':8000', --root, /fixture]
    healthcheck:
      disable: true
    volumes:
      - $directory/upload:/fixture:ro
    expose: [8000]
    environment:
      VIRTUAL_HOST: upload.deploy.test
      VIRTUAL_PORT: 8000
      HTTPS_METHOD: nohttps
    networks: [test]
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
assert_multipart_allowed() {
    # A reload lets the previous Nginx workers finish before they exit.
    for attempt in 1 2 3 4 5 6 7 8 9 10; do
        status=$(curl -sS --max-time 3 -o /dev/null -w '%{http_code}' \
            -H 'Host: deploy.test' -F "file=@$directory/payload.txt" "http://127.0.0.1:$port/")
        if test "$status" = 200; then break; fi
        sleep 1
    done
    test "$status" = 200
    for method in POST PUT PATCH; do
        for content_type in 'multipart/form-data; boundary=test' 'Multipart/Form-Data; boundary="test"' 'multipart/form-data'; do
            status=$(curl -sS --max-time 3 -o /dev/null -w '%{http_code}' \
                -X "$method" -H 'Host: deploy.test' -H "Content-Type: $content_type" \
                --data-binary 'multipart-request' "http://127.0.0.1:$port/upload")
            test "$status" = 200
        done
    done
    # Verify actual PHP multipart parsing and file contents behind the proxy.
    test "$(curl -fsS --max-time 10 -H 'Host: upload.deploy.test' \
        -F "file=@$directory/payload.txt" -F 'PHP_SESSION_UPLOAD_PROGRESS=test' \
        "http://127.0.0.1:$port/")" = multipart-upload-test
    test "$(curl -fsS --max-time 3 -H 'Host: deploy.test' \
        -H 'Content-Type: application/json' -d '{}' "http://127.0.0.1:$port/")" = old
    test "$(curl -fsS --max-time 3 -H 'Host: deploy.test' \
        -d 'field=value' "http://127.0.0.1:$port/")" = old
}
compose up -d
old=$(docker inspect -f '{{.Id}}' "$project-legacy")
# Simulate the template left by the temporary mitigation on an existing proxy.
docker exec -i "$proxy" sh <<'SH'
set -eu
cp /app/nginx.tmpl /app/nginx.tmpl.brevity-original
awk '/^server \{/ { print; print "    if ($http_content_type ~* multipart/form-data) { return 403; }"; next } { print }' \
    /app/nginx.tmpl.brevity-original > /app/nginx.tmpl
docker-gen -only-exposed /app/nginx.tmpl /etc/nginx/conf.d/default.conf
nginx -t
nginx -s reload
SH
assert_response old
test "$(curl -sS --max-time 3 -o /dev/null -w '%{http_code}' \
    -H 'Host: deploy.test' -F "file=@$directory/payload.txt" "http://127.0.0.1:$port/")" = 403
configure_proxy
assert_response old
assert_multipart_allowed
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
assert_multipart_allowed
echo 'PASS: legacy web preserved, unready candidate excluded, switch and rollback verified.'
echo 'PASS: legacy multipart block removed; patched PHP receives uploads before and after rollback.'
