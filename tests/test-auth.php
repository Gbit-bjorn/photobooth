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

// secrets bevat lokaal een admin_password (Task 1); init zet de hash
auth_init();
$hash = setting_get('admin_password_hash');
ok($hash !== '', 'hash initialized from secrets');
ok(password_verify(pb_secrets()['admin_password'], $hash), 'hash matches secret');

ok(auth_check() === false, 'not logged in initially');
$t0 = microtime(true);
ok(auth_login('fout-wachtwoord') === false, 'wrong password rejected');
ok(microtime(true) - $t0 >= 1.0, 'failed login is slowed');
ok(auth_check() === false, 'still not logged in');

ok(auth_login(pb_secrets()['admin_password']) === true, 'correct password accepted');
ok(auth_check() === true, 'logged in');
ok(strlen(csrf_token()) >= 32, 'csrf token present');
$t = csrf_token();
ok(csrf_token() === $t, 'csrf token stable in session');

auth_logout();
ok(auth_check() === false, 'logged out');
