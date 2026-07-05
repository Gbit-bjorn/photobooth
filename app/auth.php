<?php
declare(strict_types=1);

function auth_init(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        if (PHP_SAPI !== 'cli') {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => ($_SERVER['HTTPS'] ?? '') !== '',
            ]);
        }
        session_start();
    }
    if (setting_get('admin_password_hash') === '') {
        $initial = pb_secrets()['admin_password'] ?? '';
        if ($initial !== '' && $initial !== 'VERANDER-DIT-WACHTWOORD') {
            setting_set('admin_password_hash', password_hash($initial, PASSWORD_DEFAULT));
        }
    }
}

function auth_login(string $password): bool
{
    auth_init();
    $hash = setting_get('admin_password_hash');
    if ($hash === '' || !password_verify($password, $hash)) {
        sleep(1); // brute-force-rem
        return false;
    }
    if (PHP_SAPI !== 'cli') {
        session_regenerate_id(true);
    }
    $_SESSION['pb_admin'] = true;
    return true;
}

function auth_check(): bool
{
    auth_init();
    return ($_SESSION['pb_admin'] ?? false) === true;
}

function auth_logout(): void
{
    auth_init();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
        session_destroy();
    }
}

function auth_require_page(): void
{
    if (!auth_check()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function auth_require_api(): void
{
    if (!auth_check()) {
        json_out(['ok' => false, 'error' => 'Niet ingelogd.'], 401);
    }
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), (string)$sent)) {
        json_out(['ok' => false, 'error' => 'Ongeldige sessie — herlaad de pagina.'], 403);
    }
}

function csrf_token(): string
{
    auth_init();
    if (empty($_SESSION['pb_csrf'])) {
        $_SESSION['pb_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pb_csrf'];
}
