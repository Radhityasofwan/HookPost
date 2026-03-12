<?php
/* =========================================================
 * PAGE: post-form
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_login();

/* SECTION: AUTH */
// Auth enforced above.

/* SECTION: HANDLE REQUEST */
$flash = flash_get();

$errors = [];
$is_edit = false;
$post_id = (int) ($_GET['id'] ?? 0);
$old = [
    'title' => '',
    'caption' => '',
    'scheduled_at' => '',
    'platforms' => [],
    'mode' => 'mirror',
    'caption_instagram' => '',
    'caption_facebook' => '',
    'caption_threads' => '',
    'caption_tiktok' => '',
];

function normalize_datetime_local($value) {
    $value = trim((string) $value);
    if ($value === '') return null;
    if (str_contains($value, 'T')) {
        $value = str_replace('T', ' ', $value);
    }
    return strlen($value) === 16 ? $value . ':00' : $value;
}

/* SECTION: LOAD DATA */
$existing_media = null;
if ($post_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $is_edit = true;
    try {
        $stmt = $pdo->prepare("SELECT id, title, caption, mode, overall_status, scheduled_at
                               FROM posts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $post_id]);
        $post = $stmt->fetch();
        if (!$post) {
            flash_set('danger', 'Post tidak ditemukan.');
            redirect_to('posts');
        }
        $old['title'] = $post['title'];
        $old['caption'] = $post['caption'];
        $old['mode'] = $post['mode'] ?: 'mirror';
        $old['scheduled_at'] = $post['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($post['scheduled_at'])) : '';

        $vstmt = $pdo->prepare("SELECT platform, caption FROM post_variants WHERE post_id = :id");
        $vstmt->execute([':id' => $post_id]);
        $variants = $vstmt->fetchAll();
        foreach ($variants as $v) {
            $old['platforms'][] = $v['platform'];
            if ($v['platform'] === 'instagram') $old['caption_instagram'] = $v['caption'];
            if ($v['platform'] === 'facebook') $old['caption_facebook'] = $v['caption'];
            if ($v['platform'] === 'threads') $old['caption_threads'] = $v['caption'];
            if ($v['platform'] === 'tiktok') $old['caption_tiktok'] = $v['caption'];
        }

        $mstmt = $pdo->prepare("
            SELECT ma.id, ma.file_path, ma.mime_type, ma.media_kind, ma.original_name
            FROM media_assets ma
            JOIN post_media pm ON pm.media_asset_id = ma.id
            JOIN post_variants pv ON pv.id = pm.post_variant_id
            WHERE pv.post_id = :id
            LIMIT 1
        ");
        $mstmt->execute([':id' => $post_id]);
        $existing_media = $mstmt->fetch();
    } catch (Throwable $e) {
        log_line('post_form load error: ' . $e->getMessage(), 'app.log');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $post_id = (int) ($_POST['id'] ?? 0);
    $is_edit = $post_id > 0;
    $old['title'] = trim($_POST['title'] ?? '');
    $old['caption'] = trim($_POST['caption'] ?? '');
    $old['scheduled_at'] = trim($_POST['scheduled_at'] ?? '');
    $old['platforms'] = $_POST['platforms'] ?? [];
    $old['mode'] = ($_POST['mode'] ?? 'mirror') === 'custom' ? 'custom' : 'mirror';

    $old['caption_instagram'] = trim($_POST['caption_instagram'] ?? '');
    $old['caption_facebook'] = trim($_POST['caption_facebook'] ?? '');
    $old['caption_threads'] = trim($_POST['caption_threads'] ?? '');
    $old['caption_tiktok'] = trim($_POST['caption_tiktok'] ?? '');

    if ($old['title'] === '') $errors[] = 'Title wajib diisi.';
    if (!$old['platforms']) $errors[] = 'Pilih minimal satu platform.';

    $has_new_media = !empty($_FILES['media']['name']);
    if (!$is_edit && !$has_new_media) $errors[] = 'Upload media wajib diisi.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $status = $old['scheduled_at'] ? 'scheduled' : 'draft';
            $scheduled_at = normalize_datetime_local($old['scheduled_at']);
            $now = date('Y-m-d H:i:s');

            if ($is_edit) {
                $stmt = $pdo->prepare("UPDATE posts
                                       SET title = :title,
                                           caption = :caption,
                                           mode = :mode,
                                           overall_status = :status,
                                           scheduled_at = :scheduled_at
                                       WHERE id = :id");
                $stmt->execute([
                    ':title' => $old['title'],
                    ':caption' => $old['caption'],
                    ':mode' => $old['mode'],
                    ':status' => $status,
                    ':scheduled_at' => $scheduled_at,
                    ':id' => $post_id,
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO posts (title, caption, mode, overall_status, scheduled_at, created_at)
                                       VALUES (:title, :caption, :mode, :status, :scheduled_at, :now)");
                $stmt->execute([
                    ':title' => $old['title'],
                    ':caption' => $old['caption'],
                    ':mode' => $old['mode'],
                    ':status' => $status,
                    ':scheduled_at' => $scheduled_at,
                    ':now' => $now,
                ]);
                $post_id = (int) $pdo->lastInsertId();
            }

            $media_id = null;
            if ($is_edit) {
                $mid_stmt = $pdo->prepare("
                    SELECT ma.id
                    FROM media_assets ma
                    JOIN post_media pm ON pm.media_asset_id = ma.id
                    JOIN post_variants pv ON pv.id = pm.post_variant_id
                    WHERE pv.post_id = :id
                    LIMIT 1
                ");
                $mid_stmt->execute([':id' => $post_id]);
                $row = $mid_stmt->fetch();
                $media_id = $row['id'] ?? null;
            }

            if ($has_new_media) {
                $media = upload_media_file($_FILES['media']);
                $stmt = $pdo->prepare("INSERT INTO media_assets
                    (original_name, file_path, mime_type, file_size, media_kind, created_at)
                    VALUES (:original_name, :file_path, :mime_type, :file_size, :media_kind, :now)");
                $stmt->execute([
                    ':original_name' => $media['original_name'],
                    ':file_path' => $media['file_path'],
                    ':mime_type' => $media['mime_type'],
                    ':file_size' => $media['file_size'],
                    ':media_kind' => $media['media_kind'],
                    ':now' => $now,
                ]);
                $media_id = (int) $pdo->lastInsertId();
            }

            if (!$media_id) {
                throw new RuntimeException('Media belum tersedia.');
            }

            $pdo->prepare("DELETE FROM post_variants WHERE post_id = :id")->execute([':id' => $post_id]);

            $variant_ids = [];
            $variant_stmt = $pdo->prepare("INSERT INTO post_variants
                (post_id, platform, caption, status, scheduled_at, external_post_id, external_url, published_at, failed_reason, created_at)
                VALUES (:post_id, :platform, :caption, :status, :scheduled_at, NULL, NULL, NULL, NULL, :now)");

            foreach ($old['platforms'] as $platform) {
                $platform = trim($platform);
                $caption = $old['caption'];
                if ($old['mode'] === 'custom') {
                    if ($platform === 'instagram') $caption = $old['caption_instagram'] ?: $old['caption'];
                    if ($platform === 'facebook') $caption = $old['caption_facebook'] ?: $old['caption'];
                    if ($platform === 'threads') $caption = $old['caption_threads'] ?: $old['caption'];
                    if ($platform === 'tiktok') $caption = $old['caption_tiktok'] ?: $old['caption'];
                }
                $variant_stmt->execute([
                    ':post_id' => $post_id,
                    ':platform' => $platform,
                    ':caption' => $caption,
                    ':status' => $status,
                    ':scheduled_at' => $scheduled_at,
                    ':now' => $now,
                ]);
                $variant_ids[] = (int) $pdo->lastInsertId();
            }

            $link_stmt = $pdo->prepare("INSERT INTO post_media (post_variant_id, media_asset_id)
                                        VALUES (:post_variant_id, :media_asset_id)");
            foreach ($variant_ids as $variant_id) {
                $link_stmt->execute([
                    ':post_variant_id' => $variant_id,
                    ':media_asset_id' => $media_id,
                ]);
            }

            $pdo->commit();
            flash_set('success', $is_edit ? 'Konten berhasil diperbarui.' : 'Konten berhasil dibuat.');
            redirect_to('posts');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Gagal menyimpan konten.';
            log_line('post_form save error: ' . $e->getMessage(), 'app.log');
        }
    }
}

if ($is_edit && $existing_media === null) {
    try {
        $mstmt = $pdo->prepare("
            SELECT ma.id, ma.file_path, ma.mime_type, ma.media_kind, ma.original_name
            FROM media_assets ma
            JOIN post_media pm ON pm.media_asset_id = ma.id
            JOIN post_variants pv ON pv.id = pm.post_variant_id
            WHERE pv.post_id = :id
            LIMIT 1
        ");
        $mstmt->execute([':id' => $post_id]);
        $existing_media = $mstmt->fetch();
    } catch (Throwable $e) {
        log_line('post_form media reload error: ' . $e->getMessage(), 'app.log');
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>post-form - <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- SECTION: INLINE CSS -->
<style>
:root{
  --bg:#f6f7fb;
  --card:#ffffff;
  --ink:#0f172a;
  --muted:#6b7280;
  --line:#e6eaf2;
  --meta:#0866ff;
  --tiktok:#fe2c55;
  --tiktok-2:#25f4ee;
  --soft-shadow:0 18px 40px rgba(15,23,42,.08);
  --radius:20px;
}
body{background:var(--bg);color:var(--ink)}
.sidebar{width:260px;flex:0 0 260px;min-height:100vh;background:linear-gradient(180deg,#0b1220 0%,#0f172a 100%);color:#fff;border-right:1px solid rgba(255,255,255,.06);transition:width .2s ease}
body.sidebar-collapsed .sidebar{width:84px;flex:0 0 84px}
.sidebar .brand{display:flex;align-items:center;gap:12px;padding:8px 10px}
.brand-logo{width:36px;height:36px;border-radius:12px;box-shadow:0 8px 18px rgba(0,0,0,.25);object-fit:cover}
.brand-title{font-weight:700;line-height:1.1}
.brand-sub{font-size:.75rem;color:rgba(255,255,255,.65)}
body.sidebar-collapsed .brand-text{display:none}
.sidebar a{color:rgba(255,255,255,.86);text-decoration:none;display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:16px}
.sidebar .nav-icon{width:10px;height:10px;border-radius:999px;background:var(--tiktok);box-shadow:0 0 0 4px rgba(254,44,85,.15)}
.sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.12);color:#fff}
body.sidebar-collapsed .sidebar a{justify-content:center}
body.sidebar-collapsed .sidebar .nav-label{display:none}
.card-soft{border:0;border-radius:var(--radius);background:var(--card);box-shadow:var(--soft-shadow)}
.navbar{background:var(--card);border-bottom:1px solid var(--line)}
.preview-box{border:1px dashed var(--line);border-radius:16px;background:#fafbff}
.preview-media{max-width:100%;max-height:320px;border-radius:14px}
.platform-grid label{border:1px solid var(--line);border-radius:14px;padding:10px 12px;width:100%}
.section-title{font-size:.95rem;color:var(--muted);letter-spacing:.3px;text-transform:uppercase}
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
        <nav class="navbar px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    ☰
                </button>
                <button class="btn btn-outline-secondary d-none d-lg-inline-flex" id="sidebarToggle" type="button">
                    ⇄
                </button>
                <div class="fw-semibold"><?= $is_edit ? 'Edit Post' : 'Create Post' ?></div>
            </div>
            <div class="text-secondary small">Kelola konten</div>
        </nav>

        <main class="p-3 p-lg-4">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> rounded-4"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-2">Periksa input:</div>
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="card card-soft p-3 p-lg-4" method="post" enctype="multipart/form-data">
                <?= csrf_input() ?>
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?= e($post_id) ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="section-title">Konten</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Title</label>
                        <input type="text" name="title" class="form-control" value="<?= e($old['title']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Caption</label>
                        <textarea name="caption" class="form-control" rows="4" placeholder="Caption umum untuk semua platform"><?= e($old['caption']) ?></textarea>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold">Upload Media (image/video)</label>
                        <input type="file" name="media" id="mediaInput" class="form-control" accept="image/*,video/*" <?= $is_edit ? '' : 'required' ?>>
                        <div class="form-text">Format: JPG, PNG, WEBP, MP4, MOV, WEBM</div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold">Schedule DateTime</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= e($old['scheduled_at']) ?>">
                        <div class="form-text">Kosongkan jika ingin dibuat sebagai draft.</div>
                    </div>

                    <div class="col-12">
                        <div class="section-title">Platform</div>
                        <label class="form-label fw-semibold">Target Platform</label>
                        <div class="row g-2 platform-grid">
                            <?php
                                $platforms = ['instagram'=>'Instagram','facebook'=>'Facebook','threads'=>'Threads','tiktok'=>'TikTok'];
                                foreach ($platforms as $key => $label):
                                    $checked = in_array($key, $old['platforms'], true) ? 'checked' : '';
                            ?>
                            <div class="col-6 col-lg-3">
                                <label class="d-flex align-items-center gap-2">
                                    <input type="checkbox" name="platforms[]" value="<?= e($key) ?>" <?= $checked ?>>
                                    <span><?= e($label) ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3" id="selectedPlatforms"></div>
                    </div>

                    <div class="col-12">
                        <div class="section-title">Mode Posting</div>
                        <label class="form-label fw-semibold">Mode Posting</label>
                        <div class="d-flex gap-3">
                            <label class="d-flex align-items-center gap-2">
                                <input type="radio" name="mode" value="mirror" <?= $old['mode'] === 'mirror' ? 'checked' : '' ?>>
                                <span>Mirror (caption sama)</span>
                            </label>
                            <label class="d-flex align-items-center gap-2">
                                <input type="radio" name="mode" value="custom" <?= $old['mode'] === 'custom' ? 'checked' : '' ?>>
                                <span>Custom per platform</span>
                            </label>
                        </div>
                    </div>

                    <div class="col-12" id="customCaptionWrap" style="display:none;">
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <label class="form-label fw-semibold">Caption Instagram</label>
                                <textarea name="caption_instagram" class="form-control" rows="3"><?= e($old['caption_instagram']) ?></textarea>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label class="form-label fw-semibold">Caption Facebook</label>
                                <textarea name="caption_facebook" class="form-control" rows="3"><?= e($old['caption_facebook']) ?></textarea>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label class="form-label fw-semibold">Caption Threads</label>
                                <textarea name="caption_threads" class="form-control" rows="3"><?= e($old['caption_threads']) ?></textarea>
                            </div>
                            <div class="col-12 col-lg-6">
                                <label class="form-label fw-semibold">Caption TikTok</label>
                                <textarea name="caption_tiktok" class="form-control" rows="3"><?= e($old['caption_tiktok']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="section-title">Preview</div>
                        <label class="form-label fw-semibold">Preview Caption per Platform</label>
                        <div class="card card-soft p-3" id="captionPreview">
                            <div class="text-secondary">Pilih platform untuk melihat preview.</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Preview Media</label>
                        <div class="preview-box p-3 text-center" id="mediaPreview">
                            <?php if ($existing_media): ?>
                                <?php if (str_starts_with($existing_media['mime_type'], 'image/')): ?>
                                    <img src="<?= e($existing_media['file_path']) ?>" class="preview-media" alt="preview">
                                <?php elseif (str_starts_with($existing_media['mime_type'], 'video/')): ?>
                                    <video src="<?= e($existing_media['file_path']) ?>" class="preview-media" controls></video>
                                <?php else: ?>
                                    <div class="text-secondary">Media tersimpan: <?= e($existing_media['original_name']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-secondary">Belum ada media.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="/posts">Batal</a>
                    <button class="btn btn-primary" type="submit" data-loading="Menyimpan...">Simpan Konten</button>
                </div>
            </form>
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
function toggleCustomCaptions(){
  const mode = document.querySelector('input[name="mode"]:checked')?.value || 'mirror';
  document.getElementById('customCaptionWrap').style.display = mode === 'custom' ? 'block' : 'none';
  updateCaptionPreview();
}
document.querySelectorAll('input[name="mode"]').forEach(r=>{
  r.addEventListener('change', toggleCustomCaptions);
});
toggleCustomCaptions();

function selectedPlatforms(){
  return Array.from(document.querySelectorAll('input[name="platforms[]"]:checked')).map(el=>el.value);
}

function updatePlatformBadges(){
  const container = document.getElementById('selectedPlatforms');
  const platforms = selectedPlatforms();
  if (!platforms.length) {
    container.innerHTML = '<span class="text-secondary">Belum ada platform dipilih.</span>';
    return;
  }
  const labels = {
    instagram: 'Instagram',
    facebook: 'Facebook',
    threads: 'Threads',
    tiktok: 'TikTok',
  };
  container.innerHTML = platforms.map(p=>`<span class="badge text-bg-light border me-1">${labels[p] || p}</span>`).join('');
}

function getCaptionFor(platform){
  const mode = document.querySelector('input[name="mode"]:checked')?.value || 'mirror';
  const main = document.querySelector('textarea[name="caption"]').value || '';
  if (mode === 'mirror') return main;
  if (platform === 'instagram') return document.querySelector('textarea[name="caption_instagram"]').value || main;
  if (platform === 'facebook') return document.querySelector('textarea[name="caption_facebook"]').value || main;
  if (platform === 'threads') return document.querySelector('textarea[name="caption_threads"]').value || main;
  if (platform === 'tiktok') return document.querySelector('textarea[name="caption_tiktok"]').value || main;
  return main;
}

function updateCaptionPreview(){
  const container = document.getElementById('captionPreview');
  const platforms = selectedPlatforms();
  if (!platforms.length) {
    container.innerHTML = '<div class="text-secondary">Pilih platform untuk melihat preview.</div>';
    return;
  }
  const labels = {
    instagram: 'Instagram',
    facebook: 'Facebook',
    threads: 'Threads',
    tiktok: 'TikTok',
  };
  container.innerHTML = platforms.map(p=>{
    const caption = getCaptionFor(p);
    const safe = caption.replace(/</g,'&lt;').replace(/>/g,'&gt;');
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
updatePlatformBadges();
updateCaptionPreview();

const preview = document.getElementById('mediaPreview');
const input = document.getElementById('mediaInput');
input?.addEventListener('change', ()=>{
  preview.innerHTML = '';
  const file = input.files?.[0];
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

const currentPath = location.pathname.replace(/\/$/, '');
const sidebarToggle = document.getElementById('sidebarToggle');
const collapsed = localStorage.getItem('sidebar-collapsed') === '1';
if (collapsed) document.body.classList.add('sidebar-collapsed');
sidebarToggle?.addEventListener('click', () => {
  document.body.classList.toggle('sidebar-collapsed');
  localStorage.setItem('sidebar-collapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
});
document.querySelectorAll('.sidebar a').forEach(a=>{
  if(a.getAttribute('href') === currentPath){a.classList.add('active')}
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
