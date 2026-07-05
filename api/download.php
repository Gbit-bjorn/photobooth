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
$zip->addFromString('wensen.html', pb_wensen_html($rows));
foreach ($rows as $row) {
    $file = pb_uploads_dir() . '/' . $row['filename'];
    if (is_file($file)) {
        $zip->addFile($file, $row['filename']);
    }
    // origineel (volle resolutie, mét filter niet toegepast) in aparte map
    foreach (glob(pb_originals_dir() . '/o_' . substr($row['filename'], 2, 16) . '.*') ?: [] as $orig) {
        $zip->addFile($orig, 'originelen/' . basename($orig));
    }
}
$zip->close();

/**
 * Offline mini-album: toont na het uitpakken de foto's uit de zip zelf,
 * met naam, wens, hartjes en tijdstip — in de stijl van de uitnodiging.
 */
function pb_wensen_html(array $rows): string
{
    $ev = pb_event();
    $couple = htmlspecialchars($ev['couple']);
    $datum = htmlspecialchars($ev['date_display']);
    $items = '';
    foreach (array_reverse($rows) as $row) { // oudste eerst: chronologisch album
        $foto = htmlspecialchars($row['filename']);
        $naam = htmlspecialchars($row['guest_name']);
        $wens = htmlspecialchars($row['message']);
        $likes = (int)$row['likes'] > 0 ? '♥ ' . (int)$row['likes'] : '';
        $tijd = htmlspecialchars(substr($row['created_at'], 0, 16));
        $onderschrift = '';
        if ($naam !== '') $onderschrift .= "<strong>{$naam}</strong>";
        if ($wens !== '') $onderschrift .= "<span>{$wens}</span>";
        $items .= <<<HTML
    <figure>
      <img src="{$foto}" alt="" loading="lazy">
      <figcaption>{$onderschrift}<small>{$tijd} · {$likes}</small></figcaption>
    </figure>

HTML;
    }
    $aantal = count($rows);
    return <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wensen — {$couple}</title>
<style>
  body { margin: 0; background: #f4efe6; color: #3d4438; font-family: Georgia, 'Times New Roman', serif; }
  .wrap { max-width: 46rem; margin: 0 auto; padding: 3rem 1.25rem 4rem; }
  h1 { font-weight: 400; letter-spacing: 0.18em; text-transform: uppercase; text-align: center; margin: 0; }
  .sub { text-align: center; color: #666a5c; font-style: italic; margin: 0.5rem 0 3rem; }
  figure { margin: 0 0 2.5rem; background: #fff; border: 1px solid #ddd6c8; border-radius: 4px; padding: 10px; }
  img { width: 100%; height: auto; display: block; }
  figcaption { padding: 0.7rem 0.4rem 0.2rem; }
  figcaption strong { display: block; font-size: 1.15rem; letter-spacing: 0.05em; font-weight: 400; }
  figcaption span { display: block; color: #666a5c; margin-top: 0.15rem; }
  figcaption small { display: block; color: #a9613e; margin-top: 0.4rem; font-size: 0.8rem; }
</style>
</head>
<body>
<main class="wrap">
  <h1>{$couple}</h1>
  <p class="sub">{$datum} · {$aantal} gedeelde momenten en wensen</p>
{$items}</main>
</body>
</html>
HTML;
}

$naam = 'fotos-' . date('Y-m-d') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $naam . '"');
header('Content-Length: ' . (string)filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
