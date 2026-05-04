<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/package-functions.php';
include __DIR__ . '/../includes/response.php';

try {
    $packages = getActivePackages($conn);

  apiSuccess('Lấy danh sách gói tập thành công.', $packages);
} catch (Throwable $e) {
apiError('Có lỗi xảy ra khi lấy danh sách gói tập.', 500, $e->getMessage());}