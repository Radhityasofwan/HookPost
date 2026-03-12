<?php
/* =========================================================
 * FILE: helpers.php
 * PURPOSE: Shared helpers
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/config.php';

/* SECTION: AUTH */
function require_login() {
    if (empty($_SESSION['user_id'])) {
        redirect_to('login');
    }
}

function csrf_token() {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_input() {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify() {
    $token = $_POST['_csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('CSRF token mismatch');
    }
}

/* SECTION: HANDLE REQUEST */
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_to($url) {
    if (preg_match('#^https?://#i', $url)) {
        header("Location: $url");
    } else {
        $path = '/' . ltrim($url, '/');
        header("Location: $path");
    }
    exit;
}

function url($path = '') {
    return APP_URL . ($path ? '/' . ltrim($path, '/') : '');
}

function flash_set($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get() {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/* SECTION: LOAD DATA */
function format_datetime($datetime, $format = 'd M Y H:i') {
    if (!$datetime) return '-';
    try {
        return (new DateTime($datetime))->format($format);
    } catch (Throwable $e) {
        return $datetime;
    }
}

function app_status_badge($status) {
    $map = [
        'draft' => 'secondary',
        'scheduled' => 'warning',
        'published' => 'success',
        'failed' => 'danger',
        'processing' => 'info',
        'pending' => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function create_safe_filename($originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $base = preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $base = trim($base, '-');
    $base = $base ?: 'file';
    return $base . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
}

function detect_media_kind($mime) {
    if (str_starts_with((string) $mime, 'image/')) return 'image';
    if (str_starts_with((string) $mime, 'video/')) return 'video';
    return 'other';
}

function upload_media_file($file) {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }

    $maxBytes = MAX_UPLOAD_MB * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('File too large.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg','image/png','image/webp','image/gif','image/avif',
        'video/mp4','video/quicktime','video/webm','video/3gpp'
    ];

    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Unsupported file type.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    $safeName = create_safe_filename($file['name']);
    $target = UPLOAD_DIR . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return [
        'original_name' => $file['name'],
        'file_name' => $safeName,
        'file_path' => 'uploads/' . $safeName,
        'mime_type' => $mime,
        'file_size' => (int) $file['size'],
        'media_kind' => detect_media_kind($mime),
    ];
}

function log_line($message, $file = 'app.log') {
    $path = __DIR__ . '/storage/logs/' . $file;
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($path, $line, FILE_APPEND);
}

function http_get_json($url, $params = [], $headers = []) {
    if ($params) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) {
        throw new RuntimeException('HTTP GET failed: ' . $err);
    }
    $data = json_decode($res, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        $data = ['_raw' => $res];
    }
    if ($code >= 400) {
        $msg = $data['error']['message'] ?? $data['error_description'] ?? $data['_raw'] ?? $res;
        throw new RuntimeException('HTTP GET error: ' . $msg);
    }
    return $data;
}

function http_post_json($url, $params = [], $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) {
        throw new RuntimeException('HTTP POST failed: ' . $err);
    }
    $data = json_decode($res, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        $data = ['_raw' => $res];
    }
    if ($code >= 400) {
        $msg = $data['error']['message'] ?? $data['error_description'] ?? $data['_raw'] ?? $res;
        throw new RuntimeException('HTTP POST error: ' . $msg);
    }
    return $data;
}

function http_post_json_body($url, $json_body, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_body,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json; charset=UTF-8'], $headers),
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) {
        throw new RuntimeException('HTTP POST failed: ' . $err);
    }
    $data = json_decode($res, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        $data = ['_raw' => $res];
    }
    if ($code >= 400) {
        $msg = $data['error']['message'] ?? $data['error_description'] ?? $data['_raw'] ?? $res;
        throw new RuntimeException('HTTP POST error: ' . $msg);
    }
    return $data;
}

function meta_oauth_url($scopes, $redirect_uri, $state) {
    $base = 'https://www.facebook.com/v19.0/dialog/oauth';
    $params = [
        'client_id' => META_APP_ID,
        'redirect_uri' => $redirect_uri,
        'state' => $state,
        'response_type' => 'code',
        'scope' => implode(',', $scopes),
    ];
    return $base . '?' . http_build_query($params);
}

function meta_exchange_code($code, $redirect_uri) {
    $url = 'https://graph.facebook.com/v19.0/oauth/access_token';
    return http_get_json($url, [
        'client_id' => META_APP_ID,
        'client_secret' => META_APP_SECRET,
        'redirect_uri' => $redirect_uri,
        'code' => $code,
    ]);
}

function meta_api_get($path, $params, $access_token) {
    $url = 'https://graph.facebook.com/v19.0' . $path;
    $params['access_token'] = $access_token;
    return http_get_json($url, $params);
}

function meta_graph_get($path, $params, $access_token) {
    $url = 'https://graph.facebook.com/v19.0' . $path;
    $params['access_token'] = $access_token;
    return http_get_json($url, $params);
}

function meta_graph_post($path, $params, $access_token) {
    $url = 'https://graph.facebook.com/v19.0' . $path;
    $params['access_token'] = $access_token;
    return http_post_json($url, $params);
}

function build_public_media_url($file_path) {
    if (!APP_URL) {
        throw new RuntimeException('APP_URL belum dikonfigurasi untuk akses publik media.');
    }
    return rtrim(APP_URL, '/') . '/' . ltrim($file_path, '/');
}

function refresh_meta_token_if_needed($pdo, $platform, $threshold_seconds = 259200) {
    if (!META_APP_ID || !META_APP_SECRET) {
        $pdo->prepare("UPDATE channels SET last_error = :err WHERE platform = :platform")
            ->execute([':err' => 'Meta app credentials missing', ':platform' => $platform]);
        return false;
    }
    $stmt = $pdo->prepare("SELECT access_token, token_expiry FROM channels WHERE platform = :platform LIMIT 1");
    $stmt->execute([':platform' => $platform]);
    $row = $stmt->fetch();
    if (!$row || empty($row['access_token'])) return false;

    $expiry = $row['token_expiry'] ? strtotime($row['token_expiry']) : null;
    if (!$expiry) return false;
    if ($expiry - time() > $threshold_seconds) return false;

    try {
        $url = 'https://graph.facebook.com/v19.0/oauth/access_token';
        $data = http_get_json($url, [
            'grant_type' => 'fb_exchange_token',
            'client_id' => META_APP_ID,
            'client_secret' => META_APP_SECRET,
            'fb_exchange_token' => $row['access_token'],
        ]);
        $access_token = $data['access_token'] ?? null;
        $expires_in = (int) ($data['expires_in'] ?? 0);
        if (!$access_token) {
            throw new RuntimeException('Meta refresh token gagal.');
        }
        $token_expiry = $expires_in ? date('Y-m-d H:i:s', time() + $expires_in) : null;
        $now = date('Y-m-d H:i:s');
        $update = $pdo->prepare("UPDATE channels
            SET access_token = :access_token,
                token_expiry = :token_expiry,
                last_checked_at = :now,
                last_error = NULL
            WHERE platform = :platform");
        $update->execute([
            ':access_token' => $access_token,
            ':token_expiry' => $token_expiry,
            ':now' => $now,
            ':platform' => $platform,
        ]);
        return true;
    } catch (Throwable $e) {
        $now = date('Y-m-d H:i:s');
        $update = $pdo->prepare("UPDATE channels
            SET last_checked_at = :now,
                last_error = :error
            WHERE platform = :platform");
        $update->execute([
            ':error' => $e->getMessage(),
            ':now' => $now,
            ':platform' => $platform,
        ]);
        log_line('meta refresh error: ' . $e->getMessage(), 'cron.log');
        return false;
    }
}

function refresh_tiktok_token_if_needed($pdo, $threshold_seconds = 259200) {
    if (!TIKTOK_CLIENT_KEY || !TIKTOK_CLIENT_SECRET) {
        $pdo->prepare("UPDATE channels SET last_error = :err WHERE platform = 'tiktok'")
            ->execute([':err' => 'TikTok app credentials missing']);
        return false;
    }
    $stmt = $pdo->prepare("SELECT access_token, refresh_token, token_expiry FROM channels WHERE platform = 'tiktok' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row || empty($row['refresh_token'])) return false;

    $expiry = $row['token_expiry'] ? strtotime($row['token_expiry']) : null;
    if (!$expiry) return false;
    if ($expiry - time() > $threshold_seconds) return false;

    try {
        $data = tiktok_api_post('oauth/refresh_token/', [
            'client_key' => TIKTOK_CLIENT_KEY,
            'client_secret' => TIKTOK_CLIENT_SECRET,
            'grant_type' => 'refresh_token',
            'refresh_token' => $row['refresh_token'],
        ]);
        $access_token = $data['access_token'] ?? null;
        $refresh_token = $data['refresh_token'] ?? null;
        $expires_in = (int) ($data['expires_in'] ?? 0);
        if (!$access_token) {
            throw new RuntimeException('TikTok refresh token gagal.');
        }
        $token_expiry = $expires_in ? date('Y-m-d H:i:s', time() + $expires_in) : null;
        $now = date('Y-m-d H:i:s');
        $update = $pdo->prepare("UPDATE channels
            SET access_token = :access_token,
                refresh_token = :refresh_token,
                token_expiry = :token_expiry,
                last_checked_at = :now,
                last_error = NULL
            WHERE platform = 'tiktok'");
        $update->execute([
            ':access_token' => $access_token,
            ':refresh_token' => $refresh_token ?: $row['refresh_token'],
            ':token_expiry' => $token_expiry,
            ':now' => $now,
        ]);
        return true;
    } catch (Throwable $e) {
        $now = date('Y-m-d H:i:s');
        $update = $pdo->prepare("UPDATE channels
            SET last_checked_at = :now,
                last_error = :error
            WHERE platform = 'tiktok'");
        $update->execute([
            ':error' => $e->getMessage(),
            ':now' => $now,
        ]);
        log_line('tiktok refresh error: ' . $e->getMessage(), 'cron.log');
        return false;
    }
}

function check_channel_health($pdo, $platform, $refresh_if_needed = true) {
    $stmt = $pdo->prepare("SELECT platform, access_token, token_expiry, last_error, is_active, status
                           FROM channels WHERE platform = :platform LIMIT 1");
    $stmt->execute([':platform' => $platform]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'label' => 'Inactive'];
    }
    if ((int) $row['is_active'] !== 1 || $row['status'] !== 'connected') {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE channels SET last_checked_at = :now WHERE platform = :platform")
            ->execute([':now' => $now, ':platform' => $platform]);
        return ['ok' => false, 'label' => 'Inactive'];
    }

    // update last_checked_at on every health check
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE channels SET last_checked_at = :now WHERE platform = :platform")
        ->execute([':now' => $now, ':platform' => $platform]);

    if ($refresh_if_needed) {
        if (in_array($platform, ['facebook','instagram','threads'], true)) {
            refresh_meta_token_if_needed($pdo, $platform);
        }
        if ($platform === 'tiktok') {
            refresh_tiktok_token_if_needed($pdo);
        }
        $stmt->execute([':platform' => $platform]);
        $row = $stmt->fetch();
    }

    if (empty($row['access_token'])) {
        $pdo->prepare("UPDATE channels SET last_error = :err WHERE platform = :platform")
            ->execute([':err' => 'Access token missing', ':platform' => $platform]);
        return ['ok' => false, 'label' => 'Error'];
    }

    if (!empty($row['last_error'])) {
        return ['ok' => false, 'label' => 'Error'];
    }

    $expiry = $row['token_expiry'] ? strtotime($row['token_expiry']) : null;
    if ($expiry && $expiry <= time()) {
        return ['ok' => false, 'label' => 'Expired'];
    }
    if ($expiry && ($expiry - time() <= 259200)) {
        return ['ok' => true, 'label' => 'Expiring Soon'];
    }
    return ['ok' => true, 'label' => 'Connected'];
}

function make_job_key($variant_id) {
    return 'variant-' . (int) $variant_id;
}

function lock_publish_job($pdo, $job_id, $lock_ttl_seconds = 300) {
    $now = date('Y-m-d H:i:s');
    $stale_before = date('Y-m-d H:i:s', time() - $lock_ttl_seconds);
    $stmt = $pdo->prepare("
        UPDATE publish_jobs
        SET status='processing',
            locked_at=:now,
            locked_by=:locked_by,
            started_at=COALESCE(started_at, :now)
        WHERE id=:id
          AND (status='pending' OR status='retrying' OR (status='processing' AND locked_at < :stale_before))
    ");
    $stmt->execute([
        ':now' => $now,
        ':locked_by' => php_uname('n'),
        ':stale_before' => $stale_before,
        ':id' => $job_id,
    ]);
    return $stmt->rowCount() > 0;
}

function release_publish_job($pdo, $job_id, $status, $error = null, $next_retry_at = null) {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        UPDATE publish_jobs
        SET status=:status,
            finished_at=:now,
            last_error=:error,
            next_retry_at=:next_retry_at
        WHERE id=:id
    ");
    $stmt->execute([
        ':status' => $status,
        ':error' => $error,
        ':next_retry_at' => $next_retry_at,
        ':now' => $now,
        ':id' => $job_id,
    ]);
}

function mark_job_retry($pdo, $job_id, $attempts, $next_retry_at, $error) {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        UPDATE publish_jobs
        SET status='retrying',
            attempts=:attempts,
            last_error=:error,
            next_retry_at=:next_retry_at,
            finished_at=:now
        WHERE id=:id
    ");
    $stmt->execute([
        ':attempts' => $attempts,
        ':error' => $error,
        ':next_retry_at' => $next_retry_at,
        ':now' => $now,
        ':id' => $job_id,
    ]);
}

function tiktok_base64url($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function tiktok_generate_code_verifier() {
    return tiktok_base64url(random_bytes(32));
}

function tiktok_generate_code_challenge($verifier) {
    return tiktok_base64url(hash('sha256', $verifier, true));
}

function tiktok_build_oauth_url($redirect_uri, $state, $code_challenge = null) {
    $base = 'https://www.tiktok.com/v2/auth/authorize/';
    $params = [
        'client_key' => TIKTOK_CLIENT_KEY,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => 'user.info.basic,video.publish',
        'state' => $state,
    ];
    if ($code_challenge) {
        $params['code_challenge'] = $code_challenge;
        $params['code_challenge_method'] = 'S256';
    }
    return $base . '?' . http_build_query($params);
}

function tiktok_api_post($path, $params, $use_auth_header = false, $as_json = false) {
    $url = 'https://open.tiktokapis.com/v2/' . ltrim($path, '/');
    $headers = [];
    if ($use_auth_header && isset($params['access_token'])) {
        $headers[] = 'Authorization: Bearer ' . $params['access_token'];
        unset($params['access_token']);
    }
    if ($as_json) {
        return http_post_json_body($url, json_encode($params, JSON_UNESCAPED_SLASHES), $headers);
    }
    return http_post_json($url, $params, $headers);
}

function tiktok_api_get($path, $params, $access_token = null) {
    $url = 'https://open.tiktokapis.com/v2/' . ltrim($path, '/');
    $headers = [];
    if ($access_token) $headers[] = 'Authorization: Bearer ' . $access_token;
    return http_get_json($url, $params, $headers);
}

function threads_api_post($path, $params, $access_token) {
    $url = 'https://graph.threads.net/v1.0' . $path;
    $params['access_token'] = $access_token;
    return http_post_json($url, $params);
}

function threads_api_get($path, $params, $access_token) {
    $url = 'https://graph.threads.net/v1.0' . $path;
    $params['access_token'] = $access_token;
    return http_get_json($url, $params);
}

/* SECTION: HTML */
// Not applicable.

/* SECTION: INLINE CSS */
// Not applicable.

/* SECTION: INLINE JS */
// Not applicable.
