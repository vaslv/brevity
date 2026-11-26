#!/bin/sh
# Production releases without downtime. Run from any directory:
#   sh deploy-host.sh deploy IMAGE    release IMAGE (ghcr.io/owner/repo:tag or @sha256:digest)
#   sh deploy-host.sh entrance        recreate Caddy after its Compose definition changed
#   sh deploy-host.sh retire-legacy   remove the containers of the layout before generations
#   sh deploy-host.sh artisan ARGS    php artisan in the web role of the live generation
#   sh deploy-host.sh status
#
# Every release is a generation: the Compose project brevity-gen-<N> with web, Horizon and the
# scheduler of one image. The order follows the application Caddy contract: stop the background
# roles of the active generation, migrate, start the new web, switch Caddy to it, prove the
# switch through Caddy, start the new background roles, then drain and remove the old
# generation. Until the switch is proven a failure restores the previous route and roles.
# Migrations are not rolled back: they must stay compatible with the running generation.
set -eu
umask 077
cd "$(CDPATH='' cd -- "$(dirname "$0")" && pwd)"
root=$(pwd)

conf=caddy/conf
upstream=$conf/upstream.caddy
caddy_uid=65532
wait_timeout=300
drain_timeout=300

fail() { echo "$*" >&2; exit 1; }

# The last assignment of KEY in the server .env, without surrounding quotes. The file is read,
# never sourced: it belongs to the application.
env_value() {
    sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" .env | tail -n 1 |
        sed -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"
}

load_settings() {
    test -f .env || fail 'Missing server .env.'
    mode=$(env_value CADDY_MODE)
    case "$mode" in
        public|behind-host) ;;
        *) fail 'Set CADDY_MODE=public or CADDY_MODE=behind-host in .env.' ;;
    esac
    database=$(env_value BREVITY_DATABASE)
    database=${database:-external}
    stable_files='-f docker-compose.production.yml'
    generation_files='-f docker-compose.generation.yml'
    database_network=
    case "$database" in
        external) ;;
        bundled) stable_files="$stable_files -f docker-compose.db.yml" ;;
        host)
            generation_files="$generation_files -f docker-compose.host.yml"
            database_network=$(env_value DATABASE_NETWORK)
            test -n "$database_network" || fail 'Set DATABASE_NETWORK for BREVITY_DATABASE=host.'
            ;;
        *) fail 'BREVITY_DATABASE must be external, bundled or host.' ;;
    esac
    hosts=$(env_value APP_HOST | tr -d ' ')
    printf '%s\n' "$hosts" | grep -Eqx '[A-Za-z0-9.-]+(,[A-Za-z0-9.-]+)*' ||
        fail 'APP_HOST must list hostnames separated by commas.'
    technical_host=${hosts%%,*}
    # Caddy separates site addresses with ", "; the application accepts either form.
    CADDY_SITE_ADDRESSES=$(printf '%s' "$hosts" | sed 's/,/, /g')
    export CADDY_SITE_ADDRESSES
    subnet=$(env_value BREVITY_APPS_SUBNET)
    subnet=${subnet:-172.31.250.0/24}
    caddy_port=$(env_value CADDY_PORT)
    caddy_port=${caddy_port:-8082}
    # The application trusts X-Forwarded-* from its Caddy only: the brevity-apps subnet.
    test "$(env_value TRUSTED_PROXIES)" = "$subnet" ||
        fail "Set TRUSTED_PROXIES=$subnet (BREVITY_APPS_SUBNET) in .env."
    if test "$mode" = public; then
        test -n "$(env_value ACME_EMAIL)" || fail 'Set ACME_EMAIL for CADDY_MODE=public.'
    else
        test -n "$(env_value CADDY_TRUSTED_PROXY)" ||
            fail 'Set CADDY_TRUSTED_PROXY to the gateway of the brevity-apps network.'
    fi
}

# shellcheck disable=SC2086 # the file lists are word lists by design
stable() {
    docker compose -p brevity --env-file .env --profile "$mode" $stable_files "$@"
}

# shellcheck disable=SC2086
generation() {
    number=$1 image_ref=$2
    shift 2
    # Interpolation takes only these variables: the application .env reaches the roles as a
    # mounted file, and its keys must not steer the Compose CLI.
    BREVITY_GENERATION=$number LARAVEL_IMAGE=$image_ref LARAVEL_ENV_FILE="$root/.env" \
        DATABASE_NETWORK=$database_network \
        docker compose -p "brevity-gen-$number" --env-file /dev/null $generation_files "$@"
}

project_down() {
    (cd / && docker compose -p "$1" down --timeout 360)
}

lock() {
    mkdir -p .deploy
    chmod 700 .deploy
    exec 9>.deploy/deploy.lock
    flock -n 9 || fail 'Another deployment is running.'
}

containers() {
    # project service [extra docker ps flags]
    project=$1 service=$2
    shift 2
    docker ps -q "$@" --filter "label=com.docker.compose.project=$project" \
        --filter "label=com.docker.compose.service=$service"
}

caddy_container() {
    found=$(containers brevity "caddy-$mode")
    test -n "$found" && test "$(printf '%s\n' "$found" | wc -l | tr -d ' ')" = 1 || return 1
    printf '%s\n' "$found"
}

caddy_exec() {
    docker exec "$caddy" "$@"
}

owner() {
    # shellcheck disable=SC2012 # fixed directory names; ls -n is the portable numeric owner
    ls -nd "$1" | awk '{ print $3 }'
}

# One line, exactly the shapes the Caddyfile defines; anything else is refused before a reload.
valid_route() {
    printf '%s\n' "$1" | grep -Eqx 'import brevity_maintenance|import brevity_generation brevity-web-[1-9][0-9]*:8000 [1-9][0-9]*'
}

write_file() {
    # path mode content: atomic replace; Caddy (UID 65532) reads with mode 0644.
    temporary="$1.next.$$"
    printf '%s\n' "$3" > "$temporary"
    chmod "$2" "$temporary"
    mv -f "$temporary" "$1"
}

reload_caddy() {
    caddy_exec caddy validate --config /etc/caddy/conf/Caddyfile --adapter caddyfile &&
        caddy_exec caddy reload --config /etc/caddy/conf/Caddyfile --adapter caddyfile
}

# route [address to health-check from inside Caddy]
switch_route() {
    route=$1
    valid_route "$route" || fail "Refusing upstream route: $route"
    current=$(cat "$upstream")
    valid_route "$current" || fail "Unexpected content in $upstream; expected one brevity_* import line."
    if test -n "${2:-}"; then
        caddy_exec curl -fsS -o /dev/null --max-time 5 "http://$2/up" ||
            { echo "Caddy cannot reach $2." >&2; return 1; }
    fi
    write_file "$upstream" 644 "$route"
    if ! reload_caddy; then
        # A failed reload keeps the loaded configuration: put back the file it came from.
        write_file "$upstream" 644 "$current"
        return 1
    fi
}

# Requests through Caddy must be answered by the expected generation.
probe() {
    expected=$1
    for path in /up /login; do
        attempt=0
        while :; do
            if test "$mode" = public; then
                code=$(curl -sS --max-time 10 -o /dev/null -D .deploy/probe.headers -w '%{http_code}' \
                    --resolve "$technical_host:443:127.0.0.1" "https://$technical_host$path" || true)
            else
                code=$(curl -sS --max-time 10 -o /dev/null -D .deploy/probe.headers -w '%{http_code}' \
                    -H "Host: $technical_host" -H 'X-Forwarded-Proto: https' \
                    "http://127.0.0.1:$caddy_port$path" || true)
            fi
            if test "$code" = 200 &&
                tr -d '\r' < .deploy/probe.headers | grep -Fxiq "X-Brevity-Generation: $expected"; then
                break
            fi
            attempt=$((attempt + 1))
            # Up to two minutes: on the first public start Caddy obtains certificates first.
            test "$attempt" -lt 24 || { echo "Probe $path: HTTP $code, not generation $expected." >&2; return 1; }
            sleep 5
        done
    done
}

# Wait until Caddy has no request in flight to the address of the previous route.
drain() {
    address=$1 waited=0
    while :; do
        pool=$(caddy_exec curl -fsS --max-time 5 http://127.0.0.1:2019/reverse_proxy/upstreams) || return 1
        busy=$(printf '%s' "$pool" | tr '{' '\n' | grep -F "\"address\":\"$address\"" |
            grep -v '"num_requests":0' || true)
        test -n "$busy" || return 0
        test "$waited" -lt "$drain_timeout" || return 1
        sleep 1
        waited=$((waited + 1))
    done
}

prepare_entrance() {
    for directory in caddy/data caddy/config; do
        test -d "$directory" && test "$(owner "$directory")" = "$caddy_uid" ||
            fail "Create $directory owned by $caddy_uid (once, as root): install -d -m 700 -o $caddy_uid -g $caddy_uid $root/$directory"
    done
    mkdir -p "$conf"
    chmod 755 caddy "$conf"
    if ! test -f "$upstream"; then
        # Before the first generation Caddy answers 503.
        write_file "$upstream" 644 'import brevity_maintenance'
    fi
    stable config --quiet
    docker volume create brevity_geoip_data >/dev/null
    stable up -d --no-recreate --wait --wait-timeout "$wait_timeout" redis
    if test "$database" = bundled; then
        stable up -d --no-recreate --wait --wait-timeout "$wait_timeout" pgsql
    fi
    if caddy=$(caddy_container); then
        if ! cmp -s Caddyfile "$conf/Caddyfile"; then
            cp Caddyfile "$conf/Caddyfile.next"
            chmod 644 "$conf/Caddyfile.next"
            caddy_exec caddy validate --config /etc/caddy/conf/Caddyfile.next --adapter caddyfile ||
                { rm -f "$conf/Caddyfile.next"; fail 'The new Caddyfile is invalid; the running one is kept.'; }
            cp "$conf/Caddyfile" .deploy/Caddyfile.previous
            mv -f "$conf/Caddyfile.next" "$conf/Caddyfile"
            if ! caddy_exec caddy reload --config /etc/caddy/conf/Caddyfile --adapter caddyfile; then
                cp .deploy/Caddyfile.previous "$conf/Caddyfile.next"
                mv -f "$conf/Caddyfile.next" "$conf/Caddyfile"
                fail 'Caddy rejected the new Caddyfile; the running configuration is kept.'
            fi
        fi
        expected=$(stable config --hash "caddy-$mode" | awk '{ print $2 }')
        actual=$(docker inspect --format '{{index .Config.Labels "com.docker.compose.config-hash"}}' "$caddy")
        if test -n "$expected" && test "$expected" != "$actual"; then
            echo "The Caddy service definition changed; apply it in a window: sh deploy-host.sh entrance" >&2
        fi
    else
        cp Caddyfile "$conf/Caddyfile"
        chmod 644 "$conf/Caddyfile"
        stable up -d --wait --wait-timeout "$wait_timeout" "caddy-$mode"
        caddy=$(caddy_container) || fail 'Caddy did not start.'
    fi
    test "$(docker network inspect --format '{{(index .IPAM.Config 0).Subnet}}' brevity-apps)" = "$subnet" ||
        fail "The brevity-apps network does not use BREVITY_APPS_SUBNET=$subnet."
    if test "$mode" = behind-host; then
        gateway=$(docker network inspect --format '{{(index .IPAM.Config 0).Gateway}}' brevity-apps)
        test "$(env_value CADDY_TRUSTED_PROXY)" = "$gateway" ||
            fail "Set CADDY_TRUSTED_PROXY=$gateway (gateway of brevity-apps) and run: sh deploy-host.sh entrance"
    fi
}

read_active() {
    active=
    if test -f .deploy/active.env; then
        active=$(sed -n 's/^GENERATION=//p' .deploy/active.env)
        printf '%s\n' "$active" | grep -Eqx '[1-9][0-9]*' || fail 'Corrupt .deploy/active.env.'
    fi
}

# Background roles of the active generation, or of the layout before generations.
stop_background() {
    stopped=
    if test -n "$active"; then
        ids=$(containers "brevity-gen-$active" scheduler; containers "brevity-gen-$active" horizon)
    else
        ids=$(containers brevity scheduler; containers brevity horizon)
        for id in $ids; do
            # The old layout restarts them with the Docker daemon otherwise.
            docker update --restart=no "$id" >/dev/null
        done
    fi
    for id in $ids; do
        stopped="$stopped $id"
        docker stop --time 360 "$id" >/dev/null
    done
}

start_background() {
    for id in $stopped; do
        if test -z "$active"; then docker update --restart=always "$id" >/dev/null || true; fi
        docker start "$id" >/dev/null || echo "Could not restart container $id." >&2
    done
}

deploy() {
    image=${1:?Expected a GHCR image tag or digest}
    printf '%s\n' "$image" | grep -Eq '^ghcr\.io/[a-z0-9._-]+/[a-z0-9._-]+(:[a-zA-Z0-9_][a-zA-Z0-9_.-]*|@sha256:[a-f0-9]{64})$' ||
        { echo 'Expected ghcr.io/owner/repository:tag or ghcr.io/owner/repository@sha256:digest.' >&2; exit 2; }
    load_settings
    test -f .deploy/database-ready ||
        fail 'Database is not ready. Restore and verify the backup before creating .deploy/database-ready.'
    lock

    # Download and resolve the digest before anything changes.
    docker pull "$image"
    repository=${image%%@*}
    repository=${repository%%:*}
    digest=$(docker image inspect --format '{{range .RepoDigests}}{{println .}}{{end}}' "$image" |
        awk -v prefix="$repository@sha256:" 'index($0, prefix) == 1 { print; exit }')
    test -n "$digest" || fail 'Could not resolve the downloaded image digest.'

    prepare_entrance
    valid_route "$(cat "$upstream")" ||
        fail "Unexpected content in $upstream; expected one brevity_* import line."
    read_active
    last=$(cat .deploy/generation 2>/dev/null || echo 0)
    printf '%s\n' "$last" | grep -Eqx '[0-9]+' || fail 'Corrupt .deploy/generation.'
    if test -n "$active" && test "$active" -gt "$last"; then last=$active; fi
    new=$((last + 1))
    printf '%s\n' "$new" > .deploy/generation
    generation "$new" "$digest" config --quiet

    stopped='' switched=0 committed=0
    previous_route=$(cat "$upstream")
    trap 'cleanup' EXIT
    trap 'exit 130' HUP INT TERM

    stop_background
    generation "$new" "$digest" run --rm --no-deps -T --name "brevity-migrate-$new" web \
        sh -c 'php artisan migrate --force --no-interaction && php artisan domains:sync --no-interaction'
    generation "$new" "$digest" up -d --no-deps --wait --wait-timeout "$wait_timeout" web
    switched=1
    switch_route "import brevity_generation brevity-web-$new:8000 $new" "brevity-web-$new:8000"
    probe "$new"
    generation "$new" "$digest" up -d --no-deps --wait --wait-timeout "$wait_timeout" scheduler horizon

    if test -f .deploy/active.env; then cp .deploy/active.env .deploy/previous.env; fi
    write_file .deploy/active.env 600 "GENERATION=$new
IMAGE=$digest"
    committed=1
    echo "Generation $new is live: $digest"

    if test -n "$active"; then
        if drain "brevity-web-$active:8000"; then
            project_down "brevity-gen-$active" || echo "Could not remove generation $active." >&2
        else
            echo "Requests to generation $active are still running; remove it later: docker compose -p brevity-gen-$active down" >&2
        fi
    elif test -n "$(containers brevity web)"; then
        echo "The web container of the old layout still runs. Point the host web server at 127.0.0.1:$caddy_port, then run: sh deploy-host.sh retire-legacy" >&2
    fi
}

cleanup() {
    result=$?
    trap - EXIT HUP INT TERM
    if test "$result" -ne 0 && test "$committed" = 0; then
        restored=1
        if test "$switched" = 1 && test "$(cat "$upstream")" != "$previous_route"; then
            old_address=
            if test -n "$active"; then old_address="brevity-web-$active:8000"; fi
            switch_route "$previous_route" "$old_address" || restored=0
        fi
        if test "$restored" = 1; then
            project_down "brevity-gen-$new" || true
        else
            echo "Could not restore the previous route; generation $new is kept running." >&2
        fi
        start_background
        echo "Deployment of generation $new failed; the previous generation keeps serving. Migrations are not rolled back." >&2
    fi
    exit "$result"
}

entrance() {
    load_settings
    lock
    test -f "$conf/Caddyfile" && test -f "$upstream" || fail 'Run a deploy first.'
    cp Caddyfile "$conf/Caddyfile"
    chmod 644 "$conf/Caddyfile"
    stable run --rm --no-deps "caddy-$mode" caddy validate --config /etc/caddy/conf/Caddyfile --adapter caddyfile
    stable up -d --force-recreate --wait --wait-timeout "$wait_timeout" "caddy-$mode"
}

retire_legacy() {
    load_settings
    lock
    read_active
    test -n "$active" || fail 'No generation is live yet.'
    caddy=$(caddy_container) || fail 'Caddy is not running.'
    probe "$active"
    for service in web horizon scheduler nginx-proxy acme-companion; do
        for id in $(containers brevity "$service" -a); do
            docker stop --time 70 "$id" >/dev/null
            docker rm "$id" >/dev/null
        done
    done
    echo 'Containers of the old layout removed.'
}

artisan() {
    load_settings
    read_active
    test -n "$active" || fail 'No generation is live yet.'
    web=$(containers "brevity-gen-$active" web)
    test -n "$web" || fail "The web role of generation $active is not running."
    if test -t 0; then
        docker exec -it "$web" php artisan "$@"
    else
        docker exec -i "$web" php artisan "$@"
    fi
}

status() {
    load_settings
    cat .deploy/active.env 2>/dev/null || echo 'No generation is live yet.'
    cat "$upstream" 2>/dev/null || true
    docker ps --filter label=app.brevity.generation --format '{{.Names}}\t{{.Status}}'
}

action=${1:-}
case "$action" in
    deploy) shift; deploy "$@" ;;
    entrance) entrance ;;
    retire-legacy) retire_legacy ;;
    artisan) shift; artisan "$@" ;;
    status) status ;;
    *) echo 'Usage: sh deploy-host.sh deploy IMAGE | entrance | retire-legacy | artisan ARGS | status' >&2; exit 2 ;;
esac
