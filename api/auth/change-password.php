<?php
include __DIR__ . '/../../includes/config.php';
include __DIR__ . '/../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('Phương thức không hợp lệ. Hãy dùng POST.', 405);
}

$user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$current_password = trim($_POST['current_password'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

if ($user_id <= 0 || $current_password === '' || $new_password === '' || $confirm_password === '') {
    apiError('Vui lòng nhập đầy đủ thông tin.', 422);
}

if ($new_password !== $confirm_password) {
    apiError('Mật khẩu xác nhận không khớp.', 422);
}

if (strlen($new_password) < 6) {
    apiError('Mật khẩu mới phải có ít nhất 6 ký tự.', 422);
}

try {
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        apiError('Không tìm thấy user.', 404);
    }

    if (!password_verify($current_password, $user['password'])) {
        apiError('Mật khẩu hiện tại không đúng.', 401);
    }

    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt_update->bind_param("si", $new_password_hash, $user_id);

    if (!$stmt_update->execute()) {
        throw new Exception('Không thể đổi mật khẩu.');
    }

    $stmt_update->close();

    apiSuccess('Đổi mật khẩu thành công.');

} catch (Throwable $e) {
    apiError('Có lỗi xảy ra khi đổi mật khẩu.', 500, $e->getMessage());
}