<?php
/* =========================================================
 * PAGE: privacy
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/config.php';

/* SECTION: AUTH */
// Public page, no auth required.

/* SECTION: HANDLE REQUEST */
$app_name = defined('APP_NAME') ? APP_NAME : 'Social Publisher';
$effective_date = 'March 12, 2026';
$contact_email = defined('APP_CONTACT_EMAIL') ? APP_CONTACT_EMAIL : 'support@example.com';

/* SECTION: LOAD DATA */
// No dynamic data.

/* SECTION: HTML */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($app_name); ?> - Privacy Policy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 policy-card">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                            <div>
                                <h1 class="h3 mb-1">Privacy Policy</h1>
                                <div class="text-muted small">Effective date: <?php echo htmlspecialchars($effective_date); ?></div>
                            </div>
                            <a href="/login" class="btn btn-outline-light btn-sm rounded-pill">Back to App</a>
                        </div>

                        <p>This Privacy Policy explains how <strong><?php echo htmlspecialchars($app_name); ?></strong> (the "Service") collects, uses, and protects your information when you use our scheduling and publishing tools.</p>

                        <h2 class="h5 mt-4">1. Information We Collect</h2>
                        <ul>
                            <li><strong>Account data:</strong> basic user credentials and identifiers required to access the Service.</li>
                            <li><strong>Connected platform data:</strong> access tokens, account IDs, and profile metadata returned by Instagram, Facebook, Threads, and TikTok during OAuth.</li>
                            <li><strong>Content data:</strong> post titles, captions, media files, schedules, and publishing logs you create.</li>
                            <li><strong>Technical data:</strong> request logs, timestamps, and error messages for debugging and reliability.</li>
                        </ul>

                        <h2 class="h5 mt-4">2. How We Use Information</h2>
                        <ul>
                            <li>To connect and manage social media channels you authorize.</li>
                            <li>To schedule, publish, and monitor posts across platforms.</li>
                            <li>To troubleshoot errors, improve reliability, and ensure security.</li>
                        </ul>

                        <h2 class="h5 mt-4">3. Data Storage</h2>
                        <p>Tokens and content are stored securely in the Service database. Access tokens are used only to perform actions you request. Media files are stored on the server for publishing.</p>

                        <h2 class="h5 mt-4">4. Data Sharing</h2>
                        <p>We do not sell or share your personal data with third parties. Data is only transmitted to connected platforms to execute publishing actions you authorize.</p>

                        <h2 class="h5 mt-4">5. Data Retention</h2>
                        <p>We retain your data while your account remains active. You may request deletion of your account and data by contacting us.</p>

                        <h2 class="h5 mt-4">6. Security</h2>
                        <p>We implement reasonable security measures to protect your data, including access control and server-side validations. However, no system is fully secure.</p>

                        <h2 class="h5 mt-4">7. Your Rights</h2>
                        <p>You can request access, correction, or deletion of your data. You may also disconnect social media channels at any time.</p>

                        <h2 class="h5 mt-4">8. Changes</h2>
                        <p>We may update this Privacy Policy from time to time. The latest version will always be available at this URL.</p>

                        <h2 class="h5 mt-4">9. Contact</h2>
                        <p>Questions about this Privacy Policy can be sent to <strong><?php echo htmlspecialchars($contact_email); ?></strong>.</p>

                        <hr class="my-4">
                        <p class="text-muted small mb-0">By using the Service, you acknowledge that you have read and understood this Privacy Policy.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION: INLINE CSS -->
    <style>
        :root{
            --bg:#0b1020;
            --card:#111827;
            --ink:#f8fafc;
            --muted:#94a3b8;
            --line:#1f2937;
            --tiktok:#fe2c55;
            --meta:#0866ff;
        }
        body{
            background:
                radial-gradient(1100px 600px at 90% -10%, rgba(37,244,238,.12), transparent 60%),
                radial-gradient(900px 500px at -10% 10%, rgba(254,44,85,.16), transparent 55%),
                #0b1020;
            color:var(--ink);
        }
        .policy-card{
            border-radius: 1.25rem;
            background:rgba(17,24,39,.92);
            border:1px solid rgba(255,255,255,.06);
            box-shadow:0 20px 60px rgba(0,0,0,.35);
            backdrop-filter: blur(6px);
        }
        h1, h2 { letter-spacing: -0.01em; }
        .text-muted, .small { color: var(--muted) !important; }
        a { color: #e2e8f0; }
        ul { padding-left: 1.25rem; }
        li { margin-bottom: 0.25rem; }
    </style>

    <!-- SECTION: INLINE JS -->
    <script>
        // No JS required.
    </script>
</body>
</html>
