#!/usr/bin/env bash
# Phase K (IMPLEMENTATION_PLAN B18) — nginx access-log redaction for credential-bearing URLs.
#
# The default `main` log_format writes the full "$request" and "$http_referer", so one-time / bearer tokens
# carried in the URL path end up in /var/log/nginx/access.log*:
#   /invite/{token}, /v1/invites/{token}[/accept|/decline]   (B6/B16 invitation links)
#   /v/{token}, /v1/visit/{token}[/open|/command/{id}]        (visitor links — open the gate!)
#   /v1/payments/fake-checkout/{ref}[/{action}]?expires=&signature=   (signed fake-checkout links)
# This adds `main_redacted` (same fields, token segments → <redacted>) and switches every server block in
# conf.d/salam.conf to it. Idempotent; backs up salam.conf; rolls back automatically if `nginx -t` fails.
# Affects NEW requests only — existing access logs are deliberately left as they are (decision 2026-10-02).
#
#   sudo bash phaseK_log_redaction.sh        # apply + reload + verify with one synthetic request
#
# Run ONLY with an explicit GO (production infra change). Does not touch the app, its .env or old logs.
set -euo pipefail

NGX=/etc/nginx
SITE=$NGX/conf.d/salam.conf
REDACT=$NGX/conf.d/00-salam-log-redact.conf
LOGDIR=/var/log/nginx
STAMP=$(date +%Y%m%d%H%M%S)

[ "$(id -u)" -eq 0 ] || { echo "run as root (sudo)"; exit 1; }
[ -f "$SITE" ] || { echo "missing $SITE"; exit 1; }

echo "===== PHASE K: nginx access-log redaction ====="
cp -a "$SITE" "$SITE.bak-$STAMP"
[ -f "$REDACT" ] && cp -a "$REDACT" "$REDACT.bak-$STAMP"

cat > "$REDACT" <<'EOF'
# B18 — token-free access log (see deploy/phaseK_log_redaction.sh). First matching regex wins.
map $request $salam_request_redacted {
    default $request;
    "~^(?<rqa1>\S+)\s(?<rqb1>/invite/)[^/?\s]+(?<rqc1>.*)$"                              "$rqa1 $rqb1<redacted>$rqc1";
    "~^(?<rqa2>\S+)\s(?<rqb2>/v1/invites/)[^/?\s]+(?<rqc2>.*)$"                          "$rqa2 $rqb2<redacted>$rqc2";
    "~^(?<rqa3>\S+)\s(?<rqb3>/v1/visit/)[^/?\s]+(?<rqc3>.*)$"                            "$rqa3 $rqb3<redacted>$rqc3";
    "~^(?<rqa4>\S+)\s(?<rqb4>/v/)[^/?\s]+(?<rqc4>.*)$"                                   "$rqa4 $rqb4<redacted>$rqc4";
    "~^(?<rqa5>\S+)\s(?<rqb5>/v1/payments/fake-checkout/[^?\s]+)\?\S*(?<rqc5>\s.*)$"     "$rqa5 $rqb5?<redacted>$rqc5";
}
map $http_referer $salam_referer_redacted {
    default $http_referer;
    "~^(?<rfa1>https?://[^/]+/(invite|v)/)[^/?#]+(?<rfc1>.*)$"                           "$rfa1<redacted>$rfc1";
    "~^(?<rfa2>https?://[^/]+/v1/payments/fake-checkout/[^?#]+)\?.*$"                    "$rfa2?<redacted>";
}
log_format main_redacted '$remote_addr - $remote_user [$time_local] "$salam_request_redacted" '
                         '$status $body_bytes_sent "$salam_referer_redacted" "$http_user_agent"';
EOF

# Server-level access_log overrides the http-level `access_log ... main;` (location-level `off` stays).
if ! grep -q 'main_redacted' "$SITE"; then
  sed -i -E 's|^([[:space:]]*)(server_name[[:space:]][^;]*;)|\1\2\n\1access_log /var/log/nginx/access.log main_redacted;|' "$SITE"
fi
echo "server blocks: $(grep -c '^[[:space:]]*server_name' "$SITE"), redacted access_log lines: $(grep -c 'main_redacted' "$SITE")"

if nginx -t 2>&1; then
  systemctl reload nginx
  echo "nginx reloaded with main_redacted"
else
  echo "nginx -t FAILED — rolling back"
  cp -a "$SITE.bak-$STAMP" "$SITE"
  if [ -f "$REDACT.bak-$STAMP" ]; then cp -a "$REDACT.bak-$STAMP" "$REDACT"; else rm -f "$REDACT"; fi
  nginx -t && systemctl reload nginx
  exit 1
fi

# --- verification on ONE synthetic request (old log lines are intentionally not inspected / not touched) ---
PROBE="PHASEKSELFTEST$(date +%s)abcdefghijklmnop"
curl -sk -o /dev/null -H 'Host: api.salamheyetimiz.com' "https://127.0.0.1/v1/invites/$PROBE" || true
sleep 1
if tail -n 200 "$LOGDIR/access.log" | grep -q "$PROBE"; then
  echo "VERIFY FAILED: probe token visible in access.log"; exit 1
fi
tail -n 200 "$LOGDIR/access.log" | grep -q '/v1/invites/<redacted>'   && echo "VERIFY OK: new requests are logged as /v1/invites/<redacted>"   || { echo "VERIFY: probe line not found (check access_log path)"; exit 1; }
