<?php
declare(strict_types=1);

function pb_setting_defaults(): array
{
    return [
        'upload_enabled'      => '1',
        'filters_enabled'     => '1',
        'gallery_public'      => '1',
        'tagline'             => pb_event()['tagline'],
        'welcome_text'        => pb_event()['welcome_text'],
        'thanks_text'         => pb_event()['thanks_text'],
        'gallery_subtitle'    => 'Herinneringen van onze dag',
        'slideshow_enabled'   => '1',
        'slide_seconds'       => '7',
        'slide_transition'    => 'fade',  /* fade | drift | zoom */
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
