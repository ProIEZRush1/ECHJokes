# Email setup for vacilada.com

Goal: send transactional email *from* `hello@vacilada.com` (welcome, magic-link,
receipts, referral notifications) and *receive* email at `hello@vacilada.com`
(forwarded to your Gmail). Zero servers to maintain.

## Architecture

```
OUTBOUND:   Laravel  →  Resend SMTP (smtp.resend.com:587)  →  recipient
INBOUND:    sender  →  Cloudflare MX  →  Email Routing  →  your Gmail
```

Resend handles outbound (free 3k emails/mo). Cloudflare Email Routing handles
inbound and is completely free. Both only need DNS records — nothing to run.

## One-time setup

### 1. Sign up for Resend
1. Go to https://resend.com, create account.
2. Domains → Add Domain → `vacilada.com`.
3. Resend shows 2–3 DKIM CNAME records. **Copy them** — you'll paste into
   Cloudflare next.
4. API Keys → Create API Key → label `vacilada-production` → **copy the key**.

### 2. Add DNS records in Cloudflare

The file `docs/deploy/vacilada.com.zone` has the template. Paste the Resend
DKIM CNAMEs in the `resend._domainkey` slot. Full record list to add:

| Type  | Name                    | Content                                | Proxy |
|-------|-------------------------|----------------------------------------|-------|
| MX    | @                       | route1.mx.cloudflare.net (priority 10) | —     |
| MX    | @                       | route2.mx.cloudflare.net (priority 20) | —     |
| MX    | @                       | route3.mx.cloudflare.net (priority 30) | —     |
| TXT   | @                       | `v=spf1 include:_spf.mx.cloudflare.net include:amazonses.com ~all` | — |
| TXT   | _dmarc                  | `v=DMARC1; p=quarantine; rua=mailto:dmarc@vacilada.com; ruf=mailto:dmarc@vacilada.com; fo=1; adkim=r; aspf=r` | — |
| CNAME | resend._domainkey       | (from Resend dashboard)                | DNS only |
| CNAME | resend2._domainkey      | (from Resend dashboard, if shown)      | DNS only |

**Important**: DKIM CNAMEs must be set to "DNS only" (grey cloud), not proxied,
or the CNAME lookup won't resolve correctly.

### 3. Enable Cloudflare Email Routing (inbound)
1. Cloudflare dashboard → vacilada.com → **Email** → **Email Routing**.
2. Click **Get started**.
3. Add a "catch-all" or specific addresses:
   - `hello@vacilada.com` → your-gmail@gmail.com
   - `soporte@vacilada.com` → your-gmail@gmail.com
   - `privacidad@vacilada.com` → your-gmail@gmail.com
   - `dmarc@vacilada.com` → your-gmail@gmail.com
4. Cloudflare auto-adds the MX + SPF TXT records if they're missing.

### 4. Wire Laravel to Resend SMTP

Add to production `.env` (Coolify environment settings):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_USERNAME=resend
MAIL_PASSWORD=re_xxx_your_resend_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@vacilada.com
MAIL_FROM_NAME="Vacilada"
```

Clear config cache after:
```
docker exec <app-container> php artisan config:clear
```

### 5. Verify it works

Inside the app container:
```bash
php artisan tinker
>>> Mail::raw('Prueba desde Vacilada', fn($m) => $m->to('your-email@gmail.com')->subject('Ping'));
```

You should receive the email within a few seconds. Check Resend dashboard →
Emails for the send log.

## Sending from Gmail as hello@vacilada.com (optional)

Once outbound works, you can reply from Gmail with `hello@vacilada.com` as
the "from":

1. Gmail → Settings → Accounts → **Add another email address**.
2. Use Resend's SMTP details: `smtp.resend.com`, port 587, username `resend`,
   password = your Resend API key.
3. Gmail sends a verification code to hello@vacilada.com (Cloudflare forwards
   it back to you). Paste, done.

## Alternatives if Resend isn't a fit

- **Postmark** — better transactional tracking, ~Generate.25/1k after free tier.
- **Mailgun / Mailchannels / Brevo** — similar SMTP relays.
- **Self-hosted Postal / Maddy / Postfix+OpenDKIM** — full mail server but:
  - Needs a dedicated IP (residential IPs are blocked by every major provider).
  - Needs reverse DNS, PTR records, IP warming, bounce handling.
  - Expect 2–3 weeks of reputation warm-up before Gmail stops spam-foldering you.
  - Recommendation: only self-host if you need full control and can afford 10+
    hours of ops work per incident.

For Vacilada's volume (a few thousand emails/month) Resend is the right choice.

## Troubleshooting

- **Resend dashboard shows "Domain pending verification"**: Cloudflare sometimes
  proxies CNAMEs by default. Switch the DKIM CNAMEs to "DNS only" (grey cloud).
- **Delivered but goes to spam**: add BIMI later, keep DMARC at p=quarantine or
  p=reject, and include only authorized senders in SPF.
- **Inbound email not arriving**: Cloudflare Email Routing needs ~10 min after
  enabling; also confirm MX records have no competing entries (e.g., an old
  Google Workspace MX).
