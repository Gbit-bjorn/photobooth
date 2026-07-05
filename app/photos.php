<?php
declare(strict_types=1);

const PB_PHOTO_STATUSES = ['active', 'hidden', 'archived'];
const PB_MAX_DIM = 2400;
const PB_THUMB_DIM = 480;
const PB_JPEG_QUALITY = 90;

function pb_uploads_dir(): string
{
    return getenv('PHOTOBOOTH_UPLOADS_DIR') ?: PB_ROOT . '/uploads';
}

/** Originelen staan buiten de publieke map (privacy: EXIF/GPS blijft erin). */
function pb_originals_dir(): string
{
    $dir = (getenv('PHOTOBOOTH_DATA_DIR') ?: PB_ROOT . '/data') . '/originals';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Valideert en herencodeert een geüploade afbeelding (neutraliseert payloads,
 * stript EXIF incl. GPS), schaalt naar max PB_MAX_DIM en maakt een thumb.
 */
function photo_save(
    string $tmpPath,
    string $guestName,
    string $message,
    ?string $originalTmp = null,
    string $originalName = ''
): array {
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

    // Origineel ongewijzigd bewaren (volle resolutie, voor de ZIP-download).
    if ($originalTmp !== null && is_file($originalTmp)) {
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($originalTmp);
        if (str_starts_with($mime, 'image/')) {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true)) {
                $ext = 'jpg';
            }
            $bestand = pb_originals_dir() . "/o_{$hex}.{$ext}";
            if (!@rename($originalTmp, $bestand)) {
                @copy($originalTmp, $bestand);
            }
        }
    }

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
        'SELECT id, filename, thumb, guest_name, message, status, likes, created_at
         FROM photos WHERE status = ? AND id > ? ORDER BY id DESC LIMIT ?'
    );
    $stmt->execute([$status, $sinceId, $limit]);
    return $stmt->fetchAll();
}

/** Verhoogt of verlaagt de like-teller; geeft de nieuwe stand of null. */
function photo_like(int $id, bool $unlike = false): ?int
{
    $delta = $unlike ? -1 : 1;
    $stmt = db()->prepare(
        'UPDATE photos SET likes = MAX(0, likes + ?) WHERE id = ? AND status = ?'
    );
    $stmt->execute([$delta, $id, 'active']);
    if ($stmt->rowCount() !== 1) {
        return null;
    }
    $q = db()->prepare('SELECT likes FROM photos WHERE id = ?');
    $q->execute([$id]);
    return (int)$q->fetchColumn();
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
    foreach (glob(pb_originals_dir() . '/o_' . substr($row['filename'], 2, 16) . '.*') ?: [] as $orig) {
        @unlink($orig);
    }
    $del = db()->prepare('DELETE FROM photos WHERE id = ?');
    $del->execute([$id]);
    return true;
}
