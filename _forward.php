<?php
// Shared helper for submit.php and privacy-request.php. No dependencies.
function ohc_error_page(): string {
  return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
    .'<meta name="viewport" content="width=device-width,initial-scale=1"><title>Submission not accepted</title>'
    .'<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#FBF9F8;color:#1E1520;'
    .'font:17px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;padding:2rem}main{max-width:34rem}'
    .'h1{font-size:1.6rem;line-height:1.2;margin:0 0 .75rem}a{display:inline-block;margin-top:1.5rem;'
    .'background:#2E1B33;color:#fff;text-decoration:none;font-weight:700;padding:.85rem 1.5rem;border-radius:999px}'
    .'</style></head><body><main><h1>We couldn\'t accept that submission</h1>'
    .'<p>Your request is missing something we need &mdash; usually the two consent checkboxes, or a complete '
    .'10-digit phone number. Nothing has been sent and no one will contact you.</p>'
    .'<p>Please go back and complete the form.</p><a href="/">Return to the form</a></main></body></html>';
}
function ohc_forward(string $kind, string $webhookKey, string $redirectTo): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); http_response_code(405); exit('Method Not Allowed'); }
  $cfg = file_exists(__DIR__.'/config.php') ? require __DIR__.'/config.php' : [];

  // A lead is only usable if the visitor actually gave consent. Anything else is a
  // forged or broken POST and must never reach a dialer.
  $digits = function ($v) { $d = preg_replace('/\D/', '', (string)$v);
    return (strlen($d) === 11 && $d[0] === '1') ? substr($d, 1) : $d; };
  $reason = null;
  if ($kind === 'lead') {
    if (($_POST['consent_contact'] ?? '') !== 'on')  $reason = 'missing consent_contact';
    elseif (($_POST['terms_ack'] ?? '') !== 'on')    $reason = 'missing terms_ack';
    elseif (strlen($digits($_POST['phone'] ?? '')) !== 10) $reason = 'invalid phone';
    elseif (trim($_POST['first_name'] ?? '') === '' || trim($_POST['last_name'] ?? '') === '') $reason = 'missing name';
  }
  if ($reason !== null) {
    error_log("$kind: rejected — $reason");
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    exit(ohc_error_page());
  }

  // The honeypot FLAGS rather than blocks: a password manager or browser autofill can fill a
  // hidden field, and turning a real customer away is worse than passing a bot through for
  // your CRM to quarantine.
  $suspected = !empty($_POST['ohc_leave_empty']);
  unset($_POST['ohc_leave_empty']);
  if ($suspected) { $_POST['spam_suspected'] = 'true'; error_log("$kind: honeypot filled — forwarded but flagged"); }

  $payload = [
    'kind'        => $kind,
    'received_at' => gmdate('c'),
    'ip'          => trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]),
    'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'referer'     => $_SERVER['HTTP_REFERER'] ?? '',
    'fields'      => $_POST,
  ];
  $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

  $url = $cfg[$webhookKey] ?? '';
  if ($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    curl_exec($ch);
    if (curl_errno($ch) || curl_getinfo($ch, CURLINFO_RESPONSE_CODE) >= 400) error_log("$kind: webhook failed");
    curl_close($ch);
  }
  if (!empty($cfg['notify_email'])) {
    $body = '';
    foreach ($_POST as $k => $v) $body .= str_pad($k, 26).': '.trim((string)$v)."\n";
    @mail($cfg['notify_email'], "[OptimalHealthChoices] New $kind", $body,
      'From: '.($cfg['from_email'] ?? 'no-reply@'.$_SERVER['HTTP_HOST'])."\r\nContent-Type: text/plain; charset=utf-8");
  }
  if (!$url && empty($cfg['notify_email'])) error_log("$kind received at ".gmdate('c')." but no webhook or notify_email is configured — submission not delivered.");

  header('Location: '.$redirectTo, true, 303);
  exit;
}
