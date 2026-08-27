#!/usr/bin/env python3
"""Pre-deploy sanity check: shared blocks identical, internal links resolve, placeholders listed."""
import re, sys, pathlib, hashlib
root = pathlib.Path(__file__).resolve().parent.parent
pages = sorted(p for p in root.glob("*.html") if not p.name.startswith("_"))
fail = 0
def block(s, a, b):
    i = s.find(a); j = s.find(b, i)
    return s[i:j] if i >= 0 and j >= 0 else ""
sig = {}
for p in pages:
    s = p.read_text(encoding="utf-8")
    for name, a, b in [("style", "<style>", "</style>"), ("header", "<header", "</header>"), ("footer", "<footer", "</footer>")]:
        sig.setdefault(name, {}).setdefault(hashlib.md5(block(s, a, b).encode()).hexdigest(), []).append(p.name)
    for href in re.findall(r'href="([^"#?]+\.html)', s):
        if not (root / href).exists():
            print(f"BROKEN LINK {p.name} -> {href}"); fail = 1
    if s.count("<h1") != 1:
        print(f"{p.name}: expected exactly one <h1>, found {s.count('<h1')}"); fail = 1
for name, groups in sig.items():
    if len(groups) > 1:
        print(f"MISMATCH: <{name}> differs across pages: {groups}"); fail = 1
    else:
        print(f"OK  shared <{name}> identical across {len(pages)} pages")
ph = {}
for p in pages:
    # Scan only visible markup: <style>/<script> blocks are full of [attr=...] selectors.
    body = re.sub(r"<(style|script)\b.*?</\1>", "", p.read_text(encoding="utf-8"), flags=re.S | re.I)
    for m in set(re.findall(r"\[[A-Za-z][^\]\n<>]{2,80}\]", body)):
        ph.setdefault(m, set()).add(p.name)
print(f"\n{len(ph)} placeholders still to populate:")
for k in sorted(ph): print(f"  {k}  ({', '.join(sorted(ph[k]))})")
sys.exit(fail)
