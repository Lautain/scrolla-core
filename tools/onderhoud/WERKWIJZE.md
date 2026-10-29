# Wekelijkse onderhoudsronde (Claude)

Geldt voor nieuwe Scrolla-sites met scrolla core. Oude sites (JetEngine, ASE, CMS Tool) vallen erbuiten.

## Hoe het werkt

- **Kanarie:** bp.scrolla.dev (optie `scrolla_kanarie` = `bp.scrolla.dev`) werkt elke update direct bij via de gewone automatische updates van WordPress.
- **Goedkeuren:** Claude test de kanarie grondig en zet de geteste versies in `onderhoud.json` (deze repo).
- **Sites:** scrolla core haalt `onderhoud.json` dagelijks op. De stack (WordPress, Elementor, Elementor Pro, Rank Math, Hello Elementor) werkt automatisch bij tot en met de goedgekeurde versie. Plug-ins buiten de stack volgen de instelling van de site.
- **Terugdraaien:** `terugdraaien` in `onderhoud.json` zet een plug-in of thema van wordpress.org terug, voor alle sites of alleen voor genoemde hosts. WordPress draait een plug-in-autoupdate met een fatale fout zelf al terug.
- **Belasting:** per site per week drie lichte verzoeken. De kanarie krijgt de zware test.

## Ronde

Werkmap: clone `Lautain/scrolla-core` (push-rechten nodig). Sites: de lijst in de opdracht van de geplande taak.

1. **Sites van vorige week controleren**
   `python3 tools/onderhoud/sites.py [domeinen] --lijst onderhoud.json > sites.json`
   - `fouten` op een site: bepaal de oorzaak (`fouten_waar` in de status, PHP-fout in de pagina, Elementor-opmaak weg).
   - Is de oorzaak een stack-update: draai terug. Zet in `onderhoud.json` onder `terugdraaien` de vorige versie (uit `git log -p onderhoud.json`), bijvoorbeeld `"elementor": {"versie": "4.3.4"}` of met `"sites": ["klant.nl"]` voor één site, en zet die versie ook terug in `stack`. Commit en push. Sites voeren het binnen een dag uit. Werkt het niet via wordpress.org (Elementor Pro): meld het als handwerk voor Steffen.
   - `let_op` (lijst nog niet opgehaald, nog niet bijgewerkt): alleen rapporteren. Twee weken op rij hetzelfde: als fout behandelen.
2. **Kanarie testen**
   `node tools/onderhoud/kanarie.js --meting onderhoud.json --out /tmp/kanarie > kanarie.json`
   - Test: basis-endpoints, 404-pagina, statusendpoint, home, contact en `/onderhoudstest/` (8 widgets, ook Elementor Pro) op desktop en mobiel, PHP- en JS-fouten, kapotte bronnen, Elementor-CSS, paginahoogte en aantallen tegen de vorige meting, formulier verzenden (AJAX-antwoord).
   - Bekijk de screenshots in `/tmp/kanarie` van `/onderhoudstest/` zelf: ziet het er normaal uit?
   - Fout: niet goedkeuren. Zoek uit welke update de oorzaak is (status `log` en `versies` van de kanarie). Meld het. De sites blijven op de vorige goedgekeurde versies.
3. **Goedkeuren** (alleen als stap 2 slaagt)
   `python3 tools/onderhoud/goedkeuren.py --repo . --kanarie kanarie.json`, dan commit ("Onderhoud [datum]: ...") en push naar `main`.
4. **Rapport** in Slack: kanaal `#lancering`, kort:
   - Kanarie: geslaagd of niet, nieuwe goedgekeurde versies.
   - Sites: aantal gecontroleerd, fouten (site + oorzaak + wat Claude deed), aandachtspunten.
   - Handwerk voor Steffen, als dat er is.
   Alles in orde: één regel. Een push-melding alleen bij fouten of handwerk.

## Regels

- Nooit iets op een klantsite zelf wijzigen; alleen via `onderhoud.json`.
- Nooit versies goedkeuren die niet op de kanarie gedraaid hebben.
- Geen sleutels of wachtwoorden; de ronde heeft ze niet nodig.
