<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions/package-functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiError('Phương thức không hợp lệ. Hãy dùng GET.', 405);
}

apiRequireAuth($conn);

try {
    $packages = getActivePackages($conn);
    apiSuccess('Lấy danh sách gói tập thành công.', $packages);
} catch (Throwable $e) {
    apiServerError('Có lỗi xảy ra khi lấy danh sách gói tập.', $e);
}
