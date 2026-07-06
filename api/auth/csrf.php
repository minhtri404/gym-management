<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiError('Phương thức không hợp lệ. Hãy dùng GET.', 405);
}

apiSuccess('Khởi tạo CSRF token thành công.', [
    'csrf_token' => $_SESSION['csrf_token'] ?? '',
]);
