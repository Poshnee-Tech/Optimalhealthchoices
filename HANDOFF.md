# Handoff — OptimalHealthChoices.com

Six static pages, no build step and no dependencies. Each page is self-contained: open `index.html` and it runs. **See `DEPLOY.md` for Vercel / Hostinger deployment and `placeholders.example.json` + `scripts/fill.py` for filling in the placeholders below in one go.**

```
index.html         home + 3-step lead form + consent + TrustedForm
thank-you.html     post-submit confirmation (form endpoints redirect here)
privacy.html       privacy policy
terms.html         terms & conditions
do-not-sell.html   opt-out / access / correct / delete request form
404.html           not-found page (wired up in vercel.json and .htaccess)

api/               Vercel serverless form endpoints  (/submit, /privacy-request)
submit.php …       PHP equivalents for Hostinger     (same paths via .htaccess)
scripts/dev_server.py  local test server — serves the pages AND the form endpoints (see DEPLOY.md)
scripts/check.py       pre-deploy check: shared blocks identical, links resolve, unfilled placeholders
scripts/fill.py        replaces every placeholder from placeholders.json
```

## Wire up before launch

1. Replace the TrustedForm placeholder script at the foot of every page with the exact snippet from your ActiveProspect dashboard.
2. `#leadForm` posts to `/submit` and `#dnsForm` posts to `/privacy-request`. Both endpoints exist for Vercel (`api/`) and PHP hosts (`*.php`) — set the webhook URL they forward to (see `DEPLOY.md`).
3. Confirm the redirect lands on `/thank-you` (lead) and `/do-not-sell?sent=1` (privacy request).
4. Server-side: claim the TrustedForm certificate on receipt, store the `consent_text_rendered` field with the lead, and quarantine any lead arriving with `cert_status=missing`.
5. Run DNC, state DNC, Blacklist Alliance, and TCPA Litigator's List scrubs before first dial.

## Placeholders

**Already populated** (via `placeholders.json` + `scripts/fill.py`):
`[TEL]` = (833) 690-5085 · `[DAYS]` = Mon–Fri · `[HOURS]` = 9:00 AM–9:00 PM · `[TIME ZONE]` = ET.
Every `tel:` link is written as `tel:+18336905085` so it dials correctly from a phone, while the
visible text stays formatted.

**Still to populate** — add them to `placeholders.json` and re-run `python3 scripts/fill.py --write`:

| Placeholder | Appears on |
|---|---|
| `[COUNTY, STATE]` | terms.html |
| `[DATE]` | do-not-sell.html, privacy.html, terms.html |
| `[EMAIL]` | every page |
| `[FULL STREET ADDRESS — physical, not a PO box]` | every page |
| `[HMO, PPO, PFFS — list only the types actually represented]` | do-not-sell.html, index.html, privacy.html, terms.html, thank-you.html |
| `[NAMED AGENCY]` | index.html, thank-you.html |
| `[NAMED AGENCY — LEGAL ENTITY NAME]` | index.html (consent text) |
| `[SMID / MULTI-PLAN_MATERIAL_ID — assigned by IFG upon approval]` | do-not-sell.html, index.html, privacy.html, terms.html, thank-you.html |
| `[STATE]` | terms.html |

Leave `[SMID / MULTI-PLAN_MATERIAL_ID]` visibly reserved in the submission draft. Populate on assignment. Do not go live without it and do not reuse one from another material or plan year.

## Verified programmatically

- No horizontal overflow at 320 / 360 / 390 / 768 / 1024 / 1280 / 1440px on any page.
- Every required disclosure renders at 16px minimum (12pt) at body contrast, expanded on load, identical on mobile.
- All three form steps and the full consent text render with JavaScript disabled.
- Consent checkboxes unchecked by default; submit is blocked until both are checked.
- One `h1` per page, no skipped heading levels, no unlabeled form controls, all tap targets 44px or larger.
- Header, microbar, footer, and design tokens byte-identical across all six pages (`scripts/check.py` enforces this).
- Mobile navigation menu (≤1040px) works with keyboard and closes on Escape / outside click; without JavaScript the links simply render inline.
- No broken internal links; every nav anchor (#how, #coverage, #periods, #faq, #form) resolves.
- Form endpoints reject a POST that lacks both consent checkboxes, a valid 10-digit phone, or a name,
  and reject anything that fills the hidden honeypot field. Client-side validation is not the only gate.
- Phone input drops a leading US country code before formatting, so a pasted or autofilled
  "+1 555 555 0123" cannot be truncated into a different, dialable number.
- Enter on steps 1-2 advances the form rather than failing silently against the hidden consent step.
- Consent text no longer references an email address the form does not collect.
- The spam honeypot FLAGS rather than blocks: browser autofill and password managers can fill hidden
  fields, and turning away a real customer is worse than forwarding a bot. A filled trap arrives as
  `spam_suspected: "true"` in the payload for your CRM to quarantine.
- Focus ring (5.8:1), field borders (3.7:1), and placeholders (4.7:1) meet WCAG AA non-text contrast.
- Type scale is in rem, so a visitor who raises their browser font size scales the whole page.

## Needs review, not shipped as final

The privacy policy, terms, and opt-out page are drafted to industry practice and to the retention and consent rules in your copy deck. They are drafting help, not legal advice. Before launch:

- Your compliance officer against IFG's own TPMO addendum, which may be stricter than federal minimums.
- TCPA counsel on the consent architecture specifically, and on `[STATE]` / `[COUNTY, STATE]` in the terms governing-law section.
- The terms now contain an **arbitration clause and class-action waiver** (`terms.html#arbitration`), added at your
  request, with a conspicuous lead-in and a cross-reference from the privacy policy. Enforceability turns on
  drafting details (provider, costs, opt-out window, severability) — have counsel review this specific wording
  before launch.