<?php
include __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function env_value_qr($key, $default = '')
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    if ($value !== false && $value !== null && trim((string)$value) !== '') {
        return trim((string)$value);
    }

    $envPath = __DIR__ . '/../.env';

    if (is_file($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            if (trim($parts[0]) === $key) {
                return trim(trim($parts[1]), "\"'");
            }
        }
    }

    return $default;
}

function resolve_qr_base_url()
{
    $configured = rtrim(env_value_qr('APP_URL', ''), '/');
    $configuredHost = $configured !== '' ? (parse_url($configured, PHP_URL_HOST) ?: '') : '';

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $httpHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $serverName = trim((string)($_SERVER['SERVER_NAME'] ?? ''));

    $hostOnly = $httpHost !== '' ? preg_replace('/:\d+$/', '', $httpHost) : $serverName;
    $hostOnly = trim((string)$hostOnly, '[]');

    $isLoopback = in_array(strtolower($hostOnly), ['localhost', '127.0.0.1', '::1'], true);
    if ($httpHost !== '' && !$isLoopback) {
        return $scheme . '://' . $httpHost;
    }

    if ($configured !== '' && !in_array(strtolower($configuredHost), ['localhost', '127.0.0.1', '::1'], true)) {
        return $configured;
    }

    $serverPort = trim((string)($_SERVER['SERVER_PORT'] ?? ''));
    $portSuffix = ($serverPort !== '' && !in_array($serverPort, ['80', '443'], true)) ? ':' . $serverPort : '';
    $detectedIps = gethostbynamel(gethostname()) ?: [];
    foreach ($detectedIps as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !preg_match('/^(127\.|169\.254\.)/', $ip)) {
            return $scheme . '://' . $ip . $portSuffix;
        }
    }

    if ($configured !== '') {
        return $configured;
    }

    return $scheme . '://' . ($httpHost !== '' ? $httpHost : 'localhost:8086');
}

$appUrl = resolve_qr_base_url();
$token = env_value_qr('CHECKIN_QR_TOKEN', 'gym_checkin_2026');

$checkinUrl = $appUrl . '/user/checkins/scan.php?token=' . urlencode($token);

// Dùng service tạo QR nhanh, không cần cài thư viện
$qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($checkinUrl);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>QR Check-in - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background:#07111f; color:#fff;">

<div class="container min-vh-100 d-flex align-items-center justify-content-center">
    <div class="card shadow-lg border-0" style="max-width:520px; width:100%; border-radius:22px;">
        <div class="card-body text-center p-5">

            <h2 class="fw-bold mb-2 text-dark">QR Check-in</h2>
            <p class="text-muted mb-4">
                Hội viên dùng điện thoại quét mã này để check-in tại phòng gym.
            </p>

            <div class="p-3 bg-light rounded-4 mb-4">
                <img src="<?php echo htmlspecialchars($qrImage); ?>" alt="QR Check-in" class="img-fluid">
            </div>

            <div class="alert alert-info text-start small">
                <strong>Link trong QR:</strong><br>
                <span style="word-break:break-all;">
                    <?php echo htmlspecialchars($checkinUrl); ?>
                </span>
            </div>

            <a href="dashboard.php" class="btn btn-dark w-100">
                Quay lại Dashboard Admin
            </a>

        </div>
    </div>
</div>

</body>
</html>
