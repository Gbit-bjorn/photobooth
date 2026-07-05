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
