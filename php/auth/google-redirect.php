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
    header('Location: ../../login.php?error=' . urlencode('Google Login chi dung duoc tren may tinh dang chay localhost. Tren dien thoai vui long dang nhap bang email/mat khau hoac cau hinh domain HTTPS cong khai.'));
    exit;
}

$client = getGoogleClient();

// Tạo state để chống giả mạo request OAuth
$_SESSION['google_oauth_state'] = bin2hex(random_bytes(32));

$client->setState($_SESSION['google_oauth_state']);

header('Location: ' . $client->createAuthUrl());
exit;
