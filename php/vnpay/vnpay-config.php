<?php
require_once __DIR__ . '/../../includes/config.php';

function vnpay_env_value($key, $default = '')
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    if ($value !== false && $value !== null && trim((string)$value) !== '') {
        return trim((string)$value);
    }

    $envPath = __DIR__ . '/../../.env';

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

$vnp_TmnCode = vnpay_env_value('VNPAY_TMN_CODE');
$vnp_HashSecret = vnpay_env_value('VNPAY_HASH_SECRET');
$vnp_Url = vnpay_env_value('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
$vnp_Returnurl = vnpay_env_value('VNPAY_RETURN_URL');

if ($vnp_TmnCode === '' || $vnp_HashSecret === '' || $vnp_Returnurl === '') {
    die('Thiếu cấu hình VNPAY trong file .env');
}