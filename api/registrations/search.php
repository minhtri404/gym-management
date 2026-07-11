<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions/registration-functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiError('Phương thức không hợp lệ. Hãy dùng GET.', 405);
}

$user = apiRequireAuth($conn);
$role = strtolower(trim((string) ($user['role'] ?? '')));
$keyword = trim((string) ($_GET['keyword'] ?? ''));

try {
    if ($role === 'admin') {
        if ($keyword === '') {
            apiError('Thiếu keyword để tra cứu đăng ký.', 400);
        }

        $results = findRegistrationsByKeyword($conn, $keyword);
    } else {
        $results = findRegistrationsForAccount(
            $conn,
            trim((string) ($user['email'] ?? '')),
            trim((string) ($user['phone'] ?? '')),
            (int) ($user['id'] ?? 0)
        );
    }

    apiSuccess('Tra cứu đăng ký thành công.', $results, 200, [
        'count' => count($results),
    ]);
} catch (Throwable $e) {
    apiServerError('Có lỗi xảy ra khi tra cứu đăng ký.', $e);
}
