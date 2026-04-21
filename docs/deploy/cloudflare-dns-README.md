# Cloudflare DNS setup for vacilada.com

Import `cloudflare-dns.csv` via Cloudflare dashboard → DNS → Import.

## Record breakdown

| Record | Purpose | Proxied? |
|---|---|---|
| `A @ → 93.127.142.243` | Main site (app) | ✅ Yes (orange cloud) |
| `A www → 93.127.142.243` | www redirect | ✅ Yes |
| `A ws → 93.127.142.243` | Websocket server (port 8443) | ❌ NO — CF proxy blocks custom ports |
| `CAA` | Allow Let's Encrypt + Cloudflare to issue SSL certs | ❌ |
| `TXT SPF` | Email sender policy (Google Workspace by default — change if different) | ❌ |
| `TXT DMARC` | Email authentication policy | ❌ |
| `MX` | Cloudflare Email Routing (free email forwarding) | ❌ |

## After importing

### 1. Verify HTTPS works on app
Visit `https://vacilada.com` — should show your app over Cloudflare SSL.

### 2. Websocket — important
`ws.vacilada.com` is **unproxied** (grey cloud) so port 8443 passes through raw to the server. Cloudflare free plan doesn't proxy arbitrary ports.

### 3. Email setup
Two options:

**Option A — Cloudflare Email Routing (free, forwarding only):**
1. Cloudflare dashboard → Email → Email Routing → Enable
2. Add forwarding: `hola@vacilada.com` → your personal email
3. CF auto-adds the correct MX records (you can delete my placeholder ones)

**Option B — Google Workspace ($6/user/month):**
1. Buy Google Workspace for vacilada.com
2. Replace MX records with:
   ```
   MX @ ASPMX.L.GOOGLE.COM 1
   MX @ ALT1.ASPMX.L.GOOGLE.COM 5
   MX @ ALT2.ASPMX.L.GOOGLE.COM 5
   MX @ ALT3.ASPMX.L.GOOGLE.COM 10
   MX @ ALT4.ASPMX.L.GOOGLE.COM 10
   ```
3. Update SPF: `v=spf1 include:_spf.google.com ~all` (already set)
4. Add DKIM from Google Admin console (generate + paste TXT).

### 4. SSL settings (Cloudflare dashboard)
- SSL/TLS mode: **Full (strict)** once Coolify renews the cert for vacilada.com
- Always Use HTTPS: **ON**
- Automatic HTTPS Rewrites: **ON**
- Min TLS Version: **1.2**

### 5. After DNS propagates (5-30 min)
Tell me, and I'll:
1. Add `vacilada.com` + `ws.vacilada.com` as custom domains in Coolify
2. Update `APP_URL=https://vacilada.com` in Coolify env
3. Update all Twilio phone numbers' voice webhooks to point at `https://vacilada.com/inbound`
4. Set up 301 redirect from old `echjokes.overcloud.us` → `vacilada.com`
