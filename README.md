# OptimalHealthChoices.com

Static Medicare lead-generation site for **Poshnee Tech LLC**. Six self-contained HTML pages, no build
step and no dependencies — open `index.html` and it runs.

```
index.html         home + 3-step lead form + TCPA consent + TrustedForm
thank-you.html     post-submit confirmation (form endpoints redirect here)
privacy.html       privacy policy
terms.html         terms & conditions (incl. arbitration clause)
do-not-sell.html   opt-out / access / correct / delete request form
404.html           not-found page
```

## Run it locally

```bash
python3 scripts/dev_server.py        # http://localhost:8080
```

Use this rather than `python3 -m http.server`: a plain static server answers a form POST with
`501 Unsupported method`, so the form appears to do nothing. The dev server also handles `/submit`
and `/privacy-request`, serves clean URLs, and prints the exact JSON your webhook will receive.

## Deploying

Both targets are pre-configured — see **[DEPLOY.md](DEPLOY.md)** for step-by-step instructions.

| Target | Config | Form endpoints |
|---|---|---|
| **Vercel** | `vercel.json` — clean URLs, security headers, rewrites | `api/submit.js`, `api/privacy-request.js` (Node) |
| **Hostinger / Apache** | `.htaccess` — HTTPS, clean URLs, headers, 404 | `submit.php`, `privacy-request.php` |

Set `LEAD_WEBHOOK_URL` / `PRIVACY_WEBHOOK_URL` (Vercel env vars) or copy `config.example.php` to
`config.php` (PHP hosts). Submissions are forwarded as JSON, then the visitor is redirected.

## Before launch

```bash
cp placeholders.example.json placeholders.json   # fill in the remaining values
python3 scripts/fill.py --write
python3 scripts/check.py                         # shared blocks, links, unfilled placeholders
```

Still to populate: the number of organizations/plans represented, the plan types (HMO/PPO/PFFS), and the
**SMID / MULTI-PLAN_MATERIAL_ID assigned by IFG** — do not go live without it, and never reuse one from
another material or plan year. Also replace the TrustedForm placeholder script on every page with the
snippet from your ActiveProspect dashboard.

See **[HANDOFF.md](HANDOFF.md)** for the full compliance notes and what has been verified.

## Editing

Every page is plain HTML. The header, footer, utility bar, and `<style>` block are **identical on every
page** by design — change one, change all six, then run `scripts/check.py`, which fails if they drift.
Design tokens (colors, type, spacing) live in the `:root{}` block at the top of the `<style>`.
