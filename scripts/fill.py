#!/usr/bin/env python3
"""Replace every [PLACEHOLDER] in the HTML pages using placeholders.json (see placeholders.example.json).
Run from anywhere:  python3 scripts/fill.py            (dry run — shows what would change)
                    python3 scripts/fill.py --write    (applies the replacements)"""
import json, re, sys, pathlib
root = pathlib.Path(__file__).resolve().parent.parent
cfg = root / "placeholders.json"
if not cfg.exists():
    sys.exit("placeholders.json not found — copy placeholders.example.json to placeholders.json and fill it in.")
values = {k: v for k, v in json.loads(cfg.read_text(encoding="utf-8")).items() if not k.startswith("_")}
empty = [k for k, v in values.items() if not str(v).strip()]
if empty:
    sys.exit("These placeholders have no value yet:\n  " + "\n  ".join(empty))
write = "--write" in sys.argv
total = 0

def digits(v):
    d = re.sub(r"\D", "", str(v))
    return d[1:] if len(d) == 11 and d.startswith("1") else d

# A tel: href must be a dialable E.164 number, not the formatted display text,
# so these are rewritten before the plain literal replacements run.
LINK_FIXES = []
if values.get("[TEL]"):
    n = digits(values["[TEL]"])
    if len(n) == 10:
        LINK_FIXES.append(('href="tel:[TEL]"', f'href="tel:+1{n}"'))
if values.get("[EMAIL]"):
    LINK_FIXES.append(('href="mailto:[EMAIL]"', f'href="mailto:{values["[EMAIL]"]}"'))
for p in sorted(p for p in root.glob("*.html") if not p.name.startswith("_")):
    s = p.read_text(encoding="utf-8"); n = 0
    for k, v in LINK_FIXES:
        n += s.count(k); s = s.replace(k, v)
    for k, v in values.items():
        c = s.count(k); n += c
        s = s.replace(k, str(v))
    if n:
        total += n
        print(f"{'wrote' if write else 'would update'} {p.name}: {n} replacements")
        if write: p.write_text(s, encoding="utf-8")
print(f"{total} replacements {'applied' if write else 'pending — re-run with --write'}")
