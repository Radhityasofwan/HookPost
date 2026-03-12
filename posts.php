<?php
/* =========================================================
 * PAGE: posts.php
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login();

/* SECTION: AUTH */
// Auth enforced above.

/* SECTION: HANDLE REQUEST */
$flash = flash_get();

$status_filter = $_GET['status'] ?? 'all';
$allowed = ['all','draft','scheduled','published','failed'];
if (!in_array($status_filter, $allowed, true)) $status_filter = 'all';

$posts = [];
try {
    $where = '';
    $params = [];
    if ($status_filter !== 'all') {
        $where = "WHERE p.overall_status = :status";
        $params[':status'] = $status_filter;
    }
    $sql = "SELECT p.id, p.title, p.overall_status, p.scheduled_at, p.created_at,
                   MAX(pv.published_at) AS published_at,
                   GROUP_CONCAT(DISTINCT pv.platform) AS platforms
            FROM posts p
            LEFT JOIN post_variants pv ON pv.post_id = p.id
            $where
            GROUP BY p.id
            ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
} catch (Throwable $e) {
    log_line('posts load error: ' . $e->getMessage(), 'app.log');
}

/* SECTION: LOAD DATA */
// Data already loaded above.
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>posts.php - <?= e(APP_NAME) ?></title>
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
.filter-pill{border-radius:999px}
.table> :not(caption)>*>*{padding:12px 14px}
.table thead th{font-size:.85rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.empty-state{padding:28px;border:1px dashed var(--line);border-radius:16px;background:#fafbff;color:var(--muted)}
.action-bar{gap:10px}
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
                <div class="fw-semibold">Posts</div>
            </div>
            <div class="text-secondary small">Kelola konten</div>
        </nav>

        <main class="p-3 p-lg-4">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> rounded-4"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="card card-soft p-3 p-lg-4 mb-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between action-bar">
                    <div class="fw-semibold">Filter Status</div>
                    <div class="d-flex flex-wrap gap-2">
                <?php
                    $filters = [
                        'all' => 'All',
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                        'failed' => 'Failed',
                    ];
                    foreach ($filters as $key => $label):
                        $active = $status_filter === $key ? 'btn-primary' : 'btn-outline-secondary';
                ?>
                    <a class="btn btn-sm <?= e($active) ?> filter-pill" href="posts.php?status=<?= e($key) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card card-soft p-3 p-lg-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-semibold">Daftar Konten</div>
                    <a class="btn btn-sm btn-primary" href="post_form.php">Create Post</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Platforms</th>
                                <th>Schedule Time</th>
                                <th>Status</th>
                                <th>Published Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$posts): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state text-center">Belum ada konten.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($posts as $row): ?>
                                <tr>
                                    <td class="fw-medium"><?= e($row['title']) ?></td>
                                    <td><?= e($row['platforms'] ?: '-') ?></td>
                                    <td><?= e($row['scheduled_at'] ? format_datetime($row['scheduled_at']) : '-') ?></td>
                                    <td><?= app_status_badge($row['overall_status']) ?></td>
                                    <td><?= e($row['published_at'] ? format_datetime($row['published_at']) : '-') ?></td>
                                    <td class="d-flex flex-wrap gap-2">
                                        <a class="btn btn-sm btn-outline-secondary" href="post_form.php?id=<?= e($row['id']) ?>">Edit</a>
                                        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= e($row['id']) ?>">Delete</button>
                                        <form method="post" action="posts_retry.php" class="d-inline">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                            <button class="btn btn-sm btn-outline-warning" type="submit" data-loading="Retrying...">Retry Publish</button>
                                        </form>
                                        <a class="btn btn-sm btn-outline-info" href="logs.php?post_id=<?= e($row['id']) ?>">View Logs</a>
                                    </td>
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

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="fw-semibold">Hapus Konten</div>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Konten ini akan dihapus. Lanjutkan?
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="post" action="posts_delete.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" id="deleteId">
                    <button class="btn btn-danger" type="submit" data-loading="Menghapus...">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SECTION: INLINE JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const deleteModal = document.getElementById('deleteModal');
deleteModal?.addEventListener('show.bs.modal', event=>{
  const btn = event.relatedTarget;
  const id = btn?.getAttribute('data-id');
  document.getElementById('deleteId').value = id || '';
});

document.querySelectorAll('.sidebar a').forEach(a=>{
  if(a.getAttribute('href')===location.pathname.split('/').pop()){a.classList.add('active')}
});

document.querySelectorAll('form').forEach(form=>{
  form.addEventListener('submit', ()=>{
    const btn = form.querySelector('button[type="submit"][data-loading]');
    if (btn) {
      btn.dataset.originalText = btn.textContent;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + btn.getAttribute('data-loading');
    }
  });
});
</script>
</body>
</html>
