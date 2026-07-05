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
