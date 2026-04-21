# WebSocket Watchdog — Never Break Again

This systemd timer + script runs on the Coolify host every 30 seconds.
It keeps `ws.vacilada.com` routing synchronized with the current websocket container, even after Coolify redeploys change the container name.

## What it solves

Coolify-managed docker-compose apps get new container names (hash suffix) on every deploy. The Traefik dynamic rule at `/data/coolify/proxy/dynamic/ws-vacilada.yml` hardcodes the container name, so every redeploy breaks `ws.vacilada.com` with a 502 error — which kills in-progress calls and prevents new calls from connecting.

## How it works

1. `vacilada-ws-watchdog.timer` fires every 30 seconds
2. Runs `vacilada-ws-watchdog.sh`
3. Script:
   - Finds the current `websocket-xgva…` container
   - Checks `https://ws.vacilada.com/health` (current status)
   - If rule file is outdated OR endpoint returns non-200, rewrites the Traefik rule files from scratch
   - Traefik picks up the change within ~5 seconds
4. Worst-case downtime after a Coolify redeploy: **30 seconds**

## Files on the server

| File | Purpose |
|---|---|
| `/usr/local/bin/vacilada-ws-watchdog.sh` | The watchdog script |
| `/etc/systemd/system/vacilada-ws-watchdog.service` | systemd unit |
| `/etc/systemd/system/vacilada-ws-watchdog.timer` | systemd timer (30s interval) |
| `/var/log/vacilada-watchdog.log` | Debug log |
| `/data/coolify/proxy/dynamic/ws-vacilada.yml` | Traefik dynamic rule (auto-rewritten) |
| `/data/coolify/proxy/dynamic/ws-echjokes.yml` | Legacy rule (also auto-rewritten) |

## If the host ever rebuilds

To reinstall the watchdog on a fresh host:

```bash
ssh coolify
# 1. Copy ws-watchdog.sh from the repo (see below) to /usr/local/bin/vacilada-ws-watchdog.sh
# 2. chmod +x /usr/local/bin/vacilada-ws-watchdog.sh
# 3. Create the systemd unit + timer (content below)
# 4. systemctl daemon-reload
# 5. systemctl enable --now vacilada-ws-watchdog.timer
```

## Verifying it's running

```bash
ssh coolify "systemctl status vacilada-ws-watchdog.timer --no-pager"
ssh coolify "tail -20 /var/log/vacilada-watchdog.log"
```

## Script (canonical copy)

See `scripts/ws-watchdog.sh` in this repo.

## systemd unit files (canonical copy)

See `scripts/vacilada-ws-watchdog.service` and `scripts/vacilada-ws-watchdog.timer` in this repo.
