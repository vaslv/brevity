#!/bin/sh
# Chain test: client -> application Caddy -> echo backend, built from the production service
# definition (docker-compose.production.yml) and the Caddyfile of the repository. Only the
# ACME issuer (tls internal), the published ports and the network are replaced.
# Run after every change of the Caddyfile, the Caddy service or proxy trust:
#   sh tests/caddy-chain.sh
# Needs Docker with Compose v2. CHAIN_SUBNET_PREFIX picks a free /24 (default 192.168.251).
set -eu
repo=$(CDPATH='' cd -- "$(dirname "$0")/.." && pwd)
image=$(sed -n 's/^  image: \(caddy:.*\)$/\1/p' "$repo/docker-compose.production.yml" | head -n 1)
test -n "$image" || { echo 'No Caddy image in docker-compose.production.yml.' >&2; exit 1; }
prefix=${CHAIN_SUBNET_PREFIX:-192.168.251}
id=$$
project="brevity-chain-$id"
network="brevity-chain-$id"
caddy_ip="$prefix.10"
proxy_ip="$prefix.40"
client_ip="$prefix.50"
work=$(mktemp -d)

compose() {
    BREVITY_APPS_SUBNET="$prefix.0/24" CADDY_SITE_ADDRESSES='brevity.test, lnk1.test' \
        ACME_EMAIL=chain@example.com CADDY_TRUSTED_PROXY="$proxy_ip" TLS_MAX=tls1.3 \
        docker compose -p "$project" --project-directory "$work" --env-file /dev/null \
        -f "$repo/docker-compose.production.yml" -f "$work/override.yml" "$@"
}

teardown() {
    for name in backend-1 backend-2 proxy client; do
        docker rm -f "$project-$name" >/dev/null 2>&1 || true
    done
    compose --profile public --profile behind-host down --remove-orphans >/dev/null 2>&1 || true
    # Caddy wrote its data as UID 65532: remove it with the same image.
    docker run --rm --user 0 -v "$work:/work" --entrypoint sh "$image" -c 'rm -rf /work/caddy' >/dev/null 2>&1 || true
}
cleanup() {
    teardown
    rm -rf "$work"
}
trap cleanup EXIT

cat > "$work/override.yml" <<YAML
services:
  caddy-public:
    ports: !reset []
    networks:
      apps:
        ipv4_address: $caddy_ip
  caddy-behind-host:
    ports: !reset []
    networks:
      apps:
        ipv4_address: $caddy_ip
networks:
  apps:
    name: $network
YAML
cat > "$work/backend.caddyfile" <<'CADDY'
{
	admin off
}
:8000 {
	respond /up "ok" 200
	respond "gen={$GEN} xff={header.X-Forwarded-For} proto={header.X-Forwarded-Proto} host={header.X-Forwarded-Host} port={header.X-Forwarded-Port} prefix=[{header.X-Forwarded-Prefix}]" 200
}
CADDY

failed=''
check() {
    # description, then a command that must succeed
    description=$1
    shift
    if ! "$@" >/dev/null 2>&1; then
        echo "  FAIL: $description" >&2
        failed=1
    fi
}
in_client() { docker exec "$project-client" "$@"; }
in_proxy() { docker exec "$project-proxy" "$@"; }
https() {
    # client, host, path, extra curl flags: prints headers and body
    who=$1 host=$2 path=$3
    shift 3
    docker exec "$project-$who" curl -sS -k -i --max-time 10 --resolve "$host:443:$caddy_ip" "$@" "https://$host$path"
}
contains() { printf '%s' "$1" | tr -d '\r' | grep -Fiq -- "$2"; }
lacks() { ! printf '%s' "$1" | tr -d '\r' | grep -Fiq -- "$2"; }
route() {
    printf '%s\n' "$1" > "$work/caddy/conf/upstream.caddy.next"
    chmod 644 "$work/caddy/conf/upstream.caddy.next"
    mv -f "$work/caddy/conf/upstream.caddy.next" "$work/caddy/conf/upstream.caddy"
}
reload() {
    docker exec "$(compose --profile "$1" ps -q "caddy-$1")" \
        caddy reload --config /etc/caddy/conf/Caddyfile --adapter caddyfile
}
generation_route() { route "import brevity_generation brevity-web-$1:8000 $1"; }

# Sequential requests while Caddy switches to generation $2 and back: only 200 answers, and
# at most two reset connections per reload (a Caddy 2.11 listener handover).
switch_under_load() {
    mode=$1
    # shellcheck disable=SC2016 # the loop runs inside the client container
    in_client sh -c 'rm -f /tmp/codes; i=0; while [ $i -lt 150 ]; do
        curl -sk -o /dev/null -w "%{http_code}\n" --max-time 5 --resolve brevity.test:443:'"$caddy_ip"' https://brevity.test/up >> /tmp/codes 2>/dev/null
        i=$((i+1)); done' &
    loop=$!
    sleep 1
    generation_route 2
    reload "$mode" >/dev/null 2>&1 || return 1
    sleep 1
    generation_route 1
    reload "$mode" >/dev/null 2>&1 || return 1
    wait "$loop" || true
    codes=$(in_client cat /tmp/codes)
    test "$(printf '%s\n' "$codes" | grep -cvx '200\|000')" = 0 || return 1
    test "$(printf '%s\n' "$codes" | grep -cx '000')" -le 4
}

suite() {
    caddyfile=$1
    failed=''
    teardown
    mkdir -p "$work/caddy/conf" "$work/caddy/data" "$work/caddy/config"
    cp "$caddyfile" "$work/caddy/conf/Caddyfile"
    # The only production change: certificates from Caddy's internal CA instead of ACME.
    # shellcheck disable=SC2016 # a literal Caddyfile placeholder
    test "$(grep -c 'tls {\$ACME_EMAIL} {' "$work/caddy/conf/Caddyfile")" = 1 || { echo 'Expected one ACME tls line.' >&2; return 1; }
    # shellcheck disable=SC2016
    sed -i.bak 's/tls {\$ACME_EMAIL} {/tls internal {/' "$work/caddy/conf/Caddyfile"
    rm -f "$work/caddy/conf/Caddyfile.bak"
    chmod 755 "$work/caddy" "$work/caddy/conf"
    chmod 644 "$work/caddy/conf/Caddyfile"
    generation_route 1
    docker run --rm --user 0 -v "$work/caddy:/caddy" --entrypoint chown "$image" 65532:65532 /caddy/data /caddy/config

    compose --profile public up -d --wait --wait-timeout 60 caddy-public >/dev/null 2>&1 || { echo '  FAIL: public Caddy did not become healthy' >&2; return 1; }
    for number in 1 2; do
        docker run -d --name "$project-backend-$number" --network "$network" --network-alias "brevity-web-$number" \
            -e "GEN=$number" -v "$work/backend.caddyfile:/etc/backend.caddyfile:ro" "$image" \
            caddy run --config /etc/backend.caddyfile --adapter caddyfile >/dev/null
    done
    docker run -d --name "$project-client" --network "$network" --ip "$client_ip" --entrypoint sleep "$image" 600 >/dev/null
    docker run -d --name "$project-proxy" --network "$network" --ip "$proxy_ip" --entrypoint sleep "$image" 600 >/dev/null
    sleep 2

    echo '== public'
    response=$(https client brevity.test /page -H 'X-Forwarded-For: 198.51.100.1' -H 'X-Forwarded-Proto: http' \
        -H 'X-Forwarded-Host: evil.test' -H 'X-Forwarded-Port: 8443' -H 'X-Forwarded-Prefix: /evil' || true)
    check 'the generation answers' contains "$response" 'gen=1'
    check 'the client IP reaches the application, forged X-Forwarded-For replaced' contains "$response" "xff=$client_ip proto"
    check 'X-Forwarded-Proto is https' contains "$response" 'proto=https'
    check 'X-Forwarded-Host is the requested host' contains "$response" 'host=brevity.test'
    check 'X-Forwarded-Port is 443' contains "$response" 'port=443'
    check 'X-Forwarded-Prefix is removed' contains "$response" 'prefix=[]'
    check 'generation header' contains "$response" 'X-Brevity-Generation: 1'
    check 'HSTS' contains "$response" 'Strict-Transport-Security: max-age=31536000'
    check 'nosniff' contains "$response" 'X-Content-Type-Options: nosniff'
    check 'no HTTP/3 advertised' lacks "$response" 'Alt-Svc'
    check 'every public host is served' contains "$(https client lnk1.test /up || true)" 'HTTP/2 200'
    check 'HTTP/2' contains "$response" 'HTTP/2 200'
    check 'HTTP redirects to HTTPS' contains "$(in_client curl -sS -i --max-time 10 "http://$caddy_ip/x" -H 'Host: brevity.test' || true)" 'Location: https://brevity.test/x'
    check 'an unknown name gets no certificate' sh -c "! docker exec $project-client curl -sS -k --max-time 10 --resolve unknown.test:443:$caddy_ip https://unknown.test/"
    check 'TLS 1.1 is refused' sh -c "! docker exec $project-client curl -sS -k --max-time 10 --tls-max 1.1 --resolve brevity.test:443:$caddy_ip https://brevity.test/up"
    check 'TLS 1.2 is accepted' https client brevity.test /up --tlsv1.2 --tls-max 1.2 -f
    check 'TLS 1.3 is accepted' https client brevity.test /up --tlsv1.3 -f
    check 'the body limit is 20 MB' contains "$(in_client sh -c "head -c 21000000 /dev/zero | curl -sS -k -o /dev/null -w '%{http_code}' --max-time 30 --resolve brevity.test:443:$caddy_ip --data-binary @- https://brevity.test/up" || true)" '413'
    check 'switching generations under load: only 200 and bounded resets' switch_under_load public
    check 'the route is back on generation 1' contains "$(https client brevity.test /up || true)" 'X-Brevity-Generation: 1'
    generation_route 2
    reload public >/dev/null 2>&1 || true
    check 'a switch moves traffic to generation 2' contains "$(https client brevity.test /up || true)" 'X-Brevity-Generation: 2'
    route 'import brevity_maintenance'
    reload public >/dev/null 2>&1 || true
    check 'maintenance answers 503' contains "$(https client brevity.test /up || true)" 'HTTP/2 503'
    generation_route 1

    echo '== behind-host'
    compose --profile public rm -sf caddy-public >/dev/null 2>&1
    compose --profile behind-host up -d --wait --wait-timeout 60 caddy-behind-host >/dev/null 2>&1 || { echo '  FAIL: behind-host Caddy did not become healthy' >&2; return 1; }
    response=$(in_proxy curl -sS -i --max-time 10 "http://$caddy_ip:8080/page" -H 'Host: brevity.test' \
        -H 'X-Forwarded-For: 203.0.113.7' -H 'X-Forwarded-Proto: https' -H 'X-Forwarded-Host: brevity.test' || true)
    check 'the host web server passes the client IP' contains "$response" "xff=203.0.113.7, $proxy_ip proto"
    check 'the host web server passes https' contains "$response" 'proto=https'
    check 'X-Forwarded-Port is 443 behind the host' contains "$response" 'port=443'
    check 'generation header behind the host' contains "$response" 'X-Brevity-Generation: 1'
    response=$(in_client curl -sS -i --max-time 10 "http://$caddy_ip:8080/page" -H 'Host: brevity.test' \
        -H 'X-Forwarded-For: 198.51.100.1' -H 'X-Forwarded-Proto: https' || true)
    check 'another address cannot forge X-Forwarded-For' contains "$response" "xff=$client_ip proto"
    check 'another address cannot forge X-Forwarded-Proto' contains "$response" 'proto=http '
    in_proxy curl -sS -o /dev/null --max-time 10 "http://$caddy_ip:8080/strict" -H 'Host: brevity.test' \
        -H 'X-Forwarded-For: 198.51.100.66, 203.0.113.7' >/dev/null 2>&1 || true
    sleep 1
    logs=$(docker logs "$(compose --profile behind-host ps -q caddy-behind-host)" 2>&1 | grep '"uri":"/strict"' || true)
    check 'a forged left entry from the trusted proxy is not the client IP' contains "$logs" '"client_ip":"203.0.113.7"'
    test -z "$failed"
}

echo 'Caddyfile of the repository:'
suite "$repo/Caddyfile" || { echo 'Chain test failed.' >&2; exit 1; }

# Mutations: each removes one protection; the suite must notice.
mutate() {
    description=$1 expression=$2
    sed "$expression" "$repo/Caddyfile" > "$work/mutant"
    if cmp -s "$repo/Caddyfile" "$work/mutant"; then echo "Mutation did not apply: $description" >&2; exit 1; fi
    echo "Mutation: $description"
    if suite "$work/mutant" 2>/dev/null; then echo "The chain test missed: $description" >&2; exit 1; fi
}
mutate 'no trusted_proxies' '/trusted_proxies static/d'
mutate 'no trusted_proxies_strict' '/trusted_proxies_strict/d'
mutate 'no X-Forwarded-Port override' '/header_up X-Forwarded-Port 443/d'
mutate 'HTTP/3 enabled' 's/protocols h1 h2$/protocols h1 h2 h3/'
echo 'Caddy chain test passed, and each mutation was detected.'
