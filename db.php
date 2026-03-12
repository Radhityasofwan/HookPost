<?php
/* =========================================================
 * FILE: db.php
 * PURPOSE: PDO database connection
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/config.php';

/* SECTION: AUTH */
// No auth logic here.

/* SECTION: HANDLE REQUEST */
try {
    if (DB_DRIVER === 'sqlite') {
        $dsn = "sqlite:" . DB_SQLITE_PATH;
        $pdo = new PDO($dsn);
    } else {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database connection failed: ' . (APP_DEBUG ? $e->getMessage() : 'Check configuration.'));
}

/* SECTION: LOAD DATA */
// Connection is ready to use via $pdo.

/* SECTION: HTML */
// Not applicable.

/* SECTION: INLINE CSS */
// Not applicable.

/* SECTION: INLINE JS */
// Not applicable.
