// POST /privacy-request  →  forwards to PRIVACY_WEBHOOK_URL, then redirects back with a confirmation
const forward = require('./_forward');
module.exports = (req, res) => forward(req, res, { webhookEnv: 'PRIVACY_WEBHOOK_URL', redirectTo: '/do-not-sell?sent=1#request', kind: 'privacy_request' });
