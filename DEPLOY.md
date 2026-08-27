# Deploying OptimalHealthChoices.com

Six static HTML pages (`index`, `thank-you`, `privacy`, `terms`, `do-not-sell`, `404`). No build step. Every page is self-contained — CSS is inlined, the only external requests are Google Fonts and TrustedForm. Open `index.html` in a browser and it runs.

## Test it locally first

A plain static file server cannot handle a form POST — it answers `501 Unsupported method`, which is why
submitting the form looks like it does nothing. Use the included dev server instead; it serves the pages
**and** handles both form endpoints exactly the way Vercel and Hostinger do:

```
python3 scripts/dev_server.py        # → http://localhost:8080
```

Submit the form and you will land on the thank-you page; the terminal prints the exact JSON your webhook
will receive. It also serves clean URLs (`/privacy` as well as `/privacy.html`) and the real 404 page.

Worth testing before you ship:

| Test | Expected |
|---|---|
| Complete the form and submit | lands on `/thank-you`, terminal shows `✓ lead ACCEPTED` with the full payload |
| Submit without ticking both consent boxes | blocked in the browser; a forged POST is rejected `400` |
| Type `+1 555 555 0123` in the phone field | reformats to `(555) 555-0123` — the leading 1 is dropped, not the last digit |
| Press Enter on step 1 | advances to step 2 (does not silently fail) |
| Disable JavaScript and reload | all three steps and the full consent text render; progress bar and step buttons disappear |
| Submit the do-not-sell form | lands on `/do-not-sell?sent=1` with a confirmation note |
| Submit with your browser's autofill active | still accepted — the honeypot flags, it does not block |
| Visit `/nope` | the styled 404 page |
| Resize to 320px wide | no horizontal scrolling on any page |

## Before you deploy (both hosts)

1. **Fill the placeholders.** Copy `placeholders.example.json` → `placeholders.json`, fill every value, then:
   ```
   python3 scripts/fill.py          # dry run
   python3 scripts/fill.py --write  # apply
   ```
   `scripts/check.py` lists anything still unfilled and confirms the shared header/footer/CSS are identical on every page.
2. **TrustedForm.** Replace the placeholder snippet at the foot of `index.html`, `thank-you.html`, `privacy.html`, `terms.html`, and `do-not-sell.html` with the exact snippet from your ActiveProspect dashboard.
3. **Where do leads go?** Both form endpoints forward the submission as JSON to a webhook URL you configure (CRM, LeadConduit, Zapier/Make, etc.), then redirect the visitor. Payload shape:
   ```json
   { "kind": "lead", "received_at": "…", "ip": "…", "user_agent": "…", "referer": "…", "fields": { "first_name": "…", "phone": "…", "xxTrustedFormCertUrl": "…", "consent_text_rendered": "…", … } }
   ```
   Leads arriving with `fields.cert_status = "missing"` had no TrustedForm certificate — quarantine them.

---

## Option A — Vercel (recommended)

`vercel.json` is already configured: clean URLs (`/privacy` not `/privacy.html`), security headers, and `/submit` + `/privacy-request` routed to the serverless functions in `api/`.

1. Push the folder to a Git repo (GitHub/GitLab/Bitbucket) and import it at vercel.com → **Add New Project**. Framework preset: **Other**. No build command, output directory: leave blank.
   — or, from a terminal: `npx vercel` in this folder.
2. In the project's **Settings → Environment Variables**, add:
   | Variable | Value |
   |---|---|
   | `LEAD_WEBHOOK_URL` | where `#leadForm` submissions are POSTed |
   | `PRIVACY_WEBHOOK_URL` | where `#dnsForm` submissions are POSTed |
   Redeploy after adding them. If a variable is unset, the submission is written to the function log only.
3. Add your domain under **Settings → Domains** and point DNS as Vercel instructs (A `76.76.21.21` or CNAME `cname.vercel-dns.com`). HTTPS is automatic.

Test: submit the form on the live site → you should land on `/thank-you` and see the payload at your webhook.

---

## Option B — Hostinger (or any Apache + PHP host)

`.htaccess` is already configured: forces HTTPS, clean URLs, 404 page, security headers, and `/submit` + `/privacy-request` routed to `submit.php` / `privacy-request.php`.

1. Copy `config.example.php` → `config.php` and fill in `lead_webhook_url`, `privacy_webhook_url`, and optionally `notify_email` (an email copy of every submission).
2. Upload **everything** in this folder to `public_html/` via hPanel → File Manager, or FTP. The `api/` folder and `vercel.json` are harmless on Hostinger; you can leave them.
3. hPanel → **Security → SSL**: make sure the certificate is active (free Let's Encrypt) — `.htaccess` redirects to HTTPS.
4. Confirm PHP 8.x is selected under **Advanced → PHP Configuration** (cURL must be enabled — it is by default).

Test: submit the form on the live site → you should land on `/thank-you` and see the payload at your webhook / inbox.

---

## Editing the site later

- Every page is plain HTML. Edit text directly in the file.
- The header, footer, utility bar, and `<style>` block are **identical on every page** on purpose. If you change one of them, make the same change on all six pages (find-and-replace across files), then run `python3 scripts/check.py` — it fails if they drift.
- Design tokens (colors, type sizes, radii) live in the `:root{…}` block at the top of the `<style>`. Change `--plum` / `--amber` there to re-theme every page.
- The only JavaScript is: the 3-step form (progressive enhancement — all steps render without JS), the mobile menu, and phone-number formatting.

## Compliance notes carried over from the original handoff

- Required disclosures render at 16px minimum, expanded on load, identical on mobile.
- Consent checkboxes are unchecked by default; submit is blocked until both are checked; the rendered consent text is stored with the lead (`consent_text_rendered`).
- Do not go live without the `[SMID / MULTI-PLAN_MATERIAL_ID]` assigned by IFG.
- Privacy policy, terms, and opt-out page are drafting help, not legal advice — have your compliance officer and TCPA counsel review before launch.
