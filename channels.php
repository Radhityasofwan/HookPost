<?php
/* =========================================================
 * PAGE: channels
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login();

/* SECTION: AUTH */
// Auth enforced above.

/* SECTION: HANDLE REQUEST */
$flash = flash_get();

$platforms = [
    'instagram' => 'Instagram',
    'facebook' => 'Facebook',
    'threads' => 'Threads',
    'tiktok' => 'TikTok',
];

// Meta OAuth config
$meta_scopes = [
    'public_profile',
    'pages_show_list',
    'pages_read_engagement',
    'instagram_basic',
    'instagram_manage_insights',
    'instagram_manage_comments',
    'instagram_content_publish',
    'threads_basic',
    'threads_content_publish',
];
if (defined('META_SCOPES') && trim(META_SCOPES) !== '') {
    $meta_scopes = array_values(array_filter(array_map('trim', explode(',', META_SCOPES))));
}
$meta_redirect = defined('META_REDIRECT_URI_FALLBACK') ? META_REDIRECT_URI_FALLBACK : META_REDIRECT_URI;
$tiktok_redirect = defined('TIKTOK_REDIRECT_URI_FALLBACK') ? TIKTOK_REDIRECT_URI_FALLBACK : TIKTOK_REDIRECT_URI;

function upsert_channel($pdo, $platform, $data) {
    $data[':now'] = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT id FROM channels WHERE platform = :platform LIMIT 1");
    $stmt->execute([':platform' => $platform]);
    $exists = $stmt->fetch();
    if ($exists) {
        $sql = "UPDATE channels SET
            platform_account_id = :platform_account_id,
            account_name = :account_name,
            account_username = :account_username,
            page_id = :page_id,
            token_type = :token_type,
            access_token = :access_token,
            refresh_token = :refresh_token,
            token_expiry = :token_expiry,
            last_checked_at = :last_checked_at,
            last_error = :last_error,
            is_active = :is_active,
            status = :status
            WHERE platform = :platform";
    } else {
        $sql = "INSERT INTO channels
            (platform, platform_account_id, account_name, account_username, page_id, token_type, access_token, refresh_token, token_expiry, last_checked_at, last_error, is_active, status, created_at)
            VALUES (:platform, :platform_account_id, :account_name, :account_username, :page_id, :token_type, :access_token, :refresh_token, :token_expiry, :last_checked_at, :last_error, :is_active, :status, :now)";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($data, [':platform' => $platform]));
}

if (isset($_GET['meta_connect'])) {
    if (!META_APP_ID || !META_APP_SECRET || !$meta_redirect) {
        flash_set('danger', 'Meta App ID/Secret/Redirect belum dikonfigurasi.');
        redirect_to('channels');
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['meta_oauth_state'] = $state;
    $auth_url = meta_oauth_url($meta_scopes, $meta_redirect, $state);
    redirect_to($auth_url);
}

// TikTok OAuth
if (isset($_GET['tiktok_connect'])) {
    if (!TIKTOK_CLIENT_KEY || !TIKTOK_CLIENT_SECRET || !$tiktok_redirect) {
        flash_set('danger', 'TikTok client key/secret/redirect belum dikonfigurasi.');
        redirect_to('channels');
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['tiktok_oauth_state'] = $state;
    $code_verifier = tiktok_generate_code_verifier();
    $_SESSION['tiktok_code_verifier'] = $code_verifier;
    $code_challenge = tiktok_generate_code_challenge($code_verifier);
    $auth_url = tiktok_build_oauth_url($tiktok_redirect, $state, $code_challenge);
    redirect_to($auth_url);
}

// OAuth callback handler (Meta / TikTok) using state matching
$incoming_state = $_GET['state'] ?? null;
$has_error = isset($_GET['error']) || isset($_GET['error_code']) || isset($_GET['error_message']) || isset($_GET['error_description']);
if ($has_error && !$incoming_state) {
    $msg = $_GET['error_message'] ?? $_GET['error_description'] ?? $_GET['error'] ?? 'OAuth error.';
    flash_set('danger', 'OAuth gagal: ' . $msg);
    redirect_to('channels');
}
if ($incoming_state && $has_error) {
    if (hash_equals($_SESSION['meta_oauth_state'] ?? '', $incoming_state)) {
        $msg = $_GET['error_message'] ?? $_GET['error_description'] ?? $_GET['error'] ?? 'Meta OAuth error.';
        flash_set('danger', 'Meta OAuth gagal: ' . $msg);
        redirect_to('channels');
    }
    if (hash_equals($_SESSION['tiktok_oauth_state'] ?? '', $incoming_state)) {
        $msg = $_GET['error_description'] ?? $_GET['error_message'] ?? $_GET['error'] ?? 'TikTok OAuth error.';
        flash_set('danger', 'TikTok OAuth gagal: ' . $msg);
        redirect_to('channels');
    }
    flash_set('danger', 'OAuth gagal: ' . ($_GET['error_message'] ?? $_GET['error_description'] ?? $_GET['error'] ?? 'Unknown error'));
    redirect_to('channels');
}

if (isset($_GET['code']) && $incoming_state) {
    if (hash_equals($_SESSION['meta_oauth_state'] ?? '', $incoming_state)) {
        try {
            $token = meta_exchange_code($_GET['code'], $meta_redirect);
            $access_token = $token['access_token'] ?? '';
            $token_type = $token['token_type'] ?? 'bearer';
            $expires_in = (int) ($token['expires_in'] ?? 0);
            $token_expiry = $expires_in ? date('Y-m-d H:i:s', time() + $expires_in) : null;
            if (!$access_token) {
                throw new RuntimeException('Meta access token kosong.');
            }

            $me = meta_api_get('/me', ['fields' => 'id,name'], $access_token);
            $user_id = $me['id'] ?? null;
            $user_name = $me['name'] ?? null;

            // Facebook Pages + Instagram Business account mapping:
            // - Facebook: gunakan page id + page access token
            // - Instagram: gunakan instagram_business_account.id dari page
            $pages = meta_api_get('/me/accounts', ['fields' => 'id,name,access_token,instagram_business_account'], $access_token);
            $pages_data = $pages['data'] ?? [];

            $notes = [];
            if (!$pages_data) {
                $notes[] = 'Tidak ada Page Facebook yang dapat diakses.';
            }
            foreach ($pages_data as $page) {
                $page_id = $page['id'] ?? null;
                $page_name = $page['name'] ?? null;
                $page_token = $page['access_token'] ?? null;
                if (!$page_id || !$page_token) continue;

                upsert_channel($pdo, 'facebook', [
                    ':platform_account_id' => $page_id,
                    ':account_name' => $page_name,
                    ':account_username' => null,
                    ':page_id' => $page_id,
                    ':token_type' => $token_type,
                    ':access_token' => $page_token,
                    ':refresh_token' => null,
                    ':token_expiry' => $token_expiry,
                    ':last_checked_at' => date('Y-m-d H:i:s'),
                    ':last_error' => null,
                    ':is_active' => 1,
                    ':status' => 'connected',
                ]);

                $ig = $page['instagram_business_account']['id'] ?? null;
                if ($ig) {
                    $ig_info = meta_api_get('/' . $ig, ['fields' => 'id,username,name'], $page_token);
                    upsert_channel($pdo, 'instagram', [
                        ':platform_account_id' => $ig_info['id'] ?? $ig,
                        ':account_name' => $ig_info['name'] ?? $page_name,
                        ':account_username' => $ig_info['username'] ?? null,
                        ':page_id' => $page_id,
                        ':token_type' => $token_type,
                        ':access_token' => $page_token,
                        ':refresh_token' => null,
                        ':token_expiry' => $token_expiry,
                        ':last_checked_at' => date('Y-m-d H:i:s'),
                        ':last_error' => null,
                        ':is_active' => 1,
                        ':status' => 'connected',
                    ]);
                }
            }

            // Threads mapping (sederhana):
            // Simpan user access token dari Meta untuk akun Threads (user-based).
            if ($user_id && $user_name) {
                upsert_channel($pdo, 'threads', [
                    ':platform_account_id' => $user_id,
                    ':account_name' => $user_name,
                    ':account_username' => null,
                    ':page_id' => null,
                    ':token_type' => $token_type,
                    ':access_token' => $access_token,
                    ':refresh_token' => null,
                    ':token_expiry' => $token_expiry,
                    ':last_checked_at' => date('Y-m-d H:i:s'),
                    ':last_error' => null,
                    ':is_active' => 1,
                    ':status' => 'connected',
                ]);
            }

            $msg = $notes ? 'Meta connect sebagian: ' . implode(' ', $notes) : 'Meta connect berhasil.';
            flash_set($notes ? 'warning' : 'success', $msg);
            redirect_to('channels');
        } catch (Throwable $e) {
            log_line('meta oauth error: ' . $e->getMessage(), 'app.log');
            flash_set('danger', APP_DEBUG ? $e->getMessage() : 'Meta connect gagal.');
            redirect_to('channels');
        }
    } elseif (hash_equals($_SESSION['tiktok_oauth_state'] ?? '', $incoming_state)) {
        try {
            $code_verifier = $_SESSION['tiktok_code_verifier'] ?? null;
            if (!$code_verifier) {
                throw new RuntimeException('TikTok code_verifier tidak ditemukan. Ulangi connect.');
            }
            $token = tiktok_api_post('oauth/token/', [
                'client_key' => TIKTOK_CLIENT_KEY,
                'client_secret' => TIKTOK_CLIENT_SECRET,
                'code' => $_GET['code'],
                'grant_type' => 'authorization_code',
                'redirect_uri' => $tiktok_redirect,
                'code_verifier' => $code_verifier,
            ]);
            unset($_SESSION['tiktok_code_verifier']);

            $access_token = $token['access_token'] ?? '';
            $refresh_token = $token['refresh_token'] ?? null;
            $expires_in = (int) ($token['expires_in'] ?? 0);
            $token_expiry = $expires_in ? date('Y-m-d H:i:s', time() + $expires_in) : null;
            if (!$access_token) {
                throw new RuntimeException('TikTok access token kosong.');
            }

            $user = tiktok_api_get('user/info/', [
                'fields' => 'open_id,display_name,username',
            ], $access_token);

            $user_data = $user['data']['user'] ?? [];
            upsert_channel($pdo, 'tiktok', [
                ':platform_account_id' => $user_data['open_id'] ?? null,
                ':account_name' => $user_data['display_name'] ?? null,
                ':account_username' => $user_data['username'] ?? null,
                ':page_id' => null,
                ':token_type' => 'bearer',
                ':access_token' => $access_token,
                ':refresh_token' => $refresh_token,
                ':token_expiry' => $token_expiry,
                ':last_checked_at' => date('Y-m-d H:i:s'),
                ':last_error' => null,
                ':is_active' => 1,
                ':status' => 'connected',
            ]);

            flash_set('success', 'TikTok connect berhasil.');
            redirect_to('channels');
        } catch (Throwable $e) {
            log_line('tiktok oauth error: ' . $e->getMessage(), 'app.log');
            flash_set('danger', APP_DEBUG ? $e->getMessage() : 'TikTok connect gagal.');
            redirect_to('channels');
        }
    } else {
        flash_set('danger', 'Invalid OAuth state.');
        redirect_to('channels');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $platform = $_POST['platform'] ?? '';

    if (!isset($platforms[$platform])) {
        flash_set('danger', 'Platform tidak valid.');
        redirect_to('channels');
    }

    try {
        if ($action === 'connect') {
            $now = date('Y-m-d H:i:s');
            $account_name = trim($_POST['account_name'] ?? '');
            $account_id = trim($_POST['account_id'] ?? '');
            $access_token = trim($_POST['access_token'] ?? '');
            $status = $account_name && $access_token ? 'connected' : 'pending';
            $is_active = $status === 'connected' ? 1 : 0;

            $stmt = $pdo->prepare("SELECT id FROM channels WHERE platform = :platform LIMIT 1");
            $stmt->execute([':platform' => $platform]);
            $exists = $stmt->fetch();

            if ($exists) {
                $update = $pdo->prepare("UPDATE channels
                    SET account_name = :account_name,
                        platform_account_id = :account_id,
                        access_token = :access_token,
                        token_type = :token_type,
                        status = :status,
                        is_active = :is_active,
                        last_checked_at = :last_checked_at,
                        last_error = NULL
                    WHERE platform = :platform");
                $update->execute([
                    ':account_name' => $account_name,
                    ':account_id' => $account_id,
                    ':access_token' => $access_token,
                    ':token_type' => 'bearer',
                    ':status' => $status,
                    ':is_active' => $is_active,
                    ':last_checked_at' => $now,
                    ':platform' => $platform,
                ]);
            } else {
                $insert = $pdo->prepare("INSERT INTO channels
                    (platform, account_name, platform_account_id, access_token, token_type, status, is_active, last_checked_at, last_error, created_at)
                    VALUES (:platform, :account_name, :account_id, :access_token, :token_type, :status, :is_active, :last_checked_at, NULL, :now)");
                $insert->execute([
                    ':platform' => $platform,
                    ':account_name' => $account_name,
                    ':account_id' => $account_id,
                    ':access_token' => $access_token,
                    ':token_type' => 'bearer',
                    ':status' => $status,
                    ':is_active' => $is_active,
                    ':last_checked_at' => $now,
                    ':now' => date('Y-m-d H:i:s'),
                ]);
            }

            flash_set('success', 'Koneksi disimpan.');
            redirect_to('channels');
        }

        if ($action === 'disconnect') {
            $now = date('Y-m-d H:i:s');
            $update = $pdo->prepare("UPDATE channels
                SET account_name = NULL,
                    platform_account_id = NULL,
                    account_username = NULL,
                    page_id = NULL,
                    token_type = NULL,
                    access_token = NULL,
                    refresh_token = NULL,
                    token_expiry = NULL,
                    last_checked_at = :now,
                    last_error = NULL,
                    is_active = 0,
                    status = 'disconnected'
                WHERE platform = :platform");
            $update->execute([':platform' => $platform, ':now' => $now]);
            flash_set('success', 'Koneksi diputus.');
            redirect_to('channels');
        }
    } catch (Throwable $e) {
        log_line('channels action error: ' . $e->getMessage(), 'app.log');
        flash_set('danger', APP_DEBUG ? $e->getMessage() : 'Gagal menyimpan koneksi.');
        redirect_to('channels');
    }
}

/* SECTION: LOAD DATA */
$channels = [];
try {
    $stmt = $pdo->query("SELECT platform, account_name, account_username, platform_account_id, status FROM channels");
    foreach ($stmt->fetchAll() as $row) {
        $channels[$row['platform']] = $row;
    }
} catch (Throwable $e) {
    log_line('channels load error: ' . $e->getMessage(), 'app.log');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>channels - <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- SECTION: INLINE CSS -->
<style>
:root{
  --bg:#f4f6fb;
  --card:#ffffff;
  --ink:#0f172a;
  --muted:#475569;
  --line:#e2e8f0;
  --meta:#0866ff;
  --tiktok:#fe2c55;
  --tiktok-2:#25f4ee;
  --soft-shadow:0 16px 40px rgba(15,23,42,.10);
  --radius:18px;
}
body{background:var(--bg);color:var(--ink)}
.text-secondary{color:var(--muted)!important}
.sidebar{width:260px;flex:0 0 260px;min-height:100vh;background:linear-gradient(180deg,#0f172a 0%,#0b1220 100%);color:#f8fafc;border-right:1px solid rgba(255,255,255,.06);transition:width .2s ease}
body.sidebar-collapsed .sidebar{width:84px;flex:0 0 84px}
.sidebar .brand{display:flex;align-items:center;gap:12px;padding:8px 10px}
.brand-logo{width:36px;height:36px;border-radius:12px;box-shadow:0 8px 18px rgba(0,0,0,.25);object-fit:cover}
.brand-title{font-weight:700;line-height:1.1}
.brand-sub{font-size:.75rem;color:rgba(255,255,255,.65)}
body.sidebar-collapsed .brand-text{display:none}
.sidebar a{color:rgba(255,255,255,.86);text-decoration:none;display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:16px}
.sidebar .nav-icon{width:10px;height:10px;border-radius:999px;background:linear-gradient(135deg,var(--tiktok),var(--meta));box-shadow:0 0 0 4px rgba(8,102,255,.12)}
.sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.12);color:#fff}
body.sidebar-collapsed .sidebar a{justify-content:center}
body.sidebar-collapsed .sidebar .nav-label{display:none}
.card-soft{border:0;border-radius:var(--radius);background:var(--card);box-shadow:var(--soft-shadow)}
.navbar{background:var(--card);border-bottom:1px solid var(--line)}
.platform-card{border:1px solid var(--line)}
.section-title{font-size:.85rem;color:var(--muted);letter-spacing:.3px;text-transform:uppercase}
.pjax-bar{
  position:fixed;top:0;left:0;width:100%;height:3px;
  background:linear-gradient(90deg,var(--tiktok),var(--tiktok-2),var(--meta));
  transform:scaleX(0);transform-origin:left;opacity:0;transition:opacity .2s ease, transform .35s ease;
  z-index:9999;
}
body.pjax-loading .pjax-bar{opacity:1;transform:scaleX(.85)}
@media (max-width: 992px){
  .sidebar{display:none}
}
</style>
</head>
<body>
<div class="pjax-bar" id="pjaxBar"></div>
<!-- SECTION: HTML -->
<div class="d-flex">
    <aside class="sidebar p-3">
        <div class="brand mb-4">
            <img src="/image/logo/hookpost.png" class="brand-logo" alt="HookPost">
            <div class="brand-text">
                <div class="brand-title"><?= e(APP_NAME) ?></div>
                <div class="brand-sub">Meta + TikTok Suite</div>
            </div>
        </div>
        <nav class="d-grid gap-2">
            <a href="/dashboard"><span class="nav-icon"></span><span class="nav-label">Dashboard</span></a>
            <a href="/posts"><span class="nav-icon"></span><span class="nav-label">Posts</span></a>
            <a href="/post-form"><span class="nav-icon"></span><span class="nav-label">Create Post</span></a>
            <a href="/channels"><span class="nav-icon"></span><span class="nav-label">Channels</span></a>
            <a href="/logs"><span class="nav-icon"></span><span class="nav-label">Logs</span></a>
            <a href="/logout"><span class="nav-icon"></span><span class="nav-label">Logout</span></a>
        </nav>
    </aside>

    <div class="flex-grow-1">
        <div id="app-main">
        <nav class="navbar px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    ☰
                </button>
                <button class="btn btn-outline-secondary d-none d-lg-inline-flex" id="sidebarToggle" type="button">
                    ⇄
                </button>
                <div class="fw-semibold">Channels</div>
            </div>
            <div class="text-secondary small">Koneksi akun</div>
        </nav>

        <main class="p-3 p-lg-4">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> rounded-4"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="card card-soft p-3 p-lg-4 mb-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="section-title">OAuth</div>
                        <div class="fw-semibold">Connect Meta (Instagram/Facebook/Threads)</div>
                        <div class="text-secondary small">Gunakan OAuth Meta untuk menyimpan token dan akun otomatis.</div>
                    </div>
                    <a class="btn btn-primary" href="/channels?meta_connect=1">Connect Meta</a>
                </div>
            </div>

            <div class="card card-soft p-3 p-lg-4 mb-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="section-title">OAuth</div>
                        <div class="fw-semibold">Connect TikTok</div>
                        <div class="text-secondary small">OAuth TikTok untuk akses publish.</div>
                    </div>
                    <a class="btn btn-dark" href="/channels?tiktok_connect=1">Connect TikTok</a>
                </div>
            </div>

            <div class="row g-3 g-lg-4">
                <?php foreach ($platforms as $key => $label):
                    $data = $channels[$key] ?? ['account_name' => '', 'account_username' => '', 'platform_account_id' => '', 'status' => 'disconnected'];
                    $health = check_channel_health($pdo, $key, false);
                ?>
                <div class="col-12 col-lg-6">
                    <div class="card card-soft platform-card p-3 p-lg-4 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="fw-semibold"><?= e($label) ?></div>
                            <?php
                                $health_map = [
                                    'Connected' => 'success',
                                    'Expiring Soon' => 'warning',
                                    'Expired' => 'danger',
                                    'Error' => 'danger',
                                    'Inactive' => 'secondary',
                                ];
                                $badge = $health_map[$health['label']] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= e($badge) ?>"><?= e($health['label']) ?></span>
                        </div>
                        <div class="mb-3">
                            <div class="text-secondary small">Account Name</div>
                            <div class="fw-medium"><?= e($data['account_name'] ?: '-') ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="text-secondary small">Username</div>
                            <div class="fw-medium"><?= e($data['account_username'] ?: '-') ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="text-secondary small">Account ID</div>
                            <div class="fw-medium"><?= e($data['platform_account_id'] ?: '-') ?></div>
                        </div>
                        <form method="post" class="row g-2">
                            <?= csrf_input() ?>
                            <input type="hidden" name="platform" value="<?= e($key) ?>">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Account Name</label>
                                <input type="text" name="account_name" class="form-control" value="<?= e($data['account_name']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Account ID</label>
                                <input type="text" name="account_id" class="form-control" value="<?= e($data['platform_account_id']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Access Token</label>
                                <textarea name="access_token" class="form-control" rows="2" placeholder="Tempel token akses"></textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary" name="action" value="connect" type="submit" data-loading="Saving...">Connect</button>
                                <button class="btn btn-outline-danger" name="action" value="disconnect" type="submit" data-loading="Disconnecting...">Disconnect</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header">
        <div class="fw-bold"><?= e(APP_NAME) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="d-grid gap-2">
            <a class="btn btn-light text-start" href="/dashboard">Dashboard</a>
            <a class="btn btn-light text-start" href="/posts">Posts</a>
            <a class="btn btn-light text-start" href="/post-form">Create Post</a>
            <a class="btn btn-light text-start" href="/channels">Channels</a>
            <a class="btn btn-light text-start" href="/logs">Logs</a>
            <a class="btn btn-outline-danger text-start" href="/logout">Logout</a>
        </nav>
    </div>
</div>

<!-- SECTION: INLINE JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const allowPaths = ['/dashboard','/posts','/post-form','/channels','/logs'];

  function setActiveNav() {
    const currentPath = location.pathname.replace(/\/$/, '');
    document.querySelectorAll('.sidebar a').forEach(a=>{
      a.classList.toggle('active', a.getAttribute('href') === currentPath);
    });
  }

  function initSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const collapsed = localStorage.getItem('sidebar-collapsed') === '1';
    if (collapsed) document.body.classList.add('sidebar-collapsed');
    if (toggle && !toggle.dataset.bound) {
      toggle.dataset.bound = '1';
      toggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebar-collapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
      });
    }
  }

  function initFormLoading() {
    document.querySelectorAll('form').forEach(form=>{
      if (form.dataset.loadingBound) return;
      form.dataset.loadingBound = '1';
      form.addEventListener('submit', ()=>{
        const btn = form.querySelector('button[type="submit"][data-loading]');
        if (btn) {
          btn.dataset.originalText = btn.textContent;
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + btn.getAttribute('data-loading');
        }
      });
    });
  }

  function initDeleteModal() {
    const deleteModal = document.getElementById('deleteModal');
    if (!deleteModal || deleteModal.dataset.bound) return;
    deleteModal.dataset.bound = '1';
    deleteModal.addEventListener('show.bs.modal', event=>{
      const btn = event.relatedTarget;
      const id = btn?.getAttribute('data-id');
      const input = document.getElementById('deleteId');
      if (input) input.value = id || '';
    });
  }

  function initPostFormUI() {
    const form = document.querySelector('form');
    const mediaInput = document.getElementById('mediaInput');
    const customWrap = document.getElementById('customCaptionWrap');
    if (!form || (!mediaInput && !customWrap)) return;
    if (form.dataset.postFormBound) return;
    form.dataset.postFormBound = '1';

    function toggleCustomCaptions(){
      const mode = document.querySelector('input[name="mode"]:checked')?.value || 'mirror';
      const wrap = document.getElementById('customCaptionWrap');
      if (wrap) wrap.style.display = mode === 'custom' ? 'block' : 'none';
      updateCaptionPreview();
    }
    document.querySelectorAll('input[name="mode"]').forEach(r=>{
      r.addEventListener('change', toggleCustomCaptions);
    });

    function selectedPlatforms(){
      return Array.from(document.querySelectorAll('input[name="platforms[]"]:checked')).map(el=>el.value);
    }

    function updatePlatformBadges(){
      const container = document.getElementById('selectedPlatforms');
      if (!container) return;
      const platforms = selectedPlatforms();
      if (!platforms.length) {
        container.innerHTML = '<span class="text-secondary">Belum ada platform dipilih.</span>';
        return;
      }
      const labels = { instagram:'Instagram', facebook:'Facebook', threads:'Threads', tiktok:'TikTok' };
      container.innerHTML = platforms.map(p=>`<span class="badge text-bg-light border me-1">${labels[p] || p}</span>`).join('');
    }

    function getCaptionFor(platform){
      const mode = document.querySelector('input[name="mode"]:checked')?.value || 'mirror';
      const main = document.querySelector('textarea[name="caption"]')?.value || '';
      if (mode === 'mirror') return main;
      if (platform === 'instagram') return document.querySelector('textarea[name="caption_instagram"]')?.value || main;
      if (platform === 'facebook') return document.querySelector('textarea[name="caption_facebook"]')?.value || main;
      if (platform === 'threads') return document.querySelector('textarea[name="caption_threads"]')?.value || main;
      if (platform === 'tiktok') return document.querySelector('textarea[name="caption_tiktok"]')?.value || main;
      return main;
    }

    function updateCaptionPreview(){
      const container = document.getElementById('captionPreview');
      if (!container) return;
      const platforms = selectedPlatforms();
      if (!platforms.length) {
        container.innerHTML = '<div class="text-secondary">Pilih platform untuk melihat preview.</div>';
        return;
      }
      const labels = { instagram:'Instagram', facebook:'Facebook', threads:'Threads', tiktok:'TikTok' };
      container.innerHTML = platforms.map(p=>{
        const caption = getCaptionFor(p);
        const safe = (caption || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        return `<div class="mb-2"><div class="fw-semibold">${labels[p] || p}</div><div class="text-secondary small">${safe || '-'}</div></div>`;
      }).join('') || '<div class="text-secondary">Pilih platform untuk melihat preview.</div>';
    }

    document.querySelectorAll('input[name="platforms[]"]').forEach(el=>{
      el.addEventListener('change', ()=>{
        updatePlatformBadges();
        updateCaptionPreview();
      });
    });
    document.querySelectorAll('textarea[name="caption"], textarea[name^="caption_"]').forEach(el=>{
      el.addEventListener('input', updateCaptionPreview);
    });

    toggleCustomCaptions();
    updatePlatformBadges();
    updateCaptionPreview();

    if (mediaInput) {
      const preview = document.getElementById('mediaPreview');
      mediaInput.addEventListener('change', ()=>{
        if (!preview) return;
        preview.innerHTML = '';
        const file = mediaInput.files?.[0];
        if (!file) {
          preview.innerHTML = '<div class="text-secondary">Belum ada media.</div>';
          return;
        }
        const url = URL.createObjectURL(file);
        if (file.type.startsWith('image/')) {
          const img = document.createElement('img');
          img.src = url;
          img.className = 'preview-media';
          preview.appendChild(img);
        } else if (file.type.startsWith('video/')) {
          const video = document.createElement('video');
          video.src = url;
          video.controls = true;
          video.className = 'preview-media';
          preview.appendChild(video);
        } else {
          preview.innerHTML = '<div class="text-secondary">Format tidak didukung.</div>';
        }
      });
    }
  }

  function appInit(){
    initSidebar();
    setActiveNav();
    initFormLoading();
    initDeleteModal();
    initPostFormUI();
  }

  function setLoading(on){
    document.body.classList.toggle('pjax-loading', on);
  }

  function pjaxNavigate(url, isPop){
    const main = document.getElementById('app-main');
    if (!main) { location.href = url; return; }
    setLoading(true);
    fetch(url, { headers: { 'X-PJAX': '1' } })
      .then(r => r.text())
      .then(html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const newMain = doc.getElementById('app-main');
        if (!newMain) { location.href = url; return; }
        main.innerHTML = newMain.innerHTML;
        document.title = doc.title || document.title;
        if (!isPop) history.pushState({}, '', url);
        appInit();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      })
      .catch(() => location.href = url)
      .finally(() => setLoading(false));
  }

  function bindPjax(){
    if (window.__pjaxBound) return;
    window.__pjaxBound = true;
    document.addEventListener('click', (e)=>{
      const link = e.target.closest('a');
      if (!link) return;
      if (link.hasAttribute('data-no-pjax')) return;
      if (link.target && link.target !== '_self') return;
      const href = link.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
      const url = new URL(link.href, location.origin);
      if (url.origin !== location.origin) return;
      if (!allowPaths.includes(url.pathname)) return;
      e.preventDefault();
      pjaxNavigate(url.href);
    });
    window.addEventListener('popstate', () => {
      if (allowPaths.includes(location.pathname)) {
        pjaxNavigate(location.href, true);
      }
    });
  }

  bindPjax();
  appInit();
})();
</script>
</body>
</html>
