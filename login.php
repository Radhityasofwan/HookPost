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
body{min-height:100vh;display:grid;place-items:center;background:#f6f8fb}
.card{border:0;border-radius:24px;box-shadow:0 10px 30px rgba(0,0,0,.06)}
</style>
</head>
<body>
<div class="container" style="max-width:460px">
    <div class="card p-4">
        <h3 class="fw-bold mb-1"><?= e(APP_NAME) ?></h3>
        <div class="text-secondary mb-4">Login dashboard</div>

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
            <button class="btn btn-dark btn-lg w-100">Login</button>
        </form>

        <div class="small text-secondary mt-3">
            Default: admin@example.com / admin123
        </div>
    </div>
</div>
</body>
</html>
