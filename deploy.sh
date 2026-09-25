#!/bin/sh
# Usage: sh deploy.sh deploy IMAGE ENV_FILE | sh deploy.sh rollback [IMAGE]
# Run from the production compose directory. Never run a whole-stack `up` here.
set -eu
umask 077
mode=${1:?Expected deploy or rollback}
image=${2:-}
case "$mode" in deploy|rollback) ;; *) exit 2;; esac
mkdir -p .deploy
exec 9>.deploy/lock
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }

proxy() {
    docker exec -i nginx-proxy sh -s -- "${1:-}" < deploy-proxy.sh
}
retire() {
    sleep 10
    docker stop --time 60 "$1"
    docker rm "$1"
}
web_healthy() {
    docker exec "$1" php -r '@file_get_contents("http://127.0.0.1:8000/up"); exit(str_contains($http_response_header[0] ?? "", " 200 ") ? 0 : 1);'
}
old_web=$(docker ps -q --no-trunc --filter label=com.docker.compose.project=brevity --filter label=com.docker.compose.service=web)
web_count=$(printf '%s\n' "$old_web" | awk 'NF { n++ } END { print n+0 }')
if test "$web_count" -eq 2 && test -f .deploy/current; then
    active=$(sed -n '3p' .deploy/current)
    found=0
    for id in $old_web; do if test "$id" = "$active"; then found=1; fi; done
    test "$found" -eq 1
    if ! web_healthy "$active"; then
        for id in $old_web; do
            if test "$id" != "$active" && web_healthy "$id"; then
                active=$id
                rm .deploy/current
                touch .deploy/failed
                break
            fi
        done
    fi
    for id in $old_web; do
        if test "$id" != "$active"; then proxy "$id"; retire "$id"; fi
    done
    old_web=$active
    web_count=1
fi
test "$web_count" -le 1 || {
    echo 'More than one existing web container; inspect before deploying.' >&2; exit 1
}

# Bootstrap the rollback target on the first deployment using this script.
# Save only a running application that actually answers its health endpoint.
if ! test -f .deploy/current && test -n "$old_web" && web_healthy "$old_web"; then
    old_image=$(docker inspect -f '{{.Config.Image}}' "$old_web")
    old_env=$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/app/.env"}}{{.Source}}{{end}}{{end}}' "$old_web")
    cp "$old_env" .deploy/bootstrap.env
    printf '%s\n%s\n%s\n' "$old_image" "$(pwd)/.deploy/bootstrap.env" "$old_web" > .deploy/current
fi

if test "$mode" = rollback; then
    target=.deploy/previous
    # After a failed deploy the last successful release is still `current`.
    if test -f .deploy/failed; then target=.deploy/current; fi
    if test -z "$image"; then
        test -f "$target" || { echo 'No saved release. Set ROLLBACK_TAG to a known-good tag.' >&2; exit 1; }
        image=$(sed -n '1p' "$target")
    fi
    if test -f "$target" && test "$image" = "$(sed -n '1p' "$target")"; then
        env_source=$(sed -n '2p' "$target")
    elif test -f .deploy/current; then
        env_source=$(sed -n '2p' .deploy/current)
    else
        env_source=.env
    fi
    export RUN_MIGRATIONS=0
else
    test -n "$image"
    env_source=${3:?Expected environment file}
    export RUN_MIGRATIONS=1
fi
test -f "$env_source"
if test -n "${HEALTHCHECK_URL:-}"; then command -v curl >/dev/null; fi
export LARAVEL_IMAGE="$image"
export LARAVEL_ENV_FILE="$(pwd)/.deploy/env-$(date +%s)-$$"
cp "$env_source" "$LARAVEL_ENV_FILE"

compose() {
    docker compose --env-file "$LARAVEL_ENV_FILE" -f docker-compose.production.yml "$@"
}
wait_healthy() {
    container=$1
    attempt=0
    while test "$attempt" -lt 120; do
        state=$(docker inspect -f '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{end}}' "$container")
        case "$state" in
            'running healthy') return 0;;
            exited*|dead*|restarting*) break;;
        esac
        attempt=$((attempt + 1))
        sleep 2
    done
    docker logs --tail 100 "$container" >&2
    echo "Container did not become healthy: $container" >&2
    return 1
}

candidate=
committed=0
scheduler_started=0
cleanup() {
    result=$?
    trap - EXIT HUP INT TERM
    if test "$result" -ne 0 && test "$committed" -eq 0; then
        touch .deploy/failed
        # Restore routing before removing the unsuccessful candidate.
        if proxy "${candidate:-}"; then
            if test -n "$candidate"; then retire "$candidate" || true; fi
        else
            echo 'Could not restore proxy configuration; keeping both web containers for recovery.' >&2
        fi
        if test "$scheduler_started" -eq 1; then compose stop horizon scheduler || true; fi
        echo 'Deployment failed; the previous web container was preserved.' >&2
    fi
    exit "$result"
}
trap cleanup EXIT
trap 'exit 130' HUP INT TERM

docker pull "$LARAVEL_IMAGE"
# Existing proxy / Redis must not be recreated by an application release.
compose up -d --no-recreate redis nginx-proxy acme-companion
proxy
compose stop horizon scheduler
scheduler_started=1
compose up -d --no-deps --force-recreate scheduler
wait_healthy "$(compose ps -q scheduler)"

# The existing web container keeps its image and bind-mounted environment.
# Scale adds a candidate without replacing it, including the legacy named web.
replicas=1
if test -n "$old_web"; then replicas=2; fi
for id in $(compose ps -a -q web); do
    if test "$(docker inspect -f '{{.State.Running}}' "$id")" = false; then
        docker rm "$id"
    fi
done
web_status=0
compose up -d --no-deps --no-recreate --scale "web=$replicas" web || web_status=$?
for id in $(compose ps -a -q web); do
    full_id=$(docker inspect -f '{{.Id}}' "$id")
    if test "$full_id" != "$old_web"; then candidate=$full_id; fi
done
test "$web_status" -eq 0
test -n "$candidate"
wait_healthy "$candidate"
compose up -d --no-deps horizon
wait_healthy "$(compose ps -q horizon)"
wait_healthy "$candidate"

# Reload Nginx with the healthy candidate and without the old upstream before
# stopping the old process. Existing Nginx workers may finish in-flight requests.
proxy "$old_web"
if test -n "${HEALTHCHECK_URL:-}"; then
    healthy=0
    for attempt in 1 2 3 4 5 6; do
        if test "$(curl -sS --max-time 10 -o /dev/null -w '%{http_code}' "$HEALTHCHECK_URL" || true)" = 200; then
            healthy=1
            break
        fi
        sleep 2
    done
    test "$healthy" -eq 1
fi
if test -f .deploy/current; then cp .deploy/current .deploy/previous; fi
printf '%s\n%s\n%s\n' "$LARAVEL_IMAGE" "$LARAVEL_ENV_FILE" "$candidate" > .deploy/current.next
mv .deploy/current.next .deploy/current
rm -f .deploy/failed
committed=1
if test -n "$old_web"; then
    retire "$old_web"
fi
echo "Deployment complete: $LARAVEL_IMAGE"
