<?php
/* =========================================================
 * PAGE: login
 * ========================================================= */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (!empty($_SESSION['user_id'])) {
    redirect_to('dashboard');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            flash_set('success', 'Login berhasil.');
            redirect_to('dashboard');
        } else {
            $error = 'Email atau password salah.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --bg:#0b1020;
  --card:#111827;
  --ink:#f8fafc;
  --muted:#94a3b8;
  --line:#1f2937;
  --tiktok:#fe2c55;
  --tiktok-2:#25f4ee;
  --meta:#0866ff;
  --radius:22px;
}
body{
  min-height:100vh;
  display:grid;
  place-items:center;
  background:
    radial-gradient(1100px 600px at 90% -10%, rgba(37,244,238,.15), transparent 60%),
    radial-gradient(900px 500px at -10% 10%, rgba(254,44,85,.18), transparent 55%),
    #0b1020;
  color:var(--ink);
}
.card{
  border:1px solid rgba(255,255,255,.06);
  border-radius:var(--radius);
  background:rgba(17,24,39,.92);
  box-shadow:0 20px 60px rgba(0,0,0,.35);
  backdrop-filter: blur(6px);
}
.brand{
  display:flex;
  align-items:center;
  gap:12px;
}
.brand-logo{
  width:40px;height:40px;border-radius:12px;object-fit:cover;
  box-shadow:0 8px 18px rgba(0,0,0,.35);
}
.muted{color:var(--muted)}
.form-control{
  background:#0f172a;
  border:1px solid var(--line);
  color:var(--ink);
}
.form-control:focus{
  background:#0f172a;
  color:var(--ink);
  border-color:rgba(37,244,238,.4);
  box-shadow:0 0 0 .2rem rgba(37,244,238,.15);
}
.btn-primary{
  background:linear-gradient(90deg,var(--tiktok),var(--meta));
  border:0;
}
</style>
</head>
<body>
<div class="container" style="max-width:460px">
    <div class="card p-4">
        <div class="brand mb-3">
            <img src="/image/logo/hookpost.png" class="brand-logo" alt="HookPost">
            <div>
                <div class="fw-bold h4 mb-0"><?= e(APP_NAME) ?></div>
                <div class="small muted">Meta + TikTok Suite</div>
            </div>
        </div>
        <div class="muted mb-4">Login dashboard</div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_input() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control form-control-lg" name="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control form-control-lg" name="password" required>
            </div>
            <button class="btn btn-primary btn-lg w-100">Login</button>
        </form>

        <div class="small muted mt-3">
            Default: admin@example.com / admin123
        </div>
    </div>
</div>
</body>
</html>
