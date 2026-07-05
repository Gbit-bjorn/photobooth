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
    'likes'      => (int)$r['likes'],
    'created_at' => $r['created_at'],
], $rows);
$latest = $photos === [] ? $since : max(array_column($photos, 'id'));
json_out(['ok' => true, 'latest' => $latest, 'photos' => $photos]);
