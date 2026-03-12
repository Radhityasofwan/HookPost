<?php
/* =========================================================
 * FILE: cron_publisher.php
 * PURPOSE: Publish pending jobs
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* SECTION: AUTH */
// Not applicable.

/* SECTION: HANDLE REQUEST */
function save_publish_log($pdo, $job_id, $variant_id, $platform, $level, $message, $response = null) {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO publish_logs
        (publish_job_id, post_variant_id, platform, log_level, message, response_snapshot, created_at)
        VALUES (:job_id, :variant_id, :platform, :level, :message, :response, :now)");
    $stmt->execute([
        ':job_id' => $job_id,
        ':variant_id' => $variant_id,
        ':platform' => $platform,
        ':level' => $level,
        ':message' => $message,
        ':response' => $response,
        ':now' => $now,
    ]);
}

function load_variant_payload($pdo, $variant_id, $platform) {
    $stmt = $pdo->prepare("
        SELECT pv.id, pv.caption, pv.platform,
               pv.publish_mode,
               ma.file_path, ma.mime_type, ma.media_kind,
               ch.platform_account_id, ch.access_token, ch.token_expiry
        FROM post_variants pv
        JOIN post_media pm ON pm.post_variant_id = pv.id
        JOIN media_assets ma ON ma.id = pm.media_asset_id
        JOIN channels ch ON ch.platform = :platform
        WHERE pv.id = :variant_id
          AND ch.status = 'connected'
          AND ch.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([
        ':variant_id' => $variant_id,
        ':platform' => $platform,
    ]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Channel/platform tidak terhubung atau data variant tidak lengkap.');
    }
    if (empty($row['access_token']) || empty($row['platform_account_id'])) {
        throw new RuntimeException('Token atau account id belum tersedia.');
    }
    return $row;
}

function load_variant_media_list($pdo, $variant_id) {
    $stmt = $pdo->prepare("
        SELECT ma.file_path, ma.mime_type, ma.media_kind
        FROM post_media pm
        JOIN media_assets ma ON ma.id = pm.media_asset_id
        WHERE pm.post_variant_id = :variant_id
        ORDER BY ma.id ASC
    ");
    $stmt->execute([':variant_id' => $variant_id]);
    return $stmt->fetchAll();
}

function publish_instagram_variant($pdo, $variant_id) {
    $data = load_variant_payload($pdo, $variant_id, 'instagram');
    $ig_user_id = $data['platform_account_id'];
    $access_token = $data['access_token'];

    $media_url = build_public_media_url($data['file_path']);
    $params = [
        'caption' => $data['caption'] ?? '',
    ];
    if ($data['media_kind'] === 'video') {
        $params['video_url'] = $media_url;
        $params['media_type'] = 'VIDEO';
    } else {
        $params['image_url'] = $media_url;
    }

    $create = meta_graph_post("/{$ig_user_id}/media", $params, $access_token);
    $container_id = $create['id'] ?? null;
    if (!$container_id) {
        throw new RuntimeException('IG media container gagal dibuat.');
    }

    $status_snapshot = null;
    if ($data['media_kind'] === 'video') {
        $max_tries = 10;
        $tries = 0;
        while ($tries < $max_tries) {
            $status_snapshot = meta_graph_get("/{$container_id}", ['fields' => 'status_code'], $access_token);
            $status = $status_snapshot['status_code'] ?? 'UNKNOWN';
            if ($status === 'FINISHED') break;
            if ($status === 'ERROR') {
                throw new RuntimeException('IG container error.');
            }
            sleep(3);
            $tries++;
        }
        if (($status_snapshot['status_code'] ?? '') !== 'FINISHED') {
            throw new RuntimeException('IG container timeout.');
        }
    }

    $publish = meta_graph_post("/{$ig_user_id}/media_publish", [
        'creation_id' => $container_id,
    ], $access_token);

    $external_post_id = $publish['id'] ?? null;
    if (!$external_post_id) {
        throw new RuntimeException('IG publish gagal.');
    }

    return [
        'external_post_id' => $external_post_id,
        'external_url' => null,
        'payload_snapshot' => json_encode([
            'create' => $create,
            'status' => $status_snapshot,
            'publish' => $publish,
        ], JSON_UNESCAPED_SLASHES),
    ];
}

function publish_facebook_variant($pdo, $variant_id) {
    $data = load_variant_payload($pdo, $variant_id, 'facebook');
    $page_id = $data['platform_account_id'];
    $access_token = $data['access_token'];
    $media_url = build_public_media_url($data['file_path']);

    if ($data['media_kind'] === 'video') {
        $response = meta_graph_post("/{$page_id}/videos", [
            'file_url' => $media_url,
            'description' => $data['caption'] ?? '',
        ], $access_token);
    } else {
        $response = meta_graph_post("/{$page_id}/photos", [
            'url' => $media_url,
            'caption' => $data['caption'] ?? '',
        ], $access_token);
    }

    $external_post_id = $response['post_id'] ?? $response['id'] ?? null;
    if (!$external_post_id) {
        throw new RuntimeException('FB publish gagal.');
    }

    return [
        'external_post_id' => $external_post_id,
        'external_url' => null,
        'payload_snapshot' => json_encode([
            'publish' => $response,
        ], JSON_UNESCAPED_SLASHES),
    ];
}

function publish_threads_variant($pdo, $variant_id) {
    $data = load_variant_payload($pdo, $variant_id, 'threads');
    $threads_user_id = $data['platform_account_id'];
    $access_token = $data['access_token'];
    $caption = $data['caption'] ?? '';

    $media_list = load_variant_media_list($pdo, $variant_id);
    $payload_snapshot = ['create' => [], 'publish' => null];

    $container_id = null;
    if (!$media_list) {
        // Text-only
        $create = threads_api_post("/{$threads_user_id}/threads", [
            'media_type' => 'TEXT',
            'text' => $caption,
        ], $access_token);
        $container_id = $create['id'] ?? null;
        $payload_snapshot['create'][] = $create;
    } elseif (count($media_list) === 1) {
        $media = $media_list[0];
        $media_url = build_public_media_url($media['file_path']);
        if ($media['media_kind'] === 'video') {
            $create = threads_api_post("/{$threads_user_id}/threads", [
                'media_type' => 'VIDEO',
                'video_url' => $media_url,
                'text' => $caption,
            ], $access_token);
        } else {
            $create = threads_api_post("/{$threads_user_id}/threads", [
                'media_type' => 'IMAGE',
                'image_url' => $media_url,
                'text' => $caption,
            ], $access_token);
        }
        $container_id = $create['id'] ?? null;
        $payload_snapshot['create'][] = $create;
    } else {
        // Carousel sederhana (hanya image) jika memungkinkan
        foreach ($media_list as $m) {
            if ($m['media_kind'] !== 'image') {
                throw new RuntimeException('Threads carousel hanya mendukung image pada implementasi sederhana ini.');
            }
        }
        $child_ids = [];
        foreach ($media_list as $m) {
            $media_url = build_public_media_url($m['file_path']);
            $child = threads_api_post("/{$threads_user_id}/threads", [
                'media_type' => 'IMAGE',
                'image_url' => $media_url,
                'is_carousel_item' => 'true',
            ], $access_token);
            $payload_snapshot['create'][] = $child;
            if (!empty($child['id'])) $child_ids[] = $child['id'];
        }
        if (!$child_ids) {
            throw new RuntimeException('Gagal membuat item carousel.');
        }
        $create = threads_api_post("/{$threads_user_id}/threads", [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $child_ids),
            'text' => $caption,
        ], $access_token);
        $container_id = $create['id'] ?? null;
        $payload_snapshot['create'][] = $create;
    }

    if (!$container_id) {
        throw new RuntimeException('Threads container gagal dibuat.');
    }

    // Poll status sederhana (timeout aman)
    $status_snapshot = null;
    for ($i = 0; $i < 10; $i++) {
        $status_snapshot = threads_api_get("/{$container_id}", ['fields' => 'status'], $access_token);
        $status = $status_snapshot['status'] ?? '';
        if ($status === 'FINISHED' || $status === 'READY') break;
        if ($status === 'ERROR') {
            throw new RuntimeException('Threads container error.');
        }
        sleep(3);
    }

    $publish = threads_api_post("/{$threads_user_id}/threads_publish", [
        'creation_id' => $container_id,
    ], $access_token);

    $external_post_id = $publish['id'] ?? null;
    if (!$external_post_id) {
        throw new RuntimeException('Threads publish gagal.');
    }

    $payload_snapshot['status'] = $status_snapshot;
    $payload_snapshot['publish'] = $publish;

    return [
        'external_post_id' => $external_post_id,
        'external_url' => null,
        'payload_snapshot' => json_encode($payload_snapshot, JSON_UNESCAPED_SLASHES),
    ];
}

function publish_tiktok_variant($pdo, $variant_id) {
    $data = load_variant_payload($pdo, $variant_id, 'tiktok');
    $access_token = $data['access_token'];
    $publish_mode = $data['publish_mode'] ?: 'direct';

    if (!empty($data['token_expiry'])) {
        $expiry = strtotime($data['token_expiry']);
        if ($expiry !== false && $expiry <= time()) {
            throw new RuntimeException('Token TikTok expired.');
        }
    }

    if ($data['media_kind'] !== 'video') {
        throw new RuntimeException('TikTok hanya mendukung video.');
    }

    $video_url = build_public_media_url($data['file_path']);
    $caption = $data['caption'] ?? '';

    $endpoint = $publish_mode === 'draft'
        ? 'post/publish/inbox/video/init/'
        : 'post/publish/video/init/';

    $payload = [
        'post_info' => [
            'title' => $caption,
        ],
        'source_info' => [
            'source' => 'PULL_FROM_URL',
            'video_url' => $video_url,
        ],
    ];

    $response = tiktok_api_post($endpoint, $payload, true, true);
    $publish_id = $response['data']['publish_id'] ?? null;
    if (!$publish_id) {
        throw new RuntimeException('TikTok publish init gagal.');
    }

    return [
        'external_post_id' => $publish_id,
        'external_url' => null,
        'payload_snapshot' => json_encode([
            'publish' => $response,
        ], JSON_UNESCAPED_SLASHES),
    ];
}

log_line('cron_publisher started', 'cron.log');

$now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("
    SELECT pj.id, pj.post_variant_id, pj.attempts, pj.next_retry_at,
           pv.platform, pv.caption
    FROM publish_jobs pj
    JOIN post_variants pv ON pv.id = pj.post_variant_id
    WHERE pj.status IN ('pending','retrying')
      AND (pj.next_retry_at IS NULL OR pj.next_retry_at <= :now)
    ORDER BY pj.id ASC
    LIMIT 10
");
$stmt->execute([':now' => $now]);
$jobs = $stmt->fetchAll();

foreach ($jobs as $job) {
    $job_id = (int) $job['id'];
    $variant_id = (int) $job['post_variant_id'];
    $platform = $job['platform'];

    if (!lock_publish_job($pdo, $job_id, 300)) {
        continue;
    }
    $pdo->prepare("UPDATE post_variants SET status='processing' WHERE id=?")
        ->execute([$variant_id]);

    try {
        $result = null;
        if ($platform === 'instagram') {
            $result = publish_instagram_variant($pdo, $variant_id);
        } elseif ($platform === 'facebook') {
            $result = publish_facebook_variant($pdo, $variant_id);
        } elseif ($platform === 'threads') {
            $result = publish_threads_variant($pdo, $variant_id);
        } elseif ($platform === 'tiktok') {
            $result = publish_tiktok_variant($pdo, $variant_id);
        } else {
            throw new RuntimeException('Platform belum didukung di publisher.');
        }

        $pdo->prepare("UPDATE post_variants
            SET status='published',
                external_post_id=:external_post_id,
                external_url=:external_url,
                published_at=:published_at,
                payload_snapshot=:payload_snapshot
            WHERE id=:id")
            ->execute([
                ':external_post_id' => $result['external_post_id'],
                ':external_url' => $result['external_url'],
                ':published_at' => $now,
                ':payload_snapshot' => $result['payload_snapshot'],
                ':id' => $variant_id,
            ]);
        release_publish_job($pdo, $job_id, 'success', null, null);

        save_publish_log($pdo, $job_id, $variant_id, $platform, 'info', 'Publish success', $result['payload_snapshot']);
        log_line("job success #{$job_id}", 'cron.log');
    } catch (Throwable $e) {
        $attempts = ((int) $job['attempts']) + 1;
        $status = $attempts >= 3 ? 'failed' : 'retrying';
        $next_retry = $status === 'retrying' ? date('Y-m-d H:i:s', time() + 300) : null;
        $error_payload = json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);

        $pdo->prepare("UPDATE post_variants
            SET status='failed',
                failed_reason=:reason,
                payload_snapshot=COALESCE(payload_snapshot, :payload)
            WHERE id=:id")
            ->execute([
                ':reason' => $e->getMessage(),
                ':payload' => $error_payload,
                ':id' => $variant_id,
            ]);
        if ($status === 'retrying') {
            mark_job_retry($pdo, $job_id, $attempts, $next_retry, $e->getMessage());
        } else {
            release_publish_job($pdo, $job_id, 'failed', $e->getMessage(), null);
            $pdo->prepare("UPDATE publish_jobs SET attempts=? WHERE id=?")->execute([$attempts, $job_id]);
        }

        save_publish_log($pdo, $job_id, $variant_id, $platform, 'error', 'Publish failed', $error_payload);

        log_line("job failed #{$job_id}: " . $e->getMessage(), 'cron.log');
    }
}

log_line('cron_publisher finished', 'cron.log');

/* SECTION: LOAD DATA */
// Not applicable.

/* SECTION: HTML */
// Not applicable.

/* SECTION: INLINE CSS */
// Not applicable.

/* SECTION: INLINE JS */
// Not applicable.
