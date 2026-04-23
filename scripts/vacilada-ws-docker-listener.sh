#!/bin/bash
# Listen for Docker start events on websocket-xgva containers and fire the
# watchdog immediately so Traefik never points at a dead container during a
# Coolify redeploy. Pair with the 5-second systemd timer for belt-and-
# suspenders: the listener handles the expected case (redeploy), the timer
# handles any edge case where the event is missed.
docker events --filter "type=container" --filter "event=start" --format '{{.Actor.Attributes.name}}' 2>&1 | \
while read -r name; do
  case "$name" in
    websocket-xgva*)
      echo "[$(date)] ws container start: $name → firing watchdog" >> /var/log/vacilada-watchdog.log
      /usr/local/bin/vacilada-ws-watchdog.sh
      ;;
  esac
done
