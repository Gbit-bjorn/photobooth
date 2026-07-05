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
    // migratie: likes-kolom (toegevoegd na eerste release)
    $kolommen = $pdo->query('PRAGMA table_info(photos)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('likes', $kolommen, true)) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN likes INTEGER NOT NULL DEFAULT 0');
    }
    return $pdo;
}
