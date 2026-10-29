#!/usr/bin/env python3
"""Lichte controle van live sites met scrolla core. Per site 3 verzoeken: status, homepage, contactpagina.

Gebruik: python3 sites.py domein1.nl domein2.nl ... [--lijst onderhoud.json]
Uitvoer: JSON op stdout; exitcode 1 als een site een fout heeft.
"""
import json
import re
import sys
import time
import urllib.request
import urllib.error

FOUT = re.compile(r"critical error|kritieke fout|Fatal error|Parse error|wp-die-message", re.I)
UA = {"User-Agent": "scrolla-onderhoud/1.0"}


def get(url, timeout=20):
    t = time.time()
    try:
        with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=timeout) as r:
            return r.status, r.read().decode("utf-8", "replace"), round(time.time() - t, 2)
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace"), round(time.time() - t, 2)
    except Exception as e:  # noqa: BLE001
        return 0, str(e), round(time.time() - t, 2)


def check(domein, lijst):
    base = "https://" + domein.strip("/").replace("https://", "").replace("http://", "")
    res = {"site": base, "fouten": [], "let_op": []}
    s, body, _ = get(base + "/wp-json/scrolla/v1/status")
    if s != 200:
        s, body, _ = get(base + "/?rest_route=/scrolla/v1/status")
    try:
        st = json.loads(body) if s == 200 else {}
    except ValueError:
        st = {}
    res["status"] = st
    if not st:
        res["fouten"].append(f"statusendpoint niet bereikbaar ({s})")
    else:
        if st.get("fouten_7d"):
            res["fouten"].append(f"fatale fouten 7d: {st['fouten_7d']} ({', '.join(st.get('fouten_waar', []))})")
        if st.get("mislukt_7d"):
            res["fouten"].append(f"mislukte updates/terugzettingen 7d: {st['mislukt_7d']}")
        if lijst and st.get("lijst") != lijst.get("bijgewerkt"):
            res["let_op"].append(f"lijst {st.get('lijst') or '-'} op site, actueel {lijst.get('bijgewerkt')} (sync volgt dagelijks)")
        elif st.get("achter"):
            res["let_op"].append("nog niet bijgewerkt: " + ", ".join(st["achter"]))
    for pad in ("/", "/contact/"):
        s, body, sec = get(base + pad)
        res[pad] = {"status": s, "sec": sec}
        if pad == "/" and s != 200:
            res["fouten"].append(f"homepage status {s}")
        if pad == "/contact/" and s not in (200, 404):
            res["fouten"].append(f"contact status {s}")
        if s == 200 and FOUT.search(body):
            res["fouten"].append(f"{pad}: PHP-fout in de pagina")
        if s == 200 and "elementor" not in body:
            res["fouten"].append(f"{pad}: geen Elementor-opmaak")
        if sec > 8:
            res["let_op"].append(f"{pad}: traag ({sec}s)")
    return res


def main():
    args = sys.argv[1:]
    lijst = {}
    if "--lijst" in args:
        i = args.index("--lijst")
        lijst = json.load(open(args[i + 1]))
        del args[i:i + 2]
    out = [check(d, lijst) for d in args]
    print(json.dumps(out, indent=2, ensure_ascii=False))
    sys.exit(1 if any(r["fouten"] for r in out) else 0)


if __name__ == "__main__":
    main()
