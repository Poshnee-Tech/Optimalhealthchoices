// Shared helper for the two form endpoints (Vercel serverless, Node runtime).
// Parses a urlencoded POST body, validates it, forwards it as JSON to a webhook, then redirects.
// No dependencies.

function readBody(req) {
  // The Vercel Node runtime may already have consumed and parsed the stream.
  // Attaching 'data'/'end' listeners to an exhausted stream never resolves, so check req.body first.
  if (req.body !== undefined && req.body !== null) {
    if (typeof req.body === 'string') return Promise.resolve(Object.fromEntries(new URLSearchParams(req.body)));
    if (Buffer.isBuffer(req.body)) return Promise.resolve(Object.fromEntries(new URLSearchParams(req.body.toString('utf8'))));
    if (typeof req.body === 'object') return Promise.resolve(req.body);
  }
  return new Promise((resolve) => {
    let raw = '';
    let done = false;
    const finish = () => { if (!done) { done = true; resolve(Object.fromEntries(new URLSearchParams(raw))); } };
    req.on('data', (c) => {
      raw += c;
      if (raw.length > 64 * 1024) { raw = raw.slice(0, 64 * 1024); finish(); req.destroy(); }
    });
    req.on('end', finish);
    req.on('error', finish);
    req.on('close', finish);
    setTimeout(finish, 8000).unref?.();
  });
}

function digits(v) {
  const d = String(v || '').replace(/\D/g, '');
  return d.length === 11 && d[0] === '1' ? d.slice(1) : d;
}

// A lead is only usable if the visitor actually gave consent. Anything else is
// a forged or broken POST and must never reach a dialer.
function rejectReason(kind, f) {
  if (kind !== 'lead') return null;
  if (f.consent_contact !== 'on') return 'missing consent_contact';
  if (f.terms_ack !== 'on') return 'missing terms_ack';
  if (digits(f.phone).length !== 10) return 'invalid phone';
  if (!String(f.first_name || '').trim() || !String(f.last_name || '').trim()) return 'missing name';
  return null;
}

function errorPage() {
  return `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Submission not accepted</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#FBF9F8;color:#1E1520;
font:17px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;padding:2rem}
main{max-width:34rem}h1{font-size:1.6rem;line-height:1.2;margin:0 0 .75rem}
a{display:inline-block;margin-top:1.5rem;background:#2E1B33;color:#fff;text-decoration:none;
font-weight:700;padding:.85rem 1.5rem;border-radius:999px}</style></head><body><main>
<h1>We couldn't accept that submission</h1>
<p>Your request is missing something we need &mdash; usually the two consent checkboxes, or a complete
10-digit phone number. Nothing has been sent and no one will contact you.</p>
<p>Please go back and complete the form.</p>
<a href="/">Return to the form</a></main></body></html>`;
}

module.exports = async function forward(req, res, { webhookEnv, redirectTo, kind }) {
  if (req.method !== 'POST') { res.statusCode = 405; res.setHeader('Allow', 'POST'); return res.end('Method Not Allowed'); }

  const fields = await readBody(req);

  const reason = rejectReason(kind, fields);
  if (reason) {
    console.warn(`${kind}: rejected — ${reason}`);
    res.statusCode = 400;
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    return res.end(errorPage());
  }

  // The honeypot FLAGS rather than blocks: a password manager or browser autofill can
  // fill a hidden field, and turning a real customer away is worse than passing a bot
  // through for your CRM to quarantine.
  const suspected = Boolean(fields.ohc_leave_empty);
  delete fields.ohc_leave_empty;
  if (suspected) { fields.spam_suspected = 'true'; console.warn(`${kind}: honeypot filled — forwarded but flagged`); }

  const payload = {
    kind,
    received_at: new Date().toISOString(),
    ip: String(req.headers['x-forwarded-for'] || '').split(',')[0].trim() || req.socket?.remoteAddress || '',
    user_agent: req.headers['user-agent'] || '',
    referer: req.headers['referer'] || '',
    fields,
  };

  const url = process.env[webhookEnv];
  if (url) {
    try {
      const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      if (!r.ok) console.error(`${kind}: webhook responded ${r.status}`);
    } catch (e) {
      console.error(`${kind}: webhook error`, e);
    }
  } else {
    console.warn(`${kind}: ${webhookEnv} is not set — submission accepted but not forwarded.`);
  }

  res.statusCode = 303;
  res.setHeader('Location', redirectTo);
  res.end();
};
