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
$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    apiError('Vui lòng nhập đầy đủ thông tin.', 422);
}

if ($newPassword !== $confirmPassword) {
    apiError('Mật khẩu xác nhận không khớp.', 422);
}

if (strlen($newPassword) < 8) {
    apiError('Mật khẩu mới phải có ít nhất 8 ký tự.', 422);
}

try {
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($currentPassword, (string) $user['password'])) {
        apiError('Mật khẩu hiện tại không đúng.', 401);
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->bind_param('si', $passwordHash, $userId);
    $stmt->execute();
    $stmt->close();

    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    apiSuccess('Đổi mật khẩu thành công.', [
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (Throwable $e) {
    apiServerError('Có lỗi xảy ra khi đổi mật khẩu.', $e);
}
