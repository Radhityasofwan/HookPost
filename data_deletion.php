<?php
/* =========================================================
 * PAGE: data-deletion
 * ========================================================= */
/* SECTION: BOOTSTRAP */
require_once __DIR__ . '/config.php';

/* SECTION: AUTH */
// Public page, no auth required.

/* SECTION: HANDLE REQUEST */
$app_name = defined('APP_NAME') ? APP_NAME : 'Social Publisher';
$effective_date = 'March 12, 2026';
$contact_email = defined('APP_CONTACT_EMAIL') ? APP_CONTACT_EMAIL : 'support@example.com';
$callback_url = (defined('APP_URL') && APP_URL) ? (APP_URL . '/data-deletion') : '';

/* SECTION: LOAD DATA */
// No dynamic data.

/* SECTION: HTML */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($app_name); ?> - Data Deletion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                            <div>
                                <h1 class="h3 mb-1">Data Deletion Instructions</h1>
                                <div class="text-muted small">Effective date: <?php echo htmlspecialchars($effective_date); ?></div>
                            </div>
                            <a href="/login" class="btn btn-outline-secondary btn-sm rounded-pill">Back to App</a>
                        </div>

                        <p>This page explains how to request deletion of your data associated with <strong><?php echo htmlspecialchars($app_name); ?></strong>.</p>

                        <h2 class="h5 mt-4">1. Request Deletion</h2>
                        <p>Send an email to <strong><?php echo htmlspecialchars($contact_email); ?></strong> with the subject <strong>“Data Deletion Request”</strong> and include:</p>
                        <ul>
                            <li>Your account email used in the Service.</li>
                            <li>The connected platform(s) you want removed (Instagram, Facebook, Threads, TikTok).</li>
                            <li>Optional: confirmation to delete all posts, media, and logs.</li>
                        </ul>

                        <h2 class="h5 mt-4">2. What We Delete</h2>
                        <ul>
                            <li>Connected channel tokens and account identifiers.</li>
                            <li>Scheduled posts, variants, and publishing logs (if requested).</li>
                            <li>Uploaded media assets stored on our server.</li>
                        </ul>

                        <h2 class="h5 mt-4">3. Processing Time</h2>
                        <p>We aim to complete deletion requests within <strong>7 business days</strong>. You will receive a confirmation email after completion.</p>

                        <h2 class="h5 mt-4">4. Callback URL</h2>
                        <p>If a platform requires a data deletion callback URL, use:</p>
                        <div class="p-3 bg-light border rounded-3 small">
                            <?php echo htmlspecialchars($callback_url ?: 'Set APP_URL in .env to display callback URL'); ?>
                        </div>
                        <p class="text-muted small mt-2">This page can be provided as the public data deletion URL for platform compliance.</p>

                        <hr class="my-4">
                        <p class="text-muted small mb-0">By using the Service, you acknowledge this data deletion process.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION: INLINE CSS -->
    <style>
        .card {
            border-radius: 1.25rem;
        }
        h1, h2 {
            letter-spacing: -0.01em;
        }
        ul {
            padding-left: 1.25rem;
        }
        li {
            margin-bottom: 0.25rem;
        }
    </style>

    <!-- SECTION: INLINE JS -->
    <script>
        // No JS required.
    </script>
</body>
</html>
