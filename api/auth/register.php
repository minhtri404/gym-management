<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    apiError('Phương thức không hợp lệ. Hãy dùng POST.', 405);
}

apiRequireCsrf();

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($fullName === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
    apiError('Vui lòng nhập đầy đủ thông tin.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('Email không hợp lệ.', 422);
}

if (!preg_match('/^0\d{9,10}$/', $phone)) {
    apiError('Số điện thoại không hợp lệ.', 422);
}

if ($password !== $confirmPassword) {
    apiError('Mật khẩu xác nhận không khớp.', 422);
}

if (strlen($password) < 8) {
    apiError('Mật khẩu phải có ít nhất 8 ký tự.', 422);
}

try {
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1');
    $stmt->bind_param('ss', $email, $phone);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        apiError('Email hoặc số điện thoại đã tồn tại.', 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'member';
    $status = 1;

    $stmt = $conn->prepare('
        INSERT INTO users (full_name, email, phone, password, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->bind_param('sssssi', $fullName, $email, $phone, $passwordHash, $role, $status);
    $stmt->execute();
    $newUserId = (int) $stmt->insert_id;
    $stmt->close();

    apiSuccess('Đăng ký tài khoản thành công.', [
        'id' => $newUserId,
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'role' => $role,
    ], 201);
} catch (Throwable $e) {
    apiServerError('Có lỗi xảy ra khi đăng ký tài khoản.', $e);
}
