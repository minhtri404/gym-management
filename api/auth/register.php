<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Phương thức không hợp lệ. Hãy dùng POST.', 405);
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

if ($full_name === '' || $email === '' || $phone === '' || $password === '' || $confirm_password === '') {
    apiError('Vui lòng nhập đầy đủ thông tin.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('Email không hợp lệ.', 422);
}

if ($password !== $confirm_password) {
    apiError('Mật khẩu xác nhận không khớp.', 422);
}

if (strlen($password) < 6) {
    apiError('Mật khẩu phải có ít nhất 6 ký tự.', 422);
}

try {
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
    $stmt_check->bind_param("ss", $email, $phone);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $exists = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($exists) {
        apiError('Email hoặc số điện thoại đã tồn tại.', 409);
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'user';

    $stmt = $conn->prepare("
        INSERT INTO users (full_name, email, phone, password, role, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("sssss", $full_name, $email, $phone, $password_hash, $role);

    if (!$stmt->execute()) {
        throw new Exception('Không thể tạo tài khoản.');
    }

    $new_user_id = $stmt->insert_id;
    $stmt->close();

    apiSuccess('Đăng ký tài khoản thành công.', [
        'id' => $new_user_id,
        'full_name' => $full_name,
        'email' => $email,
        'phone' => $phone,
        'role' => $role,
    ]);
} catch (Throwable $e) {
    apiError('Có lỗi xảy ra khi đăng ký tài khoản.', 500, $e->getMessage());
}
