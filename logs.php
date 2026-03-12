<?php
/* =========================================================
 * PAGE: logs
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login();

/* SECTION: AUTH */
// Auth enforced above.

/* SECTION: HANDLE REQUEST */
$flash = flash_get();

$platform_filter = $_GET['platform'] ?? 'all';
$platforms = ['all','instagram','facebook','threads','tiktok'];
if (!in_array($platform_filter, $platforms, true)) $platform_filter = 'all';

$post_filter = (int) ($_GET['post_id'] ?? 0);

/* SECTION: LOAD DATA */
$rows = [];
try {
    $where = [];
    $params = [];
    if ($platform_filter !== 'all') {
        $where[] = "pv.platform = :platform";
        $params[':platform'] = $platform_filter;
    }
    if ($post_filter > 0) {
        $where[] = "p.id = :post_id";
        $params[':post_id'] = $post_filter;
    }
    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT p.title, pv.platform, pv.status, pv.published_at, pj.attempts, pv.failed_reason
            FROM publish_jobs pj
            JOIN post_variants pv ON pv.id = pj.post_variant_id
            JOIN posts p ON p.id = pv.post_id
            $where_sql
            ORDER BY pj.id DESC
            LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    log_line('logs load error: ' . $e->getMessage(), 'app.log');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>logs - <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- SECTION: INLINE CSS -->
<style>
:root{
  --bg:#f7f8fc;
  --card:#ffffff;
  --ink:#111827;
  --muted:#6b7280;
  --line:#eef2f7;
  --brand:#2563eb;
  --soft-shadow:0 18px 40px rgba(15,23,42,.08);
  --radius:20px;
}
body{background:var(--bg);color:var(--ink)}
.sidebar{width:260px;min-height:100vh;background:#0f172a;color:#fff;border-right:1px solid rgba(255,255,255,.04)}
.sidebar .brand{display:flex;align-items:center;gap:10px;font-weight:700}
.sidebar .dot{width:10px;height:10px;border-radius:999px;background:var(--brand)}
.sidebar a{color:rgba(255,255,255,.86);text-decoration:none;display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:16px}
.sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.12);color:#fff}
.card-soft{border:0;border-radius:var(--radius);background:var(--card);box-shadow:var(--soft-shadow)}
.navbar{background:var(--card);border-bottom:1px solid var(--line)}
.table> :not(caption)>*>*{padding:12px 14px}
.table thead th{font-size:.85rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.empty-state{padding:28px;border:1px dashed var(--line);border-radius:16px;background:#fafbff;color:var(--muted)}
@media (max-width: 992px){
  .sidebar{display:none}
}
</style>
</head>
<body>
<!-- SECTION: HTML -->
<div class="d-flex">
    <aside class="sidebar p-3">
        <div class="brand mb-4">
            <span class="dot"></span>
            <span><?= e(APP_NAME) ?></span>
        </div>
        <nav class="d-grid gap-2">
            <a href="/dashboard">Dashboard</a>
            <a href="/posts">Posts</a>
            <a href="/post-form">Create Post</a>
            <a href="/channels">Channels</a>
            <a href="/logs">Logs</a>
            <a href="/logout">Logout</a>
        </nav>
    </aside>

    <div class="flex-grow-1">
        <nav class="navbar px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    ☰
                </button>
                <div class="fw-semibold">Logs</div>
            </div>
            <div class="text-secondary small">Riwayat publish</div>
        </nav>

        <main class="p-3 p-lg-4">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> rounded-4"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="card card-soft p-3 p-lg-4 mb-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="fw-semibold">Filter Platform</div>
                    <div class="d-flex flex-wrap gap-2">
                <?php
                    $filter_labels = [
                        'all' => 'All',
                        'instagram' => 'Instagram',
                        'facebook' => 'Facebook',
                        'threads' => 'Threads',
                        'tiktok' => 'TikTok',
                    ];
                    foreach ($filter_labels as $key => $label):
                        $active = $platform_filter === $key ? 'btn-primary' : 'btn-outline-secondary';
                        $url = "logs?platform=" . $key;
                        if ($post_filter > 0) $url .= "&post_id=" . $post_filter;
                ?>
                    <a class="btn btn-sm <?= e($active) ?>" href="<?= e($url) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card card-soft p-3 p-lg-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-semibold">Riwayat Publish</div>
                    <?php if ($post_filter > 0): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="/logs">Clear Filter</a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Post Title</th>
                                <th>Platform</th>
                                <th>Status</th>
                                <th>Published Time</th>
                                <th>Attempts</th>
                                <th>Error Message</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state text-center">Belum ada riwayat publish.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td class="fw-medium"><?= e($row['title']) ?></td>
                                    <td><?= e(ucfirst($row['platform'])) ?></td>
                                    <td><?= app_status_badge($row['status']) ?></td>
                                    <td><?= e($row['published_at'] ? format_datetime($row['published_at']) : '-') ?></td>
                                    <td><?= e($row['attempts']) ?></td>
                                    <td><?= e($row['failed_reason'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
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
document.querySelectorAll('.sidebar a').forEach(a=>{
  if(a.getAttribute('href')===location.pathname.split('/').pop()){a.classList.add('active')}
});
</script>
</body>
</html>
