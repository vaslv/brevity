#!/bin/sh
# Runs inside nginx-proxy. Preserve the image's template and add readiness /
# draining filters without replacing its TLS, ACME or virtual-host configuration.
set -eu
draining=${1:-}
case "$draining" in *[!a-f0-9]*) echo 'Invalid draining container ID' >&2; exit 1;; esac
template=/app/nginx.tmpl
baseline=/app/nginx.tmpl.brevity-original
test -f "$baseline" || cp "$template" "$baseline"
grep -F 'range $container := $containers }}' "$baseline" >/dev/null || {
    echo 'Unsupported nginx-proxy template; keeping the existing web version.' >&2
    exit 1
}
cp "$template" "$template.previous"
awk -v draining="$draining" '
    /range \$container := \$containers }}/ {
        print
        print "{{- if or (eq $container.State.Health.Status \"starting\") (eq $container.State.Health.Status \"unhealthy\") }}{{ continue }}{{ end }}"
        if (draining != "") {
            print "{{- if eq $container.ID \"" draining "\" }}{{ continue }}{{ end }}"
        }
        next
    }
    { print }
' "$baseline" > "$template.next"
mv "$template.next" "$template"
if docker-gen -only-exposed "$template" /tmp/brevity-nginx.conf &&
    cp /etc/nginx/conf.d/default.conf /tmp/brevity-nginx.previous &&
    cp /tmp/brevity-nginx.conf /etc/nginx/conf.d/default.conf &&
    nginx -t && nginx -s reload; then
    exit 0
fi
mv "$template.previous" "$template"
if test -f /tmp/brevity-nginx.previous; then
    cp /tmp/brevity-nginx.previous /etc/nginx/conf.d/default.conf
    nginx -s reload
fi
exit 1
