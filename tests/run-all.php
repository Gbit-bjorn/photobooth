<?php
declare(strict_types=1);
$fail = 0;
foreach (glob(__DIR__ . '/test-*.php') as $test) {
    echo "== " . basename($test) . " ==\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($test), $code);
    if ($code !== 0) $fail = 1;
}
exit($fail);
