<?php
/* =========================================================
 * FILE: cron_scheduler.php
 * PURPOSE: Schedule publish jobs for due posts
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* SECTION: AUTH */
// Not applicable.

/* SECTION: HANDLE REQUEST */
log_line('cron_scheduler started', 'cron.log');

$now = date('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare("
        SELECT pv.id AS variant_id, p.id AS post_id, pv.platform
        FROM posts p
        JOIN post_variants pv ON pv.post_id = p.id
        WHERE p.overall_status = 'scheduled'
          AND p.scheduled_at IS NOT NULL
          AND p.scheduled_at <= :now
    ");
    $stmt->execute([':now' => $now]);
    $variants = $stmt->fetchAll();

    $health_cache = [];
    $check = $pdo->prepare("
        SELECT id FROM publish_jobs
        WHERE job_key = :job_key
        LIMIT 1
    ");
    $insert = $pdo->prepare("
        INSERT INTO publish_jobs (job_key, post_variant_id, status, attempts, next_retry_at, created_at)
        VALUES (:job_key, :variant_id, 'pending', 0, NULL, :created_at)
    ");
    $update_variant = $pdo->prepare("
        UPDATE post_variants SET status = 'scheduled' WHERE id = :variant_id
    ");

    foreach ($variants as $row) {
        $variant_id = (int) $row['variant_id'];
        $platform = $row['platform'];

        if (!isset($health_cache[$platform])) {
            $health_cache[$platform] = check_channel_health($pdo, $platform, true);
        }
        if (!$health_cache[$platform]['ok']) {
            log_line("skip job for {$platform}: " . $health_cache[$platform]['label'], 'cron.log');
            continue;
        }

        $job_key = make_job_key($variant_id);
        $check->execute([':job_key' => $job_key]);
        if ($check->fetch()) continue;

        $insert->execute([
            ':job_key' => $job_key,
            ':variant_id' => $variant_id,
            ':created_at' => $now,
        ]);
        $update_variant->execute([':variant_id' => $variant_id]);

        log_line("job created for variant {$variant_id}", 'cron.log');
    }
} catch (Throwable $e) {
    log_line('cron_scheduler error: ' . $e->getMessage(), 'cron.log');
}

log_line('cron_scheduler finished', 'cron.log');

/* SECTION: LOAD DATA */
// Not applicable.

/* SECTION: HTML */
// Not applicable.

/* SECTION: INLINE CSS */
// Not applicable.

/* SECTION: INLINE JS */
// Not applicable.
