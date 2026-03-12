<?php
require_once __DIR__ . '/helpers.php';
$body = file_get_contents('php://input');
log_line('TIKTOK WEBHOOK: ' . $body, 'webhook-tiktok.log');
http_response_code(200);
echo 'OK';
