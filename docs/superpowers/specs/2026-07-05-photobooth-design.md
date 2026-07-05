# Photobooth — Design/Spec

**Datum:** 2026-07-05
**Event:** Trouw Lotte & Stef — 11 juli 2026, parochiezaal Herselt
**Opdrachtgever:** Bjorn Vandegaer (trouwgeschenk voor het koppel)
**Live-URL:** https://photobooth.g-bit.be (push naar repo = live via Plesk)

## 1. Doel

Een herbruikbare, self-hosted wedding-photobooth-webapp waarmee gasten via QR-code of korte URL foto's delen (uploaden of nemen), met een Instagram-achtig filtersysteem, een publieke scrollbare galerij, een fullscreen live slideshow voor het grote scherm, en een admin-dashboard voor het bruidspaar. Volledig browser-based: geen app, geen account, geen login voor gasten.

**Expliciet niet in scope:** gastenboek (tekst/video-boodschappen als aparte feature), gastaccounts, video-upload, print/fotoboek-integratie.

## 2. Tech stack & motivatie

| Keuze | Motivatie |
|---|---|
| **PHP 8.2+** (geen framework) | Native op de Plesk shared hosting; push = live zonder build- of installstap; per-request model kan niet "platliggen" zoals een Node-proces onder Passenger. Node.js is beschikbaar op de hosting maar bewust niet gekozen: meer bewegende delen, geen meerwaarde voor deze app. |
| **SQLite** (PDO) | Geen database-setup per event; backup = 1 bestand. Db-laag via PDO zodat een MySQL-fallback alleen de DSN raakt (risico: `pdo_sqlite` uitgeschakeld op hosting — als eerste verifiëren bij deploy). |
| **Vanilla JS (ES-modules), geen build-stap** | Filters, offline-queue en live-refresh zijn frontend-werk; geen framework nodig. Repo is direct deploybaar. |
| **GD** (PHP-extensie) | Server-side herencodering/validatie van uploads en thumbnail-generatie. |

**PHP-versie voor het subdomein in Plesk op 8.2+ zetten** (hoofdsite draait 7.4 — EOL).

## 3. Repo-structuur

De repo-root **is** de webroot van photobooth.g-bit.be (geen aparte `public/`, geen document-root-wijziging in Plesk). Niet-publieke mappen worden afgeschermd met `.htaccess` (`Require all denied`).

```
photobooth/                  ← webroot van photobooth.g-bit.be
├── index.php                ← gastenpagina: upload + (optioneel) camera + filters
├── galerij.php              ← publieke scrollbare feed
├── slideshow.php            ← fullscreen slideshow voor TV/beamer
├── admin/
│   ├── index.php            ← dashboard (fotogrid + moderatie)
│   ├── login.php
│   ├── instellingen.php     ← feature-toggles, teksten
│   └── qr.php               ← QR-code + printbaar tafelkaartje
├── api/
│   ├── upload.php           ← POST multipart (foto + naam + boodschap)
│   ├── photos.php           ← GET JSON (galerij/slideshow, ?since= voor polling)
│   ├── moderate.php         ← POST (verberg/herstel/archiveer/wis) — admin-sessie + CSRF
│   ├── settings.php         ← POST instellingen — admin-sessie + CSRF
│   └── download.php         ← GET ZIP van alle actieve+gearchiveerde foto's — admin
├── uploads/                 ← publiek leesbare foto's + thumbs (niet in git)
├── assets/
│   ├── css/theme.css        ← ALLE kleuren/fonts/spacing als CSS custom properties
│   ├── css/app.css          ← structurele styling (verwijst enkel naar variabelen)
│   ├── js/                  ← filters.js, upload-queue.js, camera.js, gallery.js, slideshow.js
│   └── fonts/               ← lokaal gehoste fonts (geen Google-CDN-afhankelijkheid op de trouwdag)
├── app/                     ← PHP-logica; .htaccess deny (niet via HTTP bereikbaar)
│   ├── db.php               ← PDO-connectie + schema-migratie bij eerste run
│   ├── auth.php             ← admin-sessie, password_hash/verify, CSRF
│   ├── photos.php           ← opslaan, herencoderen (GD), thumbs, statusbeheer
│   └── settings.php         ← key/value settings met defaults uit event-config
├── config/                  ← .htaccess deny
│   ├── event.php            ← per-event: namen, datum, welkomsttekst, korte URL, taal-strings
│   ├── filters.php          ← filterdefinities: [id, label, CSS/canvas-filterformule]
│   └── secrets.php.example  ← template; echte secrets.php niet in git
├── data/                    ← SQLite-db; .htaccess deny (niet in git)
└── docs/                    ← .htaccess deny
```

**Herbruikbaarheid:** nieuwe trouw = `config/event.php` invullen, kleuren/fonts in `theme.css` aanpassen, `data/` en `uploads/` leegmaken. Nul code-wijzigingen. Geen event-specifieke strings, kleuren of datums in code of templates — alles uit config/CSS-variabelen.

## 4. Gastenflow (mobile-first)

1. **Landing (`index.php`):** welkom ("Deel jouw moment met Lotte & Stef"), grote knop "Kies foto's" (`<input type="file" accept="image/*" multiple>`), en — indien toggle aan — knop "Neem een foto" (getUserMedia met aftelklok). Grote touch-targets, minimale tekst.
2. **Filterkeuze per foto:** preview met horizontale rij filter-bolletjes (thumbnail per filter, IG-stijl). Filters uit `config/filters.php`; standaardset: Origineel, Zwart-wit, Sepia, Warm, Koel, Fade. Preview via CSS `filter`; bij bevestiging wordt de foto op een `<canvas>` gerenderd met `ctx.filter` = zelfde formule.
3. **Canvas-pipeline per foto:** `createImageBitmap(blob, {imageOrientation: 'from-image'})` (EXIF-rotatie) → resize naar max 2000px lange zijde → filter → `canvas.toBlob('image/jpeg', 0.8)`.
4. **Optioneel:** naam + korte boodschap (één formulier, beide optioneel).
5. **Offline-wachtrij:** blob + metadata in IndexedDB; uploader verwerkt de queue met retry/backoff zolang de pagina open is; per foto status (wachtrij → bezig → verzonden / mislukt + "opnieuw"-knop). Verificatie van connectiviteit via echte request, niet `navigator.onLine`. Background Sync niet gebruiken (geen iOS-support) — de queue-in-page volstaat.
6. **Bevestiging** + uitnodiging om nog foto's te delen / galerij te bekijken.

**HEIC:** iOS Safari levert via de file-picker doorgaans JPEG aan. Eerste integratietest met echte iPhone; alleen als dat faalt wordt server-side conversie toegevoegd (geen zware client-side heic2any).

## 5. Galerij & slideshow

- **Galerij (`galerij.php`):** verticale feed, nieuwste eerst, lazy-loaded thumbs, naam + boodschap onder de foto. Polling (~15 s, `?since=` op `api/photos.php`) voegt nieuwe foto's bovenaan toe. Publiek, geen login.
- **Slideshow (`slideshow.php`):** fullscreen crossfade, ~7 s per foto, naam/boodschap stijlvol als overlay. Polling ~5 s; nieuwe foto's worden vooraan in de rotatie geschoven zodat verse uploads snel op het scherm komen. Werkt op elk toestel met browser (TV, laptop aan beamer). Wake-lock via de Screen Wake Lock API waar beschikbaar.

## 6. Admin-dashboard

- **Login:** één admin-wachtwoord (hash in db, initieel gezet via secrets-config), PHP-sessie, brute-force-vertraging (sleep + teller).
- **Fotogrid:** alle foto's met status; per foto één-tik **Verberg** (direct weg uit galerij + slideshow), **Herstel**, **Archiveer** (bewaard maar niet publiek — vervangt de oude imgarchive-map), **Wis definitief** (met bevestiging).
- **Statuslogica:** `actief` (publiek) / `verborgen` / `gearchiveerd`. Wissen verwijdert bestand + record.
- **Download:** ZIP van alle actieve en gearchiveerde foto's (resolutie zoals opgeslagen; verborgen foto's blijven eruit) via `ZipArchive`-streaming.
- **QR & tafelkaartje:** QR-code (in PHP gegenereerd, bibliotheek zonder Flash/legacy) + printvriendelijke pagina met QR én korte URL, in de event-stijl.
- **Instellingen:** bestandsupload aan/uit (camera-capture is altijd beschikbaar — de kern van de booth; de upload-optie voor bv. oude babyfoto's is de beheerderskeuze), filters aan/uit, welkomsttekst, galerij publiek aan/uit. Opgeslagen in `settings`-tabel; defaults uit `event.php`.

## 7. Datamodel (SQLite)

```sql
CREATE TABLE photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  filename TEXT NOT NULL,          -- servergegenereerd, bv. p_<random>.jpg
  thumb TEXT NOT NULL,
  guest_name TEXT DEFAULT '',
  message TEXT DEFAULT '',
  status TEXT NOT NULL DEFAULT 'active',  -- active | hidden | archived
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
```

Schema wordt bij eerste run automatisch aangemaakt (`app/db.php`).

## 8. Stijl (niet-AI, gebaseerd op de uitnodiging)

- **Palet (CSS-variabelen in `theme.css`, af te stemmen op de uitnodigingsscan):** crème/ivoor achtergrond (~#F3EDE3), sage/eucalyptus-groen (~#B2BFA3) als basis, donker olijf voor tekst, terracotta (~#C67B5C) als accent.
- **Typografie:** karaktervolle serif voor koppen ("LOTTE & STEF", ruime letterspatiëring zoals op de uitnodiging), rustige leesbare tekstfont; eventueel subtiel script-accent voor de "&". Fonts lokaal hosten.
- **Texturen/decor:** subtiele papier/linnen-achtergrond, botanische accenten (eucalyptustakjes) als SVG in hoeken — spiegelt de uitnodiging.
- **Vermijden:** paars/blauwe gradients, glassmorphism, emoji als UI-decoratie, generieke geometrische sans als hoofdfont, harde schaduwen — de bekende AI-template-signalen.

## 9. Beveiliging

- Prepared statements overal (PDO), geen string-interpolatie in SQL.
- Upload-validatie server-side: MIME + `getimagesize`, herencodering via GD (neutraliseert payloads, stript EXIF incl. GPS — privacyvoordeel), servergegenereerde bestandsnamen, max bestandsgrootte (~15 MB pre-resize-fallback), rate-limiting per IP (eenvoudige teller).
- Secrets (admin-init-wachtwoord) buiten git; `app/`, `config/`, `data/` en `docs/` afgeschermd met `.htaccess` (`Require all denied`) — de repo-root is immers de webroot. Alle PHP-includes gebeuren via bestandspad, niet via HTTP.
- CSRF-token op alle admin-POSTs; sessie met `httponly`/`samesite`.
- HTTPS via bestaande Let's Encrypt op Plesk.

## 10. Testen & verificatie

- **Unit-achtig:** PHP-endpoints smoke-testen (upload happy path, afgewezen niet-afbeelding, moderatie-statusovergangen) met een klein PHP-testscript, lokaal via `php -S`.
- **Integratie op echte toestellen vóór de trouwdag:** iPhone Safari (EXIF-rotatie! HEIC!), Android Chrome, upload op mobiele data, offline-queue (vliegtuigmodus aan/uit), slideshow >1 u stabiel.
- **Generale repetitie:** deploy naar photobooth.g-bit.be, `pdo_sqlite`/GD/`ZipArchive` verifiëren, end-to-end QR → upload → galerij → slideshow → admin.

## 11. Risico's & fallbacks

| Risico | Mitigatie |
|---|---|
| `pdo_sqlite` niet beschikbaar op hosting | Dag-1-check via info-script; PDO-laag maakt MySQL-switch tot DSN-wijziging |
| iPhone levert HEIC i.p.v. JPEG | Test eerst; zo nodig server-side conversie toevoegen |
| Slechte zaal-wifi | Offline-queue + client-side compressie; korte URL voor mobiele data |
| Burst-uploads tijdens receptie/dansfeest | Kleine JPEG's (~300-600 KB), geen zware server-verwerking, SQLite WAL-mode |
| Display valt uit tijdens feest | Slideshow herstelt state bij reload (gewoon pagina heropenen) |
