<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../../includes/functions/registration-functions.php';
include __DIR__ . '/../includes/response.php';

$keyword = trim($_GET['keyword'] ?? '');

if ($keyword === '') {
 apiError('Thiếu keyword để tra cứu đăng ký.', 400);
}

try {
    $results = findRegistrationsByKeyword($conn, $keyword);

  apiSuccess('Tra cứu đăng ký thành công.', $results, 200, [
    'count' => count($results),
]);
} catch (Throwable $e) {
apiError('Có lỗi xảy ra khi tra cứu đăng ký.', 500, $e->getMessage());
}