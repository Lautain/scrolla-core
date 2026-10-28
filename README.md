# Scrolla Core

Basis-plug-in voor elke website van [Scrolla!](https://www.scrolla.nl). Vervangt ASE en Admin Menu Editor Pro.

| Module | Wat het doet |
|---|---|
| Algemeen | Bedrijfsgegevens op één plek; Elementor dynamic tags (groep Scrolla) en `[scrolla veld="telefoon" link="1"]` |
| Beheeromgeving | Opgeruimde toolbar en dashboard, meldingen gebundeld, breder menu, systeemoverzicht, zoekmachinestatus, dupliceren |
| Inloggen | Alleen gebruikersnaam, algemene foutmelding, kolom laatste login |
| Onnodige onderdelen uit | Gutenberg, reacties, feeds, emoji, oEmbed, jQuery Migrate, Dashicons (voorkant), head-tags |
| Beveiliging | Loginpogingen beperken met log, XML-RPC uit, gebruikers niet op te vragen, applicatiewachtwoorden uit, security-headers |
| Afbeeldingen | Max. 2400px, jpg/png naar webp (82), revisies max. 10 (uitzondering: bestandsnaam eindigt op `-nr`) |
| Menu per rol | Whitelist per rol, ook URL-toegang geblokkeerd; alleen Scrolla kan het instellen |
| SMTP | Mail via SMTP met testmail en mailog |
| Onderhoud | Onderhoudspagina tijdens updates (drop-in) en handmatige onderhoudsmodus |
| Berichttypes | Eenvoudige CPT's met velden, als dynamic tags in Elementor |

Instellingen zijn alleen zichtbaar voor Scrolla-beheerders (beheerder met @scrolla.nl-adres of gebruiker "Scrolla").

## Updates

Sites controleren één keer per week op een nieuwe GitHub-release en werken automatisch bij. Direct bijwerken: WP Toolkit > Alles bijwerken, of Dashboard > Updates > Opnieuw controleren.

Release maken: verhoog `Version` en `SCROLLA_CORE_VERSION`, push, en publiceer een GitHub-release met tag `vX.Y.Z`. De broncode-zip van de release wordt automatisch gebruikt.
