#!/bin/sh
# Run from any directory: sh /opt/brevity/deploy-host.sh IMAGE
# The database and TLS are managed on the host, outside this deployment.
set -eu
umask 077
cd "$(CDPATH= cd -- "$(dirname "$0")" && pwd)"

image=${1:?Expected a GHCR image tag or digest}
if ! printf '%s\n' "$image" | grep -Eq '^ghcr\.io/[a-z0-9._-]+/[a-z0-9._-]+(:[a-zA-Z0-9_][a-zA-Z0-9_.-]*|@sha256:[a-f0-9]{64})$'; then
    echo 'Expected ghcr.io/owner/repository:tag or ghcr.io/owner/repository@sha256:digest.' >&2
    exit 2
fi
test -f .env || { echo 'Missing server .env.' >&2; exit 1; }
test -f .deploy/database-ready || {
    echo 'Database is not ready. Restore and verify the backup before creating .deploy/database-ready.' >&2
    exit 1
}
exec 9>.deploy/deploy.lock
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }

export LARAVEL_ENV_FILE="$(pwd)/.env"
export LARAVEL_IMAGE="$image"
export RUN_MIGRATIONS=1
compose() {
    docker compose -p brevity --env-file .env \
        -f docker-compose.production.yml -f docker-compose.host.yml "$@"
}

# Validate and download before stopping the running application.
compose config --quiet
docker pull "$image"
repository=${image%%@*}
repository=${repository%%:*}
digest=$(docker image inspect --format '{{range .RepoDigests}}{{println .}}{{end}}' "$image" \
    | awk -v prefix="$repository@sha256:" 'index($0, prefix) == 1 { print; exit }')
test -n "$digest" || { echo 'Could not resolve the downloaded image digest.' >&2; exit 1; }
export LARAVEL_IMAGE="$digest"

started=0
cleanup() {
    result=$?
    trap - EXIT HUP INT TERM
    if test "$result" -ne 0 && test "$started" = 1; then
        compose stop web horizon scheduler || true
        echo 'Deployment failed; application services stopped. Database migrations are not rolled back.' >&2
    fi
    exit "$result"
}
trap cleanup EXIT
trap 'exit 130' HUP INT TERM

compose up -d --wait --wait-timeout 120 redis
started=1
compose stop web horizon scheduler
compose up -d --no-deps --force-recreate --wait --wait-timeout 300 scheduler
compose up -d --no-deps --force-recreate --wait --wait-timeout 300 web horizon
address=$(compose port web 8000)
curl --fail --silent --show-error --max-time 15 "http://$address/up" >/dev/null

if test -f .deploy/image.env; then cp .deploy/image.env .deploy/previous-image.env; fi
printf 'LARAVEL_IMAGE=%s\n' "$digest" > .deploy/image.env.next
mv .deploy/image.env.next .deploy/image.env
echo "Deployment complete: $digest"
