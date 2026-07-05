# Photobooth — Lotte & Stef (11/07/2026)

Wedding-photobooth: gasten delen foto's via QR (met filters), publieke galerij,
live slideshow, admin-dashboard. PHP 8.2 zonder framework + SQLite + vanilla JS.
Volledige context: [README.md](README.md) (deploy/hergebruik),
[docs/superpowers/specs/](docs/superpowers/specs/) (design-spec),
[docs/superpowers/plans/](docs/superpowers/plans/) (implementatieplan).

## ⚠️ Push = live deploy

`git push` deployt automatisch naar **https://photobooth.g-bit.be** (Plesk-Git-
webhook). Nooit pushen met falende tests of lokale testdata in `data/`/`uploads/`
(gitignored, maar hou ze leeg — zie Opruimen). Remote-URL bevat de username
(`Gbit-bjorn@`) tegen credential-popups bij meerdere GitHub-accounts.

## Commando's (Windows, geen PHP in PATH)

```powershell
C:\xampp\php\php.exe -S localhost:8080     # dev-server, vanuit repo-root
C:\xampp\php\php.exe tests\run-all.php     # PHP-suite (moet exit 0 zijn vóór push)
```

- Browser-tests: `tests/filters.test.html` en `tests/queue.test.html` via de
  dev-server openen (headless: Selenium + echte wachttijd; `--dump-dom` met
  virtual-time werkt NIET voor async canvas/IndexedDB).
- Negeer de `php_sqlsrv`-startup-warning van XAMPP.
- Opruimen na lokaal testen: `data\photobooth.sqlite*`, `data\originals\`,
  `uploads\p_*.jpg`, `uploads\t_*.jpg` verwijderen.

## Architectuur in één minuut

Repo-root = webroot. `app/`, `config/`, `data/`, `docs/`, `tests/` zijn
afgeschermd via `.htaccess` (`Require all denied`) — check dat bij elke nieuwe map.

- `app/bootstrap.php` — elke pagina/endpoint require't alleen dit
- `app/photos.php` — opslaan/herencoderen (GD, max 2400px q90), originelen
  gaan ongewijzigd naar `data/originals/` (privé, EXIF intact, alleen in de ZIP)
- `api/*.php` — JSON-endpoints; admin-mutaties via `auth_require_api()` (CSRF)
- `assets/js/filters.js` — filterengine: kleurmatrix + fx (vignette/korrel)
  wordt client-side in de foto gebakken; CSS-filter is alleen preview
- `assets/js/queue.js` — offline upload-wachtrij (IndexedDB, retry/backoff)

## Herbruikbaar per event — nooit hardcoden

Namen/datum/teksten → `config/event.php` · kleuren/fonts → CSS-variabelen in
`assets/css/theme.css` · filters → `config/filters.php` · runtime-instellingen
(toggles, teksten, slideshow) → settings-tabel met defaults in
`app/settings.php`. Event-specifieke strings horen nergens anders.

## Conventies & valkuilen

- UI-taal Nederlands; code-identifiers en commits Engels (Conventional Commits).
- Geen build-stap, geen composer/npm, geen CDN — alles gevendord
  (`assets/js/vendor/`, `assets/fonts/`).
- Schema-wijzigingen: `CREATE TABLE IF NOT EXISTS` volstaat niet voor bestaande
  live db — voeg een migratie toe in `app/db.php` (zie de `likes`-kolom).
- Admin-wachtwoord: hash wordt éénmalig uit `config/secrets.php` gezet;
  wijzigen = ook de `admin_password_hash`-rij in settings wissen.
- Rate-limiting deelt één tabel: prefix de key per doel (`up:`, `like:`).
- `[hidden]`-attribuut verliest van CSS `display:`-regels — de globale
  `[hidden] { display: none !important; }` in app.css vangt dat op.
- Aquarel-assets: witte achtergrond + `mix-blend-mode: multiply`; bij nieuwe
  beelden eerst bijna-wit naar zuiver wit clampen (jpeg-waas op de textuur).
- `styling research/` is lokaal referentiemateriaal, bewust gitignored.
