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

ok(setting_get('upload_enabled') === '1', 'default upload_enabled 1');
ok(setting_get('filters_enabled') === '1', 'default filters_enabled 1');
ok(setting_get('welcome_text') === pb_event()['welcome_text'], 'welcome_text default from event');
ok(setting_get('tagline') === pb_event()['tagline'], 'tagline default from event');
ok(setting_get('thanks_text') === pb_event()['thanks_text'], 'thanks_text default from event');
ok(setting_get('gallery_subtitle') !== '', 'gallery_subtitle has default');

setting_set('upload_enabled', '0');
ok(setting_get('upload_enabled') === '0', 'setting_set persists');
setting_set('upload_enabled', '1');
ok(setting_get('upload_enabled') === '1', 'setting_set overwrites');

$all = settings_all();
ok($all['filters_enabled'] === '1' && array_key_exists('gallery_public', $all), 'settings_all merged');

// rate limiting: 3 hits toegestaan binnen venster, 4e geweigerd
ok(rate_ok('1.2.3.4', 3, 600) === true, 'rate hit 1');
ok(rate_ok('1.2.3.4', 3, 600) === true, 'rate hit 2');
ok(rate_ok('1.2.3.4', 3, 600) === true, 'rate hit 3');
ok(rate_ok('1.2.3.4', 3, 600) === false, 'rate hit 4 blocked');
ok(rate_ok('5.6.7.8', 3, 600) === true, 'other ip unaffected');
