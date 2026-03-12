<?php
/* =========================================================
 * PAGE: posts_retry.php
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

$now = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    $variant_stmt = $pdo->prepare("SELECT id FROM post_variants WHERE post_id = :post_id");
    $variant_stmt->execute([':post_id' => $id]);
    $variants = $variant_stmt->fetchAll();

    if (!$variants) {
        throw new RuntimeException('Post tidak memiliki variant.');
    }

    $update_variant = $pdo->prepare("UPDATE post_variants SET status = 'scheduled' WHERE id = :id");
    $insert_job = $pdo->prepare("INSERT INTO publish_jobs (post_variant_id, status, attempts, next_retry_at, created_at)
                                 VALUES (:post_variant_id, 'pending', 0, NULL, :created_at)");

    foreach ($variants as $row) {
        $variant_id = (int) $row['id'];
        $update_variant->execute([':id' => $variant_id]);
        $insert_job->execute([
            ':post_variant_id' => $variant_id,
            ':created_at' => $now,
        ]);
    }

    $update_post = $pdo->prepare("UPDATE posts SET status = 'scheduled', scheduled_at = COALESCE(scheduled_at, :now) WHERE id = :id");
    $update_post->execute([':now' => $now, ':id' => $id]);

    $pdo->commit();
    flash_set('success', 'Retry publish dijadwalkan.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_line('posts retry error: ' . $e->getMessage(), 'app.log');
    flash_set('danger', APP_DEBUG ? $e->getMessage() : 'Gagal membuat ulang job.');
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
