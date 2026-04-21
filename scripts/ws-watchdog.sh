#!/bin/bash
# Watchdog: keep ws.vacilada.com Traefik rule in sync with current WS container

RULE_FILE=/data/coolify/proxy/dynamic/ws-vacilada.yml
LEGACY_FILE=/data/coolify/proxy/dynamic/ws-echjokes.yml
LOG=/var/log/vacilada-watchdog.log

WS_NAME=$(docker ps --filter 'name=websocket-xgva' --format '{{.Names}}' | head -1)
if [ -z "$WS_NAME" ]; then
  echo "[$(date)] WS container not running" >> $LOG
  exit 1
fi

TARGET="http://$WS_NAME:8081"
CURRENT=$(grep -oE 'http://[^"]+' $RULE_FILE 2>/dev/null | head -1)

# Also check: is ws.vacilada.com actually up?
STATUS=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 5 https://ws.vacilada.com/health 2>/dev/null)

if [ "$CURRENT" = "$TARGET" ] && [ "$STATUS" = "200" ]; then
  exit 0
fi

echo "[$(date)] Refreshing: current=$CURRENT target=$TARGET status=$STATUS" >> $LOG

# Rewrite BOTH rule files from scratch
cat > $RULE_FILE <<INNER
http:
  routers:
    ws-vacilada-override:
      rule: "Host(\`ws.vacilada.com\`)"
      entryPoints:
        - https
      service: ws-vacilada-svc
      priority: 1000
      tls:
        certResolver: letsencrypt
  services:
    ws-vacilada-svc:
      loadBalancer:
        servers:
          - url: "$TARGET"
INNER

cat > $LEGACY_FILE <<INNER
http:
  routers:
    ws-echjokes-override:
      rule: "Host(\`ws.echjokes.overcloud.us\`)"
      entryPoints:
        - https
      service: ws-echjokes-svc
      priority: 1000
      tls:
        certResolver: letsencrypt
  services:
    ws-echjokes-svc:
      loadBalancer:
        servers:
          - url: "$TARGET"
INNER

echo "[$(date)] Wrote new rules pointing to $WS_NAME" >> $LOG
