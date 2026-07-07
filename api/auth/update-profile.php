<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    apiError('Phương thức không hợp lệ. Hãy dùng POST.', 405);
}

$currentUser = apiRequireAuth($conn);
apiRequireCsrf();
apiRejectForeignUserId((int) $currentUser['id'], 'POST');

$userId = (int) $currentUser['id'];
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));

if ($fullName === '' || $email === '' || $phone === '') {
    apiError('Vui lòng nhập đầy đủ thông tin.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('Email không hợp lệ.', 422);
}

try {
    $stmt = $conn->prepare('
        SELECT id
        FROM users
        WHERE (email = ? OR phone = ?) AND id <> ?
        LIMIT 1
    ');
    $stmt->bind_param('ssi', $email, $phone, $userId);
    $stmt->execute();
    $duplicate = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($duplicate) {
        apiError('Email hoặc số điện thoại đã được dùng bởi tài khoản khác.', 409);
    }

    $stmt = $conn->prepare('
        UPDATE users
        SET full_name = ?, email = ?, phone = ?
        WHERE id = ?
    ');
    $stmt->bind_param('sssi', $fullName, $email, $phone, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('
        SELECT id, full_name, email, phone, role, status, avatar, created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $_SESSION['user_name'] = $user['full_name'] ?? '';
    $_SESSION['user_email'] = $user['email'] ?? '';

    apiSuccess('Cập nhật hồ sơ thành công.', $user);
} catch (Throwable $e) {
    apiServerError('Có lỗi xảy ra khi cập nhật hồ sơ.', $e);
}
