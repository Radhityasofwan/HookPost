<?php
/* =========================================================
 * PAGE: posts_delete.php
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login();

/* SECTION: AUTH */
// Auth enforced above.

/* SECTION: HANDLE REQUEST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('danger', 'ID tidak valid.');
    redirect_to('posts.php');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $pdo->commit();
    flash_set('success', 'Konten berhasil dihapus.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_line('posts delete error: ' . $e->getMessage(), 'app.log');
    flash_set('danger', APP_DEBUG ? $e->getMessage() : 'Gagal menghapus konten.');
}

redirect_to('posts.php');

/* SECTION: LOAD DATA */
// Not applicable.

/* SECTION: HTML */
// Not applicable.

/* SECTION: INLINE CSS */
// Not applicable.

/* SECTION: INLINE JS */
// Not applicable.
