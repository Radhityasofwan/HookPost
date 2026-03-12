<?php
/* =========================================================
 * FILE: router.php
 * PURPOSE: Clean URL router for PHP built-in server
 * ========================================================= */
/* SECTION: BOOTSTRAP */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
if ($uri === '') $uri = '/dashboard';

/* SECTION: AUTH */
// Not applicable.

/* SECTION: HANDLE REQUEST */
$routes = [
    '/dashboard' => 'dashboard.php',
    '/posts' => 'posts.php',
    '/post-form' => 'post_form.php',
    '/channels' => 'channels.php',
    '/logs' => 'logs.php',
    '/login' => 'login.php',
    '/logout' => 'logout.php',
    '/terms' => 'terms.php',
    '/privacy' => 'privacy.php',
    '/data-deletion' => 'data_deletion.php',
    '/posts-delete' => 'posts_delete.php',
    '/posts-retry' => 'posts_retry.php',
    '/webhook-meta' => 'webhook_meta.php',
    '/webhook-tiktok' => 'webhook_tiktok.php',
];

if (isset($routes[$uri])) {
    require __DIR__ . '/' . $routes[$uri];
    return true;
}

/* SECTION: LOAD DATA */
// Let the built-in server handle existing files.
$path = __DIR__ . $uri;
if ($uri !== '/' && file_exists($path) && !is_dir($path)) {
    return false;
}

/* SECTION: HTML */
http_response_code(404);
echo '404 Not Found';

/* SECTION: INLINE CSS */
// Not applicable.

/* SECTION: INLINE JS */
// Not applicable.
