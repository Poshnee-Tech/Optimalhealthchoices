// POST /submit  →  forwards the lead to LEAD_WEBHOOK_URL, then redirects to /thank-you
const forward = require('./_forward');
module.exports = (req, res) => forward(req, res, { webhookEnv: 'LEAD_WEBHOOK_URL', redirectTo: '/thank-you', kind: 'lead' });
