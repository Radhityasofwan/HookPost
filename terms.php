<?php
/* =========================================================
 * PAGE: terms
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
    <title><?php echo htmlspecialchars($app_name); ?> - Terms of Service</title>
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
                                <h1 class="h3 mb-1">Terms of Service</h1>
                                <div class="text-muted small">Effective date: <?php echo htmlspecialchars($effective_date); ?></div>
                            </div>
                            <a href="/login" class="btn btn-outline-light btn-sm rounded-pill">Back to App</a>
                        </div>

                        <p>These Terms of Service ("Terms") govern your access to and use of <strong><?php echo htmlspecialchars($app_name); ?></strong> (the "Service"). By using the Service, you agree to these Terms.</p>

                        <h2 class="h5 mt-4">1. Eligibility</h2>
                        <p>You must be at least 18 years old and have the legal capacity to enter into a binding contract.</p>

                        <h2 class="h5 mt-4">2. Accounts & Security</h2>
                        <p>You are responsible for maintaining the confidentiality of your login credentials and for all activities that occur under your account.</p>

                        <h2 class="h5 mt-4">3. Connected Platforms</h2>
                        <p>The Service allows you to connect third-party social platforms (such as Instagram, Facebook, Threads, and TikTok) using their official OAuth flows. You are responsible for complying with the terms, policies, and rate limits of those platforms.</p>

                        <h2 class="h5 mt-4">4. Content & Publishing</h2>
                        <p>You retain ownership of the content you create and publish. You grant the Service permission to store and process your content solely to provide publishing and scheduling features.</p>

                        <h2 class="h5 mt-4">5. Prohibited Use</h2>
                        <ul>
                            <li>Violating any applicable laws or platform policies.</li>
                            <li>Publishing content that is illegal, harmful, or infringes intellectual property rights.</li>
                            <li>Attempting to access or disrupt the Service or its infrastructure.</li>
                        </ul>

                        <h2 class="h5 mt-4">6. Availability & Changes</h2>
                        <p>The Service is provided on an "as is" and "as available" basis. We may update or discontinue features at any time without notice.</p>

                        <h2 class="h5 mt-4">7. Limitation of Liability</h2>
                        <p>To the maximum extent permitted by law, the Service and its operators are not liable for any indirect, incidental, special, or consequential damages.</p>

                        <h2 class="h5 mt-4">8. Termination</h2>
                        <p>We may suspend or terminate your access if you violate these Terms or applicable platform policies.</p>

                        <h2 class="h5 mt-4">9. Contact</h2>
                        <p>Questions about these Terms can be sent to <strong><?php echo htmlspecialchars($contact_email); ?></strong>.</p>

                        <hr class="my-4">
                        <p class="text-muted small mb-0">By continuing to use the Service, you acknowledge that you have read and understood these Terms.</p>
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
