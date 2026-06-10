<?php
ini_set('implicit_flush', '0');
ob_implicit_flush(false);

if (ob_get_level() === 0) {
    ob_start();
}

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    if (empty($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
    $maxIdleSeconds = 60 * 60 * 2; // 2 jam
    if ((time() - (int)$_SESSION['last_activity']) > $maxIdleSeconds) {
        session_unset();
        session_destroy();
        redirect(app_url('login.php?expired=1'));
    }
    $_SESSION['last_activity'] = time();
}
