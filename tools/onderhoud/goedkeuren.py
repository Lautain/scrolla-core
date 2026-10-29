#!/usr/bin/env python3
"""Zet de geteste kanarieversies in onderhoud.json (repo scrolla-core) en bewaar de metingen.

Gebruik: python3 goedkeuren.py --repo /pad/scrolla-core --kanarie kanarie.json [--droog]
Leest de versies uit status.versies van de kanarie. Print wat er verandert; schrijft alleen zonder --droog.
"""
import argparse
import datetime
import json
import os

ap = argparse.ArgumentParser()
ap.add_argument("--repo", required=True)
ap.add_argument("--kanarie", required=True)
ap.add_argument("--droog", action="store_true")
a = ap.parse_args()

k = json.load(open(a.kanarie))
if not k.get("ok"):
    raise SystemExit("Kanarietest niet geslaagd: niet goedkeuren.")
versies = {s: v for s, v in (k.get("status", {}).get("versies") or {}).items() if s != "php"}
pad = os.path.join(a.repo, "onderhoud.json")
lijst = json.load(open(pad))
oud = lijst.get("stack", {})
wijzig = {s: (oud.get(s), v) for s, v in versies.items() if s in oud and oud.get(s) != v}
for s, (van, naar) in wijzig.items():
    print(f"{s}: {van} > {naar}")
if not wijzig:
    print("Geen nieuwe versies.")
lijst["stack"] = {s: versies.get(s, v) for s, v in oud.items()}
lijst["meting"] = k.get("meting", {})
lijst["bijgewerkt"] = datetime.date.today().isoformat()
# Een opgeloste terugzetting vervalt zodra een nieuwere versie is goedgekeurd.
lijst["terugdraaien"] = {s: r for s, r in lijst.get("terugdraaien", {}).items() if s not in wijzig}
if not a.droog:
    json.dump(lijst, open(pad, "w"), indent=2, ensure_ascii=False)
    open(pad, "a").write("\n")
    print("onderhoud.json bijgewerkt.")
