<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
apiError('Phương thức không hợp lệ. Hãy dùng POST.', 405);
}

$email_or_phone = trim($_POST['email_or_phone'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email_or_phone === '' || $password === '') {
apiError('Vui lòng nhập email hoặc số điện thoại và mật khẩu.', 422);
}

try {
    $stmt = $conn->prepare("
        SELECT id, full_name, email, phone, password, role, status, avatar
        FROM users
        WHERE email = ? OR phone = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $email_or_phone, $email_or_phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
   apiError('Tài khoản hoặc mật khẩu không đúng.', 401);
    }

    if (isset($user['status']) && (int)$user['status'] !== 1) {
   apiError('Tài khoản đã bị khóa hoặc chưa kích hoạt.', 403);
    }

    if (!password_verify($password, $user['password'])) {
        apiError('Tài khoản hoặc mật khẩu không đúng.', 401);
    }

    unset($user['password']);

 apiSuccess('Đăng nhập thành công.', $user);    

} catch (Throwable $e) {
apiError('Có lỗi xảy ra khi đăng nhập.', 500, $e->getMessage());}