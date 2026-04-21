# Cloudflare DNS setup for vacilada.com

## How to import

Cloudflare's "Import DNS" expects a **BIND zone file** (not CSV).

1. Cloudflare dashboard → Select `vacilada.com` → DNS → Records
2. Click **"Import and Export"** → **"Import DNS records"**
3. Upload **`vacilada.com.zone`** (not the CSV)
4. Confirm the preview, import.

## ⚠️ After import — toggle "Proxied" manually

The zone file format can't specify Cloudflare proxy status. After import, verify in the DNS tab:

| Record | Name | Proxied (orange cloud)? |
|---|---|---|
| A | `@` (vacilada.com) | ✅ **ON** |
| A | `www` | ✅ **ON** |
| A | `ws` | ❌ **OFF** (grey cloud — websocket on port 8443 needs raw passthrough) |
| MX | `@` | ❌ OFF (MX can't be proxied) |
| TXT | all | ❌ OFF (TXT can't be proxied) |
| CAA | all | ❌ OFF (CAA can't be proxied) |

## Record breakdown

| Record | Purpose |
|---|---|
| `A @ → 93.127.142.243` | Main site — proxied through Cloudflare SSL |
| `A www → 93.127.142.243` | www → same server |
| `A ws → 93.127.142.243` | Websocket server (port 8443, direct) |
| `CAA` | Allow Let's Encrypt + Cloudflare to issue SSL certs |
| `MX` | Cloudflare Email Routing (default placeholder) |
| `TXT SPF` | Email sender policy |
| `TXT DMARC` | Email authentication |

## Email setup after import

**Option A — Cloudflare Email Routing (free forwarding):**
1. Cloudflare → Email → Email Routing → Enable
2. Add forward: `hola@vacilada.com` → your personal email
3. CF validates the MX records automatically

**Option B — Google Workspace (~$6 USD/user/month):**
Delete the CF `MX` records and add:
```
MX @ 1  ASPMX.L.GOOGLE.COM
MX @ 5  ALT1.ASPMX.L.GOOGLE.COM
MX @ 5  ALT2.ASPMX.L.GOOGLE.COM
MX @ 10 ALT3.ASPMX.L.GOOGLE.COM
MX @ 10 ALT4.ASPMX.L.GOOGLE.COM
```
Update SPF: `v=spf1 include:_spf.google.com ~all`
Add DKIM from Google Admin console.

## Cloudflare SSL settings

After import:
1. SSL/TLS → Overview → **Full (strict)** (once Coolify renews cert for vacilada.com)
2. SSL/TLS → Edge Certificates → **Always Use HTTPS: ON**
3. SSL/TLS → Edge Certificates → **Automatic HTTPS Rewrites: ON**
4. SSL/TLS → Edge Certificates → **Minimum TLS: 1.2**

## After DNS propagates (5–30 min)

Tell me, and I'll do the prod switch:
1. Add `vacilada.com` + `ws.vacilada.com` as custom domains in Coolify (via API/SSH)
2. Update `APP_URL=https://vacilada.com` in Coolify env
3. Update all Twilio phone numbers' voice webhooks: `echjokes.overcloud.us` → `vacilada.com`
4. Update websocket URLs in code: `ws.echjokes.overcloud.us` → `ws.vacilada.com`
5. Set up a 301 redirect from `echjokes.overcloud.us` → `vacilada.com`
