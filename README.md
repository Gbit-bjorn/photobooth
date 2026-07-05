# Photobooth

Herbruikbare wedding-photobooth: gasten delen foto's via QR-code (met filters),
er is een publieke galerij, een fullscreen slideshow voor het grote scherm en
een beheerdashboard voor het bruidspaar. Puur PHP 8.2 + SQLite + vanilla JS —
geen build-stap, repo-root = webroot.

## Deploy (Plesk)

1. Subdomein laten wijzen naar deze repo-map; PHP **8.2+** kiezen;
   `upload_max_filesize` en `post_max_size` ≥ 16M.
2. Op de server `config/secrets.php` aanmaken (kopie van
   `config/secrets.php.example`) met een sterk admin-wachtwoord.
3. Pushen = live. Daarna inloggen op `/admin/login.php` en `/admin/diagnose.php`
   openen: alle checks moeten groen zijn.

## Nieuwe trouw / nieuw feest

1. `config/event.php` — namen, datum, teksten, korte URL.
2. `assets/css/theme.css` — kleuren en fonts (CSS-variabelen bovenaan).
3. `config/filters.php` — filteraanbod (optioneel).
4. `data/` en `uploads/` leegmaken, nieuwe `secrets.php` zetten. Klaar.

## Lokaal ontwikkelen

```powershell
C:\xampp\php\php.exe -S localhost:8080   # vanuit de repo-root
C:\xampp\php\php.exe tests\run-all.php   # PHP-testsuite
```

Browser-tests: open `tests/filters.test.html` en `tests/queue.test.html` via de
lokale server. Vereiste PHP-extensies: pdo_sqlite, gd, zip, fileinfo, mbstring.

## Structuur

- `index.php` — gastenpagina (upload, camera-optie, filters)
- `galerij.php` / `slideshow.php` — publieke weergave
- `admin/` — dashboard: moderatie (verberg/archiveer/wis), instellingen,
  QR-tafelkaartje, ZIP-download, diagnose
- `api/` — JSON-endpoints
- `app/` — PHP-logica (afgeschermd via .htaccess)
- `config/` — event-config, filters, secrets (afgeschermd)
- `data/` — SQLite-database (afgeschermd, niet in git)
