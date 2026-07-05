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
ok($w === 2400 && $h === 1200, 'resized to max 2400 long side');
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

// origineel bewaren naast de verwerkte versie
$img2 = imagecreatetruecolor(500, 400);
$src2 = $tmp . '/bron2.jpg';
imagejpeg($img2, $src2, 90);
$origKopie = $tmp . '/origineel-upload.jpg';
copy($src2, $origKopie);
$saved2 = photo_save($src2, 'Met Origineel', '', $origKopie, 'IMG_1234.JPG');
$hex = substr($saved2['filename'], 2, 16);
$origs = glob(pb_originals_dir() . "/o_{$hex}.*");
ok(count($origs) === 1 && str_ends_with($origs[0], '.jpg'), 'original stored');

// likes: verhogen, verlagen, niet onder nul, alleen actieve foto's
ok(photo_like($saved2['id']) === 1, 'like increments');
ok(photo_like($saved2['id']) === 2, 'like increments again');
ok(photo_like($saved2['id'], true) === 1, 'unlike decrements');
ok(photo_like($saved2['id'], true) === 0, 'unlike to zero');
ok(photo_like($saved2['id'], true) === 0, 'never below zero');
photo_set_status($saved2['id'], 'hidden');
ok(photo_like($saved2['id']) === null, 'like on hidden photo rejected');
photo_set_status($saved2['id'], 'active');

// delete ruimt ook het origineel op
photo_delete($saved2['id']);
ok(glob(pb_originals_dir() . "/o_{$hex}.*") === [], 'original removed on delete');
