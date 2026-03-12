<?php
/* =========================================================
 * PAGE: dashboard.php
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login();

/* SECTION: AUTH */
// Auth enforced above.

/* SECTION: HANDLE REQUEST */
$flash = flash_get();

/* SECTION: LOAD DATA */
$stats = [
    'total' => 0,
    'scheduled' => 0,
    'published' => 0,
    'failed' => 0,
];
$recent_posts = [];

try {
    $sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN overall_status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN overall_status = 'published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN overall_status = 'failed' THEN 1 ELSE 0 END) AS failed
            FROM posts";
    $stmt = $pdo->query($sql);
    $stats = $stmt->fetch() ?: $stats;

    $recent_stmt = $pdo->query("SELECT id, title, overall_status, scheduled_at, created_at
                                FROM posts
                                ORDER BY created_at DESC
                                LIMIT 10");
    $recent_posts = $recent_stmt->fetchAll();
} catch (Throwable $e) {
    log_line('dashboard load error: ' . $e->getMessage(), 'app.log');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>dashboard.php - <?= e(APP_NAME) ?></title>
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
.stat-card .label{font-size:.85rem;color:var(--muted);letter-spacing:.2px}
.stat-card .value{font-size:1.9rem;font-weight:700}
.content-wrap{min-height:100vh}
.navbar{background:var(--card);border-bottom:1px solid var(--line)}
.table> :not(caption)>*>*{padding:12px 14px}
.table thead th{font-size:.85rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.pill{font-size:.8rem}
.empty-state{padding:28px;border:1px dashed var(--line);border-radius:16px;background:#fafbff;color:var(--muted)}
@media (max-width: 992px){
  .sidebar{display:none}
}
</style>
</head>
<body>
<!-- SECTION: HTML -->
<div class="d-flex content-wrap">
    <aside class="sidebar p-3">
        <div class="brand mb-4">
            <span class="dot"></span>
            <span><?= e(APP_NAME) ?></span>
        </div>
        <nav class="d-grid gap-2">
            <a href="dashboard.php">Dashboard</a>
            <a href="posts.php">Posts</a>
            <a href="post_form.php">Create Post</a>
            <a href="channels.php">Channels</a>
            <a href="logs.php">Logs</a>
            <a href="logout.php">Logout</a>
        </nav>
    </aside>

    <div class="flex-grow-1">
        <nav class="navbar px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    ☰
                </button>
                <div class="fw-semibold">Dashboard</div>
            </div>
            <div class="text-secondary small">Ringkasan sistem</div>
        </nav>

        <main class="p-3 p-lg-4">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> rounded-4"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="row g-3 g-lg-4 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card card-soft stat-card p-3">
                        <div class="label">Total Konten</div>
                        <div class="value"><?= e($stats['total'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card card-soft stat-card p-3">
                        <div class="label">Scheduled</div>
                        <div class="value"><?= e($stats['scheduled'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card card-soft stat-card p-3">
                        <div class="label">Published</div>
                        <div class="value"><?= e($stats['published'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card card-soft stat-card p-3">
                        <div class="label">Failed</div>
                        <div class="value"><?= e($stats['failed'] ?? 0) ?></div>
                    </div>
                </div>
            </div>

            <div class="card card-soft p-3 p-lg-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-semibold">Recent Posts</div>
                    <a class="btn btn-sm btn-outline-secondary" href="posts.php">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Judul</th>
                                <th>Status Publish</th>
                                <th>Schedule</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$recent_posts): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state text-center">Belum ada konten.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_posts as $row): ?>
                                <tr>
                                    <td><?= e($row['id']) ?></td>
                                    <td class="fw-medium"><?= e($row['title']) ?></td>
                                    <td><?= app_status_badge($row['overall_status']) ?></td>
                                    <td><?= e($row['scheduled_at'] ? format_datetime($row['scheduled_at']) : '-') ?></td>
                                    <td><?= e(format_datetime($row['created_at'])) ?></td>
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
            <a class="btn btn-light text-start" href="dashboard.php">Dashboard</a>
            <a class="btn btn-light text-start" href="posts.php">Posts</a>
            <a class="btn btn-light text-start" href="post_form.php">Create Post</a>
            <a class="btn btn-light text-start" href="channels.php">Channels</a>
            <a class="btn btn-light text-start" href="logs.php">Logs</a>
            <a class="btn btn-outline-danger text-start" href="logout.php">Logout</a>
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
