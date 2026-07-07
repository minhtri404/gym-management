<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiError('Phương thức không hợp lệ. Hãy dùng GET.', 405);
}

try {
    $user = apiRequireAuth($conn);
    apiRejectForeignUserId((int) $user['id'], 'GET');

    apiSuccess('Lấy thông tin user thành công.', [
        'user' => $user,
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
    ]);
} catch (Throwable $e) {
    apiServerError('Có lỗi xảy ra khi lấy thông tin user.', $e);
}
