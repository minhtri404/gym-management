<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions/package-functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiError('Phương thức không hợp lệ. Hãy dùng GET.', 405);
}

apiRequireAuth($conn);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    apiError('Thiếu hoặc sai id gói tập.', 400);
}

try {
    $package = getPackageById($conn, $id);

    if (!$package) {
        apiError('Không tìm thấy gói tập.', 404);
    }

    apiSuccess('Lấy chi tiết gói tập thành công.', $package);
} catch (Throwable $e) {
    apiServerError('Có lỗi xảy ra khi lấy chi tiết gói tập.', $e);
}
