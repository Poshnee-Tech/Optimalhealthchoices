#!/usr/bin/env python3
"""Local test server for the OptimalHealthChoices site.

    python3 scripts/dev_server.py          → http://localhost:8080

A plain static file server (python3 -m http.server) answers POST with
"501 Unsupported method", which is why submitting the form appears to do
nothing locally. This server serves the pages AND handles the two form
endpoints exactly the way Vercel and Hostinger do:

    POST /submit           → validates, prints the payload, 303 → /thank-you
    POST /privacy-request  → validates, prints the payload, 303 → /do-not-sell?sent=1

It also serves clean URLs (/privacy as well as /privacy.html) and a real 404
page, so what you test locally matches what deploys.
"""
import http.server, socketserver, urllib.parse, json, os, sys, datetime, re, threading

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8080
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def digits(v):
    d = re.sub(r"\D", "", str(v or ""))
    return d[1:] if len(d) == 11 and d[0] == "1" else d


def reject_reason(kind, f):
    if kind != "lead":
        return None
    if f.get("consent_contact") != "on":
        return "missing consent_contact"
    if f.get("terms_ack") != "on":
        return "missing terms_ack"
    if len(digits(f.get("phone"))) != 10:
        return "invalid phone"
    if not (f.get("first_name") or "").strip() or not (f.get("last_name") or "").strip():
        return "missing name"
    return None


ENDPOINTS = {
    "/submit": ("lead", "/thank-you.html"),
    "/privacy-request": ("privacy_request", "/do-not-sell.html?sent=1#request"),
}


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *a, **kw):
        super().__init__(*a, directory=ROOT, **kw)

    def do_POST(self):
        path = urllib.parse.urlparse(self.path).path
        if path not in ENDPOINTS:
            self.send_error(404, "No endpoint here")
            return
        kind, redirect = ENDPOINTS[path]
        n = int(self.headers.get("Content-Length") or 0)
        raw = self.rfile.read(n).decode("utf-8", "replace")
        fields = {k: v[0] for k, v in urllib.parse.parse_qs(raw, keep_blank_values=True).items()}

        reason = reject_reason(kind, fields)
        if reason:
            print(f"\n  ✗ {kind} REJECTED — {reason}\n", flush=True)
            self.send_response(400)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(b"This submission could not be accepted. Please go back and "
                             b"complete the form, including the consent checkboxes.")
            return
        # The honeypot flags rather than blocks — autofill can fill hidden fields.
        if fields.pop("ohc_leave_empty", ""):
            fields["spam_suspected"] = "true"
            print("  ! honeypot was filled — forwarded but flagged as spam_suspected", flush=True)

        print(f"\n  ✓ {kind} ACCEPTED at {datetime.datetime.now():%H:%M:%S} "
              f"— would POST this JSON to your webhook:")
        print(json.dumps({"kind": kind, "fields": fields}, indent=2)[:2000], flush=True)
        print(f"  → redirecting to {redirect}\n", flush=True)
        self.send_response(303)
        self.send_header("Location", redirect)
        self.end_headers()

    def do_GET(self):
        # Parity with Vercel/PHP: the endpoints exist but only accept POST.
        if urllib.parse.urlparse(self.path).path in ENDPOINTS:
            self.send_response(405)
            self.send_header("Allow", "POST")
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(b"Method Not Allowed")
            return
        super().do_GET()

    def end_headers(self):
        # Never cache during local testing: a stale page makes you debug code that
        # is no longer on disk.
        self.send_header("Cache-Control", "no-store, must-revalidate")
        self.send_header("Pragma", "no-cache")
        self.send_header("Expires", "0")
        super().end_headers()

    def send_head(self):
        # Clean URLs: /privacy → privacy.html, mirroring vercel.json and .htaccess
        path = urllib.parse.urlparse(self.path).path
        if path not in ("/", "") and not os.path.splitext(path)[1]:
            candidate = os.path.join(ROOT, path.lstrip("/") + ".html")
            if os.path.isfile(candidate):
                self.path = path + ".html"
        return super().send_head()

    def send_error(self, code, message=None, explain=None):
        page = os.path.join(ROOT, "404.html")
        if code == 404 and os.path.isfile(page):
            body = open(page, "rb").read()
            self.send_response(404)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return
        super().send_error(code, message, explain)

    def handle_one_request(self):
        try:
            super().handle_one_request()
        except (BrokenPipeError, ConnectionResetError):
            self.close_connection = True   # the browser navigated away mid-response

    def log_message(self, fmt, *args):
        sys.stderr.write("  %s\n" % (fmt % args))


class Server(socketserver.ThreadingMixIn, http.server.HTTPServer):
    """Threaded: a browser holding a keep-alive connection open must not block
    every other request, which on a single-threaded server looks like the site hanging."""
    daemon_threads = True
    allow_reuse_address = True


with Server(("", PORT), Handler) as httpd:
    print(f"\n  OptimalHealthChoices dev server → http://localhost:{PORT}\n"
          f"  Form submissions are validated and printed here. Ctrl-C to stop.\n")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\n  stopped\n")
