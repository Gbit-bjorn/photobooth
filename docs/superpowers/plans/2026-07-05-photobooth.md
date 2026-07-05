# Photobooth Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Herbruikbare wedding-photobooth-webapp (gasten-upload met filters, galerij, slideshow, admin-dashboard) voor photobooth.g-bit.be, volgens de spec in `docs/superpowers/specs/2026-07-05-photobooth-design.md`.

**Architecture:** Repo-root = webroot. Multi-page PHP 8.2 zonder framework; niet-publieke mappen (`app/`, `config/`, `data/`, `docs/`) afgeschermd met `.htaccess`. SQLite via PDO (WAL). Frontend vanilla JS ES-modules zonder build-stap; filters worden client-side gebakken via canvas-pixelmatrix (identiek op elk toestel, geen `ctx.filter`-afhankelijkheid); uploads via IndexedDB-wachtrij met retry.

**Tech Stack:** PHP 8.2+ (PDO/SQLite, GD, ZipArchive), vanilla JS (ES-modules), CSS custom properties, gevendorde qrcode.js.

## Global Constraints

- PHP lokaal: `C:\xampp\php\php.exe` (8.2.12). In alle commando's hieronder afgekort als `$PHP`; in PowerShell eerst `$PHP = "C:\xampp\php\php.exe"` zetten.
- Lokale server voor handmatige verificatie: `& $PHP -S localhost:8080` **vanuit de repo-root** (`C:\fotobooth\photobooth`).
- Alle paden zijn relatief t.o.v. repo-root `C:\fotobooth\photobooth`.
- **Geen composer, geen npm, geen build-stap.** Externe assets (fonts, qrcode.js) worden gevendord in de repo.
- **Nul hardcoded event-gegevens** (namen, datum, teksten, kleuren) buiten `config/event.php` en `assets/css/theme.css`. UI-taal: Nederlands, strings uit config waar event-specifiek.
- Alle SQL via prepared statements. Alle admin-mutaties via POST + CSRF.
- Absolute URL-paden vanaf `/` (werkt identiek lokaal en op photobooth.g-bit.be).
- Tests: kale PHP-scripts in `tests/`, printen `OK <naam>` per assert en eindigen met exit code 0; bij falen `FAIL ...` + exit 1. Runner: `tests/run-all.php`.
- **NIET pushen naar de remote** — push = live deploy. Alleen lokaal committen; de gebruiker beslist wanneer er gepusht wordt.
- Commits: Conventional Commits-stijl, met `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.

---

### Task 0: Lokale dev-omgeving + repo-scaffold

**Files:**
- Modify: `C:\xampp\php\php.ini` (buiten repo — extensies aanzetten, zie stap 1)
- Create: `.gitignore`, `app/.htaccess`, `config/.htaccess`, `data/.htaccess`, `docs/.htaccess`, `.htaccess`, `uploads/.htaccess`, `uploads/.gitkeep`, `tests/run-all.php`

**Interfaces:**
- Produces: mapstructuur + testrunner waar alle volgende taken op bouwen.

- [ ] **Step 1: XAMPP-extensies aanzetten (systeem-configwijziging, omkeerbaar)**

In `C:\xampp\php\php.ini` deze drie regels de-commentariëren (`;extension=` → `extension=`):

```ini
extension=gd
extension=sqlite3
extension=zip
```

PowerShell:
```powershell
$ini = "C:\xampp\php\php.ini"
(Get-Content $ini) -replace '^;extension=(gd|sqlite3|zip)$', 'extension=$1' | Set-Content $ini -Encoding ascii
```

- [ ] **Step 2: Verifieer extensies**

Run: `& $PHP -m | Select-String -Pattern '^(gd|zip|sqlite3|pdo_sqlite|fileinfo)$'`
Expected: alle vijf verschijnen in de output.

- [ ] **Step 3: Scaffold-bestanden schrijven**

`.gitignore`:
```gitignore
data/
uploads/*
!uploads/.htaccess
!uploads/.gitkeep
config/secrets.php
```

`.htaccess` (repo-root):
```apache
Options -Indexes
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

`app/.htaccess`, `config/.htaccess`, `data/.htaccess`, `docs/.htaccess` (identieke inhoud, 4 bestanden):
```apache
Require all denied
```

`uploads/.htaccess` (publiek leesbaar, maar nooit PHP uitvoeren):
```apache
<FilesMatch "\.(?i:php|phtml|phar)$">
    Require all denied
</FilesMatch>
Options -Indexes
```

`uploads/.gitkeep`: leeg bestand.

`tests/run-all.php`:
```php
<?php
declare(strict_types=1);
$fail = 0;
foreach (glob(__DIR__ . '/test-*.php') as $test) {
    echo "== " . basename($test) . " ==\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($test), $code);
    if ($code !== 0) $fail = 1;
}
exit($fail);
```

- [ ] **Step 4: Verifieer dat de testrunner draait (nog zonder tests)**

Run: `& $PHP tests\run-all.php`
Expected: geen output, exit code 0. Check: `$LASTEXITCODE` → `0`.

- [ ] **Step 5: Commit**

```powershell
git add .gitignore .htaccess app/.htaccess config/.htaccess data/.htaccess docs/.htaccess uploads/.htaccess uploads/.gitkeep tests/run-all.php
git commit -m "chore: scaffold repo structure, htaccess protection, test runner"
```

---

### Task 1: Config-bestanden, bootstrap en database-laag

**Files:**
- Create: `config/event.php`, `config/filters.php`, `config/secrets.php.example`, `config/secrets.php` (lokaal, niet in git), `app/bootstrap.php`, `app/db.php`
- Test: `tests/test-db.php`

**Interfaces:**
- Produces:
  - `PB_ROOT` (const, absolute repo-root) — gedefinieerd in `app/bootstrap.php`
  - `pb_event(): array`, `pb_filters(): array`, `pb_secrets(): array`
  - `db(): PDO` — singleton, maakt schema aan bij eerste gebruik; datamap overschrijfbaar via env `PHOTOBOOTH_DATA_DIR`
- Elke pagina/endpoint doet enkel `require __DIR__ . '/app/bootstrap.php';` (of `../app/...` vanuit submap).

- [ ] **Step 1: Schrijf de failing test**

`tests/test-db.php`:
```php
<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp, 0777, true);
putenv("PHOTOBOOTH_DATA_DIR=$tmp");
require dirname(__DIR__) . '/app/bootstrap.php';

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

$pdo = db();
ok($pdo instanceof PDO, 'db returns PDO');
ok(db() === $pdo, 'db is singleton');

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('photos', $tables, true), 'photos table exists');
ok(in_array('settings', $tables, true), 'settings table exists');
ok(in_array('rate_log', $tables, true), 'rate_log table exists');

$mode = $pdo->query("PRAGMA journal_mode")->fetchColumn();
ok(strtolower((string)$mode) === 'wal', 'WAL mode active');

$stmt = $pdo->prepare("INSERT INTO photos (filename, thumb, guest_name, message) VALUES (?,?,?,?)");
$stmt->execute(['p_test.jpg', 't_test.jpg', 'Test', 'Hallo']);
$row = $pdo->query("SELECT * FROM photos WHERE id=1")->fetch(PDO::FETCH_ASSOC);
ok($row['status'] === 'active', 'default status active');
ok($row['created_at'] !== '', 'created_at set');

$ev = pb_event();
ok(is_string($ev['couple']) && $ev['couple'] !== '', 'event config has couple');
$fl = pb_filters();
ok(count($fl) >= 4 && isset($fl[0]['id'], $fl[0]['label'], $fl[0]['ops']), 'filters config shape');
```

- [ ] **Step 2: Run test om te verifiëren dat hij faalt**

Run: `& $PHP tests\test-db.php`
Expected: FAIL — `Failed opening required '...app/bootstrap.php'`.

- [ ] **Step 3: Schrijf config + bootstrap + db**

`config/event.php` — **de enige plek met event-specifieke gegevens**:
```php
<?php
return [
    'couple'        => 'Lotte & Stef',
    'couple_initials' => 'L·S',
    'date_iso'      => '2026-07-11',
    'date_display'  => '11 juli 2026',
    'tagline'       => "Let's celebrate together",
    'welcome_title' => 'Deel jouw moment',
    'welcome_text'  => 'Neem of kies een foto en deel hem met Lotte & Stef — hij verschijnt meteen in de galerij en op het grote scherm.',
    'thanks_text'   => 'Bedankt! Je foto is onderweg. Deel er gerust nog eentje.',
    'short_url'     => 'photobooth.g-bit.be',
];
```

`config/filters.php` — filterdefinities als **primitieve operaties** (géén vrije CSS-strings: JS bouwt hieruit zowel de CSS-preview als de pixelmatrix voor het bakken, zodat preview en resultaat op elk toestel identiek zijn):
```php
<?php
// ops: lijst van [naam, waarde]. Ondersteund: grayscale, sepia, saturate,
// brightness, contrast, hue-rotate (graden). Volgorde is betekenisvol.
return [
    ['id' => 'origineel', 'label' => 'Origineel', 'ops' => []],
    ['id' => 'zwartwit',  'label' => 'Zwart-wit', 'ops' => [['grayscale', 1], ['contrast', 1.05]]],
    ['id' => 'sepia',     'label' => 'Sepia',     'ops' => [['sepia', 0.75], ['contrast', 1.02]]],
    ['id' => 'warm',      'label' => 'Warm',      'ops' => [['sepia', 0.28], ['saturate', 1.25], ['brightness', 1.03]]],
    ['id' => 'koel',      'label' => 'Koel',      'ops' => [['saturate', 0.9], ['hue-rotate', 12], ['brightness', 1.02]]],
    ['id' => 'fade',      'label' => 'Fade',      'ops' => [['contrast', 0.85], ['brightness', 1.08], ['saturate', 0.85]]],
];
```

`config/secrets.php.example`:
```php
<?php
// Kopieer naar secrets.php en pas aan. secrets.php staat in .gitignore.
return [
    // Initieel admin-wachtwoord; wordt bij de eerste login-initialisatie
    // gehasht opgeslagen in de database. Daarna mag dit bestand blijven staan
    // maar wordt deze waarde niet meer gelezen.
    'admin_password' => 'VERANDER-DIT-WACHTWOORD',
];
```

Maak lokaal ook meteen `config/secrets.php` aan (kopie van het example, ander wachtwoord, bv. `lokaal-dev-wachtwoord`).

`app/bootstrap.php`:
```php
<?php
declare(strict_types=1);

define('PB_ROOT', dirname(__DIR__));

require_once __DIR__ . '/db.php';
// Volgende requires komen er in latere taken bij:
// settings.php, rate.php, photos.php, auth.php, layout.php

function pb_event(): array
{
    static $cfg = null;
    return $cfg ??= require PB_ROOT . '/config/event.php';
}

function pb_filters(): array
{
    static $cfg = null;
    return $cfg ??= require PB_ROOT . '/config/filters.php';
}

function pb_secrets(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = PB_ROOT . '/config/secrets.php';
        $cfg = is_file($file) ? require $file : [];
    }
    return $cfg;
}
```

`app/db.php`:
```php
<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $dataDir = getenv('PHOTOBOOTH_DATA_DIR') ?: PB_ROOT . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }
    $pdo = new PDO('sqlite:' . $dataDir . '/photobooth.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    thumb TEXT NOT NULL,
    guest_name TEXT NOT NULL DEFAULT '',
    message TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_photos_status ON photos(status, id);
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS rate_log (
    ip TEXT NOT NULL,
    ts INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_rate ON rate_log(ip, ts);
SQL);
    return $pdo;
}
```

- [ ] **Step 4: Run tests om te verifiëren dat ze slagen**

Run: `& $PHP tests\run-all.php`
Expected: alle regels `OK ...`, exit code 0.

- [ ] **Step 5: Lint**

Run: `& $PHP -l app\bootstrap.php; & $PHP -l app\db.php; & $PHP -l config\event.php; & $PHP -l config\filters.php`
Expected: 4× `No syntax errors detected`.

- [ ] **Step 6: Commit**

```powershell
git add config/event.php config/filters.php config/secrets.php.example app/bootstrap.php app/db.php tests/test-db.php
git commit -m "feat: event/filter config, bootstrap and sqlite db layer"
```

---

### Task 2: Settings-laag en rate-limiting

**Files:**
- Create: `app/settings.php`, `app/rate.php`
- Modify: `app/bootstrap.php` (requires toevoegen)
- Test: `tests/test-settings.php`

**Interfaces:**
- Consumes: `db(): PDO`
- Produces:
  - `setting_get(string $key): string` — met ingebouwde defaults
  - `setting_set(string $key, string $value): void`
  - `settings_all(): array<string,string>` — defaults gemerged met db-waarden
  - `rate_ok(string $ip, int $max = 30, int $windowSec = 600): bool` — registreert meteen een hit als toegestaan
- Setting-keys (exact): `camera_enabled` ('0'/'1', default '0'), `filters_enabled` (default '1'), `gallery_public` (default '1'), `welcome_text` (default uit `pb_event()['welcome_text']`), `admin_password_hash` (default '').

- [ ] **Step 1: Schrijf de failing test**

`tests/test-settings.php`:
```php
<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp, 0777, true);
putenv("PHOTOBOOTH_DATA_DIR=$tmp");
require dirname(__DIR__) . '/app/bootstrap.php';

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

ok(setting_get('camera_enabled') === '0', 'default camera_enabled 0');
ok(setting_get('filters_enabled') === '1', 'default filters_enabled 1');
ok(setting_get('welcome_text') === pb_event()['welcome_text'], 'welcome_text default from event');

setting_set('camera_enabled', '1');
ok(setting_get('camera_enabled') === '1', 'setting_set persists');
setting_set('camera_enabled', '0');
ok(setting_get('camera_enabled') === '0', 'setting_set overwrites');

$all = settings_all();
ok($all['filters_enabled'] === '1' && array_key_exists('gallery_public', $all), 'settings_all merged');

// rate limiting: 3 hits toegestaan binnen venster, 4e geweigerd
ok(rate_ok('1.2.3.4', 3, 600) === true, 'rate hit 1');
ok(rate_ok('1.2.3.4', 3, 600) === true, 'rate hit 2');
ok(rate_ok('1.2.3.4', 3, 600) === true, 'rate hit 3');
ok(rate_ok('1.2.3.4', 3, 600) === false, 'rate hit 4 blocked');
ok(rate_ok('5.6.7.8', 3, 600) === true, 'other ip unaffected');
```

- [ ] **Step 2: Run test om te verifiëren dat hij faalt**

Run: `& $PHP tests\test-settings.php`
Expected: FAIL — `Call to undefined function setting_get()`.

- [ ] **Step 3: Implementeer**

`app/settings.php`:
```php
<?php
declare(strict_types=1);

function pb_setting_defaults(): array
{
    return [
        'camera_enabled'      => '0',
        'filters_enabled'     => '1',
        'gallery_public'      => '1',
        'welcome_text'        => pb_event()['welcome_text'],
        'admin_password_hash' => '',
    ];
}

function setting_get(string $key): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    if ($value !== false) {
        return (string)$value;
    }
    return pb_setting_defaults()[$key] ?? '';
}

function setting_set(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value'
    );
    $stmt->execute([$key, $value]);
}

function settings_all(): array
{
    $all = pb_setting_defaults();
    foreach (db()->query('SELECT key, value FROM settings') as $row) {
        $all[$row['key']] = $row['value'];
    }
    return $all;
}
```

`app/rate.php`:
```php
<?php
declare(strict_types=1);

/** Geeft true en registreert een hit als het IP onder de limiet zit. */
function rate_ok(string $ip, int $max = 30, int $windowSec = 600): bool
{
    $now = time();
    $pdo = db();
    $pdo->prepare('DELETE FROM rate_log WHERE ts < ?')->execute([$now - $windowSec]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM rate_log WHERE ip = ? AND ts >= ?');
    $stmt->execute([$ip, $now - $windowSec]);
    if ((int)$stmt->fetchColumn() >= $max) {
        return false;
    }
    $pdo->prepare('INSERT INTO rate_log (ip, ts) VALUES (?, ?)')->execute([$ip, $now]);
    return true;
}
```

In `app/bootstrap.php`, vervang het require-commentaarblok door:
```php
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/rate.php';
```
(laat het commentaar voor de nog komende `photos.php`, `auth.php`, `layout.php` staan).

- [ ] **Step 4: Run tests**

Run: `& $PHP tests\run-all.php`
Expected: alle `OK`, exit 0.

- [ ] **Step 5: Commit**

```powershell
git add app/settings.php app/rate.php app/bootstrap.php tests/test-settings.php
git commit -m "feat: settings layer with defaults and ip rate limiting"
```

---

### Task 3: Foto-domein (opslaan, herencoderen, thumbs, status)

**Files:**
- Create: `app/photos.php`
- Modify: `app/bootstrap.php` (require toevoegen)
- Test: `tests/test-photos.php`

**Interfaces:**
- Consumes: `db()`, `PB_ROOT`
- Produces:
  - `pb_uploads_dir(): string` — filesystem-pad, overschrijfbaar via env `PHOTOBOOTH_UPLOADS_DIR`
  - `photo_save(string $tmpPath, string $guestName, string $message): array` — valideert + herencodeert; return `['id'=>int,'filename'=>string,'thumb'=>string]`; gooit `InvalidArgumentException` bij ongeldige afbeelding
  - `photos_list(string $status = 'active', int $sinceId = 0, int $limit = 500): array` — rijen nieuwste eerst; met `sinceId` alleen `id > sinceId`
  - `photo_set_status(int $id, string $status): bool` — status ∈ {active, hidden, archived}
  - `photo_delete(int $id): bool` — verwijdert bestanden + db-rij
- Bestandsnaamconventie: `p_<16 hex>.jpg` (max 2000px lange zijde, JPEG q82), thumb `t_<zelfde hex>.jpg` (max 480px).

- [ ] **Step 1: Schrijf de failing test**

`tests/test-photos.php`:
```php
<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp . '/uploads', 0777, true);
putenv("PHOTOBOOTH_DATA_DIR=$tmp");
putenv("PHOTOBOOTH_UPLOADS_DIR=$tmp/uploads");
require dirname(__DIR__) . '/app/bootstrap.php';

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

// Maak een test-JPEG van 3000x1500 (groter dan max 2000)
$img = imagecreatetruecolor(3000, 1500);
imagefilledrectangle($img, 0, 0, 2999, 1499, imagecolorallocate($img, 180, 120, 90));
$src = $tmp . '/bron.jpg';
imagejpeg($img, $src, 90);
imagedestroy($img);

$saved = photo_save($src, 'Tante Rita', 'Proficiat!');
ok($saved['id'] === 1, 'photo id 1');
ok(preg_match('/^p_[0-9a-f]{16}\.jpg$/', $saved['filename']) === 1, 'filename convention');
ok(is_file(pb_uploads_dir() . '/' . $saved['filename']), 'file written');
ok(is_file(pb_uploads_dir() . '/' . $saved['thumb']), 'thumb written');

[$w, $h] = getimagesize(pb_uploads_dir() . '/' . $saved['filename']);
ok($w === 2000 && $h === 1000, 'resized to max 2000 long side');
[$tw, $th] = getimagesize(pb_uploads_dir() . '/' . $saved['thumb']);
ok($tw === 480, 'thumb max 480');

// Geen geldige afbeelding → exception
file_put_contents($tmp . '/nep.jpg', 'dit is geen afbeelding');
try {
    photo_save($tmp . '/nep.jpg', '', '');
    ok(false, 'invalid image rejected');
} catch (InvalidArgumentException) {
    ok(true, 'invalid image rejected');
}

// Lijst + status + since
$rows = photos_list();
ok(count($rows) === 1 && $rows[0]['guest_name'] === 'Tante Rita', 'photos_list active');
ok(photos_list('active', 1) === [], 'since filters out');
ok(photo_set_status(1, 'hidden') === true, 'set hidden');
ok(photos_list() === [], 'hidden not in active list');
ok(count(photos_list('hidden')) === 1, 'hidden list');
ok(photo_set_status(1, 'onzin') === false, 'invalid status rejected');

$file = pb_uploads_dir() . '/' . $saved['filename'];
ok(photo_delete(1) === true, 'delete returns true');
ok(!is_file($file), 'file removed');
ok(photos_list('hidden') === [], 'row removed');
```

- [ ] **Step 2: Run test om te verifiëren dat hij faalt**

Run: `& $PHP tests\test-photos.php`
Expected: FAIL — `Call to undefined function photo_save()`.

- [ ] **Step 3: Implementeer**

`app/photos.php`:
```php
<?php
declare(strict_types=1);

const PB_PHOTO_STATUSES = ['active', 'hidden', 'archived'];
const PB_MAX_DIM = 2000;
const PB_THUMB_DIM = 480;
const PB_JPEG_QUALITY = 82;

function pb_uploads_dir(): string
{
    return getenv('PHOTOBOOTH_UPLOADS_DIR') ?: PB_ROOT . '/uploads';
}

/**
 * Valideert en herencodeert een geüploade afbeelding (neutraliseert payloads,
 * stript EXIF incl. GPS), schaalt naar max PB_MAX_DIM en maakt een thumb.
 */
function photo_save(string $tmpPath, string $guestName, string $message): array
{
    $info = @getimagesize($tmpPath);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new InvalidArgumentException('Geen geldige afbeelding (jpeg/png/webp).');
    }
    $src = @imagecreatefromstring((string)file_get_contents($tmpPath));
    if ($src === false) {
        throw new InvalidArgumentException('Afbeelding kon niet gelezen worden.');
    }

    $hex = bin2hex(random_bytes(8));
    $filename = "p_{$hex}.jpg";
    $thumbname = "t_{$hex}.jpg";
    $dir = pb_uploads_dir();

    pb_write_scaled($src, "$dir/$filename", PB_MAX_DIM);
    pb_write_scaled($src, "$dir/$thumbname", PB_THUMB_DIM);
    imagedestroy($src);

    $stmt = db()->prepare(
        'INSERT INTO photos (filename, thumb, guest_name, message) VALUES (?,?,?,?)'
    );
    $stmt->execute([
        $filename,
        $thumbname,
        mb_substr(trim($guestName), 0, 60),
        mb_substr(trim($message), 0, 280),
    ]);
    return ['id' => (int)db()->lastInsertId(), 'filename' => $filename, 'thumb' => $thumbname];
}

/** Schrijft $src geschaald (alleen verkleinen) als JPEG naar $dest. */
function pb_write_scaled(GdImage $src, string $dest, int $maxDim): void
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, $maxDim / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    // Witte achtergrond voor transparante PNG's
    imagefilledrectangle($out, 0, 0, $nw, $nh, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagejpeg($out, $dest, PB_JPEG_QUALITY);
    imagedestroy($out);
}

function photos_list(string $status = 'active', int $sinceId = 0, int $limit = 500): array
{
    if (!in_array($status, PB_PHOTO_STATUSES, true)) {
        return [];
    }
    $stmt = db()->prepare(
        'SELECT id, filename, thumb, guest_name, message, status, created_at
         FROM photos WHERE status = ? AND id > ? ORDER BY id DESC LIMIT ?'
    );
    $stmt->execute([$status, $sinceId, $limit]);
    return $stmt->fetchAll();
}

function photo_set_status(int $id, string $status): bool
{
    if (!in_array($status, PB_PHOTO_STATUSES, true)) {
        return false;
    }
    $stmt = db()->prepare('UPDATE photos SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    return $stmt->rowCount() === 1;
}

function photo_delete(int $id): bool
{
    $stmt = db()->prepare('SELECT filename, thumb FROM photos WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return false;
    }
    @unlink(pb_uploads_dir() . '/' . $row['filename']);
    @unlink(pb_uploads_dir() . '/' . $row['thumb']);
    $del = db()->prepare('DELETE FROM photos WHERE id = ?');
    $del->execute([$id]);
    return true;
}
```

In `app/bootstrap.php` toevoegen na de bestaande requires:
```php
require_once __DIR__ . '/photos.php';
```

- [ ] **Step 4: Run tests**

Run: `& $PHP tests\run-all.php`
Expected: alle `OK`, exit 0.

- [ ] **Step 5: Commit**

```powershell
git add app/photos.php app/bootstrap.php tests/test-photos.php
git commit -m "feat: photo domain - save/reencode/thumb/status/delete"
```

---

### Task 4: API-endpoints — ping, upload, photos

**Files:**
- Create: `api/ping.php`, `api/upload.php`, `api/photos.php`, `app/http.php`
- Modify: `app/bootstrap.php` (require toevoegen)
- Test: `tests/test-api.php` (integratietest via `php -S`)

**Interfaces:**
- Consumes: `photo_save`, `photos_list`, `rate_ok`, `settings_all`
- Produces (HTTP-contract, gebruikt door alle frontend-JS):
  - `GET /api/ping.php` → `{"ok":true}` — connectiviteitscheck voor de upload-wachtrij
  - `POST /api/upload.php` multipart: veld `photo` (bestand, verplicht), `guest_name` (optioneel), `message` (optioneel) → 200 `{"ok":true,"id":<int>}`; 400 `{"ok":false,"error":"..."}`; 429 bij rate-limit
  - `GET /api/photos.php?since=<id>` → `{"ok":true,"latest":<maxId>,"photos":[{"id","src","thumb","name","message","created_at"}]}` — alleen `active`, nieuwste eerst; `src` = `/uploads/<filename>`
- `app/http.php` produceert: `json_out(array $data, int $status = 200): never`, `client_ip(): string`

- [ ] **Step 1: Schrijf http-helper**

`app/http.php`:
```php
<?php
declare(strict_types=1);

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
```

In `app/bootstrap.php` toevoegen: `require_once __DIR__ . '/http.php';`

- [ ] **Step 2: Schrijf de endpoints**

`api/ping.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';
json_out(['ok' => true]);
```

`api/upload.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Alleen POST.'], 405);
}
if (!rate_ok(client_ip())) {
    json_out(['ok' => false, 'error' => 'Even rustig aan — probeer zo weer.'], 429);
}
$file = $_FILES['photo'] ?? null;
if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_out(['ok' => false, 'error' => 'Geen foto ontvangen.'], 400);
}
if ($file['size'] > 15 * 1024 * 1024) {
    json_out(['ok' => false, 'error' => 'Foto is te groot (max 15 MB).'], 400);
}
try {
    $saved = photo_save(
        $file['tmp_name'],
        (string)($_POST['guest_name'] ?? ''),
        (string)($_POST['message'] ?? '')
    );
} catch (InvalidArgumentException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 400);
}
json_out(['ok' => true, 'id' => $saved['id']]);
```

`api/photos.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';

$since = max(0, (int)($_GET['since'] ?? 0));
$rows = photos_list('active', $since);
$photos = array_map(fn(array $r) => [
    'id'         => (int)$r['id'],
    'src'        => '/uploads/' . $r['filename'],
    'thumb'      => '/uploads/' . $r['thumb'],
    'name'       => $r['guest_name'],
    'message'    => $r['message'],
    'created_at' => $r['created_at'],
], $rows);
$latest = $photos === [] ? $since : max(array_column($photos, 'id'));
json_out(['ok' => true, 'latest' => $latest, 'photos' => $photos]);
```

- [ ] **Step 3: Schrijf de integratietest**

`tests/test-api.php` — start `php -S` met een tijdelijke datamap, test de drie endpoints end-to-end:
```php
<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp . '/uploads', 0777, true);
$root = dirname(__DIR__);
$port = 8123;

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

$desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$env = array_merge(getenv(), [
    'PHOTOBOOTH_DATA_DIR' => $tmp,
    'PHOTOBOOTH_UPLOADS_DIR' => $tmp . '/uploads',
]);
$server = proc_open(
    [PHP_BINARY, '-S', "localhost:$port", '-t', $root],
    $desc, $pipes, $root, $env
);
usleep(700_000); // server even laten opstarten

try {
    $ping = json_decode((string)file_get_contents("http://localhost:$port/api/ping.php"), true);
    ok(($ping['ok'] ?? false) === true, 'ping ok');

    // upload: maak test-jpeg en POST als multipart via curl-less stream
    $img = imagecreatetruecolor(800, 600);
    imagefilledrectangle($img, 0, 0, 799, 599, imagecolorallocate($img, 100, 140, 100));
    $srcFile = $tmp . '/up.jpg';
    imagejpeg($img, $srcFile, 90);
    imagedestroy($img);

    $boundary = 'pb' . bin2hex(random_bytes(8));
    $body = "--$boundary\r\n"
        . "Content-Disposition: form-data; name=\"photo\"; filename=\"up.jpg\"\r\n"
        . "Content-Type: image/jpeg\r\n\r\n"
        . file_get_contents($srcFile) . "\r\n"
        . "--$boundary\r\n"
        . "Content-Disposition: form-data; name=\"guest_name\"\r\n\r\nNonkel Jef\r\n"
        . "--$boundary\r\n"
        . "Content-Disposition: form-data; name=\"message\"\r\n\r\nSanté!\r\n"
        . "--$boundary--\r\n";
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: multipart/form-data; boundary=$boundary",
        'content' => $body,
        'ignore_errors' => true,
    ]]);
    $up = json_decode((string)file_get_contents("http://localhost:$port/api/upload.php", false, $ctx), true);
    ok(($up['ok'] ?? false) === true && $up['id'] === 1, 'upload ok');

    // upload zonder bestand → 400
    $ctx2 = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true,
    ]]);
    $raw = (string)file_get_contents("http://localhost:$port/api/upload.php", false, $ctx2);
    $bad = json_decode($raw, true);
    ok(($bad['ok'] ?? true) === false, 'upload without file rejected');

    $list = json_decode((string)file_get_contents("http://localhost:$port/api/photos.php"), true);
    ok(count($list['photos']) === 1 && $list['latest'] === 1, 'photos list');
    ok($list['photos'][0]['name'] === 'Nonkel Jef', 'guest name in list');
    ok(str_starts_with($list['photos'][0]['src'], '/uploads/p_'), 'src path');

    $since = json_decode((string)file_get_contents("http://localhost:$port/api/photos.php?since=1"), true);
    ok($since['photos'] === [], 'since=1 empty');
} finally {
    proc_terminate($server);
    proc_close($server);
}
```

- [ ] **Step 4: Run tests**

Run: `& $PHP tests\run-all.php`
Expected: alle `OK` (db, settings, photos én api), exit 0.

- [ ] **Step 5: Commit**

```powershell
git add api/ping.php api/upload.php api/photos.php app/http.php app/bootstrap.php tests/test-api.php
git commit -m "feat: json api - ping, upload with rate limit, photos feed"
```

---

### Task 5: Theming, fonts en gedeelde layout

**Files:**
- Create: `assets/css/theme.css`, `assets/css/app.css`, `assets/fonts/` (gevendorde woff2's), `app/layout.php`
- Modify: `app/bootstrap.php` (require toevoegen)

**Interfaces:**
- Produces:
  - `page_header(string $title, string $bodyClass = ''): void` en `page_footer(): void` — echoën de HTML-schil; header bevat `<link>` naar beide css-bestanden en de meta viewport
  - CSS custom properties (exact deze namen, gebruikt door alle pagina's): `--c-bg`, `--c-surface`, `--c-ink`, `--c-ink-soft`, `--c-sage`, `--c-sage-deep`, `--c-terracotta`, `--c-line`, `--font-display`, `--font-body`, `--radius`, `--space-1..4`, `--shadow-soft`
- **Alle** kleur/typografie-keuzes staan in `theme.css`; `app.css` en pagina's verwijzen uitsluitend naar variabelen. Nieuwe trouw = alleen `theme.css` + `config/event.php` aanpassen.

- [ ] **Step 1: Vendor de fonts**

Cormorant Garamond (koppen, SIL OFL-licentie) via google-webfonts-helper; body blijft een systeem-stack (bewust — voelt natuurlijk, laadt instant):

```powershell
$dst = "assets\fonts"; New-Item -ItemType Directory -Force $dst | Out-Null
Invoke-WebRequest "https://gwfh.mranftl.com/api/fonts/cormorant-garamond?download=zip&subsets=latin&variants=500,600,italic" -OutFile "$env:TEMP\cg.zip"
Expand-Archive "$env:TEMP\cg.zip" -DestinationPath $dst -Force
Get-ChildItem $dst -Exclude *.woff2 | Remove-Item -Force -Confirm:$false
```

Expected: minstens `cormorant-garamond-v*-latin-500.woff2` en `-600.woff2` in `assets/fonts/`. **Let op:** het versienummer in de bestandsnamen (`v31` in theme.css hieronder) moet overeenkomen met wat effectief gedownload is — pas de `@font-face`-url's aan aan de echte bestandsnamen. **Fallback als de download faalt:** sla deze stap over; de font-stack in theme.css valt terug op Georgia/serif en de rest van de taak werkt gewoon.

- [ ] **Step 2: Schrijf theme.css**

`assets/css/theme.css`:
```css
/* ==========================================================================
   THEMA — het enige css-bestand dat je per event aanpast.
   Palet: crème / sage / diep olijf / terracotta, naar de uitnodiging.
   ========================================================================== */

@font-face {
  font-family: 'Cormorant Garamond';
  src: url('../fonts/cormorant-garamond-v31-latin-500.woff2') format('woff2');
  font-weight: 500;
  font-display: swap;
}
@font-face {
  font-family: 'Cormorant Garamond';
  src: url('../fonts/cormorant-garamond-v31-latin-600.woff2') format('woff2');
  font-weight: 600;
  font-display: swap;
}

:root {
  /* kleuren */
  --c-bg:         #f4efe6;  /* crème, papierachtig */
  --c-surface:    #fbf8f2;  /* kaarten */
  --c-ink:        #3d4438;  /* diep olijf, hoofdtekst */
  --c-ink-soft:   #75796d;  /* secundaire tekst */
  --c-sage:       #aab5a0;  /* sage-groen, accenten/randen */
  --c-sage-deep:  #7c8a72;  /* knoppen, actieve staat */
  --c-terracotta: #bd7550;  /* warm accent, spaarzaam */
  --c-line:       #ddd6c8;  /* hairlines */

  /* typografie */
  --font-display: 'Cormorant Garamond', Georgia, 'Times New Roman', serif;
  --font-body: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
  --tracking-display: 0.14em;

  /* vorm & ruimte */
  --radius: 6px;
  --space-1: 0.5rem;
  --space-2: 1rem;
  --space-3: 1.75rem;
  --space-4: 3rem;
  --shadow-soft: 0 1px 2px rgba(61, 68, 56, 0.08), 0 6px 24px rgba(61, 68, 56, 0.07);

  /* subtiele papiertextuur (data-uri, geen extern verzoek) */
  --texture-paper: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3CfeColorMatrix values='0 0 0 0 0.24 0 0 0 0 0.27 0 0 0 0 0.22 0 0 0 0.035 0'/%3E%3C/filter%3E%3Crect width='120' height='120' filter='url(%23n)'/%3E%3C/svg%3E");
}
```

- [ ] **Step 3: Schrijf app.css (structuur; verwijst enkel naar variabelen)**

`assets/css/app.css`:
```css
*, *::before, *::after { box-sizing: border-box; }

html { -webkit-text-size-adjust: 100%; }

body {
  margin: 0;
  min-height: 100dvh;
  background-color: var(--c-bg);
  background-image: var(--texture-paper);
  color: var(--c-ink);
  font-family: var(--font-body);
  font-size: 1rem;
  line-height: 1.55;
}

.wrap {
  max-width: 40rem;
  margin: 0 auto;
  padding: var(--space-3) var(--space-2) var(--space-4);
}

/* Koppen in de stijl van de uitnodiging: serif, spatiëring, kapitalen */
.display {
  font-family: var(--font-display);
  font-weight: 500;
  letter-spacing: var(--tracking-display);
  text-transform: uppercase;
  text-align: center;
  margin: 0;
}
h1.display { font-size: clamp(1.7rem, 6vw, 2.4rem); }

.subtitle {
  text-align: center;
  color: var(--c-ink-soft);
  font-size: 0.95rem;
  margin: var(--space-1) 0 0;
}

/* botanisch scheidingsteken onder titels */
.leaf-divider {
  display: block;
  width: 5.5rem;
  margin: var(--space-2) auto;
  color: var(--c-sage);
}

.card {
  background: var(--c-surface);
  border: 1px solid var(--c-line);
  border-radius: var(--radius);
  box-shadow: var(--shadow-soft);
  padding: var(--space-3) var(--space-2);
}

/* Knoppen: rustig, grote touch-targets */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5em;
  min-height: 3rem;
  padding: 0.6em 1.4em;
  border: 1px solid var(--c-sage-deep);
  border-radius: var(--radius);
  background: var(--c-sage-deep);
  color: #fff;
  font-family: var(--font-body);
  font-size: 1rem;
  cursor: pointer;
  text-decoration: none;
  width: 100%;
}
.btn.secondary {
  background: transparent;
  color: var(--c-ink);
  border-color: var(--c-line);
}
.btn.danger { background: var(--c-terracotta); border-color: var(--c-terracotta); }
.btn:disabled { opacity: 0.5; cursor: default; }

input[type="text"], textarea {
  width: 100%;
  padding: 0.7em 0.9em;
  border: 1px solid var(--c-line);
  border-radius: var(--radius);
  background: #fff;
  font: inherit;
  color: var(--c-ink);
}
label { display: block; margin: var(--space-2) 0 0.35rem; font-size: 0.9rem; color: var(--c-ink-soft); }

.field-hint { font-size: 0.8rem; color: var(--c-ink-soft); margin-top: 0.25rem; }

/* Eenvoudige top-nav (alleen waar nodig) */
.topnav {
  display: flex;
  justify-content: center;
  gap: var(--space-3);
  padding: var(--space-2);
  font-size: 0.9rem;
}
.topnav a { color: var(--c-ink-soft); text-decoration: none; letter-spacing: 0.06em; }
.topnav a.active, .topnav a:hover { color: var(--c-ink); }

.visually-hidden {
  position: absolute; width: 1px; height: 1px;
  overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap;
}
```

- [ ] **Step 4: Schrijf layout-helper**

`app/layout.php`:
```php
<?php
declare(strict_types=1);

function page_header(string $title, string $bodyClass = ''): void
{
    $ev = pb_event();
    $couple = htmlspecialchars($ev['couple']);
    $titleEsc = htmlspecialchars($title);
    echo <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>{$titleEsc} — {$couple}</title>
<link rel="stylesheet" href="/assets/css/theme.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="{$bodyClass}">
HTML;
}

function page_footer(): void
{
    echo "\n</body>\n</html>\n";
}

/** Eucalyptus-takje als inline SVG (decoratief, uit de uitnodigingsstijl). */
function leaf_divider(): void
{
    echo <<<'SVG'
<svg class="leaf-divider" viewBox="0 0 88 20" fill="none" aria-hidden="true">
  <path d="M4 10 H84" stroke="currentColor" stroke-width="0.8"/>
  <g fill="currentColor" opacity="0.85">
    <ellipse cx="30" cy="7" rx="4.5" ry="2" transform="rotate(-28 30 7)"/>
    <ellipse cx="40" cy="5.5" rx="4.5" ry="2" transform="rotate(-18 40 5.5)"/>
    <ellipse cx="50" cy="6.8" rx="4.5" ry="2" transform="rotate(-30 50 6.8)"/>
    <ellipse cx="36" cy="13" rx="4.5" ry="2" transform="rotate(22 36 13)"/>
    <ellipse cx="47" cy="13.6" rx="4.5" ry="2" transform="rotate(26 47 13.6)"/>
    <ellipse cx="57" cy="12" rx="4.5" ry="2" transform="rotate(18 57 12)"/>
  </g>
</svg>
SVG;
}
```

In `app/bootstrap.php` toevoegen: `require_once __DIR__ . '/layout.php';`

- [ ] **Step 5: Visuele smoke-check**

Maak tijdelijk `_smoke.php` in de root:
```php
<?php
require __DIR__ . '/app/bootstrap.php';
page_header('Smoke');
echo '<div class="wrap"><h1 class="display">' . htmlspecialchars(pb_event()['couple']) . '</h1>';
leaf_divider();
echo '<p class="subtitle">' . htmlspecialchars(pb_event()['tagline']) . '</p>';
echo '<div class="card"><button class="btn">Kies foto\'s</button></div></div>';
page_footer();
```
Run: `& $PHP -S localhost:8080` (vanuit repo-root), open `http://localhost:8080/_smoke.php` in de browser.
Expected: crème papierachtergrond, "LOTTE & STEF" in serif met ruime spatiëring, eucalyptus-divider, sage-groene knop. Verwijder daarna `_smoke.php`.

- [ ] **Step 6: Commit**

```powershell
git add assets/css/theme.css assets/css/app.css assets/fonts app/layout.php app/bootstrap.php
git commit -m "feat: theme system (css variables), vendored fonts, shared layout"
```

---

### Task 6: Filter-engine (JS) — preview-CSS + pixelmatrix-bake

**Files:**
- Create: `assets/js/filters.js`
- Test: `tests/filters.test.html` (handmatige browser-test met zichtbare PASS/FAIL)

**Interfaces:**
- Consumes: filterdefinities uit `config/filters.php`, door PHP als JSON in de pagina geïnjecteerd (`window.PB_CONFIG.filters`, zelfde shape: `[{id, label, ops: [[naam, waarde], ...]}]`)
- Produces (ES-module exports, gebruikt door booth.js en camera.js):
  - `cssFilter(ops): string` — bv. `"sepia(0.75) contrast(1.02)"`, `""` bij lege ops (voor live preview via CSS `filter`)
  - `applyOpsToCanvas(canvas, ops): void` — bakt dezelfde ops in de canvas-pixels via één 3×4-kleurmatrix-pass (identiek resultaat op elk toestel; geen `ctx.filter`-afhankelijkheid — Safari < 18 ondersteunt dat niet)
  - `loadOriented(file): Promise<HTMLImageElement|ImageBitmap>` — laadt een File/Blob mét correcte EXIF-orientatie
  - `processPhoto(file, ops, maxDim = 2000, quality = 0.82): Promise<Blob>` — volledige pipeline: laden → schalen → filter → JPEG-blob

- [ ] **Step 1: Schrijf filters.js**

`assets/js/filters.js`:
```js
// Filter-engine: één bron van waarheid (ops) → CSS-string voor live preview
// én kleurmatrix voor het definitieve bakken in canvas-pixels.
// Matrixformules volgen de W3C Filter Effects spec (zelfde wiskunde als CSS),
// dus preview en resultaat zijn visueel identiek.

const LUM_R = 0.2126, LUM_G = 0.7152, LUM_B = 0.0722;

function identity() {
  return { m: [1, 0, 0, 0, 1, 0, 0, 0, 1], o: [0, 0, 0] };
}

// combineer: eerst a, dan b  →  b∘a
function compose(b, a) {
  const m = new Array(9);
  for (let r = 0; r < 3; r++) {
    for (let c = 0; c < 3; c++) {
      m[r * 3 + c] =
        b.m[r * 3] * a.m[c] + b.m[r * 3 + 1] * a.m[3 + c] + b.m[r * 3 + 2] * a.m[6 + c];
    }
  }
  const o = [0, 1, 2].map(r =>
    b.m[r * 3] * a.o[0] + b.m[r * 3 + 1] * a.o[1] + b.m[r * 3 + 2] * a.o[2] + b.o[r]
  );
  return { m, o };
}

function lerpMatrix(target, v) {
  // identiteit → target naarmate v 0 → 1
  const id = identity();
  return {
    m: target.m.map((x, i) => id.m[i] + (x - id.m[i]) * v),
    o: target.o.map((x, i) => id.o[i] + (x - id.o[i]) * v),
  };
}

const PRIMITIVES = {
  grayscale: v => lerpMatrix({
    m: [LUM_R, LUM_G, LUM_B, LUM_R, LUM_G, LUM_B, LUM_R, LUM_G, LUM_B], o: [0, 0, 0],
  }, v),
  sepia: v => lerpMatrix({
    m: [0.393, 0.769, 0.189, 0.349, 0.686, 0.168, 0.272, 0.534, 0.131], o: [0, 0, 0],
  }, v),
  saturate: v => ({
    m: [
      LUM_R + (1 - LUM_R) * v, LUM_G * (1 - v),        LUM_B * (1 - v),
      LUM_R * (1 - v),         LUM_G + (1 - LUM_G) * v, LUM_B * (1 - v),
      LUM_R * (1 - v),         LUM_G * (1 - v),        LUM_B + (1 - LUM_B) * v,
    ],
    o: [0, 0, 0],
  }),
  brightness: v => ({ m: [v, 0, 0, 0, v, 0, 0, 0, v], o: [0, 0, 0] }),
  contrast: v => ({
    m: [v, 0, 0, 0, v, 0, 0, 0, v],
    o: [127.5 * (1 - v), 127.5 * (1 - v), 127.5 * (1 - v)],
  }),
  'hue-rotate': deg => {
    const a = (deg * Math.PI) / 180;
    const cos = Math.cos(a), sin = Math.sin(a);
    return {
      m: [
        LUM_R + cos * (1 - LUM_R) - sin * LUM_R,       LUM_G - cos * LUM_G - sin * LUM_G,           LUM_B - cos * LUM_B + sin * (1 - LUM_B),
        LUM_R - cos * LUM_R + sin * 0.143,             LUM_G + cos * (1 - LUM_G) + sin * 0.140,     LUM_B - cos * LUM_B - sin * 0.283,
        LUM_R - cos * LUM_R - sin * (1 - LUM_R),       LUM_G - cos * LUM_G + sin * LUM_G,           LUM_B + cos * (1 - LUM_B) + sin * LUM_B,
      ],
      o: [0, 0, 0],
    };
  },
};

const CSS_UNITS = { 'hue-rotate': 'deg' };

export function cssFilter(ops) {
  return (ops || [])
    .map(([name, v]) => `${name}(${v}${CSS_UNITS[name] ?? ''})`)
    .join(' ');
}

export function buildMatrix(ops) {
  let acc = identity();
  for (const [name, v] of ops || []) {
    const prim = PRIMITIVES[name];
    if (prim) acc = compose(prim(v), acc);
  }
  return acc;
}

export function applyOpsToCanvas(canvas, ops) {
  if (!ops || ops.length === 0) return;
  const { m, o } = buildMatrix(ops);
  const ctx = canvas.getContext('2d');
  const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const d = img.data;
  for (let i = 0; i < d.length; i += 4) {
    const r = d[i], g = d[i + 1], b = d[i + 2];
    d[i]     = m[0] * r + m[1] * g + m[2] * b + o[0];
    d[i + 1] = m[3] * r + m[4] * g + m[5] * b + o[1];
    d[i + 2] = m[6] * r + m[7] * g + m[8] * b + o[2];
    // clamping doet Uint8ClampedArray zelf
  }
  ctx.putImageData(img, 0, 0);
}

// Laadt een File/Blob met correcte EXIF-orientatie.
// Moderne browsers (iOS 13.4+, Chrome 81+) passen EXIF-rotatie automatisch
// toe op <img>; createImageBitmap met imageOrientation dekt de rest af.
export async function loadOriented(file) {
  if ('createImageBitmap' in window) {
    try {
      return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
      /* val terug op <img> */
    }
  }
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Kon afbeelding niet laden')); };
    img.src = url;
  });
}

export async function processPhoto(file, ops, maxDim = 2000, quality = 0.82) {
  const src = await loadOriented(file);
  const w = src.width ?? src.naturalWidth;
  const h = src.height ?? src.naturalHeight;
  const scale = Math.min(1, maxDim / Math.max(w, h));
  const canvas = document.createElement('canvas');
  canvas.width = Math.max(1, Math.round(w * scale));
  canvas.height = Math.max(1, Math.round(h * scale));
  const ctx = canvas.getContext('2d');
  ctx.drawImage(src, 0, 0, canvas.width, canvas.height);
  if (src.close) src.close();
  applyOpsToCanvas(canvas, ops);
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      blob => (blob ? resolve(blob) : reject(new Error('Kon foto niet verwerken'))),
      'image/jpeg',
      quality
    );
  });
}
```

- [ ] **Step 2: Schrijf de browser-test**

`tests/filters.test.html`:
```html
<!DOCTYPE html>
<html lang="nl">
<head><meta charset="UTF-8"><title>filters.js test</title></head>
<body>
<h1>filters.js</h1>
<pre id="out">bezig…</pre>
<script type="module">
import { cssFilter, buildMatrix, applyOpsToCanvas, processPhoto } from '/assets/js/filters.js';

const out = [];
const ok = (cond, name) => out.push((cond ? 'OK   ' : 'FAIL ') + name);
const near = (a, b, eps = 2) => Math.abs(a - b) <= eps;

// cssFilter
ok(cssFilter([]) === '', 'cssFilter empty');
ok(cssFilter([['sepia', 0.75], ['contrast', 1.02]]) === 'sepia(0.75) contrast(1.02)', 'cssFilter string');
ok(cssFilter([['hue-rotate', 12]]) === 'hue-rotate(12deg)', 'cssFilter deg unit');

// grayscale(1): kleur → luminantie, alle kanalen gelijk
{
  const c = document.createElement('canvas');
  c.width = c.height = 1;
  const ctx = c.getContext('2d');
  ctx.fillStyle = 'rgb(200, 100, 50)';
  ctx.fillRect(0, 0, 1, 1);
  applyOpsToCanvas(c, [['grayscale', 1]]);
  const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
  const expected = 0.2126 * 200 + 0.7152 * 100 + 0.0722 * 50; // ≈ 117
  ok(near(r, expected) && r === g && g === b, `grayscale luminance (${r} ≈ ${Math.round(expected)})`);
}

// brightness(2) clampt op 255
{
  const c = document.createElement('canvas');
  c.width = c.height = 1;
  const ctx = c.getContext('2d');
  ctx.fillStyle = 'rgb(200, 200, 200)';
  ctx.fillRect(0, 0, 1, 1);
  applyOpsToCanvas(c, [['brightness', 2]]);
  ok(ctx.getImageData(0, 0, 1, 1).data[0] === 255, 'brightness clamps');
}

// processPhoto: schaal 3000→2000 en levert JPEG-blob
{
  const big = document.createElement('canvas');
  big.width = 3000; big.height = 1500;
  big.getContext('2d').fillRect(0, 0, 3000, 1500);
  const blob = await new Promise(r => big.toBlob(r, 'image/jpeg', 0.9));
  const result = await processPhoto(blob, [['sepia', 0.5]]);
  ok(result.type === 'image/jpeg', 'processPhoto jpeg');
  const bmp = await createImageBitmap(result);
  ok(bmp.width === 2000 && bmp.height === 1000, `processPhoto resized (${bmp.width}x${bmp.height})`);
}

document.getElementById('out').textContent = out.join('\n');
if (out.some(l => l.startsWith('FAIL'))) document.body.style.background = '#fbb';
</script>
</body>
</html>
```

- [ ] **Step 3: Run de browser-test**

Run: `& $PHP -S localhost:8080` en open `http://localhost:8080/tests/filters.test.html`.
Expected: alle regels `OK`, geen rode achtergrond.

- [ ] **Step 4: Commit**

```powershell
git add assets/js/filters.js tests/filters.test.html
git commit -m "feat: js filter engine - css preview + color matrix bake"
```

---

### Task 7: Offline upload-wachtrij (IndexedDB + retry)

**Files:**
- Create: `assets/js/queue.js`
- Test: `tests/queue.test.html`

**Interfaces:**
- Consumes: `POST /api/upload.php`, `GET /api/ping.php` (Task 4)
- Produces (ES-module exports, gebruikt door booth.js):
  - `initQueue(onChange): Promise<void>` — opent IndexedDB `pb-booth` (store `queue`, keyPath `id` autoIncrement), hervat pending items, start de verwerkingslus; `onChange(items)` krijgt bij elke wijziging de volledige lijst `[{id, status, error}]` met status ∈ `queued|uploading|done|failed`
  - `enqueue(blob, guestName, message): Promise<void>` — voegt toe en triggert verwerking
  - `retryFailed(): void` — zet alle `failed` terug op `queued` en triggert verwerking
- Gedrag: sequentiële verwerking; bij netwerk-fout exponentiële backoff (2s → 4s → 8s → … max 30s) en opnieuw proberen zolang de pagina open is; bij HTTP 4xx (afgekeurd door server) status `failed` mét foutmelding, geen auto-retry; `done`-items worden na 3 s uit de store verwijderd. Connectiviteit wordt gecheckt met een echte `GET /api/ping.php` (met `cache: 'no-store'`), nooit met `navigator.onLine`.

- [ ] **Step 1: Schrijf queue.js**

`assets/js/queue.js`:
```js
// Offline-vriendelijke upload-wachtrij: IndexedDB + sequentiële uploader
// met backoff. Overleeft pagina-refresh (blobs staan in IndexedDB).

const DB_NAME = 'pb-booth';
const STORE = 'queue';
const MAX_BACKOFF = 30_000;

let db = null;
let notify = () => {};
let running = false;
let wakeup = null; // resolve-fn om backoff-slaap te onderbreken

function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, 1);
    req.onupgradeneeded = () => {
      req.result.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

function tx(mode, fn) {
  return new Promise((resolve, reject) => {
    const t = db.transaction(STORE, mode);
    const store = t.objectStore(STORE);
    const result = fn(store);
    t.oncomplete = () => resolve(result?.result ?? result);
    t.onerror = () => reject(t.error);
  });
}

async function allItems() {
  return await tx('readonly', s => s.getAll());
}

async function emit() {
  const items = await allItems();
  notify(items.map(({ id, status, error }) => ({ id, status, error: error ?? null })));
}

async function setItem(item) {
  await tx('readwrite', s => s.put(item));
}

async function removeItem(id) {
  await tx('readwrite', s => s.delete(id));
}

function sleep(ms) {
  return new Promise(resolve => {
    const timer = setTimeout(() => { wakeup = null; resolve(); }, ms);
    wakeup = () => { clearTimeout(timer); wakeup = null; resolve(); };
  });
}

async function serverReachable() {
  try {
    const res = await fetch('/api/ping.php', { cache: 'no-store' });
    return res.ok;
  } catch {
    return false;
  }
}

async function uploadOne(item) {
  const form = new FormData();
  form.append('photo', item.blob, 'foto.jpg');
  form.append('guest_name', item.guestName);
  form.append('message', item.message);
  const res = await fetch('/api/upload.php', { method: 'POST', body: form });
  if (res.ok) return { ok: true };
  if (res.status >= 400 && res.status < 500 && res.status !== 429) {
    let msg = 'Foto geweigerd.';
    try { msg = (await res.json()).error ?? msg; } catch { /* hou default */ }
    return { ok: false, permanent: true, error: msg };
  }
  return { ok: false, permanent: false };
}

async function processLoop() {
  if (running) return;
  running = true;
  let backoff = 2000;
  try {
    for (;;) {
      const items = await allItems();
      const next = items.find(i => i.status === 'queued' || i.status === 'uploading');
      if (!next) break;

      next.status = 'uploading';
      await setItem(next);
      await emit();

      let result;
      try {
        result = await uploadOne(next);
      } catch {
        result = { ok: false, permanent: false };
      }

      if (result.ok) {
        next.status = 'done';
        await setItem(next);
        await emit();
        backoff = 2000;
        setTimeout(async () => { await removeItem(next.id); await emit(); }, 3000);
      } else if (result.permanent) {
        next.status = 'failed';
        next.error = result.error;
        await setItem(next);
        await emit();
      } else {
        // netwerk/serverfout: wachten en opnieuw (zelfde item blijft 'uploading' → weer opgepakt)
        next.status = 'queued';
        await setItem(next);
        await emit();
        await sleep(backoff);
        backoff = Math.min(backoff * 2, MAX_BACKOFF);
        if (!(await serverReachable())) continue;
      }
    }
  } finally {
    running = false;
  }
}

export async function initQueue(onChange) {
  notify = onChange;
  db = await openDb();
  // items die 'uploading' bleven hangen na een refresh → terug naar queued
  const items = await allItems();
  for (const item of items) {
    if (item.status === 'uploading') {
      item.status = 'queued';
      await setItem(item);
    }
    if (item.status === 'done') await removeItem(item.id);
  }
  await emit();
  processLoop();
}

export async function enqueue(blob, guestName, message) {
  await tx('readwrite', s => s.add({ blob, guestName, message, status: 'queued' }));
  await emit();
  if (wakeup) wakeup();
  processLoop();
}

export async function retryFailed() {
  const items = await allItems();
  for (const item of items) {
    if (item.status === 'failed') {
      item.status = 'queued';
      delete item.error;
      await setItem(item);
    }
  }
  await emit();
  if (wakeup) wakeup();
  processLoop();
}
```

- [ ] **Step 2: Schrijf de browser-test**

`tests/queue.test.html`:
```html
<!DOCTYPE html>
<html lang="nl">
<head><meta charset="UTF-8"><title>queue.js test</title></head>
<body>
<h1>queue.js</h1>
<pre id="out">bezig…</pre>
<script type="module">
import { initQueue, enqueue } from '/assets/js/queue.js';

const out = [];
const ok = (cond, name) => out.push((cond ? 'OK   ' : 'FAIL ') + name);
const render = () => { document.getElementById('out').textContent = out.join('\n'); };

// verse testomgeving
await new Promise(r => { const d = indexedDB.deleteDatabase('pb-booth'); d.onsuccess = d.onerror = r; });

const states = [];
await initQueue(items => states.push(items.map(i => i.status).join(',')));

// geldige mini-jpeg maken
const c = document.createElement('canvas');
c.width = c.height = 50;
c.getContext('2d').fillRect(0, 0, 50, 50);
const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.8));

await enqueue(blob, 'Testgast', 'Hallo uit de test');
await new Promise(r => setTimeout(r, 2500));

ok(states.some(s => s.includes('queued') || s.includes('uploading')), 'passeert queued/uploading');
ok(states.some(s => s.includes('done')), 'upload bereikt done');

// ongeldig bestand → permanent failed (server weigert met 400)
const badBlob = new Blob(['geen afbeelding'], { type: 'text/plain' });
await enqueue(badBlob, '', '');
await new Promise(r => setTimeout(r, 2500));
ok(states.some(s => s.includes('failed')), 'ongeldige upload wordt failed');

render();
if (out.some(l => l.startsWith('FAIL'))) document.body.style.background = '#fbb';
</script>
</body>
</html>
```

- [ ] **Step 3: Run de browser-test**

Run: `& $PHP -S localhost:8080` en open `http://localhost:8080/tests/queue.test.html`.
Expected: 3× `OK`. Controleer daarna in de galerij-API (`http://localhost:8080/api/photos.php`) dat er een foto van "Testgast" bijkwam (testdata in lokale db is prima).

- [ ] **Step 4: Commit**

```powershell
git add assets/js/queue.js tests/queue.test.html
git commit -m "feat: indexeddb upload queue with retry and backoff"
```

---

### Task 8: Gastenpagina (index.php + booth.js + camera.js)

**Files:**
- Create: `index.php`, `assets/js/booth.js`, `assets/js/camera.js`

**Interfaces:**
- Consumes: `page_header/page_footer/leaf_divider`, `pb_event()`, `pb_filters()`, `settings_all()`, `processPhoto/cssFilter` (filters.js), `initQueue/enqueue/retryFailed` (queue.js)
- Produces: `window.PB_CONFIG = { filters, cameraEnabled, filtersEnabled, welcomeText, thanksText }` — geïnjecteerd door index.php, gelezen door booth.js/camera.js
- Flow: kies foto('s) (`multiple`) of neem foto (indien toggle aan) → per foto filterkeuze (preview-bolletjes met CSS-filter op thumbnails) → optioneel naam + boodschap (naam wordt onthouden in localStorage `pb-name`) → "Verstuur" → `processPhoto` → `enqueue` → statuslijstje onderaan.

- [ ] **Step 1: Schrijf index.php**

`index.php`:
```php
<?php
require __DIR__ . '/app/bootstrap.php';

$ev = pb_event();
$settings = settings_all();
$config = [
    'filters'        => $settings['filters_enabled'] === '1' ? pb_filters() : [pb_filters()[0]],
    'cameraEnabled'  => $settings['camera_enabled'] === '1',
    'filtersEnabled' => $settings['filters_enabled'] === '1',
    'welcomeText'    => $settings['welcome_text'],
    'thanksText'     => $ev['thanks_text'],
];

page_header($ev['welcome_title'], 'page-booth');
?>
<nav class="topnav">
  <a href="/" class="active">Deel een foto</a>
  <?php if ($settings['gallery_public'] === '1'): ?><a href="/galerij.php">Galerij</a><?php endif; ?>
</nav>
<main class="wrap">
  <header>
    <h1 class="display"><?= htmlspecialchars($ev['couple']) ?></h1>
    <?php leaf_divider(); ?>
    <p class="subtitle"><?= htmlspecialchars($ev['date_display']) ?> · <?= htmlspecialchars($ev['tagline']) ?></p>
  </header>

  <section class="card" id="stap-kies">
    <p id="welkom"><?= htmlspecialchars($settings['welcome_text']) ?></p>
    <label class="btn" for="foto-input">
      Kies foto's
      <input type="file" id="foto-input" class="visually-hidden" accept="image/*" multiple>
    </label>
    <button type="button" class="btn secondary" id="camera-knop" hidden>Neem een foto</button>
  </section>

  <section class="card" id="stap-bewerk" hidden>
    <div class="preview-holder"><img id="preview" alt="Jouw foto"></div>
    <div id="filter-rij" class="filter-rij" role="radiogroup" aria-label="Kies een filter"></div>
    <label for="gast-naam">Je naam (mag leeg)</label>
    <input type="text" id="gast-naam" maxlength="60" autocomplete="name">
    <label for="gast-boodschap">Boodschap voor <?= htmlspecialchars($ev['couple']) ?> (mag leeg)</label>
    <input type="text" id="gast-boodschap" maxlength="280">
    <p class="field-hint" id="meerdere-hint" hidden></p>
    <button type="button" class="btn" id="verstuur">Verstuur</button>
    <button type="button" class="btn secondary" id="annuleer">Annuleer</button>
  </section>

  <section id="stap-klaar" class="card" hidden>
    <p id="bedankt"></p>
    <button type="button" class="btn secondary" id="nog-een">Nog een foto delen</button>
  </section>

  <section id="upload-status" aria-live="polite"></section>
</main>

<div id="camera-overlay" hidden>
  <video id="camera-video" playsinline autoplay muted></video>
  <div id="camera-aftel" hidden></div>
  <div class="camera-knoppen">
    <button type="button" class="btn" id="camera-neem">Neem foto</button>
    <button type="button" class="btn secondary" id="camera-sluit">Sluit</button>
  </div>
</div>

<script>window.PB_CONFIG = <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>;</script>
<script type="module" src="/assets/js/booth.js"></script>
<?php page_footer(); ?>
```

- [ ] **Step 2: Booth-specifieke styling toevoegen aan app.css**

Onderaan `assets/css/app.css` toevoegen:
```css
/* --- gastenpagina --- */
.preview-holder {
  border-radius: var(--radius);
  overflow: hidden;
  border: 1px solid var(--c-line);
  background: #fff;
}
.preview-holder img { display: block; width: 100%; height: auto; }

.filter-rij {
  display: flex;
  gap: var(--space-1);
  overflow-x: auto;
  padding: var(--space-2) 0;
  -webkit-overflow-scrolling: touch;
}
.filter-optie {
  flex: 0 0 auto;
  width: 4.2rem;
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
  text-align: center;
  font-family: var(--font-body);
  font-size: 0.72rem;
  color: var(--c-ink-soft);
}
.filter-optie img {
  width: 4.2rem; height: 4.2rem;
  object-fit: cover;
  border-radius: 50%;
  border: 2px solid var(--c-line);
  display: block;
  margin-bottom: 0.3rem;
}
.filter-optie[aria-checked="true"] img { border-color: var(--c-terracotta); }
.filter-optie[aria-checked="true"] { color: var(--c-ink); font-weight: 600; }

#upload-status { margin-top: var(--space-2); }
.upload-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-1);
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--c-line);
  border-radius: var(--radius);
  background: var(--c-surface);
  margin-bottom: 0.4rem;
  font-size: 0.85rem;
}
.upload-item .status-done { color: var(--c-sage-deep); }
.upload-item .status-failed { color: var(--c-terracotta); }

#stap-bewerk .btn { margin-top: var(--space-2); }

/* --- camera-overlay --- */
#camera-overlay {
  position: fixed; inset: 0;
  background: rgba(30, 33, 28, 0.96);
  display: flex; flex-direction: column;
  justify-content: center; align-items: center;
  gap: var(--space-2); padding: var(--space-2);
  z-index: 50;
}
#camera-video { max-width: 100%; max-height: 70dvh; border-radius: var(--radius); }
#camera-aftel {
  position: absolute;
  font-family: var(--font-display);
  font-size: 7rem; color: #fff;
}
.camera-knoppen { display: flex; gap: var(--space-2); width: 100%; max-width: 24rem; }
```

- [ ] **Step 3: Schrijf booth.js**

`assets/js/booth.js`:
```js
import { cssFilter, processPhoto } from '/assets/js/filters.js';
import { initQueue, enqueue, retryFailed } from '/assets/js/queue.js';
import { initCamera } from '/assets/js/camera.js';

const cfg = window.PB_CONFIG;
const $ = id => document.getElementById(id);

let wachtrij = [];        // File/Blob's die nog bewerkt moeten worden
let huidig = null;        // huidige File/Blob
let huidigeFilter = cfg.filters[0];
let previewUrl = null;

const STATUS_LABELS = {
  queued: 'in wachtrij…',
  uploading: 'versturen…',
  done: 'verzonden ✓',
  failed: 'mislukt',
};

function toonStap(id) {
  for (const stap of ['stap-kies', 'stap-bewerk', 'stap-klaar']) {
    $(stap).hidden = stap !== id;
  }
}

function renderStatus(items) {
  const box = $('upload-status');
  box.innerHTML = '';
  for (const item of items) {
    const row = document.createElement('div');
    row.className = 'upload-item';
    const label = document.createElement('span');
    label.textContent = 'Foto';
    const status = document.createElement('span');
    status.className = `status-${item.status}`;
    status.textContent = item.error ?? STATUS_LABELS[item.status];
    row.append(label, status);
    if (item.status === 'failed') {
      const retry = document.createElement('button');
      retry.className = 'btn secondary';
      retry.style.minHeight = '2rem';
      retry.style.width = 'auto';
      retry.textContent = 'Opnieuw';
      retry.addEventListener('click', () => retryFailed());
      row.append(retry);
    }
    box.append(row);
  }
}

function renderFilters(file) {
  const rij = $('filter-rij');
  rij.innerHTML = '';
  rij.hidden = !cfg.filtersEnabled;
  const url = URL.createObjectURL(file);
  for (const f of cfg.filters) {
    const knop = document.createElement('button');
    knop.type = 'button';
    knop.className = 'filter-optie';
    knop.setAttribute('role', 'radio');
    knop.setAttribute('aria-checked', f.id === huidigeFilter.id ? 'true' : 'false');
    const img = document.createElement('img');
    img.src = url;
    img.alt = '';
    img.style.filter = cssFilter(f.ops);
    const naam = document.createElement('span');
    naam.textContent = f.label;
    knop.append(img, naam);
    knop.addEventListener('click', () => {
      huidigeFilter = f;
      $('preview').style.filter = cssFilter(f.ops);
      rij.querySelectorAll('.filter-optie').forEach(k => k.setAttribute('aria-checked', 'false'));
      knop.setAttribute('aria-checked', 'true');
    });
    rij.append(knop);
  }
}

function bewerkVolgende() {
  huidig = wachtrij.shift() ?? null;
  if (!huidig) {
    $('bedankt').textContent = cfg.thanksText;
    toonStap('stap-klaar');
    return;
  }
  huidigeFilter = cfg.filters[0];
  if (previewUrl) URL.revokeObjectURL(previewUrl);
  previewUrl = URL.createObjectURL(huidig);
  $('preview').src = previewUrl;
  $('preview').style.filter = '';
  renderFilters(huidig);
  const hint = $('meerdere-hint');
  hint.hidden = wachtrij.length === 0;
  hint.textContent = wachtrij.length > 0 ? `Nog ${wachtrij.length} foto('s) hierna.` : '';
  toonStap('stap-bewerk');
}

$('foto-input').addEventListener('change', e => {
  wachtrij = [...e.target.files];
  e.target.value = '';
  if (wachtrij.length > 0) bewerkVolgende();
});

$('verstuur').addEventListener('click', async () => {
  const knop = $('verstuur');
  knop.disabled = true;
  knop.textContent = 'Bezig…';
  try {
    const naam = $('gast-naam').value.trim();
    localStorage.setItem('pb-name', naam);
    const blob = await processPhoto(huidig, huidigeFilter.ops);
    await enqueue(blob, naam, $('gast-boodschap').value.trim());
    $('gast-boodschap').value = '';
    bewerkVolgende();
  } catch {
    alert('Deze foto kon niet verwerkt worden. Probeer een andere.');
  } finally {
    knop.disabled = false;
    knop.textContent = 'Verstuur';
  }
});

$('annuleer').addEventListener('click', () => {
  wachtrij = [];
  toonStap('stap-kies');
});

$('nog-een').addEventListener('click', () => toonStap('stap-kies'));

$('gast-naam').value = localStorage.getItem('pb-name') ?? '';

initQueue(renderStatus);

if (cfg.cameraEnabled) {
  $('camera-knop').hidden = false;
  initCamera(blob => {
    wachtrij = [blob];
    bewerkVolgende();
  });
}
```

- [ ] **Step 4: Schrijf camera.js**

`assets/js/camera.js`:
```js
// Camera-capture met aftelklok. Alleen actief als de beheerder de
// camera-instelling aanzet (cfg.cameraEnabled → booth.js roept initCamera aan).

const $ = id => document.getElementById(id);
let stream = null;

async function openCamera() {
  stream = await navigator.mediaDevices.getUserMedia({
    video: { facingMode: 'environment', width: { ideal: 2000 } },
    audio: false,
  });
  $('camera-video').srcObject = stream;
  $('camera-overlay').hidden = false;
}

function sluitCamera() {
  if (stream) {
    stream.getTracks().forEach(t => t.stop());
    stream = null;
  }
  $('camera-overlay').hidden = true;
}

function aftellen(vanaf = 3) {
  return new Promise(resolve => {
    const el = $('camera-aftel');
    el.hidden = false;
    let n = vanaf;
    el.textContent = n;
    const timer = setInterval(() => {
      n -= 1;
      if (n === 0) {
        clearInterval(timer);
        el.hidden = true;
        resolve();
      } else {
        el.textContent = n;
      }
    }, 1000);
  });
}

export function initCamera(onCapture) {
  $('camera-knop').addEventListener('click', async () => {
    try {
      await openCamera();
    } catch {
      alert('Camera niet beschikbaar. Kies een foto uit je galerij.');
    }
  });
  $('camera-sluit').addEventListener('click', sluitCamera);
  $('camera-neem').addEventListener('click', async () => {
    await aftellen();
    const video = $('camera-video');
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    sluitCamera();
    canvas.toBlob(blob => { if (blob) onCapture(blob); }, 'image/jpeg', 0.92);
  });
}
```

- [ ] **Step 5: Handmatige verificatie (desktop)**

Run: `& $PHP -S localhost:8080`, open `http://localhost:8080/`.
Checklist:
- Kop "LOTTE & STEF" + divider + datum zichtbaar, crème/sage-stijl.
- "Kies foto's" → selecteer 2 afbeeldingen → bewerk-stap toont preview + 6 filter-bolletjes; filter aantikken verandert de preview live.
- Verstuur → hint "Nog 1 foto('s) hierna." → tweede foto → bedankt-scherm.
- Onderaan verschijnen statusregels die naar "verzonden ✓" gaan en daarna verdwijnen.
- `http://localhost:8080/api/photos.php` bevat de 2 foto's.
- Zet in de lokale db `camera_enabled` op '1' (`& $PHP -r "putenv('PHOTOBOOTH_DATA_DIR='); require 'app/bootstrap.php'; setting_set('camera_enabled','1');"` vanuit repo-root) → herlaad → "Neem een foto" verschijnt; countdown en capture werken (webcam aanwezig). Zet daarna terug op '0'.

- [ ] **Step 6: Commit**

```powershell
git add index.php assets/js/booth.js assets/js/camera.js assets/css/app.css
git commit -m "feat: guest page - multi-photo upload flow with live filters and camera"
```

---

### Task 9: Publieke galerij (galerij.php + gallery.js)

**Files:**
- Create: `galerij.php`, `assets/js/gallery.js`
- Modify: `assets/css/app.css` (galerij-stijl onderaan toevoegen)

**Interfaces:**
- Consumes: `GET /api/photos.php?since=`, `page_header/leaf_divider`, `settings_all()` (`gallery_public`)
- Produces: publieke feed-pagina; geen nieuwe JS-exports.

- [ ] **Step 1: Schrijf galerij.php**

`galerij.php`:
```php
<?php
require __DIR__ . '/app/bootstrap.php';

$ev = pb_event();
if (settings_all()['gallery_public'] !== '1') {
    page_header('Galerij');
    echo '<main class="wrap"><h1 class="display">' . htmlspecialchars($ev['couple']) . '</h1>';
    leaf_divider();
    echo '<p class="subtitle">De galerij is momenteel niet beschikbaar.</p></main>';
    page_footer();
    exit;
}

page_header('Galerij', 'page-gallery');
?>
<nav class="topnav">
  <a href="/">Deel een foto</a>
  <a href="/galerij.php" class="active">Galerij</a>
</nav>
<main class="wrap">
  <header>
    <h1 class="display"><?= htmlspecialchars($ev['couple']) ?></h1>
    <?php leaf_divider(); ?>
    <p class="subtitle">Alle gedeelde momenten — nieuwste eerst</p>
  </header>
  <div id="feed" aria-live="polite"></div>
  <p id="leeg" class="subtitle" hidden>Nog geen foto's — deel de eerste!</p>
</main>
<script type="module" src="/assets/js/gallery.js"></script>
<?php page_footer(); ?>
```

- [ ] **Step 2: Schrijf gallery.js**

`assets/js/gallery.js`:
```js
// Feed: eerste load haalt alles op, daarna polling op ?since= en prepend.
const POLL_MS = 15_000;
let latest = 0;

function kaart(photo) {
  const fig = document.createElement('figure');
  fig.className = 'foto-kaart';
  const img = document.createElement('img');
  img.src = photo.thumb;
  img.alt = photo.message || 'Gedeelde foto';
  img.loading = 'lazy';
  img.addEventListener('click', () => { window.open(photo.src, '_blank'); });
  fig.append(img);
  if (photo.name || photo.message) {
    const cap = document.createElement('figcaption');
    if (photo.name) {
      const wie = document.createElement('strong');
      wie.textContent = photo.name;
      cap.append(wie);
    }
    if (photo.message) {
      const wat = document.createElement('span');
      wat.textContent = photo.message;
      cap.append(wat);
    }
    fig.append(cap);
  }
  return fig;
}

async function haalOp() {
  try {
    const res = await fetch(`/api/photos.php?since=${latest}`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.ok) return;
    latest = Math.max(latest, data.latest);
    const feed = document.getElementById('feed');
    // photos komen nieuwste-eerst; bij prepend in omgekeerde volgorde invoegen
    for (const photo of [...data.photos].reverse()) {
      feed.prepend(kaart(photo));
    }
    document.getElementById('leeg').hidden = feed.children.length > 0;
  } catch {
    /* volgende poll probeert opnieuw */
  }
}

await haalOp();
setInterval(haalOp, POLL_MS);
```

- [ ] **Step 3: Galerij-styling toevoegen**

Onderaan `assets/css/app.css`:
```css
/* --- galerij --- */
.foto-kaart {
  margin: 0 0 var(--space-3);
  background: var(--c-surface);
  border: 1px solid var(--c-line);
  border-radius: var(--radius);
  box-shadow: var(--shadow-soft);
  overflow: hidden;
}
.foto-kaart img {
  display: block;
  width: 100%;
  height: auto;
  cursor: pointer;
}
.foto-kaart figcaption {
  padding: var(--space-1) var(--space-2) var(--space-2);
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.foto-kaart strong {
  font-family: var(--font-display);
  font-size: 1.05rem;
  letter-spacing: 0.05em;
}
.foto-kaart span { color: var(--c-ink-soft); font-size: 0.92rem; }
```

- [ ] **Step 4: Handmatige verificatie**

Run: `& $PHP -S localhost:8080`, open `http://localhost:8080/galerij.php`.
Expected: eerder geüploade testfoto's als kaarten met naam/boodschap; upload in een tweede tab een nieuwe foto → verschijnt binnen 15 s bovenaan zonder herladen.

- [ ] **Step 5: Commit**

```powershell
git add galerij.php assets/js/gallery.js assets/css/app.css
git commit -m "feat: public gallery feed with polling"
```

---

### Task 10: Slideshow voor groot scherm

**Files:**
- Create: `slideshow.php`, `assets/js/slideshow.js`
- Modify: `assets/css/app.css` (slideshow-stijl toevoegen)

**Interfaces:**
- Consumes: `GET /api/photos.php?since=`, `pb_event()`
- Produces: fullscreen crossfade-slideshow; nieuwe foto's worden vooraan in de rotatie geschoven; Screen Wake Lock waar beschikbaar.

- [ ] **Step 1: Schrijf slideshow.php**

`slideshow.php`:
```php
<?php
require __DIR__ . '/app/bootstrap.php';
$ev = pb_event();
page_header('Slideshow', 'page-slideshow');
?>
<div id="stage">
  <img id="laag-a" class="slide-laag" alt="">
  <img id="laag-b" class="slide-laag" alt="">
  <div id="slide-caption" hidden>
    <strong id="cap-naam"></strong>
    <span id="cap-boodschap"></span>
  </div>
  <div id="slide-brand">
    <span class="display"><?= htmlspecialchars($ev['couple']) ?></span>
    <span><?= htmlspecialchars($ev['date_display']) ?> · <?= htmlspecialchars($ev['short_url']) ?></span>
  </div>
  <p id="slide-leeg">Nog even geduld — de eerste foto's komen eraan…</p>
</div>
<script type="module" src="/assets/js/slideshow.js"></script>
<?php page_footer(); ?>
```

- [ ] **Step 2: Slideshow-styling toevoegen**

Onderaan `assets/css/app.css`:
```css
/* --- slideshow --- */
.page-slideshow { background: #1e211c; cursor: none; overflow: hidden; }
#stage { position: fixed; inset: 0; }
.slide-laag {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: contain;
  opacity: 0;
  transition: opacity 1.2s ease-in-out;
}
.slide-laag.zichtbaar { opacity: 1; }
#slide-caption {
  position: absolute;
  left: 50%; bottom: 7vh;
  transform: translateX(-50%);
  max-width: 70vw;
  padding: 0.7rem 1.6rem;
  background: rgba(244, 239, 230, 0.92);
  border-radius: var(--radius);
  text-align: center;
  display: flex; flex-direction: column; gap: 0.1rem;
}
#slide-caption strong {
  font-family: var(--font-display);
  font-size: 1.5rem;
  letter-spacing: 0.08em;
  color: var(--c-ink);
}
#slide-caption span { color: var(--c-ink-soft); }
#slide-brand {
  position: absolute;
  top: 3vh; left: 50%;
  transform: translateX(-50%);
  text-align: center;
  color: rgba(244, 239, 230, 0.85);
  display: flex; flex-direction: column; gap: 0.1rem;
}
#slide-brand .display { font-size: 1.6rem; }
#slide-brand span:last-child { font-size: 0.85rem; letter-spacing: 0.12em; }
#slide-leeg {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  color: rgba(244, 239, 230, 0.7);
  font-family: var(--font-display);
  font-size: 1.6rem;
}
```

- [ ] **Step 3: Schrijf slideshow.js**

`assets/js/slideshow.js`:
```js
const SLIDE_MS = 7_000;
const POLL_MS = 5_000;

let fotos = [];          // rotatielijst
let vers = [];           // nieuw binnengekomen, krijgen voorrang
let index = 0;
let latest = 0;
let actieveLaag = 'a';

const $ = id => document.getElementById(id);

async function poll() {
  try {
    const res = await fetch(`/api/photos.php?since=${latest}`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.ok) return;
    latest = Math.max(latest, data.latest);
    if (latest === 0) return;
    if (fotos.length === 0 && vers.length === 0) {
      fotos = [...data.photos].reverse(); // oudste eerst als startrotatie
    } else {
      vers.push(...[...data.photos].reverse());
    }
  } catch { /* volgende poll */ }
}

function volgende() {
  if (vers.length > 0) {
    const foto = vers.shift();
    fotos.splice(index, 0, foto);
    return foto;
  }
  if (fotos.length === 0) return null;
  index = (index + 1) % fotos.length;
  return fotos[index];
}

function toon(foto) {
  if (!foto) return;
  $('slide-leeg').hidden = true;
  const binnenkomend = actieveLaag === 'a' ? $('laag-b') : $('laag-a');
  const uitgaand = actieveLaag === 'a' ? $('laag-a') : $('laag-b');
  binnenkomend.onload = () => {
    binnenkomend.classList.add('zichtbaar');
    uitgaand.classList.remove('zichtbaar');
    actieveLaag = actieveLaag === 'a' ? 'b' : 'a';
    const cap = $('slide-caption');
    if (foto.name || foto.message) {
      $('cap-naam').textContent = foto.name;
      $('cap-naam').hidden = !foto.name;
      $('cap-boodschap').textContent = foto.message;
      $('cap-boodschap').hidden = !foto.message;
      cap.hidden = false;
    } else {
      cap.hidden = true;
    }
  };
  binnenkomend.src = foto.src;
}

async function wakeLock() {
  try {
    if ('wakeLock' in navigator) {
      const lock = await navigator.wakeLock.request('screen');
      document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible') await navigator.wakeLock.request('screen');
      });
      return lock;
    }
  } catch { /* geen wake lock — niet fataal */ }
}

await poll();
toon(volgende());
setInterval(() => toon(volgende()), SLIDE_MS);
setInterval(poll, POLL_MS);
wakeLock();
```

- [ ] **Step 4: Handmatige verificatie**

Run: `& $PHP -S localhost:8080`, open `http://localhost:8080/slideshow.php`.
Expected: donkere fullscreen met "LOTTE & STEF"-branding bovenaan; foto's crossfaden elke 7 s met naam/boodschap-overlay; upload in een tweede tab een nieuwe foto → verschijnt binnen ~12 s als eerstvolgende slide. Laat 10+ minuten draaien: geen haperingen of geheugengroei (DevTools).

- [ ] **Step 5: Commit**

```powershell
git add slideshow.php assets/js/slideshow.js assets/css/app.css
git commit -m "feat: fullscreen crossfade slideshow with live polling"
```

---

### Task 11: Auth-laag + admin-login

**Files:**
- Create: `app/auth.php`, `admin/login.php`, `admin/logout.php`
- Modify: `app/bootstrap.php` (require toevoegen)
- Test: `tests/test-auth.php`

**Interfaces:**
- Consumes: `setting_get/setting_set`, `pb_secrets()`
- Produces (gebruikt door alle admin-pagina's en admin-API's):
  - `auth_init(): void` — start sessie met veilige cookieparams; initialiseert `admin_password_hash` uit `pb_secrets()['admin_password']` als die nog leeg is
  - `auth_login(string $password): bool` — verifieert; bij fout `sleep(1)` (brute-force-rem)
  - `auth_check(): bool`
  - `auth_require_page(): void` — redirect naar `/admin/login.php` als niet ingelogd
  - `auth_require_api(): void` — `json_out(['ok'=>false,'error'=>'Niet ingelogd'], 401)` als niet ingelogd; valideert daarna CSRF-header `X-CSRF-Token`
  - `auth_logout(): void`
  - `csrf_token(): string`

- [ ] **Step 1: Schrijf de failing test**

`tests/test-auth.php` (test de pure functies; sessie werkt in CLI met `session_start()`):
```php
<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp, 0777, true);
putenv("PHOTOBOOTH_DATA_DIR=$tmp");
require dirname(__DIR__) . '/app/bootstrap.php';

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

// secrets bevat lokaal een admin_password (Task 1); init zet de hash
auth_init();
$hash = setting_get('admin_password_hash');
ok($hash !== '', 'hash initialized from secrets');
ok(password_verify(pb_secrets()['admin_password'], $hash), 'hash matches secret');

ok(auth_check() === false, 'not logged in initially');
$t0 = microtime(true);
ok(auth_login('fout-wachtwoord') === false, 'wrong password rejected');
ok(microtime(true) - $t0 >= 1.0, 'failed login is slowed');
ok(auth_check() === false, 'still not logged in');

ok(auth_login(pb_secrets()['admin_password']) === true, 'correct password accepted');
ok(auth_check() === true, 'logged in');
ok(strlen(csrf_token()) >= 32, 'csrf token present');
$t = csrf_token();
ok(csrf_token() === $t, 'csrf token stable in session');

auth_logout();
ok(auth_check() === false, 'logged out');
```

- [ ] **Step 2: Run test om te verifiëren dat hij faalt**

Run: `& $PHP tests\test-auth.php`
Expected: FAIL — `Call to undefined function auth_init()`.

- [ ] **Step 3: Implementeer**

`app/auth.php`:
```php
<?php
declare(strict_types=1);

function auth_init(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => ($_SERVER['HTTPS'] ?? '') !== '',
        ]);
        session_start();
    }
    if (setting_get('admin_password_hash') === '') {
        $initial = pb_secrets()['admin_password'] ?? '';
        if ($initial !== '' && $initial !== 'VERANDER-DIT-WACHTWOORD') {
            setting_set('admin_password_hash', password_hash($initial, PASSWORD_DEFAULT));
        }
    }
}

function auth_login(string $password): bool
{
    auth_init();
    $hash = setting_get('admin_password_hash');
    if ($hash === '' || !password_verify($password, $hash)) {
        sleep(1); // brute-force-rem
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['pb_admin'] = true;
    return true;
}

function auth_check(): bool
{
    auth_init();
    return ($_SESSION['pb_admin'] ?? false) === true;
}

function auth_logout(): void
{
    auth_init();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function auth_require_page(): void
{
    if (!auth_check()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function auth_require_api(): void
{
    if (!auth_check()) {
        json_out(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), (string)$sent)) {
        json_out(['ok' => false, 'error' => 'Ongeldige sessie — herlaad de pagina.'], 403);
    }
}

function csrf_token(): string
{
    auth_init();
    if (empty($_SESSION['pb_csrf'])) {
        $_SESSION['pb_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pb_csrf'];
}
```

In `app/bootstrap.php` toevoegen: `require_once __DIR__ . '/auth.php';`

`admin/login.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';

$fout = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (auth_login((string)($_POST['wachtwoord'] ?? ''))) {
        header('Location: /admin/');
        exit;
    }
    $fout = 'Onjuist wachtwoord.';
}
if (auth_check()) {
    header('Location: /admin/');
    exit;
}

$ev = pb_event();
page_header('Login');
?>
<main class="wrap">
  <h1 class="display"><?= htmlspecialchars($ev['couple']) ?></h1>
  <?php leaf_divider(); ?>
  <p class="subtitle">Beheer</p>
  <form class="card" method="post" action="/admin/login.php">
    <label for="wachtwoord">Wachtwoord</label>
    <input type="password" id="wachtwoord" name="wachtwoord" autocomplete="current-password" autofocus>
    <?php if ($fout !== ''): ?><p class="field-hint" style="color: var(--c-terracotta)"><?= htmlspecialchars($fout) ?></p><?php endif; ?>
    <button class="btn" type="submit" style="margin-top: var(--space-2)">Inloggen</button>
  </form>
</main>
<?php page_footer(); ?>
```

Let op: `input[type="password"]` moet dezelfde styling krijgen als `input[type="text"]` — pas in `assets/css/app.css` de selector aan naar:
```css
input[type="text"], input[type="password"], textarea {
```

`admin/logout.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';
auth_logout();
header('Location: /admin/login.php');
```

- [ ] **Step 4: Run tests**

Run: `& $PHP tests\run-all.php`
Expected: alle `OK`, exit 0. (De CLI geeft mogelijk een `session_set_cookie_params`-notice als headers al verzonden zijn — als dat gebeurt, onderdruk in de test niet: zet in `auth_init()` de cookieparams alleen bij `PHP_SAPI !== 'cli'`.)

- [ ] **Step 5: Commit**

```powershell
git add app/auth.php app/bootstrap.php admin/login.php admin/logout.php assets/css/app.css tests/test-auth.php
git commit -m "feat: admin auth - session login, csrf, brute force delay"
```

---

### Task 12: Admin-dashboard + moderatie-API

**Files:**
- Create: `admin/index.php`, `api/moderate.php`, `assets/js/admin.js`
- Modify: `assets/css/app.css` (admin-grid-stijl)
- Test: `tests/test-moderate.php`

**Interfaces:**
- Consumes: `photos_list`, `photo_set_status`, `photo_delete`, `auth_require_page`, `auth_require_api`, `csrf_token`
- Produces:
  - `POST /api/moderate.php` JSON-body `{"id": <int>, "action": "hide"|"restore"|"archive"|"delete"}` + header `X-CSRF-Token` → `{"ok":true}` of `{"ok":false,"error":"..."}` (400 bij onbekende actie/id)
  - Dashboard met tabs Actief / Verborgen / Archief (`?status=active|hidden|archived`); per foto knoppen afhankelijk van status: Actief → [Verberg] [Archiveer]; Verborgen → [Herstel] [Wis definitief]; Archief → [Herstel] [Wis definitief]. "Wis definitief" vraagt bevestiging via een tweede klik ("Zeker?").
- Action→status mapping: hide→hidden, restore→active, archive→archived, delete→photo_delete.

- [ ] **Step 1: Schrijf de failing test**

`tests/test-moderate.php` (integratietest zoals Task 4; login via cookie-jar):
```php
<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp . '/uploads', 0777, true);
$root = dirname(__DIR__);
$port = 8124;

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

// Seed: één foto rechtstreeks via het domein
putenv("PHOTOBOOTH_DATA_DIR=$tmp");
putenv("PHOTOBOOTH_UPLOADS_DIR=$tmp/uploads");
require $root . '/app/bootstrap.php';
$img = imagecreatetruecolor(100, 100);
$srcFile = $tmp . '/seed.jpg';
imagejpeg($img, $srcFile);
photo_save($srcFile, 'Seed', '');

$desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$env = array_merge(getenv(), [
    'PHOTOBOOTH_DATA_DIR' => $tmp,
    'PHOTOBOOTH_UPLOADS_DIR' => $tmp . '/uploads',
]);
$server = proc_open([PHP_BINARY, '-S', "localhost:$port", '-t', $root], $desc, $pipes, $root, $env);
usleep(700_000);

function req(string $url, array $opts = []): array {
    $ctx = stream_context_create(['http' => array_merge([
        'ignore_errors' => true, 'method' => 'GET',
    ], $opts)]);
    $body = (string)file_get_contents($url, false, $ctx);
    $headers = $http_response_header ?? [];
    return [$body, $headers];
}

try {
    // niet ingelogd → 401
    [$body] = req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json",
        'content' => '{"id":1,"action":"hide"}',
    ]);
    ok((json_decode($body, true)['ok'] ?? true) === false, 'moderate rejects anonymous');

    // login → sessiecookie pakken
    $pw = pb_secrets()['admin_password'];
    [, $headers] = req("http://localhost:$port/admin/login.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded",
        'content' => 'wachtwoord=' . urlencode($pw),
        'follow_location' => 0,
    ]);
    $cookie = '';
    foreach ($headers as $h) {
        if (stripos($h, 'Set-Cookie:') === 0) $cookie = trim(explode(';', substr($h, 11))[0]);
    }
    ok($cookie !== '', 'login sets session cookie');

    // csrf-token uit dashboard-html halen (data-csrf attribuut)
    [$html] = req("http://localhost:$port/admin/", ['header' => "Cookie: $cookie"]);
    ok(preg_match('/data-csrf="([0-9a-f]{64})"/', $html, $m) === 1, 'dashboard exposes csrf');
    $csrf = $m[1];

    // hide → status hidden
    [$body] = req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nCookie: $cookie\r\nX-CSRF-Token: $csrf",
        'content' => '{"id":1,"action":"hide"}',
    ]);
    ok((json_decode($body, true)['ok'] ?? false) === true, 'hide ok');

    [$body] = req("http://localhost:$port/api/photos.php");
    ok(json_decode($body, true)['photos'] === [], 'hidden photo not in public feed');

    // restore → weer zichtbaar
    req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nCookie: $cookie\r\nX-CSRF-Token: $csrf",
        'content' => '{"id":1,"action":"restore"}',
    ]);
    [$body] = req("http://localhost:$port/api/photos.php");
    ok(count(json_decode($body, true)['photos']) === 1, 'restore ok');

    // delete → weg
    req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nCookie: $cookie\r\nX-CSRF-Token: $csrf",
        'content' => '{"id":1,"action":"delete"}',
    ]);
    [$body] = req("http://localhost:$port/api/photos.php");
    ok(json_decode($body, true)['photos'] === [], 'delete ok');

    // onbekende actie → 400
    [$body] = req("http://localhost:$port/api/moderate.php", [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nCookie: $cookie\r\nX-CSRF-Token: $csrf",
        'content' => '{"id":99,"action":"exploderen"}',
    ]);
    ok((json_decode($body, true)['ok'] ?? true) === false, 'unknown action rejected');
} finally {
    proc_terminate($server);
    proc_close($server);
}
```

- [ ] **Step 2: Run test om te verifiëren dat hij faalt**

Run: `& $PHP tests\test-moderate.php`
Expected: FAIL bij 'moderate rejects anonymous' (404-html i.p.v. JSON).

- [ ] **Step 3: Implementeer de API**

`api/moderate.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Alleen POST.'], 405);
}
auth_require_api();

$input = json_decode((string)file_get_contents('php://input'), true) ?? [];
$id = (int)($input['id'] ?? 0);
$action = (string)($input['action'] ?? '');

$ok = match ($action) {
    'hide'    => photo_set_status($id, 'hidden'),
    'restore' => photo_set_status($id, 'active'),
    'archive' => photo_set_status($id, 'archived'),
    'delete'  => photo_delete($id),
    default   => false,
};
if (!$ok) {
    json_out(['ok' => false, 'error' => 'Onbekende actie of foto.'], 400);
}
json_out(['ok' => true]);
```

- [ ] **Step 4: Implementeer dashboard + admin.js**

`admin/index.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';
auth_require_page();

$status = $_GET['status'] ?? 'active';
if (!in_array($status, PB_PHOTO_STATUSES, true)) {
    $status = 'active';
}
$fotos = photos_list($status);
$ev = pb_event();
$tabs = ['active' => 'Actief', 'hidden' => 'Verborgen', 'archived' => 'Archief'];

page_header('Beheer', 'page-admin');
?>
<nav class="topnav">
  <a href="/admin/" class="active">Foto's</a>
  <a href="/admin/instellingen.php">Instellingen</a>
  <a href="/admin/qr.php">QR &amp; kaartje</a>
  <a href="/api/download.php">Download alles (ZIP)</a>
  <a href="/admin/logout.php">Uitloggen</a>
</nav>
<main class="wrap wrap-breed" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
  <h1 class="display">Beheer</h1>
  <?php leaf_divider(); ?>
  <nav class="topnav">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="/admin/?status=<?= $key ?>" class="<?= $key === $status ? 'active' : '' ?>"><?= $label ?> </a>
    <?php endforeach; ?>
  </nav>
  <?php if ($fotos === []): ?>
    <p class="subtitle">Geen foto's in "<?= htmlspecialchars($tabs[$status]) ?>".</p>
  <?php endif; ?>
  <div class="admin-grid">
    <?php foreach ($fotos as $foto): ?>
      <div class="admin-kaart" data-id="<?= (int)$foto['id'] ?>">
        <a href="/uploads/<?= htmlspecialchars($foto['filename']) ?>" target="_blank">
          <img src="/uploads/<?= htmlspecialchars($foto['thumb']) ?>" alt="" loading="lazy">
        </a>
        <div class="admin-meta">
          <strong><?= htmlspecialchars($foto['guest_name']) ?></strong>
          <span><?= htmlspecialchars($foto['message']) ?></span>
          <span class="field-hint"><?= htmlspecialchars($foto['created_at']) ?> UTC</span>
        </div>
        <div class="admin-acties">
          <?php if ($status === 'active'): ?>
            <button class="btn secondary" data-action="hide">Verberg</button>
            <button class="btn secondary" data-action="archive">Archiveer</button>
          <?php else: ?>
            <button class="btn secondary" data-action="restore">Herstel</button>
            <button class="btn danger" data-action="delete">Wis definitief</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<script type="module" src="/assets/js/admin.js"></script>
<?php page_footer(); ?>
```

`assets/js/admin.js`:
```js
const csrf = document.querySelector('[data-csrf]').dataset.csrf;

document.querySelectorAll('.admin-kaart [data-action]').forEach(knop => {
  knop.addEventListener('click', async () => {
    const action = knop.dataset.action;
    if (action === 'delete' && knop.textContent !== 'Zeker?') {
      knop.textContent = 'Zeker?';
      setTimeout(() => { knop.textContent = 'Wis definitief'; }, 3000);
      return;
    }
    const kaart = knop.closest('.admin-kaart');
    knop.disabled = true;
    try {
      const res = await fetch('/api/moderate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ id: Number(kaart.dataset.id), action }),
      });
      const data = await res.json();
      if (data.ok) {
        kaart.remove();
      } else {
        alert(data.error ?? 'Er ging iets mis.');
        knop.disabled = false;
      }
    } catch {
      alert('Geen verbinding — probeer opnieuw.');
      knop.disabled = false;
    }
  });
});
```

Onderaan `assets/css/app.css`:
```css
/* --- admin --- */
.wrap-breed { max-width: 72rem; }
.admin-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
  gap: var(--space-2);
  margin-top: var(--space-2);
}
.admin-kaart {
  background: var(--c-surface);
  border: 1px solid var(--c-line);
  border-radius: var(--radius);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.admin-kaart img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; }
.admin-meta {
  padding: var(--space-1);
  display: flex; flex-direction: column; gap: 0.1rem;
  font-size: 0.85rem;
  min-height: 3.6rem;
}
.admin-acties { display: flex; gap: 0.4rem; padding: var(--space-1); }
.admin-acties .btn { min-height: 2.4rem; font-size: 0.85rem; padding: 0.3em 0.6em; }
```

- [ ] **Step 5: Run tests**

Run: `& $PHP tests\run-all.php`
Expected: alle `OK` (inclusief test-moderate), exit 0.

- [ ] **Step 6: Handmatige verificatie**

Run: `& $PHP -S localhost:8080`, upload via `/` een paar foto's, log in op `http://localhost:8080/admin/` (wachtwoord uit lokale `config/secrets.php`).
Expected: grid met foto's; Verberg laat de kaart verdwijnen en de foto uit `/galerij.php`; tab Verborgen toont hem met Herstel/Wis; Wis vraagt "Zeker?" en verwijdert dan echt (bestand weg uit `uploads/`).

- [ ] **Step 7: Commit**

```powershell
git add admin/index.php api/moderate.php assets/js/admin.js assets/css/app.css tests/test-moderate.php
git commit -m "feat: admin dashboard with hide/restore/archive/delete moderation"
```

---

### Task 13: Instellingenpagina

**Files:**
- Create: `admin/instellingen.php`, `api/settings.php`

**Interfaces:**
- Consumes: `settings_all`, `setting_set`, `auth_require_page`, `auth_require_api` (CSRF via verborgen POST-veld `csrf`)
- Produces: `POST /api/settings.php` form-encoded: `csrf`, `welcome_text`, checkboxes `camera_enabled`, `filters_enabled`, `gallery_public` → redirect naar `/admin/instellingen.php?opgeslagen=1`

- [ ] **Step 1: Schrijf de pagina**

`admin/instellingen.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';
auth_require_page();

$s = settings_all();
page_header('Instellingen', 'page-admin');
?>
<nav class="topnav">
  <a href="/admin/">Foto's</a>
  <a href="/admin/instellingen.php" class="active">Instellingen</a>
  <a href="/admin/qr.php">QR &amp; kaartje</a>
  <a href="/admin/logout.php">Uitloggen</a>
</nav>
<main class="wrap">
  <h1 class="display">Instellingen</h1>
  <?php leaf_divider(); ?>
  <?php if (isset($_GET['opgeslagen'])): ?>
    <p class="subtitle" style="color: var(--c-sage-deep)">Opgeslagen.</p>
  <?php endif; ?>
  <form class="card" method="post" action="/api/settings.php">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

    <label for="welcome_text">Welkomsttekst op de gastenpagina</label>
    <textarea id="welcome_text" name="welcome_text" rows="3"><?= htmlspecialchars($s['welcome_text']) ?></textarea>

    <label><input type="checkbox" name="camera_enabled" <?= $s['camera_enabled'] === '1' ? 'checked' : '' ?>>
      Camera-knop tonen (foto nemen in de browser)</label>
    <label><input type="checkbox" name="filters_enabled" <?= $s['filters_enabled'] === '1' ? 'checked' : '' ?>>
      Filters aanbieden bij upload</label>
    <label><input type="checkbox" name="gallery_public" <?= $s['gallery_public'] === '1' ? 'checked' : '' ?>>
      Galerij publiek zichtbaar</label>

    <button class="btn" type="submit" style="margin-top: var(--space-2)">Opslaan</button>
  </form>
</main>
<?php page_footer(); ?>
```

`api/settings.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'error' => 'Alleen POST.'], 405);
}
auth_require_api();

setting_set('welcome_text', mb_substr(trim((string)($_POST['welcome_text'] ?? '')), 0, 500));
foreach (['camera_enabled', 'filters_enabled', 'gallery_public'] as $toggle) {
    setting_set($toggle, isset($_POST[$toggle]) ? '1' : '0');
}
header('Location: /admin/instellingen.php?opgeslagen=1');
```

Checkbox-labels hebben een kleine stijlaanvulling nodig — onderaan `assets/css/app.css`:
```css
label:has(input[type="checkbox"]) {
  display: flex; align-items: center; gap: 0.6rem;
  color: var(--c-ink); font-size: 1rem;
  margin-top: var(--space-2);
}
input[type="checkbox"] { width: 1.2rem; height: 1.2rem; accent-color: var(--c-sage-deep); }
```

- [ ] **Step 2: Handmatige verificatie**

Run: `& $PHP -S localhost:8080` → `/admin/instellingen.php`:
- Camera aanvinken + opslaan → "Neem een foto" verschijnt op `/`.
- Filters uitvinken → op `/` geen filterrij meer (alleen Origineel wordt gebruikt).
- Galerij uitvinken → `/galerij.php` toont "niet beschikbaar" en de nav-link op `/` verdwijnt.
- Alles terugzetten.

- [ ] **Step 3: Commit**

```powershell
git add admin/instellingen.php api/settings.php assets/css/app.css
git commit -m "feat: admin settings page with feature toggles"
```

---

### Task 14: QR-code en printbaar tafelkaartje

**Files:**
- Create: `admin/qr.php`, `assets/js/vendor/qrcode.min.js` (gevendord)

**Interfaces:**
- Consumes: `pb_event()['short_url']`, `auth_require_page`, layout-helpers
- Produces: printbare pagina (A6-achtig kaartje, meerdere per A4 via browser-print) met QR naar `https://<short_url>/` + de korte URL als tekst.

- [ ] **Step 1: Vendor qrcode.js**

```powershell
New-Item -ItemType Directory -Force assets\js\vendor | Out-Null
Invoke-WebRequest "https://raw.githubusercontent.com/davidshimjs/qrcodejs/master/qrcode.min.js" -OutFile assets\js\vendor\qrcode.min.js
```
Expected: bestand ~20 KB. (MIT-licentie; bibliotheek is dependency-vrij en werkt offline.)

- [ ] **Step 2: Schrijf qr.php**

`admin/qr.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';
auth_require_page();

$ev = pb_event();
$url = 'https://' . $ev['short_url'] . '/';
page_header('QR & kaartje', 'page-admin');
?>
<nav class="topnav no-print">
  <a href="/admin/">Foto's</a>
  <a href="/admin/instellingen.php">Instellingen</a>
  <a href="/admin/qr.php" class="active">QR &amp; kaartje</a>
  <a href="/admin/logout.php">Uitloggen</a>
</nav>
<main class="wrap">
  <p class="subtitle no-print">Druk af via de printknop van je browser (Ctrl+P). Eén kaartje per pagina — kies "meerdere pagina's per vel" voor tafelkaartjes.</p>
  <div class="qr-kaartje">
    <h1 class="display"><?= htmlspecialchars($ev['couple']) ?></h1>
    <p class="subtitle"><?= htmlspecialchars($ev['tagline']) ?></p>
    <div id="qr"></div>
    <p class="qr-uitleg">Scan &amp; deel jouw foto's van vandaag</p>
    <p class="qr-url"><?= htmlspecialchars($ev['short_url']) ?></p>
  </div>
  <button class="btn no-print" onclick="print()" style="margin-top: var(--space-2)">Afdrukken</button>
</main>
<script src="/assets/js/vendor/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById('qr'), {
    text: <?= json_encode($url) ?>,
    width: 220,
    height: 220,
    colorDark: '#3d4438',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M,
  });
</script>
<?php page_footer(); ?>
```

Onderaan `assets/css/app.css`:
```css
/* --- qr-kaartje --- */
.qr-kaartje {
  background: var(--c-surface);
  border: 1px solid var(--c-line);
  border-radius: var(--radius);
  padding: var(--space-4) var(--space-3);
  text-align: center;
  max-width: 22rem;
  margin: 0 auto;
}
.qr-kaartje #qr { display: flex; justify-content: center; margin: var(--space-3) 0; }
.qr-uitleg { font-size: 0.95rem; color: var(--c-ink-soft); margin: 0; }
.qr-url {
  font-family: var(--font-display);
  font-size: 1.15rem;
  letter-spacing: 0.08em;
  margin: 0.3rem 0 0;
}
@media print {
  .no-print { display: none !important; }
  body { background: #fff; }
  .qr-kaartje { border: none; box-shadow: none; }
}
```

- [ ] **Step 3: Handmatige verificatie**

Open `http://localhost:8080/admin/qr.php` → QR zichtbaar in olijfkleur, scan met telefoon → opent `https://photobooth.g-bit.be/`. Printvoorbeeld (Ctrl+P) toont alleen het kaartje.

- [ ] **Step 4: Commit**

```powershell
git add admin/qr.php assets/js/vendor/qrcode.min.js assets/css/app.css
git commit -m "feat: qr code page with printable table card"
```

---

### Task 15: ZIP-download

**Files:**
- Create: `api/download.php`
- Test: `tests/test-download.php`

**Interfaces:**
- Consumes: `photos_list`, `pb_uploads_dir`, `auth_check`
- Produces: `GET /api/download.php` (alleen ingelogd; GET dus geen CSRF nodig — geen mutatie) → ZIP-bestand `fotos-<datum>.zip` met alle actieve + gearchiveerde foto's (volle resolutie zoals opgeslagen; verborgen foto's niet).

- [ ] **Step 1: Schrijf de failing test**

`tests/test-download.php`:
```php
<?php
declare(strict_types=1);
$tmp = sys_get_temp_dir() . '/pbtest-' . bin2hex(random_bytes(4));
mkdir($tmp . '/uploads', 0777, true);
$root = dirname(__DIR__);
$port = 8125;

function ok(bool $cond, string $name): void {
    if ($cond) { echo "OK $name\n"; return; }
    echo "FAIL $name\n"; exit(1);
}

putenv("PHOTOBOOTH_DATA_DIR=$tmp");
putenv("PHOTOBOOTH_UPLOADS_DIR=$tmp/uploads");
require $root . '/app/bootstrap.php';

// seed: 3 foto's — actief, gearchiveerd, verborgen
$img = imagecreatetruecolor(100, 100);
$srcFile = $tmp . '/seed.jpg';
imagejpeg($img, $srcFile);
$a = photo_save($srcFile, 'A', '');
$b = photo_save($srcFile, 'B', '');
$c = photo_save($srcFile, 'C', '');
photo_set_status($b['id'], 'archived');
photo_set_status($c['id'], 'hidden');

$desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$env = array_merge(getenv(), [
    'PHOTOBOOTH_DATA_DIR' => $tmp,
    'PHOTOBOOTH_UPLOADS_DIR' => $tmp . '/uploads',
]);
$server = proc_open([PHP_BINARY, '-S', "localhost:$port", '-t', $root], $desc, $pipes, $root, $env);
usleep(700_000);

try {
    // anoniem → geen zip
    $ctx = stream_context_create(['http' => ['ignore_errors' => true]]);
    $body = (string)file_get_contents("http://localhost:$port/api/download.php", false, $ctx);
    ok(!str_starts_with($body, 'PK'), 'anonymous gets no zip');

    // login
    $pw = pb_secrets()['admin_password'];
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => 'wachtwoord=' . urlencode($pw),
        'ignore_errors' => true, 'follow_location' => 0,
    ]]);
    file_get_contents("http://localhost:$port/admin/login.php", false, $ctx);
    $cookie = '';
    foreach ($http_response_header as $h) {
        if (stripos($h, 'Set-Cookie:') === 0) $cookie = trim(explode(';', substr($h, 11))[0]);
    }

    $ctx = stream_context_create(['http' => ['header' => "Cookie: $cookie", 'ignore_errors' => true]]);
    $zipBody = (string)file_get_contents("http://localhost:$port/api/download.php", false, $ctx);
    ok(str_starts_with($zipBody, 'PK'), 'zip magic bytes');

    $zipFile = $tmp . '/dl.zip';
    file_put_contents($zipFile, $zipBody);
    $zip = new ZipArchive();
    ok($zip->open($zipFile) === true, 'zip opens');
    ok($zip->numFiles === 2, 'zip contains active+archived, not hidden (2 files)');
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) $names[] = $zip->getNameIndex($i);
    ok(in_array($a['filename'], $names, true) && in_array($b['filename'], $names, true), 'correct files in zip');
    $zip->close();
} finally {
    proc_terminate($server);
    proc_close($server);
}
```

- [ ] **Step 2: Run test om te verifiëren dat hij faalt**

Run: `& $PHP tests\test-download.php`
Expected: FAIL bij 'zip magic bytes' (404).

- [ ] **Step 3: Implementeer**

`api/download.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';

if (!auth_check()) {
    header('Location: /admin/login.php');
    exit;
}

$rows = array_merge(photos_list('active', 0, 10000), photos_list('archived', 0, 10000));
$zipPath = tempnam(sys_get_temp_dir(), 'pbzip');
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::OVERWRITE);
foreach ($rows as $row) {
    $file = pb_uploads_dir() . '/' . $row['filename'];
    if (is_file($file)) {
        $zip->addFile($file, $row['filename']);
    }
}
$zip->close();

$naam = 'fotos-' . date('Y-m-d') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $naam . '"');
header('Content-Length: ' . (string)filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
```

- [ ] **Step 4: Run tests**

Run: `& $PHP tests\run-all.php`
Expected: alle `OK`, exit 0.

- [ ] **Step 5: Commit**

```powershell
git add api/download.php tests/test-download.php
git commit -m "feat: zip download of active and archived photos"
```

---

### Task 16: Diagnose-pagina + volledige lokale smoke-test

**Files:**
- Create: `admin/diagnose.php`

**Interfaces:**
- Consumes: `auth_require_page`
- Produces: admin-pagina die de hosting-vereisten checkt — te gebruiken direct na de eerste deploy naar photobooth.g-bit.be.

- [ ] **Step 1: Schrijf diagnose.php**

`admin/diagnose.php`:
```php
<?php
require __DIR__ . '/../app/bootstrap.php';
auth_require_page();

$checks = [
    'PHP-versie ≥ 8.2'        => version_compare(PHP_VERSION, '8.2.0', '>='),
    'pdo_sqlite'              => extension_loaded('pdo_sqlite'),
    'gd'                      => extension_loaded('gd'),
    'zip (ZipArchive)'        => class_exists('ZipArchive'),
    'fileinfo'                => extension_loaded('fileinfo'),
    'mbstring'                => extension_loaded('mbstring'),
    'data/ beschrijfbaar'     => is_writable(dirname(db()->query('PRAGMA database_list')->fetch()['file'])),
    'uploads/ beschrijfbaar'  => is_writable(pb_uploads_dir()),
    'secrets.php aanwezig'    => pb_secrets() !== [],
    'admin-wachtwoord actief' => setting_get('admin_password_hash') !== '',
    'HTTPS actief'            => ($_SERVER['HTTPS'] ?? '') !== '' || PHP_SAPI === 'cli-server',
];
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');

page_header('Diagnose', 'page-admin');
?>
<nav class="topnav">
  <a href="/admin/">Foto's</a>
  <a href="/admin/diagnose.php" class="active">Diagnose</a>
</nav>
<main class="wrap">
  <h1 class="display">Diagnose</h1>
  <?php leaf_divider(); ?>
  <div class="card">
    <?php foreach ($checks as $label => $pass): ?>
      <p style="margin: 0.3rem 0">
        <span style="color: var(--<?= $pass ? 'c-sage-deep' : 'c-terracotta' ?>)"><?= $pass ? '✓' : '✗' ?></span>
        <?= htmlspecialchars($label) ?>
      </p>
    <?php endforeach; ?>
    <p class="field-hint">upload_max_filesize: <?= htmlspecialchars((string)$uploadMax) ?> · post_max_size: <?= htmlspecialchars((string)$postMax) ?> (moeten ≥ 16M zijn; zo niet: aanpassen in Plesk → PHP-instellingen)</p>
  </div>
</main>
<?php page_footer(); ?>
```

Voeg de link toe in de topnav van `admin/index.php` (na "QR &amp; kaartje"):
```php
  <a href="/admin/diagnose.php">Diagnose</a>
```

- [ ] **Step 2: Volledige lokale smoke-test (generale repetitie)**

Run: `& $PHP tests\run-all.php` → alles `OK`.
Run: `& $PHP -S localhost:8080` en doorloop:
1. `/admin/diagnose.php` → alle checks groen (behalve HTTPS, ok bij cli-server).
2. `/` → upload 2 foto's met filter "Warm" en een boodschap.
3. `/galerij.php` → beide zichtbaar met warme tint en boodschap.
4. `/slideshow.php` → foto's roteren met caption.
5. `/admin/` → verberg er één → weg uit galerij + slideshow-API.
6. `/admin/qr.php` → QR scanbaar.
7. `/api/download.php` (ingelogd) → ZIP met juiste inhoud.
8. **Mobiel op hetzelfde netwerk** (`php -S 0.0.0.0:8080`, telefoon naar `http://<pc-ip>:8080`): upload vanaf iPhone-galerij → foto staat rechtop (EXIF-check!), filters voelen vlot; zet vliegtuigmodus aan vóór "Verstuur" → status "in wachtrij…", vliegtuigmodus uit → gaat vanzelf naar "verzonden ✓".

- [ ] **Step 3: Commit**

```powershell
git add admin/diagnose.php admin/index.php
git commit -m "feat: hosting diagnostics page"
```

---

## Deploy-notities (na afronding, mét de gebruiker — niet autonoom pushen)

1. In Plesk voor photobooth.g-bit.be: PHP 8.2+ selecteren; `upload_max_filesize`/`post_max_size` ≥ 16M.
2. `config/secrets.php` op de server aanmaken (via Plesk-bestandsbeheer of SSH) met een sterk admin-wachtwoord voor Lotte & Stef.
3. Push → live. Meteen `/admin/diagnose.php` openen: alle checks groen?
4. End-to-end test op locatie-achtige omstandigheden (mobiele data).
5. MySQL-fallback alleen nodig als `pdo_sqlite` ✗ toont — dan `app/db.php` DSN aanpassen.

## Self-review checklist (uitgevoerd bij het schrijven van dit plan)

- **Spec-dekking:** gastenflow+filters (T6-T8), offline-queue (T7), galerij (T9), slideshow (T10), admin login/moderatie/instellingen/QR/ZIP (T11-T15), theming/herbruikbaarheid (T5, config in T1), beveiliging (T0 htaccess, T3 herencodering, T4 rate-limit, T11 auth/CSRF), hosting-verificatie + iPhone/offline-test (T16). Gastenboek bewust afwezig.
- **Geen placeholders:** elke taak bevat volledige code en exacte commando's.
- **Consistentie:** functienamen en HTTP-contracten in "Interfaces"-blokken komen overeen tussen producerende en consumerende taken (`photo_save`/`photos_list`/`setting_get`/`auth_require_api`/`processPhoto`/`enqueue`; API-shapes van `photos.php` gedeeld door gallery.js, slideshow.js en tests).

