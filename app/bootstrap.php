<?php
declare(strict_types=1);

define('PB_ROOT', dirname(__DIR__));

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/rate.php';
require_once __DIR__ . '/photos.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/layout.php';
// Volgende requires komen er in latere taken bij:
// auth.php

function pb_event(): array
{
    static $cfg = null;
    return $cfg ??= require PB_ROOT . '/config/event.php';
}

function pb_filters(): array
{
    static $cfg = null;
    return $cfg ??= require PB_ROOT . '/config/filters.php';
}

function pb_secrets(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = PB_ROOT . '/config/secrets.php';
        $cfg = is_file($file) ? require $file : [];
    }
    return $cfg;
}
