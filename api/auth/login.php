<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    apiError('Phương thức không hợp lệ. Hãy dùng POST.', 405);
}

apiRequireCsrf();

$emailOrPhone = trim((string) ($_POST['email_or_phone'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($emailOrPhone === '' || $password === '') {
    apiError('Vui lòng nhập email hoặc số điện thoại và mật khẩu.', 422);
}

try {
    $stmt = $conn->prepare('
        SELECT id, full_name, email, phone, password, role, status, avatar, created_at
        FROM users
        WHERE email = ? OR phone = ?
        LIMIT 1
    ');
    $stmt->bind_param('ss', $emailOrPhone, $emailOrPhone);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, (string) $user['password'])) {
        apiError('Tài khoản hoặc mật khẩu không đúng.', 401);
    }

    if ((int) ($user['status'] ?? 0) !== 1) {
        apiError('Tài khoản đã bị khóa hoặc chưa kích hoạt.', 403);
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['full_name'] ?? '';
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_role'] = $user['role'] ?? '';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    unset($user['password']);

    apiSuccess('Đăng nhập thành công.', [
        'user' => $user,
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (Throwable $e) {
    apiServerError('Có lỗi xảy ra khi đăng nhập.', $e);
}
