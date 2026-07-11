<?php
require_once __DIR__ . '/../../includes/google-client.php';

$configuredRedirectUri = google_env_value('GOOGLE_REDIRECT_URI');
$configuredHost = parse_url($configuredRedirectUri, PHP_URL_HOST);
$currentHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);

if (
    $configuredRedirectUri !== ''
    && in_array($configuredHost, ['localhost', '127.0.0.1'], true)
    && $currentHost !== ''
    && $currentHost !== $configuredHost
) {
    header('Location: ../../login.php?error=' . urlencode('Đăng nhập Google chỉ dùng được trên máy đang chạy localhost. Trên điện thoại vui lòng đăng nhập bằng email/mật khẩu hoặc cấu hình domain HTTPS công khai.'));
    exit;
}

$client = getGoogleClient();

// Tạo state để chống giả mạo request OAuth
$_SESSION['google_oauth_state'] = bin2hex(random_bytes(32));

$client->setState($_SESSION['google_oauth_state']);

header('Location: ' . $client->createAuthUrl());
exit;
