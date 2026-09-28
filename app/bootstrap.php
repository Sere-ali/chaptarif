<?php
/**
 * ChapTarif — amorçage de l'application
 * Chargé par public/index.php avant toute route.
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');

function env(string $key, $default = null)
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($v === null || $v === '') ? $default : $v;
}

// Fichier .env local (développement uniquement — sur Render, les variables sont injectées)
if (is_file(ROOT . '/.env')) {
    foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (getenv($k) === false) putenv("$k=" . trim($v, "\"'"));
    }
}

date_default_timezone_set('Africa/Abidjan');
mb_internal_encoding('UTF-8');

define('APP_ENV', env('APP_ENV', 'production'));
define('APP_URL', rtrim((string) env('APP_URL', ''), '/'));
define('APP_KEY', (string) env('APP_KEY', 'dev-key-change-me-' . md5(ROOT)));

if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// ---- Session sécurisée -------------------------------------------------------
$https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
define('IS_HTTPS', $https);
if (PHP_SAPI !== 'cli') {
    session_name('CTSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(self)');
    if ($https) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

require APP . '/db.php';
require APP . '/helpers.php';
require APP . '/auth.php';
require APP . '/cloudinary.php';
require APP . '/pricing.php';
require APP . '/escrow.php';
require APP . '/payment.php';
require APP . '/sms.php';

db_migrate();
