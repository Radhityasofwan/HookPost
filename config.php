<?php
/* =========================================================
 * FILE: config.php
 * PURPOSE: Global config loader
 * ========================================================= */

/* SECTION: BOOTSTRAP */
if (!function_exists('env_load')) {
    function env_load($path)
    {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

env_load(__DIR__ . '/.env');
if (file_exists(__DIR__ . '/.env.local')) {
    env_load(__DIR__ . '/.env.local');
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta');

define('APP_NAME', $_ENV['APP_NAME'] ?? 'Social Publisher');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'local');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOL));
define('APP_URL', rtrim($_ENV['APP_URL'] ?? '', '/'));

define('DB_DRIVER', $_ENV['DB_DRIVER'] ?? 'mysql');
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'social_publisher');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_SQLITE_PATH', $_ENV['DB_SQLITE_PATH'] ?? (__DIR__ . '/database.sqlite'));

define('SESSION_NAME', $_ENV['SESSION_NAME'] ?? 'sp_session');
define('CSRF_KEY', $_ENV['CSRF_KEY'] ?? 'change_this');

define('UPLOAD_DIR', __DIR__ . '/' . ($_ENV['UPLOAD_DIR'] ?? 'uploads'));
define('MAX_UPLOAD_MB', (int) ($_ENV['MAX_UPLOAD_MB'] ?? 100));

define('META_APP_ID', $_ENV['META_APP_ID'] ?? '');
define('META_APP_SECRET', $_ENV['META_APP_SECRET'] ?? '');
define('META_REDIRECT_URI', $_ENV['META_REDIRECT_URI'] ?? (APP_URL ? (APP_URL . '/channels?meta_callback=1') : ''));
define('META_SCOPES', $_ENV['META_SCOPES'] ?? '');

define('TIKTOK_CLIENT_KEY', $_ENV['TIKTOK_CLIENT_KEY'] ?? '');
define('TIKTOK_CLIENT_SECRET', $_ENV['TIKTOK_CLIENT_SECRET'] ?? '');
define('TIKTOK_REDIRECT_URI', $_ENV['TIKTOK_REDIRECT_URI'] ?? (APP_URL ? (APP_URL . '/channels?tiktok_callback=1') : ''));

// Fallback redirect if configured path points to non-existent file (common misconfig)
if (APP_URL) {
    $meta_path = parse_url(META_REDIRECT_URI, PHP_URL_PATH);
    if ($meta_path && (str_contains($meta_path, '/auth/') || str_ends_with($meta_path, '/channels')) && !file_exists(__DIR__ . $meta_path)) {
        define('META_REDIRECT_URI_FALLBACK', APP_URL . '/channels?meta_callback=1');
    }
    $tiktok_path = parse_url(TIKTOK_REDIRECT_URI, PHP_URL_PATH);
    if ($tiktok_path && (str_contains($tiktok_path, '/auth/') || str_ends_with($tiktok_path, '/channels')) && !file_exists(__DIR__ . $tiktok_path)) {
        define('TIKTOK_REDIRECT_URI_FALLBACK', APP_URL . '/channels?tiktok_callback=1');
    }
}

/* SECTION: AUTH */
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/* SECTION: HANDLE REQUEST */
// No request handling in config.

/* SECTION: LOAD DATA */
// No data loading beyond env and config.

/* SECTION: HTML */
// Not applicable.

/* SECTION: INLINE CSS */
// Not applicable.

/* SECTION: INLINE JS */
// Not applicable.
