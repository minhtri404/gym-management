<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/package-functions.php';

include __DIR__ . '/../includes/response.php';

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
    apiError('Có lỗi xảy ra khi lấy chi tiết gói tập.', 500, $e->getMessage());
}