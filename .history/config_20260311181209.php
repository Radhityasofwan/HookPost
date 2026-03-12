<?php
/* =========================================================
 * FILE: config.php
 * PURPOSE: Global config loader
 * ========================================================= */

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

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta');

define('APP_NAME', $_ENV['APP_NAME'] ?? 'Social Publisher');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'local');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOL));
define('APP_URL', rtrim($_ENV['APP_URL'] ?? '', '/'));

define('DB_DRIVER', $_ENV['DB_DRIVER'] ?? 'sqlite');
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
define('META_REDIRECT_URI', $_ENV['META_REDIRECT_URI'] ?? '');

define('TIKTOK_CLIENT_KEY', $_ENV['TIKTOK_CLIENT_KEY'] ?? '');
define('TIKTOK_CLIENT_SECRET', $_ENV['TIKTOK_CLIENT_SECRET'] ?? '');
define('TIKTOK_REDIRECT_URI', $_ENV['TIKTOK_REDIRECT_URI'] ?? '');

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}