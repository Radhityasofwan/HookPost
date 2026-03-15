<?php
/* =========================================================
 * PAGE: dashboard
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
<title>dashboard - <?= e(APP_NAME) ?></title>
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
.stat-card .label{font-size:.85rem;color:var(--muted);letter-spacing:.2px}
.stat-card .value{font-size:1.9rem;font-weight:700}
.content-wrap{min-height:100vh}
.navbar{background:var(--card);border-bottom:1px solid var(--line)}
.table> :not(caption)>*>*{padding:12px 14px}
.table thead th{font-size:.85rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.pill{font-size:.8rem}
.empty-state{padding:28px;border:1px dashed var(--line);border-radius:16px;background:#fafbff;color:var(--muted)}
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
<div class="d-flex content-wrap">
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
                    <a class="btn btn-sm btn-outline-secondary" href="/posts">View All</a>
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

        <footer class="px-3 px-lg-4 pb-4">
            <div class="card card-soft p-3 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                <div class="text-muted small">© <?= e(date('Y')) ?> <?= e(APP_NAME) ?>. All rights reserved.</div>
                <div class="d-flex gap-3 small">
                    <a href="/terms" class="text-decoration-none">Terms of Service</a>
                    <a href="/privacy" class="text-decoration-none">Privacy Policy</a>
                    <a href="/data-deletion" class="text-decoration-none">Data Deletion</a>
                </div>
            </div>
        </footer>
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
