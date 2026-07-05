<?php
// Lichtgewicht map id → likes van alle actieve foto's (voor live tellers).
require __DIR__ . '/../app/bootstrap.php';

$map = [];
foreach (db()->query("SELECT id, likes FROM photos WHERE status = 'active'") as $row) {
    $map[(string)$row['id']] = (int)$row['likes'];
}
json_out(['ok' => true, 'likes' => $map]);
